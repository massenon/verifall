<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

/**
 * Empreinte d'une affirmation, insensible à la mise en forme.
 *
 * Une rumeur circule en se déformant : majuscules, points d'exclamation,
 * accents perdus au passage d'un clavier à l'autre, espaces doublés. Hacher le
 * texte brut ferait de chaque variante une rumeur différente, et l'économie
 * d'appels disparaîtrait au premier partage WhatsApp.
 *
 * La normalisation est volontairement AGRESSIVE : on cherche à reconnaître la
 * même rumeur, pas à distinguer deux textes.
 */
final class EmpreinteContenu
{
    public function pour(string $affirmation, ?string $empreinteFichier = null): string
    {
        return hash('sha256', $this->normaliser($affirmation).'|'.($empreinteFichier ?? ''));
    }

    /**
     * Texte réduit à sa substance.
     */
    public function normaliser(string $texte): string
    {
        $normalise = mb_strtolower(trim($texte));

        // Accents : « décret » et « decret » sont le même mot pour qui recopie
        // depuis un téléphone.
        $normalise = $this->sansAccents($normalise);

        // Ponctuation et symboles : seuls les mots portent le sens.
        $normalise = (string) preg_replace('/[^a-z0-9\s]/u', ' ', $normalise);

        // Espaces multiples réduits à un.
        return trim((string) preg_replace('/\s+/', ' ', $normalise));
    }

    /**
     * Retire les accents sans dépendre de l'extension intl.
     *
     * `iconv` échoue silencieusement sur certains caractères selon la locale du
     * serveur : la table explicite est plus longue mais donne le même résultat
     * partout — et une empreinte qui varie d'un serveur à l'autre ne
     * rapprocherait plus rien.
     */
    private function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae',
        ]);
    }

    /**
     * Mots significatifs d'une affirmation.
     *
     * Les mots-outils sont écartés : « le », « de », « que » se retrouvent dans
     * toutes les phrases et rapprocheraient n'importe quoi de n'importe quoi.
     *
     * @return list<string>
     */
    public function motsCles(string $texte): array
    {
        $vides = [
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'da', 'au', 'aux',
            'et', 'ou', 'que', 'qui', 'quoi', 'dont', 'ce', 'cet', 'cette', 'ces',
            'il', 'elle', 'ils', 'elles', 'on', 'nous', 'vous', 'je', 'tu',
            'est', 'sont', 'ete', 'etre', 'avoir', 'a', 'ont', 'sera', 'seront',
            'pour', 'par', 'sur', 'dans', 'avec', 'sans', 'plus', 'moins',
            'pas', 'ne', 'en', 'y', 'se', 'sa', 'son', 'ses', 'leur', 'leurs',
        ];

        $mots = array_filter(
            explode(' ', $this->normaliser($texte)),
            // Trois lettres au moins : en dessous, ce sont des articles ou du
            // bruit d'OCR.
            static fn (string $mot): bool => mb_strlen($mot) >= 3 && ! in_array($mot, $vides, true),
        );

        return array_values(array_unique($mots));
    }

    /**
     * Mots significatifs communs à deux textes.
     *
     * @return list<string>
     */
    public function motsCommuns(string $a, string $b): array
    {
        return array_values(array_intersect($this->motsCles($a), $this->motsCles($b)));
    }

    /**
     * Coefficient de chevauchement, de 0 à 1.
     *
     * Rapporte les mots communs au PLUS COURT des deux textes. C'est la mesure
     * juste quand les deux côtés n'ont pas la même nature : une phrase entière
     * face à une liste de mots-clés. Le recouvrement mutuel de Jaccard
     * pénaliserait chacun pour les mots que l'autre n'a pas, et « la taxe
     * WhatsApp entre en vigueur au Bénin lundi » ne se rapprocherait jamais de
     * « taxe whatsapp benin arcep vigueur » — alors que c'est la même rumeur.
     */
    public function recouvrement(string $a, string $b): float
    {
        $motsA = $this->motsCles($a);
        $motsB = $this->motsCles($b);

        if ($motsA === [] || $motsB === []) {
            return 0.0;
        }

        return count(array_intersect($motsA, $motsB)) / min(count($motsA), count($motsB));
    }

    /**
     * Part des mots-clés attendus effectivement présents, de 0 à 1.
     *
     * Distincte de la proximité, et pour une bonne raison : comparer une phrase
     * entière à une liste de mots-clés par recouvrement mutuel pénalise la
     * phrase pour chaque mot qu'elle contient en plus. « La taxe WhatsApp entre
     * en vigueur au Bénin lundi » ne se rapprochait ainsi jamais de
     * « taxe whatsapp benin arcep vigueur », alors que c'est la même rumeur.
     *
     * La question juste n'est pas « ces deux textes se ressemblent-ils ? » mais
     * « les mots que l'équipe a jugés discriminants sont-ils là ? ».
     *
     * @param  string  $attendus  mots-clés curés par l'équipe
     */
    public function couverture(string $texte, string $attendus): float
    {
        $motsAttendus = $this->motsCles($attendus);

        if ($motsAttendus === []) {
            return 0.0;
        }

        $presents = count(array_intersect($motsAttendus, $this->motsCles($texte)));

        return $presents / count($motsAttendus);
    }

    /**
     * Proximité de deux textes, de 0 à 1.
     *
     * Indice de Jaccard sur les mots significatifs : simple, explicable, et
     * calculable en PHP. Une recherche plein texte serait plus fine mais
     * dépendrait du moteur de base de données — et devrait être réécrite le
     * jour d'une migration.
     *
     * Explicable compte ici : quand la plateforme dit « cette rumeur a déjà été
     * tranchée », elle doit pouvoir montrer pourquoi.
     */
    public function proximite(string $a, string $b): float
    {
        $motsA = $this->motsCles($a);
        $motsB = $this->motsCles($b);

        if ($motsA === [] || $motsB === []) {
            return 0.0;
        }

        $communs = count(array_intersect($motsA, $motsB));
        $total = count(array_unique(array_merge($motsA, $motsB)));

        return $total === 0 ? 0.0 : $communs / $total;
    }
}
