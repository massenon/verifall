<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotExamens;
use App\Core\Exams\Services\PlanificationExamen;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutExamen;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * « Liste des Examens » — les épreuves et leurs sessions.
 *
 * ## Rien à réécrire : tout existait sauf l'écran
 *
 * `DepotExamens` (18 méthodes) et `PlanificationExamen` (11) étaient là,
 * complets : création, publication sous condition, archivage, questions
 * attachées, sessions planifiées, ouvertes, fermées, annulées. Comme pour la
 * banque de questions au lot 6, **il ne manquait qu'un écran** — les examens ne
 * pouvaient se créer que par la console.
 *
 * ## Un examen et ses sessions, sur le même écran
 *
 * Une épreuve n'a d'existence pratique qu'à travers ses sessions : c'est la
 * session qui a une date, des places et des candidats. Les séparer obligerait
 * à changer d'écran pour répondre à « quand passe-t-on cet examen ? », qui est
 * la première question qu'on se pose en le regardant.
 *
 * Les sessions d'un examen s'ouvrent donc EN PLACE, sous sa ligne, plutôt que
 * dans un second écran.
 *
 * ## Publier peut échouer, et l'écran le dit
 *
 * `publier()` vérifie que l'épreuve tient debout — des questions, un barème.
 * Un examen publié sans question convoquerait des candidats devant une copie
 * vide. Le refus est rattrapé et affiché, jamais rendu en 500.
 */
