<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration complet — reproduit le prototype.
 *
 * `php artisan migrate:fresh --seed --class=DemoSeeder`
 *
 * L'ORDRE est une dépendance, pas une préférence : le RBAC doit exister avant
 * qu'un compte reçoive un rôle, et les comptes avant que le journal d'audit
 * puisse désigner un acteur.
 *
 * Les seeders de modules s'ajoutent ici à mesure que leurs sprints se
 * terminent. Un module livré mais absent d'ici donne une démonstration vide —
 * et un écran vide se compare mal au prototype : on croit à une page manquante
 * alors qu'il ne manque que la donnée.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            StaffSeeder::class,
            CitizenSeeder::class,
            // Pose le mot de passe de démonstration sur un compte de référence
            // par rôle, et imprime la liste. Il ne CRÉE rien : il vient donc
            // après les deux seeders ci-dessus, qui créent les comptes.
            ComptesDeDemonstrationSeeder::class,
            AuditLogSeeder::class,
            PacksUnitesSeeder::class,
            BadgesSeeder::class,
            AcademieSeeder::class,
            // Cinq cours au contenu RÉEL, écrits pour la plateforme —
            // là où le catalogue du prototype porte le même texte
            // d'illustration sur toutes ses leçons.
            CoursReelsSeeder::class,
            LiveClassSeeder::class,
            OutilsSeeder::class,
            CommunauteSeeder::class,
            // Après le forum, et jamais avant : les notes s'accrochent à des
            // sujets et des réponses que le seeder ci-dessus vient de créer.
            NotesEtSondagesSeeder::class,
            HubSeeder::class,
            BlogSeeder::class,
            // Les quatre annonces réelles, texte complet et photographies :
            // Prix WSIS, 2ᵉ prix ODD, lancement au CDIJ, séance de Porto-Novo.
            // Elle passe APRÈS BlogSeeder et enrichit les deux articles que
            // celui-ci pose en version courte.
            ActualitesVerifallSeeder::class,
            // Fenêtres et bandeaux du site public. Deux des trois sont
            // diffusables ; « newsletter-boom » est livrée éteinte.
            CampagnesMarketingSeeder::class,
            FaitsVerifiesSeeder::class,
            /*
             | Abonnés, messages de contact et signalements de contenu.
             |
             | En dernier, et c'est nécessaire : les signalements s'accrochent à
             | des sujets et des réponses que `CommunauteSeeder` crée plus haut,
             | et les messages traités citent les comptes de démonstration.
             |
             | Sans lui, trois écrans du back-office s'ouvrent VIDES en
             | démonstration — et un écran vide se lit comme un écran cassé.
             */
            CourrierEtModerationSeeder::class,
        ]);
    }
}
