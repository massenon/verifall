<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotExamens;
use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Core\Exams\Services\EnrolementExamen;
use App\Core\Exams\Services\TableauDeLEnrolementAuxExamens;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutInscriptionExamen;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * « Enrôlement aux Examens » — les candidats, toutes sessions confondues.
 *
 * ## La session est un FILTRE, pas un préalable
 *
 * Avant la migration DataTables (14 septembre 2026), rien ne s'affichait sans
 * choisir une session d'abord. La table sert maintenant l'ensemble des
 * inscriptions ; le sélecteur de session reste, mais filtre plutôt que
 * conditionne — et restreint, quand il est renseigné, les indicateurs et
 * l'inscription manuelle (qui exige une session de destination).
 *
 * « 47 inscrits » ne veut rien dire tout seul ; « 47 inscrits sur 40 places le
 * 12 octobre à Cotonou » veut dire qu'il faut agir — c'est ce que les
 * indicateurs affichent une fois la session choisie.
 *
 * ## `convene` est un verbe à part dans la matrice
 *
 * `enrolment => ['view', 'create', 'update', 'convene', 'delete']`. Convoquer
 * n'est pas modifier : la convocation part chez le candidat, porte un code, et
 * ne se rattrape pas. Le bouton est gardé séparément.
 *
 * ## Le report est ARBITRÉ, il ne s'accepte pas tout seul
 *
 * Un candidat demande un report ; quelqu'un décide vers quelle session. Le
 * service exige la session d'arrivée (`arbitrerLeReport`) — il n'y a pas de
 * « accepter » qui laisserait l'inscription en suspens sans date.
 */
