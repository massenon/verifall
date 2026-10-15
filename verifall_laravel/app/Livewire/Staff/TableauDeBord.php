<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Services\PanneauxDuTableauDeBord;
use App\Core\Application\Services\SanteDuSysteme;
use App\Core\Application\Services\TableauDeBordPersonnel;
use App\Core\FactChecking\Services\AssignationDeVerification;
use App\Core\FactChecking\Services\RetoursSurLeRapport;
use App\Core\FactChecking\Services\SanteDesMoteurs;
use App\Enums\EtatMoteur;
use App\Enums\StatutContreExpertise;
use App\Models\CounterExpertise;
use App\Models\User;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Le tableau de bord du personnel.
 *
 * REMPLACE UN ÉCRAN D'ATTENTE. La route servait `stub.staff`, qui affichait
 * « SPRINT 1A — le contenu de cette page arrive à son sprint ». C'était la
 * PREMIÈRE page après connexion : la seule chose que voyait un membre de
 * l'équipe en arrivant, et la pire première impression possible pour un
 * back-office par ailleurs fonctionnel.
 *
 * Le composant reste mince : les chiffres et les règles qui décident de ce
 * qu'ils comptent vivent dans `TableauDeBordPersonnel`.
 */
final class TableauDeBord extends Component
{
    use InteractsWithToasts;

    /**
     * Le destinataire choisi, par UUID de vérification.
     *
     * Un tableau et non une chaîne : chaque ligne du panneau porte son propre
     * sélecteur. Avec une seule propriété, choisir un nom sur la troisième
     * ligne le ferait apparaître sur les huit.
     *
     * @var array<string, string>
     */
    public array $destinataire = [];

    /**
     * La réponse au demandeur, par UUID de contestation.
     *
     * Un tableau, comme pour l'assignation : chaque ligne porte son champ.
     *
     * @var array<string, string>
     */
    public array $resolution = [];

