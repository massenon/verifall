<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\SecurityLogService;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Journaux d'audit et de sécurité — de quoi peupler les deux pages du
 * back-office et rendre leurs filtres démontrables.
 *
 * Ne dépend d'aucun mot de passe : ce seeder n'ouvre aucun accès, il n'a donc
 * pas besoin du garde de démonstration. Il exige en revanche des comptes
 * existants — StaffSeeder passe avant.
 */
final class AuditLogSeeder extends Seeder
{
    public function run(AuditLogService $audit, SecurityLogService $securite): void
    {
        $admin = User::role(UserRole::Admin->value)->first();

        if ($admin === null) {
            // Sans acteur, les lignes seraient anonymes et la page d'audit
            // montrerait un journal qui ne désigne personne.
            $this->command?->warn('AuditLogSeeder ignoré : aucun administrateur. Lancez StaffSeeder.');

            return;
        }

        // Rejouable : on repart d'un journal vide plutôt que d'empiler les
        // mêmes traces à chaque exécution. C'est admissible ICI parce que ces
        // lignes sont fictives ; sur un journal réel, effacer serait exclu.
        AuditLog::query()->delete();
        SecurityLog::query()->delete();

        $mentor = User::role(UserRole::Mentor->value)->first();

        $audit->record(AuditLogService::CONNEXION, 'auth', $admin,
            cibleLibelle: $admin->email);

        $audit->record(AuditLogService::CREATION, 'course', $admin,
            cibleLibelle: 'OSINT — Fondamentaux');

        $audit->record(AuditLogService::PUBLICATION, 'signalement', $admin,
            cibleLibelle: 'Taxe WhatsApp 50 F');

        if ($mentor !== null) {
            $audit->recordDiff('user', $admin, $mentor,
                ['profession' => 'Mentor'],
                ['profession' => 'Mentor — Académie'],
            );

            $audit->record(AuditLogService::CHANGEMENT_ROLE, 'user', $admin,
                cibleLibelle: $mentor->name);
        }

        // Deux actions destructives : le compteur de la page d'audit les isole,
        // et sans elles la démonstration ne montrerait pas à quoi il sert.
        $audit->record(AuditLogService::SUPPRESSION, 'course', $admin,
            cibleLibelle: 'Cours obsolète');

        $audit->record(AuditLogService::EXPORT_DONNEES, 'audit', $admin,
            cibleLibelle: "Export du journal d'audit");

        $this->journalSecurite($securite);
    }

    /**
     * Quelques événements couvrant les trois criticités, pour que les filtres
     * de la page « Alertes Intrusion » aient de quoi filtrer.
     */
    private function journalSecurite(SecurityLogService $securite): void
    {
        $securite->loginEchoue('inconnu@exemple.com', 1);
        $securite->loginEchoue('inconnu@exemple.com', 5);

        $securite->record(SecurityLogService::LOGIN_VERROUILLE, 'warning',
            'inconnu@exemple.com', contexte: ['palier' => '5 tentatives']);

        $securite->record(SecurityLogService::OTP_FORCE, 'critical',
            'cible@exemple.com', contexte: ['tentatives' => 5]);

        $securite->record(SecurityLogService::DEBIT_LIMITE, 'warning',
            'robot@exemple.com', contexte: ['route' => 'login']);

        $securite->record(SecurityLogService::UPLOAD_REFUSE, 'warning',
            contexte: ['mime' => 'application/x-php']);
    }
}
