<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Enums\Langue;

/**
 * Devine si un contenu soumis est en français ou en anglais.
 *
 * ## Pourquoi une heuristique, et pas une bibliothèque
 *
 * Les bibliothèques de détection couvrent cent langues et pèsent en
 * conséquence, alors que la question posée ici est binaire : français ou
 * anglais. Sur deux langues, une liste de mots outils très fréquents suffit et
 * se relit en une minute — ce qu'aucun modèle statistique ne permet.
 *
 * ## Ce que la détection sert à faire, et ce qu'elle ne fait pas
 *
 * Elle oriente : dans quelle langue rédiger la réponse, quelles sources
 * interroger en priorité. Elle ne DÉCIDE de rien : un contenu mal détecté
 * produit un rapport dans la mauvaise langue, ce qui se corrige, alors qu'un
 * verdict adossé à une détection fausse serait une erreur de fond.
 *
 * ## Le doute retourne le FRANÇAIS
 *
 * Un texte trop court, sans mot outil reconnaissable, ou parfaitement
 * ambigu — beaucoup de mots sont communs aux deux langues — retourne le
 * français, langue par défaut de la plateforme. Inventer une langue sur trois
 * mots serait pire que ne rien conclure.
 */
final class DetecteurDeLangue
{
    /**
     * Les mots outils, choisis parce qu'ils sont FRÉQUENTS et EXCLUSIFS.
     *
     * Exclus à dessein : « information », « nation », « message », « photo »,
     * « vaccin » et tous les mots proches dans les deux langues. Ils sont
     * nombreux dans le vocabulaire de la désinformation, et les compter
     * fausserait précisément les textes que la plateforme reçoit le plus.
     *
     * @var list<string>
     */
    private const FRANCAIS = [
        'le', 'la', 'les', 'des', 'une', 'un', 'du', 'au', 'aux', 'ce', 'cette',
        'est', 'sont', 'était', 'être', 'avoir', 'a', 'ont', 'pas', 'ne', 'que',
        'qui', 'dans', 'pour', 'sur', 'avec', 'sans', 'mais', 'donc', 'car',
        'plus', 'très', 'tout', 'tous', 'nous', 'vous', 'ils', 'elle', 'elles',
        'faux', 'vrai', 'selon', 'depuis', 'aujourd', 'hier', 'demain',
    ];

    /** @var list<string> */
    private const ANGLAIS = [
        'the', 'and', 'is', 'are', 'was', 'were', 'be', 'been', 'has', 'have',
        'not', 'that', 'this', 'these', 'those', 'with', 'from', 'for', 'about',
        'they', 'their', 'there', 'what', 'when', 'where', 'which', 'would',
        'said', 'says', 'according', 'yesterday', 'tomorrow', 'fake', 'true',
        'you', 'your', 'we', 'our', 'it', 'its', 'but', 'because',
    ];

    /**
     * Le nombre de mots outils minimal pour oser conclure.
     *
     * En dessous, on retourne le français. Un titre de cinq mots ne porte
     * souvent aucun indice, et trancher dessus reviendrait à tirer au sort.
     */
    private const INDICES_MINIMUM = 3;

    public function detecter(string $texte): Langue
    {
        $mots = $this->mots($texte);

        if ($mots === []) {
            return Langue::defaut();
        }

        $francais = count(array_intersect($mots, self::FRANCAIS));
        $anglais = count(array_intersect($mots, self::ANGLAIS));

        if ($francais + $anglais < self::INDICES_MINIMUM) {
            return Langue::defaut();
        }

        /*
         | L'ÉGALITÉ RETOURNE LE FRANÇAIS, et ce n'est pas un détail : sur un
         | texte court mêlant les deux, le départage serait arbitraire. La
         | langue par défaut de la plateforme tranche.
         */
        return $anglais > $francais ? Langue::En : Langue::Fr;
    }

    /**
     * Les mots du texte, réduits pour la comparaison.
     *
     * Les accents sont CONSERVÉS : « était » et « etait » comptent tous deux
     * comme du français, mais retirer les accents ferait de « a » et « à » le
     * même mot, et « a » est un mot outil dans les deux langues.
     *
     * @return list<string>
     */
    private function mots(string $texte): array
    {
        $texte = mb_strtolower(strip_tags($texte));

        // Les adresses web sont retirées : un lien anglais dans un texte
        // français en fausserait le comptage à lui seul.
        $texte = preg_replace('#https?://\S+#u', ' ', $texte) ?? $texte;

        preg_match_all('/[\p{L}]+/u', $texte, $trouves);

        return array_values(array_filter(
            $trouves[0] ?? [],
            static fn (string $mot): bool => mb_strlen($mot) >= 1,
        ));
    }
}
