<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Garde commun aux seeders de démonstration.
 *
 * Ces seeders créent des comptes — dont un administrateur — au mot de passe
 * connu de toute l'équipe. Trois verrous indépendants, parce qu'un seul finit
 * toujours par être contourné :
 *
 *   1. jamais en production, quelle que soit la configuration ;
 *   2. un interrupteur explicite (`DEMO_SEED_ENABLED`) ;
 *   3. un mot de passe fourni par l'environnement, sans valeur de repli.
 *
 * L'échec est bruyant : un seeder de démonstration qui ne ferait rien en
 * silence laisserait croire à une base peuplée.
 */
trait GardeDemonstration
{
    protected function motDePasseDemo(): string
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Les seeders de démonstration créent des comptes à mot de passe '
                .'connu : ils sont interdits en production.'
            );
        }

        if (config('verifall.demo.enabled') !== true) {
            throw new RuntimeException(
                'Jeu de démonstration désactivé. Posez DEMO_SEED_ENABLED=true '
                .'pour l\'autoriser sur cet environnement.'
            );
        }

        $motDePasse = config('verifall.demo.password');

        if (! is_string($motDePasse) || $motDePasse === '') {
            throw new RuntimeException(
                'DEMO_PASSWORD est vide. Aucun mot de passe par défaut n\'est '
                .'fourni : il vivrait dans le dépôt.'
            );
        }

        return $motDePasse;
    }
}
