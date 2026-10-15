<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Marketing\Cartes\CarteDeDementi;
use App\Models\HubArticle;
use Illuminate\Console\Command;

/**
 * Fabrique les cartes de démenti des fact-checks publiés avant l'observateur.
 *
 * POURQUOI ELLE EXISTE. La carte est tenue à jour par un observateur de modèle,
 * qui ne se déclenche qu'à l'enregistrement. Les fact-checks déjà en base au
 * moment où l'observateur a été posé n'en ont donc jamais reçu, et n'en
 * recevront pas tant que personne ne les rouvrira. Sur une installation neuve la
 * question ne se pose pas ; sur une base en service, ce sont précisément les
 * articles les plus partagés qui manquent.
 *
 * ELLE NE TOURNE PAS AU PLANIFICATEUR, et c'est voulu : c'est un rattrapage, à
 * lancer une fois après un déploiement. Une tâche récurrente qui ne trouve
 * jamais rien à faire coûte un balayage de table toutes les heures pour rien —
 * et masquerait, le jour où elle trouverait quelque chose, le fait que
 * l'observateur ne fonctionne plus.
 */
final class EngendrerLesCartesManquantes extends Command
{
    protected $signature = 'verifall:engendrer-cartes-manquantes
        {--dry-run : Montre ce qui serait fabriqué, sans rien écrire}
        {--maintenant : Fabrique tout de suite au lieu de mettre en file}';

    protected $description = 'Rattrape les cartes de démenti des fact-checks publiés avant la mise en place de l\'observateur.';

    public function handle(CarteDeDementi $cartes): int
    {
        $essai = (bool) $this->option('dry-run');

        /*
         | Le même filtre que `estEligible()` — un brouillon n'a pas de carte,
         | et un verdict absent donnerait une bande vide. Il est répété ici pour
         | que la requête ne rapatrie pas toute la table ; `estEligible()` reste
         | la référence, et la vérification finale.
         */
        $candidats = HubArticle::query()
            ->where('is_published', true)
            ->whereNotNull('verdict')
            ->whereNotNull('published_at');

        $total = 0;
        $traites = 0;

        // Par tranches : sur une base en service, ce sont des milliers de
        // brèves, et les charger d'un coup ferait tomber la commande sur un
        // hébergement mutualisé bien avant d'avoir fabriqué la première carte.
        $candidats->chunkById(100, function ($lot) use ($cartes, $essai, &$total, &$traites): void {
            foreach ($lot as $article) {
                if (! $cartes->estEligible($article) || $cartes->estAJour($article)) {
                    continue;
                }

                $total++;

                if ($essai) {
                    $this->line('  · '.$article->slug);

                    continue;
                }

                $this->option('maintenant')
                    ? $cartes->engendrer($article)
                    : $cartes->planifierSiNecessaire($article);

                $traites++;
            }
        });

        if ($total === 0) {
            $this->info('Aucune carte manquante : toutes les cartes sont à jour.');

            return self::SUCCESS;
        }

        if ($essai) {
            $this->warn($total.' carte(s) seraient fabriquée(s). Relancez sans --dry-run.');

            return self::SUCCESS;
        }

        $this->option('maintenant')
            ? $this->info($traites.' carte(s) fabriquée(s).')
            // Le mot compte : sans travailleur de file, « mises en file » n'est
            // pas « fabriquées », et quelqu'un pourrait croire le travail fini.
            : $this->info($traites.' carte(s) mises en file. Un `queue:work` doit tourner pour qu\'elles soient fabriquées.');

        return self::SUCCESS;
    }
}
