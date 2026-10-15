<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Identity\Services\DossiersKyc;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Rétention — deux obligations qui n'existent que si quelque chose les exécute.
 *
 *   · les archives d'export expirent au bout de 24 heures. Sans purge, elles
 *     s'accumuleraient sur le disque : autant de copies complètes de données
 *     personnelles, conservées sans base légale et sans que personne le sache.
 *   · les comptes dont le délai de rétractation est écoulé doivent être
 *     anonymisés. Un droit à l'effacement qui dépend d'une intervention
 *     manuelle n'est pas un droit.
 *   · **les pièces d'identité déposées pour le KYC** sont détruites trente
 *     jours après la décision. Le dossier survit — type, décision, motif,
 *     examinateur — mais les scans partent. Conserver indéfiniment des
 *     photos de cartes d'identité serait le pire dossier de la plateforme
 *     en cas de fuite, et rien ne l'exige une fois le contrôle fait.
 *
 * Planifiée toutes les heures (routes/console.php). Idempotente : la relancer
 * ne fait rien de plus.
 */
final class PurgerDonneesExpirees extends Command
{
    protected $signature = 'verifall:purger-donnees-expirees';

    protected $description = 'Détruit les archives d\'export expirées et anonymise les comptes échus';

    public function handle(SuppressionCompteService $suppressions, DossiersKyc $dossiers): int
    {
        $this->info($this->purgerExports().' archive(s) détruite(s).');
        $this->info($this->anonymiserComptes($suppressions).' compte(s) anonymisé(s).');
        $this->info($dossiers->purgerLesPiecesExpirees().' dossier(s) KYC dépiécé(s).');

        return self::SUCCESS;
    }

    private function purgerExports(): int
    {
        $expirees = DataExport::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expirees as $archive) {
            // detruire() efface le FICHIER en plus de la ligne : un delete()
            // laisserait sur le disque un orphelin plein de données
            // personnelles que plus rien ne viendrait chercher.
            $archive->detruire();
        }

        return $expirees->count();
    }

    private function anonymiserComptes(SuppressionCompteService $suppressions): int
    {
        $echus = User::onlyTrashed()
            ->whereNotNull('deletion_requested_at')
            ->whereNull('anonymized_at')
            ->where('deletion_requested_at', '<=',
                now()->subDays(SuppressionCompteService::JOURS_RETRACTATION))
            ->get();

        foreach ($echus as $compte) {
            $suppressions->anonymiser($compte);
        }

        return $echus->count();
    }
}