    /**
     * Tranche une contre-expertise depuis le tableau de bord.
     *
     * L'issue est CHOISIE, jamais déduite d'un bouton « traiter » générique :
     * « nous maintenons », « nous nous étions trompés » et « écartée sans
     * examen » sont trois réponses différentes, et le demandeur attend
     * précisément de savoir laquelle.
     */
    public function trancherLaContestation(
        string $uuid,
        string $issue,
        RetoursSurLeRapport $retours,
    ): void {
        $arbitre = auth('staff')->user();

        // La garde est ici ET dans la vue : un appel Livewire se forge sans
        // passer par l'affichage.
        if ($arbitre === null || $arbitre->cannot('signalement.verdict')) {
            $this->toastError("Vous n'avez pas le droit de trancher une contestation.");

            return;
        }

        $demande = CounterExpertise::query()->where('uuid', $uuid)->first();
        $choisie = StatutContreExpertise::tryFrom($issue);

        if ($demande === null || $choisie === null) {
            $this->toastError('Cette contestation n’existe plus.');

            return;
        }

        try {
            $retours->trancher($demande, $choisie, $arbitre, $this->resolution[$uuid] ?? null);
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        unset($this->resolution[$uuid]);

        $this->toastSuccess(sprintf(
            '%s — le demandeur est prévenu.',
            $choisie->libelle(),
        ));
    }

    /**
     * Confie une vérification à un membre de l'équipe.
     *
     * La garde est ici ET dans la vue : la vue cache le sélecteur, mais un
     * appel Livewire se forge sans passer par la vue. Le contrôle d'accès ne
     * peut jamais reposer sur ce qui est affiché.
     */
    public function assigner(string $uuid, AssignationDeVerification $assignation): void
    {
        $auteur = auth('staff')->user();

        if ($auteur === null || $auteur->cannot('signalement.assign')) {
            $this->toastError("Vous n'avez pas le droit de confier une vérification.");

            return;
        }

        $choisi = trim($this->destinataire[$uuid] ?? '');

        if ($choisi === '') {
            $this->toastWarning('Choisissez d’abord la personne à qui confier ce dossier.');

            return;
        }

        $verification = Verification::query()->where('uuid', $uuid)->first();
        $membre = User::query()->where('uuid', $choisi)->first();

        if ($verification === null || $membre === null) {
            $this->toastError('Ce dossier ou ce membre n’existe plus.');

            return;
        }

        try {
            $assignation->confier($verification, $membre, $auteur);
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        unset($this->destinataire[$uuid]);

        /*
         | On annonce le COURRIEL, pas seulement l'assignation. Sans worker de
         | file — le cas en hébergement mutualisé sans entrée cron — le message
         | ne partira jamais et rien ne le signalera. Dire « et prévenu par
         | courriel » rend l'attente vérifiable : la carte « Santé du système »,
         | juste au-dessus, affiche l'âge du plus ancien job en attente.
         */
        $this->toastSuccess(sprintf(
            '%s est prévenu par notification et par courriel.',
            $membre->name,
        ));
    }

    /**
     * Relance les sondes des moteurs, à la demande.
     *
     * Ce geste est EXPLICITE et ne se déclenche jamais à l'affichage : le quota
     * gratuit de Google Custom Search est de cent requêtes par jour, et un
     * tableau de bord qui sonde à chaque ouverture épuiserait avant midi le
     * quota du moteur qu'il surveille. Le reste du temps, c'est le
     * planificateur qui rafraîchit, toutes les heures.
     */
    public function sonderLesMoteurs(SanteDesMoteurs $sante): void
    {
        $etat = $sante->mesurer();

        $injoignables = array_filter(
            $etat['moteurs'],
            fn (array $moteur): bool => $moteur['etat'] === EtatMoteur::Injoignable,
        );

        /*
         | Un moteur injoignable est une PANNE, pas une information : la sonde
         | qui la découvre doit le dire, sinon elle se contente de repeindre
         | une pastille que personne ne regarde.
         */
        if ($injoignables !== []) {
            $this->toastError(sprintf(
                '%d moteur(s) refusent notre clé : %s.',
                count($injoignables),
                implode(', ', array_column($injoignables, 'libelle')),
            ));

            return;
        }

        $this->toastSuccess(sprintf(
            '%d moteur(s) utilisable(s) sur la vérification.',
            $sante->nombreQuiComptent(),
        ));
    }

    #[Layout('components.layouts.staff')]
    #[Title('Tableau de bord')]
    public function render(
        TableauDeBordPersonnel $chiffres,
        SanteDuSysteme $sante,
        PanneauxDuTableauDeBord $panneaux,
        AssignationDeVerification $assignation,
    ): View {
        $membre = auth('staff')->user();
        $peutAssigner = $membre?->can('signalement.assign') ?? false;

        return view('livewire.staff.tableau-de-bord', [
            'aTraiter' => $chiffres->aTraiter(),
            'laboratoire' => $chiffres->laboratoire(),
            'volumes' => $chiffres->volumes(),
            'verifications' => $chiffres->dernieresVerifications(),
            'faits' => $chiffres->derniersFaits(),
            'avertissements' => $chiffres->avertissements(),
            'sante' => $sante->tout(),

            'articles' => $panneaux->performanceDesArticles(),
            'signalements' => $panneaux->activiteDesSignalements(),
            'citoyens' => $panneaux->nouveauxCitoyens(),
            'audit' => $panneaux->journalDAudit(),
            'navigateurs' => $panneaux->navigateurs(),
            'thematiques' => $panneaux->topThematiques(),
            /*
             | Le panneau s'affichait à TOUT membre du personnel : la file des
             | vérifications citoyennes — l'affirmation soumise, qui l'a soumise
             | — était visible d'un mentor ou d'un rédacteur, qui n'ont rien à y
             | faire.
             |
             | `signalement.view` était déclarée pour exactement cela, et
             | employée nulle part. Elle garde enfin quelque chose.
             */
            'activites' => ($membre?->can('signalement.view') ?? false)
                ? $panneaux->activitesDeFactChecking()
                : collect(),
            'peutVoirLesSignalements' => $membre?->can('signalement.view') ?? false,
            'contreExpertises' => $panneaux->contreExpertises(),

            'peutAssigner' => $peutAssigner,
            'peutArbitrer' => $membre?->can('signalement.verdict') ?? false,
            // La liste n'est construite que si elle sert : la charger pour
            // quelqu'un qui ne verra aucun sélecteur est une requête pour rien
            // sur la page la plus ouverte du back-office.
            'assignables' => $peutAssigner
                ? $assignation->destinatairesPossibles()
                : new EloquentCollection,

            'membre' => $membre,
        ]);
    }
}
