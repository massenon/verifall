<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

/**
 * Un fact-check antérieur parle-t-il VRAIMENT de l'affirmation soumise ?
 *
 * ## Le défaut que cette classe referme
 *
 * L'API Fact Check Tools de Google fait une recherche TEXTUELLE FLOUE. Sur
 * « Le Président actuel du Bénin est Patrice Talon », elle a rendu un vieux
 * démenti de l'AFP intitulé « Cette vidéo montre le soutien populaire du peuple
 * béninois au coup d'État » — deux mots en commun, « Bénin » et rien d'autre.
 *
 * L'adaptateur comptait ce démenti comme un VOTE « FAUX » à 68 %, avec une
 * fiabilité de source de 90 % parce qu'une rédaction accréditée l'avait publié.
 * Un article sans rapport pesait donc plus lourd que deux moteurs concordants,
 * et l'indice affiché tombait de 95 % à 64 % sans qu'aucune ligne du rapport ne
 * dise pourquoi.
 *
 * ## Pourquoi `similar_text() < 40` ne suffit pas
 *
 * C'était le correctif proposé, et il attrape bien le cas ci-dessus. Mais la
 * similarité lexicale n'est pas l'identité sémantique, et l'erreur qu'elle
 * laisse passer est pire que celle qu'elle corrige :
 *
 *     « Patrice Talon est président du Bénin »
 *     « Patrice Talon n'est plus président du Bénin »
 *
 * Ces deux phrases sont lexicalement presque identiques — plus de 85 % de
 * similarité — et portent des verdicts OPPOSÉS. Un seuil lexical seul
 * validerait donc le démenti comme pertinent, et le ferait voter à l'envers.
 *
 * Trois contrôles, donc, et il faut les trois :
 *
 * 1. **Recouvrement des mots porteurs.** Les noms propres, les fonctions et les
 *    lieux doivent se retrouver. C'est ce qui écarte l'article « coup d'État ».
 * 2. **Concordance de la négation.** Si l'une des deux phrases nie et l'autre
 *    affirme, elles ne portent pas sur la même proposition : le fact-check
 *    pourrait être pertinent, mais son verdict ne se reporte pas tel quel. On
 *    refuse plutôt que d'inverser un verdict par accident.
 * 3. **Similarité lexicale globale**, en dernier recours, pour les
 *    reformulations.
 *
 * ## Ce que « non pertinent » veut dire
 *
 * ABSTENTION, pas « faux ». Le moteur rend un verdict nul, qui sort du calcul.
 * Qu'aucun fact-check connu ne porte sur une rumeur n'est une information
 * neutre — la plupart des rumeurs locales n'ont jamais été vérifiées par
 * personne.
 */
final class PertinenceDuFactCheck
{
    /**
     * Seuil de recouvrement des mots porteurs, en proportion.
     *
     * Mesuré sur les mots de l'affirmation SOUMISE : c'est elle qui définit le
     * sujet. Un démenti plus long qui la couvre entièrement reste pertinent ;
     * un démenti qui n'en reprend que le nom du pays ne l'est pas.
     */
    private const RECOUVREMENT_MINIMUM = 0.5;

    /**
     * Recouvrement PLANCHER, en dessous duquel rien ne rattrape.
     *
     * Zéro mot porteur en commun veut dire zéro : aucune similarité lexicale ne
     * peut faire d'un article sur une vidéo de manifestation le fact-check d'une
     * affirmation sur un titulaire de fonction.
     *
     * Ce plancher a été ajouté après mesure, et c'est lui qui attrape le cas
     * réel. Sur « Le Président actuel du Bénin est Patrice Talon » et « Cette
     * vidéo montre le soutien populaire du peuple béninois au coup d'État », le
     * recouvrement vaut 0 — et `similar_text()` rend pourtant plus de 40 %,
     * parce que deux phrases françaises courtes partagent fatalement des
     * fragments (« le », « u », « en », les terminaisons). Le seuil lexical seul
     * déclarait donc l'article pertinent.
     */
    private const RECOUVREMENT_PLANCHER = 0.25;

    /**
     * Similarité lexicale minimale, en pourcentage.
     *
     * Employée SEULEMENT au-dessus du plancher de recouvrement, pour rattraper
     * une reformulation. Jamais seule : {@see self::RECOUVREMENT_PLANCHER}.
     */
    private const SIMILARITE_MINIMUM = 60.0;

