<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\FactChecking\Services\RegistreDesMoteurs;
use App\Enums\TypeContenu;
use App\Http\Api\V1\Support\Enveloppe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réglages lisibles par le client mobile — lecture seule, sans état.
 *
 * POURQUOI un endpoint séparé et non un champ sur `/me` : les modules actifs
 * ne dépendent pas de l'utilisateur mais de l'admin. Les regrouper avec le
 * profil obligerait le client à redemander le profil pour rafraîchir un
 * paramètre qui n'a rien à voir avec lui.
 */
final class ConfigurationController
{
    /**
     * `GET /configuration/modules`
     *
     * Retourne, pour chaque TypeContenu, si le module est ouvert — au sens de
     * `RegistreDesMoteurs::moduleOuvert()` : drapeau admin ET moteur configuré.
     *
     * Le client mobile cache cette réponse 60 s : un admin qui désactive un
     * type le voit répercuté sur les nouveaux appareils dans la minute. Les
     * vérifications en cours (file hors ligne) passent toujours par l'API, qui
     * refuse de toute façon un type désactivé avec une 422 explicite.
     */
    public function modules(
        Request $requete,
        RegistreDesMoteurs $registre,
    ): JsonResponse {
        $modules = [];
        foreach (TypeContenu::cases() as $type) {
            $modules[$type->value] = $registre->moduleOuvert($type);
        }

        return Enveloppe::donnee(['modules' => $modules]);
    }
}
