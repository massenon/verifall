<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * L'heure de départ d'une leçon ou d'un quiz — mesurée PAR LE SERVEUR.
 *
 * POURQUOI. Deux règles du produit dépendent d'une durée : une leçon n'est
 * acquise qu'après un temps de lecture minimal, un quiz est chronométré. Le
 * navigateur s'appuie sur la session pour poser le point de départ ; l'API n'a
 * pas de session, et se fier à l'horloge du téléphone rendrait les deux règles
 * décoratives — il suffirait d'annoncer « j'ai lu deux heures », ou de reculer
 * la montre pour rallonger l'épreuve.
 *
 * Le départ est posé à la LECTURE de la ressource, jamais à l'écriture : c'est
 * l'ouverture de la leçon qui démarre le compteur, pas sa validation.
 *
 * `Cache::add` et non `put` : réouvrir une leçon ne remet pas le compteur à
 * zéro. Sans cela, quitter et rouvrir suffirait à effacer le temps déjà passé
 * — et punirait exactement les gens qui lisent en plusieurs fois, dans le
 * transport, ce qui est le cas d'usage principal d'une application mobile au
 * Bénin.
 */
final class Chronometre
{
    /** Une journée : au-delà, une reprise est une nouvelle séance. */
    private const HEURES = 24;

    /**
     * Pose le départ s'il n'existe pas, et rend l'heure qui fait foi.
     */
    public static function demarrer(string $sujet, User $utilisateur, string $reference): Carbon
    {
        $cle = self::cle($sujet, $utilisateur, $reference);

        Cache::add($cle, now()->timestamp, now()->addHours(self::HEURES));

        return Carbon::createFromTimestamp((int) Cache::get($cle, now()->timestamp));
    }

    /**
     * Secondes écoulées depuis le départ.
     *
     * Zéro si aucun départ n'a été posé — c'est-à-dire si le client a tenté de
     * valider sans avoir jamais ouvert. La règle de durée minimale le refusera
     * ensuite, et c'est exactement ce qu'il faut.
     */
    public static function ecoulees(string $sujet, User $utilisateur, string $reference): int
    {
        $depart = Cache::get(self::cle($sujet, $utilisateur, $reference));

        if (! is_int($depart)) {
            return 0;
        }

        return max(0, now()->timestamp - $depart);
    }

    private static function cle(string $sujet, User $utilisateur, string $reference): string
    {
        return 'api:chrono:'.$sujet.':'.$utilisateur->getAuthIdentifier().':'.$reference;
    }
}
