<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Core\Marketing\Services\Infolettre;
use App\Mail\RecapitulatifHebdomadaire;
use App\Models\Campaign;
use App\Models\HubArticle;
use App\Models\NewsletterSubscriber;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * L'infolettre du lundi — le rendez-vous, tenu par une machine.
 *
 * ## Ce qui rend l'oubli difficile
 *
 * Pas l'existence de cette commande : sa PLANIFICATION, et le fait qu'elle
 * laisse une trace même quand elle n'envoie rien. Deux lundis manqués tuent un
 * rendez-vous hebdomadaire, et la panne d'un cron est silencieuse par nature.
 * Les deux garde-fous sont donc :
 *
 *   · une édition ouverte en base à chaque passage, datée — l'écran
 *     d'administration montre « dernier envoi il y a N jours », et devient
 *     rouge quand N dépasse huit ;
 *   · un journal écrit quand il n'y a rien à dire, et un CODE DE SORTIE
 *     distinct (2), pour qu'un superviseur puisse le remarquer.
 *
 * ## L'idempotence
 *
 * Rejouée le même lundi, la commande n'envoie pas deux fois. La garantie ne
 * vient pas d'un `if` mais de la contrainte d'unicité de
 * `newsletter_deliveries` : chaque destinataire est RÉSERVÉ avant l'envoi, et
 * une réservation refusée signifie « déjà servi ». Le même mécanisme permet de
 * reprendre un envoi interrompu à mi-course sans repartir de zéro.
 */
final class EnvoyerInfolettreHebdomadaire extends Command
{
    /**
     * Code de sortie « rien à dire cette semaine ».
     *
     * Ni 0 ni 1 : ce n'est pas un succès (rien n'est parti) et ce n'est pas une
     * panne (tout a fonctionné). Un code à part est la seule façon pour une
     * supervision de distinguer les trois.
     */
    public const RIEN_A_DIRE = 2;

    protected $signature = 'verifall:infolettre-hebdomadaire
        {--dry-run : Montre ce qui partirait, sans envoyer ni rien écrire}
        {--date= : Date de référence (AAAA-MM-JJ), pour rejouer une semaine précise}';

    protected $description = 'Envoie le récapitulatif hebdomadaire des fact-checks publiés aux abonnés confirmés.';

