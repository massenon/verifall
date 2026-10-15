<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder par défaut — le SOCLE, et lui seul.
 *
 * `php artisan db:seed` ne crée aucun compte : les rôles et permissions sont
 * la seule chose dont toute installation a besoin, y compris en production.
 *
 * Le jeu de démonstration est explicitement séparé (`DemoSeeder`) : il crée des
 * comptes au mot de passe connu, et personne ne doit pouvoir l'exécuter sans
 * l'avoir voulu.
 *
 * PAS de `WithoutModelEvents`, contrairement au squelette Laravel : spatie
 * invalide son cache de permissions DEPUIS les événements de modèle. Muets,
 * les permissions se créent en base mais restent invisibles au cache, et
 * l'attribution suivante échoue sur « There is no permission named … ». La
 * commande d'installation documentée échouait ainsi sur toute base neuve.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,

            /*
             | Le texte des 24 courriels. Idempotent — il ne remplit que les
             | champs VIDES —, donc rejouable sur une base en service sans
             | effacer ce qu'un rédacteur a corrigé.
             |
             | Ici et pas dans un semis de démonstration : sans lui, la
             | plateforme part avec des objets de repli et AUCUN corps. Un
             | courriel vide est pire qu'un courriel laid.
             */
            GabaritsDEmailSeeder::class,
        ]);
    }
}
