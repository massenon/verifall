<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Economy\Services\EconomyService;
use App\Enums\MotifMouvementUnite;
use App\Enums\UserRole;
use App\Models\UnitLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Dotation mensuelle — « 10 unités offertes chaque 1ᵉʳ du mois » (prototype).
 *
 * Elle AJOUTE, elle ne remet pas à zéro, malgré le nom « ResetMonthlyUnits »
 * de la feuille de route : un compte ayant acheté 210 unités les perdrait au
 * premier du mois, ce qui reviendrait à confisquer ce qui a été payé. Le
 * détail est consigné dans docs/ARBITRAGES.md.
 *
 * Idempotente par MOIS CIVIL : relancée le même mois — reprise après incident,
 * planificateur qui double — elle ne crédite pas deux fois.
 */
final class AttribuerDotationMensuelle extends Command
{
    protected $signature = 'verifall:dotation-mensuelle';

    protected $description = 'Crédite la dotation mensuelle d\'unités aux comptes citoyens actifs';

    public function handle(EconomyService $economie): int
    {
        if (config('economy.monthly_grant.enabled') !== true) {
            $this->warn('Dotation mensuelle désactivée.');

            return self::SUCCESS;
        }

        $unites = (int) config('economy.monthly_grant.units', 10);
        $debutDuMois = now()->startOfMonth();
        $credites = 0;

        User::role(UserRole::Citoyen->value)
            // Comptes ACTIVÉS uniquement : doter une inscription jamais
            // confirmée offrirait des unités à une adresse dont personne n'a
            // prouvé la maîtrise.
            ->whereNotNull('otp_verified_at')
            ->whereNull('anonymized_at')
            ->chunkById(200, function ($comptes) use ($economie, $unites, $debutDuMois, &$credites): void {
                foreach ($comptes as $compte) {
                    $dejaDote = UnitLog::where('user_id', $compte->id)
                        ->where('reason', MotifMouvementUnite::DotationMensuelle->value)
                        ->where('created_at', '>=', $debutDuMois)
                        ->exists();

                    if ($dejaDote) {
                        continue;
                    }

                    $economie->crediter(
                        $compte, $unites, MotifMouvementUnite::DotationMensuelle,
                        'Dotation de '.$debutDuMois->translatedFormat('F Y'),
                    );

                    $credites++;
                }
            });

        $this->info($credites.' compte(s) doté(s) de '.$unites.' unités.');

        return self::SUCCESS;
    }
}
