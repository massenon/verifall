<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application\Contracts\DepotNotes;
use App\Core\Community\Services\ScoreDePontage;
use Illuminate\Console\Command;

/**
 * Recalcule le score de pontage de toutes les notes de communauté.
 *
 * POURQUOI UNE COMMANDE, ET NON UNE REQUÊTE WEB. L'algorithme compare chaque
 * PAIRE de votants pour savoir qui vote habituellement avec qui : le coût est
 * quadratique en nombre de votants. Le faire à l'affichage d'un sujet
 * signifierait recalculer tout le forum à chaque ouverture d'un fil, et la
 * page la plus lue de la plateforme deviendrait la plus lente.
 *
 * Le score est donc CALCULÉ ICI et STOCKÉ sur la note. L'écran, lui, ne fait
 * plus qu'un `where status = published`.
 *
 * CONSÉQUENCE À CONNAÎTRE : une note franchit le pont au prochain passage de
 * la commande, pas au moment du vote qui la fait basculer. Ce délai est
 * assumé — il est même sain : il empêche de tester des combinaisons de votes
 * en direct pour trouver celle qui publie.
 *
 * Sans `schedule:run` branché sur le serveur, AUCUNE note ne se publie jamais.
 * C'est la panne silencieuse à surveiller en premier si le forum n'affiche
 * plus que des notes en attente.
 */
final class CalculerLesScoresDePontage extends Command
{
    protected $signature = 'verifall:pontage {--dry-run : Calcule et affiche le bilan, sans rien écrire}';

    protected $description = 'Recalcule le score de pontage des notes de communauté et publie celles qui franchissent le pont.';

    public function handle(ScoreDePontage $pontage, DepotNotes $depot): int
    {
        if ($this->option('dry-run')) {
            /*
             | Le mode « à blanc » n'écrit rien, mais il ne ment pas non plus :
             | il refait exactement le même calcul et montre ce qu'il aurait
             | posé. Un mode d'essai qui suivrait un autre chemin de code ne
             | prouverait rien de ce qui tournera en production.
             */
            $this->info('Mode à blanc : aucun score ne sera écrit.');

            $this->line(sprintf(
                '  %d note(s) auraient un score recalculé.',
                count($pontage->scores($depot->matriceDesVotes())),
            ));

            return self::SUCCESS;
        }

        $bilan = $pontage->recalculerTout();

        if ($bilan['evaluees'] === 0) {
            $this->info('Aucune note à évaluer : personne n\'a encore voté.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d note(s) évaluée(s) — %d publiée(s), %d en attente, %d non retenue(s).',
            $bilan['evaluees'],
            $bilan['publiees'],
            $bilan['en_attente'],
            $bilan['non_retenues'],
        ));

        /*
         | L'attente n'est pas un incident : sur un forum jeune, la plupart des
         | notes n'ont simplement pas encore assez de votes pour qu'une
         | polarisation soit mesurable. Le dire explicitement évite qu'un
         | exploitant conclue à une panne et « corrige » le seuil.
         */
        if ($bilan['en_attente'] > 0) {
            $this->line(sprintf(
                '  %d note(s) attendent : moins de %d votes, ou aucune paire d\'approbateurs dont on connaisse le désaccord habituel.',
                $bilan['en_attente'],
                ScoreDePontage::VOTES_MINIMUM,
            ));
        }

        return self::SUCCESS;
    }
}
