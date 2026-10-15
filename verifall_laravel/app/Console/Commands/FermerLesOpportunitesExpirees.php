<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HubJob;
use Illuminate\Console\Command;

/**
 * Retire les opportunités dont le délai de grâce est écoulé.
 *
 * Une offre ne disparaît pas le jour de son échéance : les candidatures
 * arrivées la veille se traitent encore, et une annonce retirée trop tôt laisse
 * croire qu'elle n'a jamais existé à qui vient de postuler. Une semaine plus
 * tard, elle ne sert plus qu'à faire perdre du temps à quelqu'un qui cherche du
 * travail — c'est le moment de la retirer.
 *
 * Elle est DÉPUBLIÉE, jamais supprimée : l'équipe doit pouvoir vérifier ce qui a
 * été diffusé, et une offre retirée par erreur se remet en ligne d'un clic.
 */
final class FermerLesOpportunitesExpirees extends Command
{
    protected $signature = 'verifall:fermer-opportunites {--dry-run : Montre ce qui serait fermé, sans rien écrire}';

    protected $description = 'Dépublie les opportunités du Hub une semaine après leur date limite.';

    public function handle(): int
    {
        $limite = now()->subDays(HubJob::JOURS_DE_GRACE)->toDateString();

        $expirees = HubJob::query()
            ->where('is_published', true)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', $limite)
            ->get();

        if ($expirees->isEmpty()) {
            $this->info('Aucune opportunité à fermer.');

            return self::SUCCESS;
        }

        foreach ($expirees as $offre) {
            $this->line(sprintf(
                '  %s — %s (limite %s)',
                $offre->organisation,
                $offre->title,
                $offre->deadline->toDateString(),
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            $offre->forceFill([
                'is_published' => false,
                // Retenu pour distinguer une offre expirée d'une offre jamais
                // publiée : sans cette date, rétablir l'une demanderait de
                // rouvrir l'historique à la main.
                'closed_at' => now(),
            ])->save();
        }

        $this->info(sprintf(
            '%d opportunité(s) %s.',
            $expirees->count(),
            $this->option('dry-run') ? 'seraient fermées' : 'fermées',
        ));

        return self::SUCCESS;
    }
}
