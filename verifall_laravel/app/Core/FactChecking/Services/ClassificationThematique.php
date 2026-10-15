<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Enums\ThematiqueVerification;
use Illuminate\Support\Str;

/**
 * Range une affirmation sous l'une des six thématiques.
 *
 * ## Pourquoi une heuristique lexicale et NON un appel de modèle
 *
 * Le réflexe serait de demander le thème à Gemini, qui lit déjà l'affirmation.
 * Trois raisons de ne pas le faire, et elles tiennent toutes :
 *
 *   1. **Le coût.** Une thématique ne vaut pas une requête facturée, ni une
 *      unité de quota prise sur celles qui servent au verdict.
 *   2. **La stabilité de l'agrégat.** Un modèle qui rend « Politiqu3 » ou
 *      « Politique et société » une fois sur cent créerait des colonnes
 *      parasites dans un panneau dont l'intérêt est de se comparer d'une
 *      semaine à l'autre.
 *   3. **L'explicabilité.** Sur une plateforme de fact-checking, « pourquoi
 *      cette analyse est-elle rangée en Arnaque ? » doit avoir une réponse
 *      vérifiable. Ici, c'est « parce qu'elle contient *mobile money* ». Avec
 *      un modèle, la réponse serait « parce qu'il l'a dit ».
 *
 * Le classement se fait donc AVANT toute analyse, ne dépend d'aucun service
 * tiers, et fonctionne même quand tous les moteurs sont en panne.
 *
 * ## La règle de départage
 *
 * Le thème qui compte le PLUS de mots distincts l'emporte. À égalité, l'ordre
 * de {@see self::ORDRE_DE_PRIORITE} tranche — et il place `Arnaque` en tête :
 * « gagnez 500 000 F en investissant dans la santé » est d'abord une tentative
 * d'escroquerie, et c'est ce qu'il faut voir monter dans le panneau.
 */
final class ClassificationThematique
{
    /**
     * À nombre égal de mots trouvés, ce qui l'emporte.
     *
     * `Arnaque` d'abord : une fraude déguisée en information de santé ou de
     * politique reste une fraude, et c'est l'urgence à faire remonter.
     *
     * @var list<ThematiqueVerification>
     */
    private const ORDRE_DE_PRIORITE = [
        ThematiqueVerification::Arnaque,
        ThematiqueVerification::Sante,
        ThematiqueVerification::Securite,
        ThematiqueVerification::Politique,
        ThematiqueVerification::Economie,
    ];

    public function pour(string $affirmation): ThematiqueVerification
    {
        $texte = $this->normaliser($affirmation);

        if ($texte === '') {
            return ThematiqueVerification::General;
        }

        $meilleur = ThematiqueVerification::General;
        $meilleurScore = 0;

        foreach (self::ORDRE_DE_PRIORITE as $theme) {
            $score = $this->compter($texte, $theme);

            // Strictement supérieur : l'ordre de priorité conserve le premier
            // arrivé en cas d'égalité, ce qui EST la règle de départage.
            if ($score > $meilleurScore) {
                $meilleur = $theme;
                $meilleurScore = $score;
            }
        }

        return $meilleur;
    }

    /** Combien de mots-clés DISTINCTS de ce thème apparaissent. */
    private function compter(string $texte, ThematiqueVerification $theme): int
    {
        $trouves = 0;

        foreach ($theme->motsCles() as $mot) {
            if (str_contains($texte, $mot)) {
                $trouves++;
            }
        }

        return $trouves;
    }

    /**
     * Minuscules, sans accents, ponctuation réduite à des espaces.
     *
     * ## `Str::ascii()` et surtout PAS `iconv('ASCII//TRANSLIT')`
     *
     * `iconv` dépend de la locale du système. Sur ce poste, il rend
     * **`pr'esident`** pour « président » : l'apostrophe insérée devient une
     * espace au nettoyage, le mot se casse en `pr sident`, et aucun mot-clé
     * accentué n'est plus jamais reconnu. Le classement fonctionnait sur
     * « vaccin » et « carburant », et échouait en silence sur « président »,
     * « économie », « sécurité » — la moitié de la taxonomie, sans qu'aucune
     * erreur ne le signale.
     *
     * `Str::ascii()` s'appuie sur une table de correspondance embarquée : même
     * résultat sur toutes les machines, ce qu'exige un agrégat qui doit se
     * comparer d'une semaine à l'autre.
     *
     * ## Pourquoi la ponctuation devient une ESPACE et non rien
     *
     * Sans cela, « santé,vaccin » deviendrait « santevaccin » et aucun des deux
     * mots ne serait reconnu. Les espaces multiples sont ensuite écrasés, pour
     * que les mots-clés en deux mots — « mobile money » — se retrouvent tels
     * quels.
     */
    private function normaliser(string $texte): string
    {
        $texte = Str::ascii(mb_strtolower(trim($texte)));

        $texte = (string) preg_replace('/[^a-z0-9]+/', ' ', $texte);

        return trim((string) preg_replace('/\s+/', ' ', $texte));
    }
}
