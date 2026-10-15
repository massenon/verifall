<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use Illuminate\Http\Request;

/**
 * Lecture d'un filtre de liste — une seule interprétation, pour toutes les
 * listes.
 *
 * POURQUOI CE HELPER EXISTE. Les trois filtres `category` de l'API — forum,
 * Hub, outils — s'écrivaient chacun à leur façon : l'un ignorait la sentinelle
 * `toutes`, l'autre la reconnaissait, le troisième traitait la chaîne vide
 * différemment des deux premiers. Trois interprétations d'un même paramètre,
 * c'est-à-dire trois occasions de rendre une liste **plausible mais fausse** —
 * l'échec le plus coûteux qui soit, puisque personne ne le voit.
 *
 * LA RÈGLE, ET IL N'Y EN A QU'UNE :
 *
 *     paramètre absent, vide, ou égal à `toutes` ⇒ AUCUN filtre.
 *
 * La sentinelle est acceptée parce que l'interface est faite de pastilles : la
 * pastille « Toutes » envoie une valeur comme les autres, et exiger qu'elle
 * n'envoie rien obligerait le client à traiter une pastille à part — ce qu'il
 * finirait par oublier.
 */
final class Filtre
{
    /**
     * Valeur envoyée par la pastille « Toutes ».
     *
     * Une constante et non une chaîne recopiée dans trois contrôleurs : c'est
     * exactement le genre de littéral qui diverge d'une lettre, après quoi le
     * filtre cesse silencieusement de fonctionner sur une liste et pas sur les
     * autres.
     */
    public const SANS_FILTRE = 'toutes';

    /**
     * Rend la valeur du filtre, ou `null` quand il ne faut filtrer sur rien.
     */
    public static function valeur(Request $requete, string $nom): ?string
    {
        $valeur = trim($requete->string($nom)->toString());

        if ($valeur === '' || $valeur === self::SANS_FILTRE) {
            return null;
        }

        return $valeur;
    }
}