    public function handle(Infolettre $infolettre, DepotAbonnes $depot): int
    {
        $jour = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::now();

        $articles = $infolettre->factChecksDeLaSemaine($jour);

        /*
         | Semaine vide : on N'ENVOIE PAS. Une infolettre sans contenu use le
         | rendez-vous plus qu'elle ne l'entretient — elle apprend au lecteur
         | que l'ouvrir ne sert à rien. Mais on le DIT : une semaine sans
         | fact-check publié est en soi une information pour l'équipe.
         */
        if ($articles->isEmpty()) {
            $infolettre->signalerSemaineVide($jour);

            $this->warn(sprintf(
                'Aucune brève publiée sur les %d derniers jours : rien n\'a été envoyé.',
                Infolettre::JOURS_DE_FENETRE,
            ));
            $this->line('  Ce n\'est pas une panne — c\'est une semaine sans publication. À vérifier côté rédaction.');

            return self::RIEN_A_DIRE;
        }

        $destinataires = $depot->compterDestinataires();

        $this->line(sprintf(
            'Édition %s — %d brève(s), %d destinataire(s) confirmé(s).',
            $infolettre->cleEdition($jour), $articles->count(), $destinataires,
        ));

        if ($this->option('dry-run')) {
            foreach ($articles as $article) {
                $this->line('  · ['.$article->libelleDuBandeau().'] '.$article->title);
            }

            $this->info('Essai à blanc : rien n\'a été envoyé, rien n\'a été écrit.');

            return self::SUCCESS;
        }

        $campagne = $infolettre->ouvrirEdition($jour, $articles);

        if (StatutCampagne::depuis($campagne->status) === StatutCampagne::Envoyee) {
            /*
             | Déjà envoyée. On s'arrête ici SANS erreur : une commande rejouée
             | par un cron qui rattrape son retard doit se taire, pas alerter.
             | Le filet de sécurité par destinataire existe de toute façon plus
             | bas — celui-ci ne fait qu'éviter le travail inutile.
             */
            $this->info('Édition déjà envoyée le '.$campagne->sent_at?->format('d/m/Y H:i').'. Rien à faire.');

            return self::SUCCESS;
        }

        $depot->recalerCompteurs($campagne, $destinataires, StatutCampagne::EnCours->value);

        $periode = $this->periode($infolettre, $jour);

        $envoyes = 0;
        $echecs = 0;
        $ignores = 0;
        $dernierId = 0;

        while (true) {
            $lot = $depot->destinataires(Infolettre::TAILLE_LOT, $dernierId);

            if ($lot->isEmpty()) {
                break;
            }

            foreach ($lot as $abonne) {
                $dernierId = $abonne->id;

                // Réservation d'abord, envoi ensuite. L'ordre est le cœur de
                // l'idempotence : deux processus ne peuvent pas réserver la
                // même ligne, donc un seul enverra.
                if (! $depot->reserverEnvoi($campagne, $abonne)) {
                    $ignores++;

                    continue;
                }

                if ($this->envoyer($infolettre, $campagne, $depot, $abonne, $articles, $periode)) {
                    $envoyes++;
                } else {
                    $echecs++;
                }
            }
        }

        $campagne = $depot->recalerCompteurs($campagne, $destinataires, StatutCampagne::Envoyee->value);

        $this->info(sprintf(
            'Édition envoyée : %d destinataire(s), %d envoyé(s), %d échec(s)%s.',
            $campagne->recipients_count,
            $campagne->sent_count,
            $campagne->failed_count,
            $ignores > 0 ? sprintf(', %d déjà servi(s)', $ignores) : '',
        ));

        if ($echecs > 0) {
            // Un envoi partiel n'est pas un envoi réussi. Le dire ici évite
            // qu'une supervision qui ne lit que le code de sortie conclue que
            // toute la liste a été touchée.
            Log::warning('Infolettre : envoi partiel.', [
                'edition' => $campagne->edition_key,
                'envoyes' => $envoyes,
                'echecs' => $echecs,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, HubArticle>  $articles
     */
    private function envoyer(
        Infolettre $infolettre,
        Campaign $campagne,
        DepotAbonnes $depot,
        NewsletterSubscriber $abonne,
        Collection $articles,
        string $periode,
    ): bool {
        try {
            Mail::to($abonne->email)->send(new RecapitulatifHebdomadaire(
                objet: (string) $campagne->subject,
                articles: $articles,
                lienDeDesabonnement: $infolettre->lienDeDesabonnement($abonne),
                nomDuSite: $infolettre->nomDuSite(),
                periode: $periode,
                prenom: $abonne->name,
            ));

            $depot->marquerEnvoye($campagne, $abonne);

            return true;
        } catch (Throwable $e) {
            /*
             | Un échec sur UNE adresse n'arrête pas l'édition : une boîte
             | pleine ne doit pas priver les 3 000 suivantes. L'erreur est
             | écrite sur la trace, comptée, et l'envoi continue.
             */
            $depot->marquerEchoue($campagne, $abonne, $e->getMessage());

            $this->line('  ✗ échec sur un destinataire : '.mb_substr($e->getMessage(), 0, 120));

            return false;
        }
    }

    private function periode(Infolettre $infolettre, CarbonImmutable $jour): string
    {
        [$debut, $fin] = $infolettre->fenetre($jour);

        return sprintf(
            'du %s au %s',
            $debut->locale('fr')->isoFormat('D MMMM'),
            $fin->locale('fr')->isoFormat('D MMMM YYYY'),
        );
    }
}