#[Layout('components.layouts.staff', ['title' => "Enrôlement aux examens · Vérif'All"])]
final class EnrolementAuxExamens extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'enrolement-examens-modifie';

    /** La session regardée. Sans elle, l'écran ne montre rien d'utile. */
    #[Url(as: 'session', except: '')]
    public string $sessionUuid = '';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    public bool $formulaireOuvert = false;

    // — inscription manuelle
    public string $compte = '';

    #[Locked]
    public ?string $compteTrouve = null;

    // — arbitrage d'un report
    #[Locked]
    public ?string $reportArbitrer = null;

    public string $sessionDArrivee = '';

    #[Locked]
    public ?string $aAnnuler = null;

    public function reinitialiser(): void
    {
        $this->reset('sessionUuid', 'recherche', 'filtreStatut');
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('enrolment.view') ?? false, 403);
    }

    public function render(DepotExamens $examens, DepotInscriptionsExamen $depot, TableauDeLEnrolementAuxExamens $tableau): View
    {
        $session = $this->session($examens);

        return view('livewire.staff.enrolement-aux-examens', [
            'session' => $session,
            'sessions' => $this->sessionsPourLeSelecteur($examens, $session),
            'examens' => $tableau->examensProposes(),
            'indicateurs' => $depot->indicateurs($session),
            'statuts' => StatutInscriptionExamen::cases(),
            'peutConvoquer' => $this->peut('enrolment.convene'),
            'peutInscrire' => $this->peut('enrolment.create'),
            'peutArbitrer' => $this->peut('enrolment.update'),
        ]);
    }

    /**
     * Les sessions à venir, PLUS la session regardée si elle n'y est pas.
     *
     * Un lien `?session=…` vers une session close doit rouvrir le sélecteur
     * sur elle — sinon l'option demandée n'existe pas dans la liste et la
     * valeur de l'adresse ne s'affiche nulle part.
     *
     * @return Collection<int, ExamSession>
     */
    private function sessionsPourLeSelecteur(DepotExamens $examens, ?ExamSession $session): Collection
    {
        $sessions = $examens->sessionsAVenir();

        if ($session !== null && ! $sessions->contains('uuid', $session->uuid)) {
            $sessions = $sessions->push($session)->sortBy('starts_at')->values();
        }

        return $sessions;
    }

    // ————————————————————————————————————————————————— inscription

    public function ouvrirInscription(): void
    {
        abort_unless($this->peut('enrolment.create'), 403);

        $this->reset('compte', 'compteTrouve');
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->resetValidation();
    }

    public function updatedCompte(string $valeur): void
    {
        $valeur = trim($valeur);

        $this->compteTrouve = $valeur === '' ? null : $this->chercherLeCompte($valeur)?->uuid;
    }

    public function inscrire(EnrolementExamen $enrolement, DepotExamens $examens, AuditLogService $audit): void
    {
        abort_unless($this->peut('enrolment.create'), 403);

        $session = $this->session($examens);

        if ($session === null) {
            return;
        }

        $this->validate(['compte' => ['required', 'string', 'max:255']], attributes: ['compte' => 'compte']);

        $candidat = $this->chercherLeCompte(trim($this->compte));

        if ($candidat === null) {
            $this->addError('compte', 'Aucun compte ne correspond.');

            return;
        }

        try {
            $inscription = $enrolement->inscrire($session, $candidat);
        } catch (RuntimeException $erreur) {
            /*
             | Le service refuse une session complète, fermée, ou une double
             | inscription. Trois réponses, pas trois pannes — et le message dit
             | laquelle, ce qu'une 500 ne ferait pas.
             */
            $this->addError('compte', $erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'enrolment.create',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            apres: ['candidat' => $candidat->email, 'session' => $session->uuid],
            cibleLibelle: $candidat->name,
        );

        $this->fermerLeTiroir();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Candidat inscrit. La convocation reste à envoyer.');
    }

    // ————————————————————————————————————————————————— convocation

    public function convoquer(string $uuid, EnrolementExamen $enrolement, DepotInscriptionsExamen $depot, AuditLogService $audit): void
    {
        abort_unless($this->peut('enrolment.convene'), 403);

        $inscription = $this->inscription($depot, $uuid);

        try {
            $enrolement->envoyerLaConvocation($inscription);
        } catch (RuntimeException $erreur) {
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'enrolment.convene',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            apres: ['convocation_sent_at' => now()->toIso8601String()],
            cibleLibelle: $inscription->user?->name ?? 'Candidat',
        );

        /*
         | Le message rappelle que l'envoi passe par la FILE. Sans `queue:work`,
         | la convocation attend en table `jobs` sans que rien ne le signale —
         | c'est le défaut documenté dans l'encadré de `CLAUDE.md`, et il a déjà
         | fait croire à une inscription cassée.
         */
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Convocation mise en file d\'envoi. Elle part dès que le worker tourne.');
    }

    public function emarger(string $uuid, EnrolementExamen $enrolement, DepotInscriptionsExamen $depot, AuditLogService $audit): void
    {
        abort_unless($this->peut('enrolment.update'), 403);

        $inscription = $this->inscription($depot, $uuid);

        try {
            $enrolement->emarger($inscription);
        } catch (RuntimeException $erreur) {
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'enrolment.attend',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            apres: ['attended_at' => now()->toIso8601String()],
            cibleLibelle: $inscription->user?->name ?? 'Candidat',
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Présence enregistrée.');
    }

    // ————————————————————————————————————————————————— report

    public function ouvrirLArbitrage(string $uuid): void
    {
        abort_unless($this->peut('enrolment.update'), 403);

        $this->reportArbitrer = $uuid;
        $this->sessionDArrivee = '';
        $this->resetValidation();
    }

    public function fermerLArbitrage(): void
    {
        $this->reportArbitrer = null;
        $this->sessionDArrivee = '';
        $this->resetValidation();
    }

    public function arbitrer(EnrolementExamen $enrolement, DepotInscriptionsExamen $depot, DepotExamens $examens, AuditLogService $audit): void
    {
        if ($this->reportArbitrer === null) {
            return;
        }

        abort_unless($this->peut('enrolment.update'), 403);

        /*
         | La session d'arrivée est OBLIGATOIRE. Il n'existe pas d'« accepter le
         | report » qui laisserait le candidat sans date : ce serait une
         | inscription en suspens que personne ne reprendrait.
         */
        $this->validate(
            ['sessionDArrivee' => ['required', 'string']],
            attributes: ['sessionDArrivee' => 'session de report'],
        );

        $inscription = $this->inscription($depot, $this->reportArbitrer);
        $nouvelle = $examens->sessionParUuid($this->sessionDArrivee);

        if ($nouvelle === null) {
            $this->addError('sessionDArrivee', 'Choisissez une session à venir.');

            return;
        }

        try {
            $enrolement->arbitrerLeReport($inscription, $nouvelle);
        } catch (RuntimeException $erreur) {
            $this->addError('sessionDArrivee', $erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'enrolment.defer',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            avant: ['motif' => $inscription->deferral_reason],
            apres: ['session' => $nouvelle->uuid],
            cibleLibelle: $inscription->user?->name ?? 'Candidat',
        );

        $this->fermerLArbitrage();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Report arbitré : le candidat est inscrit à la nouvelle session.');
    }

    // ————————————————————————————————————————————————— annulation

    public function demanderLAnnulation(string $uuid): void
    {
        abort_unless($this->peut('enrolment.delete'), 403);

        $this->aAnnuler = $uuid;
    }

    public function annulerLAnnulation(): void
    {
        $this->aAnnuler = null;
    }

    public function annuler(EnrolementExamen $enrolement, DepotInscriptionsExamen $depot, AuditLogService $audit): void
    {
        if ($this->aAnnuler === null) {
            return;
        }

        abort_unless($this->peut('enrolment.delete'), 403);

        $inscription = $this->inscription($depot, $this->aAnnuler);

        try {
            $enrolement->annuler($inscription);
        } catch (RuntimeException $erreur) {
            $this->aAnnuler = null;
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'enrolment.cancel',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            apres: ['status' => StatutInscriptionExamen::Annule->value],
            cibleLibelle: $inscription->user?->name ?? 'Candidat',
        );

        $this->aAnnuler = null;
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);

        // « Annulée » et non « supprimée » : la ligne reste, et la place se
        // libère. Effacer priverait d'explication le candidat qui reviendrait.
        $this->toastSuccess('Inscription annulée. La place est de nouveau disponible.');
    }

    // ————————————————————————————————————————————————— lectures

    private function peut(string $permission): bool
    {
        return Auth::guard('staff')->user()?->can($permission) ?? false;
    }

    private function chercherLeCompte(string $valeur): ?User
    {
        return User::query()
            ->where('email', $valeur)
            ->orWhere('uuid', $valeur)
            ->first();
    }

    private function session(DepotExamens $examens): ?ExamSession
    {
        return $this->sessionUuid !== '' ? $examens->sessionParUuid($this->sessionUuid) : null;
    }

    private function inscription(DepotInscriptionsExamen $depot, string $uuid): ExamRegistration
    {
        $inscription = $depot->parUuid($uuid);

        abort_if($inscription === null, 404);

        return $inscription;
    }
}