    /**
     * Mots trop courants pour porter un sujet.
     *
     * Volontairement court : une liste trop longue finirait par vider des
     * affirmations brèves de tous leurs mots, et le recouvrement se calculerait
     * sur un ensemble vide.
     *
     * @var list<string>
     */
    private const MOTS_VIDES = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'au', 'aux',
        'et', 'ou', 'que', 'qui', 'quoi', 'dont', 'est', 'sont', 'a', 'ont',
        'ce', 'cet', 'cette', 'ces', 'son', 'sa', 'ses', 'leur', 'leurs',
        'dans', 'sur', 'pour', 'par', 'avec', 'sans', 'en', 'il', 'elle',
        'ils', 'elles', 'on', 'nous', 'vous', 'plus', 'pas', 'ne', 'non',
    ];

    /**
     * Marqueurs de négation, en expressions régulières.
     *
     * Des motifs et non des sous-chaînes, parce que la négation française
     * s'ENCADRE : « ne double pas », « n'a jamais eu lieu », « ne sera plus ».
     * Chercher les formes figées — « n'est pas », « ne sont plus » — laissait
     * passer tous les autres verbes, et c'est précisément le titre type d'un
     * démenti : « Non, la taxe ne double pas ».
     *
     * Les marqueurs de DÉMENTI sont inclus au même titre : un titre de
     * fact-check qui s'ouvre sur « Non, » ou qui porte « intox » nie
     * l'affirmation qu'il reprend.
     *
     * @var list<string>
     */
    private const NEGATIONS = [
        // Négation encadrée : ne/n' … pas/plus/jamais/rien/aucun.
        '/\bn[e\']\s*\p{L}+\s+(?:pas|plus|jamais|rien|aucun)\b/u',
        '/\bn[e\']\s*(?:pas|plus|jamais)\b/u',
        // Un démenti s'annonce.
        '/^\s*non\s*[,:]/u',
        '/\b(?:aucun|aucune|jamais|faux|fausse|infox|intox|rumeur|dementi|dementi)\b/u',
    ];

    /**
     * Le fact-check porte-t-il sur la même proposition ?
     */
    public function concorde(string $affirmation, ?string $faitVerifie): bool
    {
        $soumise = $this->normaliser($affirmation);
        $trouvee = $this->normaliser((string) $faitVerifie);

        if ($soumise === '' || $trouvee === '') {
            return false;
        }

        if ($this->nie($soumise) !== $this->nie($trouvee)) {
            return false;
        }

        $recouvrement = $this->recouvrement($soumise, $trouvee);

        /*
         | LE PLANCHER D'ABORD, et il n'est pas rattrapable.
         |
         | En dessous, les deux phrases ne parlent pas de la même chose, et
         | aucune similarité de caractères ne peut y changer quoi que ce soit.
         | C'est la garde qui écarte le cas réel — un article sur une vidéo de
         | manifestation présenté comme le fact-check d'une affirmation sur un
         | titulaire de fonction.
         */
        if ($recouvrement < self::RECOUVREMENT_PLANCHER) {
            return false;
        }

        if ($recouvrement >= self::RECOUVREMENT_MINIMUM) {
            return true;
        }

        // Entre le plancher et le seuil : une reformulation peut encore
        // concorder, et c'est là — et seulement là — que le lexical sert.
        similar_text($soumise, $trouvee, $pourcentage);

        return $pourcentage >= self::SIMILARITE_MINIMUM;
    }

    /**
     * Part des mots porteurs de l'affirmation que le fait vérifié reprend.
     */
    public function recouvrement(string $soumise, string $trouvee): float
    {
        $mots = $this->motsPorteurs($soumise);

        if ($mots === []) {
            return 0.0;
        }

        $cibles = $this->motsPorteurs($trouvee);
        $communs = count(array_intersect($mots, $cibles));

        return $communs / count($mots);
    }

    /**
     * @return list<string>
     */
    private function motsPorteurs(string $texte): array
    {
        $mots = preg_split('/[^\p{L}\p{N}]+/u', $texte, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $mots,
            // Deux lettres au moins : « a » et « y » ne portent rien, et les
            // sigles de trois lettres comptent.
            static fn (string $mot): bool => mb_strlen($mot) > 2
                && ! in_array($mot, self::MOTS_VIDES, true),
        )));
    }

    private function nie(string $texte): bool
    {
        foreach (self::NEGATIONS as $motif) {
            if (preg_match($motif, $texte) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minuscules, accents retirés, espaces normalisés.
     *
     * Les accents partent pour que « Bénin » et « Benin » se rencontrent : les
     * titres de fact-checks sont saisis à la main, parfois sans diacritiques.
     */
    private function normaliser(string $texte): string
    {
        $propre = mb_strtolower(trim($texte));

        $translitere = @iconv('UTF-8', 'ASCII//TRANSLIT', $propre);

        if (is_string($translitere) && $translitere !== '') {
            $propre = mb_strtolower($translitere);
        }

        return (string) preg_replace('/\s+/u', ' ', $propre);
    }
}