#[Layout('components.layouts.staff', ['title' => "Examens · Vérif'All"])]
final class Examens extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'examens-modifies';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    /** L'examen dont on déplie les sessions, sous sa ligne. */
    #[Locked]
    public ?string $examenDeplie = null;

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    public bool $formulaireSession = false;

    #[Locked]
    public ?string $sessionEnEdition = null;

    #[Locked]
    public ?string $aSupprimer = null;

    // — formulaire examen
    public string $titre = '';

    public string $descriptif = '';

    public string $coursUuid = '';

    public string $duree = '60';

    public string $seuil = '70';

    public string $tentatives = '1';

    // — formulaire session
    public string $sessionDebut = '';

    public string $sessionPlaces = '30';

    public string $sessionLieu = '';

    public bool $sessionSurveillee = false;

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreStatut');
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Exam::class);
    }

    public function render(DepotExamens $depot): View
    {
        return view('livewire.staff.examens', [
            'indicateurs' => $depot->indicateurs(),
            'statuts' => StatutExamen::cases(),
            'cours' => $this->cours(),
            'sessions' => $this->examenDeplie !== null
                ? $depot->listerSessions($this->examen($depot, $this->examenDeplie))
                : null,
            'examenCourant' => $this->examenDeplie !== null
                ? $this->examen($depot, $this->examenDeplie)
                : null,
        ]);
    }

    // ————————————————————————————————————————————————— l'épreuve

    public function ouvrirCreation(): void
    {
        $this->authorize('create', Exam::class);

        $this->reset('enEdition', 'titre', 'descriptif', 'coursUuid');
        $this->duree = '60';
        $this->seuil = '70';
        $this->tentatives = '1';
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid, DepotExamens $depot): void
    {
        $examen = $this->examen($depot, $uuid);
        $this->authorize('update', $examen);

        $this->enEdition = $uuid;
        $this->titre = (string) $examen->title;
        $this->descriptif = (string) $examen->description;
        $this->coursUuid = (string) ($examen->course?->uuid ?? '');
        $this->duree = (string) $examen->duration_minutes;
        $this->seuil = (string) $examen->pass_score;
        $this->tentatives = (string) $examen->max_attempts;

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    public function enregistrer(PlanificationExamen $planification, DepotExamens $depot, AuditLogService $audit): void
    {
        $donnees = $this->validate($this->reglesDExamen());

        $coursId = $donnees['coursUuid'] !== ''
            ? $this->cours()->firstWhere('uuid', $donnees['coursUuid'])?->id
            : null;

        $charge = [
            'title' => $donnees['titre'],
            'description' => $donnees['descriptif'],
            'course_id' => $coursId,
            'duration_minutes' => (int) $donnees['duree'],
            'pass_score' => (int) $donnees['seuil'],
            'max_attempts' => (int) $donnees['tentatives'],
        ];

        if ($this->enEdition !== null) {
            $examen = $this->examen($depot, $this->enEdition);
            $this->authorize('update', $examen);
            $planification->mettreAJour($examen, $charge);
            $action = 'exam.update';
        } else {
            $this->authorize('create', Exam::class);
            $examen = $planification->creer($charge, Auth::guard('staff')->user());
            $action = 'exam.create';
        }

        $audit->record(
            action: $action,
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $examen,
            apres: $charge,
            cibleLibelle: $examen->title,
        );

        $this->fermerLeTiroir();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess($action === 'exam.create'
            ? 'Examen créé. Attachez-lui des questions avant de le publier.'
            : 'Épreuve mise à jour.');
    }

    public function publier(string $uuid, PlanificationExamen $planification, DepotExamens $depot, AuditLogService $audit): void
    {
        $examen = $this->examen($depot, $uuid);
        $this->authorize('publish', $examen);

        try {
            $planification->publier($examen);
        } catch (RuntimeException $erreur) {
            /*
             | Le service refuse une épreuve qui ne tient pas debout. Rattrapé
             | plutôt que rendu en 500 : un examen publié sans question
             | convoquerait des candidats devant une copie vide.
             */
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'exam.publish',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $examen,
            apres: ['status' => StatutExamen::Publie->value],
            cibleLibelle: $examen->title,
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Examen publié : des sessions peuvent désormais être planifiées.');
    }

    public function archiver(string $uuid, PlanificationExamen $planification, DepotExamens $depot, AuditLogService $audit): void
    {
        $examen = $this->examen($depot, $uuid);
        $this->authorize('archive', $examen);

        $planification->archiver($examen);

        $audit->record(
            action: 'exam.archive',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $examen,
            apres: ['status' => StatutExamen::Archive->value],
            cibleLibelle: $examen->title,
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        // « Archivé » et non « supprimé » : des copies rendues et des
        // certificats citent cette épreuve.
        $this->toastSuccess('Examen archivé. Les copies déjà rendues restent lisibles.');
    }

    // ————————————————————————————————————————————————— les sessions

    public function deplierLesSessions(string $uuid): void
    {
        $this->examenDeplie = $this->examenDeplie === $uuid ? null : $uuid;
    }

    public function ouvrirCreationSession(): void
    {
        $this->authorize('create', ExamSession::class);

        $this->reset('sessionEnEdition', 'sessionLieu');
        $this->sessionDebut = now()->addWeek()->format('Y-m-d\TH:i');
        $this->sessionPlaces = '30';
        $this->sessionSurveillee = false;
        $this->resetValidation();
        $this->formulaireSession = true;
    }

    public function fermerLeTiroirSession(): void
    {
        $this->formulaireSession = false;
        $this->sessionEnEdition = null;
        $this->resetValidation();
    }

    public function enregistrerLaSession(PlanificationExamen $planification, DepotExamens $depot, AuditLogService $audit): void
    {
        if ($this->examenDeplie === null) {
            return;
        }

        $examen = $this->examen($depot, $this->examenDeplie);
        $this->authorize('update', $examen);

        $donnees = $this->validate($this->reglesDeSession());

        try {
            $session = $planification->planifierUneSession(
                $examen,
                new \DateTimeImmutable($donnees['sessionDebut']),
                [
                    'seats' => (int) $donnees['sessionPlaces'],
                    'location' => $donnees['sessionLieu'] !== '' ? $donnees['sessionLieu'] : null,
                    'is_proctored' => $donnees['sessionSurveillee'],
                ],
            );
        } catch (RuntimeException $erreur) {
            /*
             | Le service refuse une session dans le passé, ou un doublon exact
             | de date sur la même épreuve. Les deux sont des réponses, pas des
             | pannes — et le message dit laquelle.
             */
            $this->addError('sessionDebut', $erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'exam.session_create',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $session,
            apres: ['starts_at' => $session->starts_at?->toIso8601String(), 'seats' => $session->seats],
            cibleLibelle: $examen->title,
        );

        $this->fermerLeTiroirSession();
        $this->toastSuccess('Session planifiée.');
    }

    public function changerLEtatDeLaSession(string $uuid, string $vers, PlanificationExamen $planification, DepotExamens $depot, AuditLogService $audit): void
    {
        $session = $depot->sessionParUuid($uuid);

        abort_if($session === null, 404);
        $this->authorize('update', $session->exam ?? Exam::class);

        if (! in_array($vers, ['ouvrir', 'fermer', 'annuler'], true)) {
            return;
        }

        $avant = $session->status;

        match ($vers) {
            'ouvrir' => $planification->ouvrirUneSession($session),
            'fermer' => $planification->fermerUneSession($session),
            'annuler' => $planification->annulerUneSession($session),
        };

        $audit->record(
            action: 'exam.session_'.$vers,
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $session,
            avant: ['status' => $avant instanceof \BackedEnum ? $avant->value : $avant],
            apres: ['status' => $session->refresh()->status instanceof \BackedEnum ? $session->status->value : $session->status],
            cibleLibelle: $session->exam?->title ?? 'Session',
        );

        $this->toastSuccess(match ($vers) {
            'ouvrir' => 'Session ouverte aux inscriptions.',
            'fermer' => 'Session fermée : plus aucune inscription.',
            'annuler' => 'Session annulée. Prévenez les inscrits.',
        });
    }

    // ————————————————————————————————————————————————— règles

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reglesDExamen(): array
    {
        return [
            'titre' => ['required', 'string', 'min:3', 'max:180'],
            'descriptif' => ['required', 'string', 'min:20', 'max:2000'],
            'coursUuid' => ['nullable', 'string', Rule::in($this->cours()->pluck('uuid')->all())],
            'duree' => ['required', 'integer', 'min:5', 'max:480'],
            /*
             | Même borne basse que les quiz de cours : sous 50 %, « réussir »
             | voudrait dire se tromper plus souvent qu'avoir juste, et le
             | certificat que cet examen adosse ne vaudrait plus rien.
             */
            'seuil' => ['required', 'integer', 'min:50', 'max:100'],
            'tentatives' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reglesDeSession(): array
    {
        return [
            // `after:now` ici AUSSI, bien que le service le vérifie : le
            // formulaire doit dire non avant l'aller-retour, pas après.
            'sessionDebut' => ['required', 'date', 'after:now'],
            'sessionPlaces' => ['required', 'integer', 'min:1', 'max:1000'],
            'sessionLieu' => ['nullable', 'string', 'max:180'],
            'sessionSurveillee' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre de l\'épreuve',
            'descriptif' => 'description',
            'coursUuid' => 'cours rattaché',
            'duree' => 'durée',
            'seuil' => 'seuil de réussite',
            'tentatives' => 'tentatives autorisées',
            'sessionDebut' => 'date et heure',
            'sessionPlaces' => 'nombre de places',
            'sessionLieu' => 'lieu',
        ];
    }

    // ————————————————————————————————————————————————— accès

    /**
     * @return Collection<int, Course>
     */
    private function cours(): Collection
    {
        return Course::query()->orderBy('title')->get(['id', 'uuid', 'title']);
    }

    private function examen(DepotExamens $depot, string $uuid): Exam
    {
        $examen = $depot->parUuid($uuid);

        abort_if($examen === null, 404);

        return $examen;
    }
}
