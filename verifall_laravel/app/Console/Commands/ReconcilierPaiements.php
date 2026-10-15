<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Economy\Services\PaymentService;
use Illuminate\Console\Command;

/**
 * Rattrape les paiements restés sans réponse.
 *
 * Un rappel HTTP se perd — réseau coupé, serveur redémarré, opérateur en
 * panne. Sans cette commande, quelqu'un aurait payé sans jamais être crédité, et
 * n'aurait aucun recours que d'écrire au support.
 *
 * Chaque demande ancienne est RÉINTERROGÉE avant d'être conclue : présumer
 * l'échec priverait de ses unités quelqu'un qui a réellement payé.
 */
final class ReconcilierPaiements extends Command
{
    protected $signature = 'verifall:reconcilier-paiements';

    protected $description = 'Réinterroge les recharges en attente et clôt celles qui sont abandonnées';

    public function handle(PaymentService $paiements): int
    {
        $expirees = $paiements->expirerLesAbandonnees();

        $this->info($expirees.' recharge(s) close(s) faute de confirmation.');

        return self::SUCCESS;
    }
}
