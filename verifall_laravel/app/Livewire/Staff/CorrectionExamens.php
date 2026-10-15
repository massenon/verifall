<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Exams\Services\CorrectionExamen;
use App\Core\Exams\Services\TableauDeLaCorrection;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutCopieExamen;
use App\Enums\UserRole;
use App\Models\ExamAttempt;
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
 * « Correction Examens » — la file, et la copie ouverte.
 *
 * ## Deux états, un seul écran
 *
 * La FILE répond à « qu'est-ce qui attend ? » ; la COPIE répond à « combien
 * vaut cette réponse-là ». On passe de l'une à l'autre des dizaines de fois par
 * séance de correction, et un changement de page à chaque fois ferait perdre le
 * fil de la file — donc la copie s'ouvre EN PLACE.
 *
 * ## Tout le calcul est dans le service, et il faut que ça reste ainsi
 *
 * `CorrectionExamen` borne déjà les points au barème de la question, refuse de
 * finaliser une copie dont une réponse attend encore une note, calcule le
 * pourcentage sur le barème FIGÉ de la copie — pas sur celui de l'examen
 * d'aujourd'hui — et lève l'événement `ExamenReussi` sans rien connaître des
 * badges ni des certificats.
 *
 * Aucune de ces règles n'est réécrite ici. Ce composant recueille des points et
 * appelle. Deux refus remontent : la copie déjà close, et la copie incomplète.
 * Tous deux sont rattrapés et affichés — ce sont des réponses, pas des pannes.
 *
 * ## Un abandon n'est PAS un échec
 *
 * Le service le dit : une copie abandonnée ne compte ni dans les réussites ni
 * dans les échecs. L'écran reprend ce mot exact plutôt que « recaler », qui
 * laisserait croire à une note de zéro.
 */
#[Layout('components.layouts.staff', ['title' => "Correction des examens · Vérif'All"])]
final class CorrectionExamens extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'correction-examens-modifiee';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    /** '' | 'moi' | uuid d'un correcteur */
    #[Url(as: 'correcteur', except: '')]
    public string $filtreCorrecteur = '';

    /** La copie ouverte, qui remplace la file à l'écran. */
    #[Locked]
    public ?string $copieOuverte = null;

    /**
     * Les points saisis, indexés par POSITION de la réponse dans la copie.
     *
     * Pas par identifiant : `exam_answers` n'a pas de colonne `uuid`, et
     * publier un `id` auto-incrémenté dans un attribut `wire:model` le rendrait
     * lisible dans le DOM — ce que la règle 4 du chantier proscrit, même hors
     * d'une URL. La position est unique par copie, et la copie est elle-même
     * adressée par son uuid : rien de global ne fuit.
     *
     * @var array<int, string>
     */
    public array $points = [];

    /**
     * Les commentaires du correcteur, indexés de la même façon.
     *
     * @var array<int, string>
     */
    public array $commentaires = [];

    public string $appreciation = '';

    #[Locked]
    public ?string $aAbandonner = null;

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreStatut', 'filtreCorrecteur');
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('exam.grade') ?? false, 403);
    }

    public function render(DepotCopiesExamen $depot, TableauDeLaCorrection $tableau): View
    {
        $copie = $this->copieOuverte !== null ? $this->copie($depot, $this->copieOuverte) : null;

        return view('livewire.staff.correction-examens', [
            'copie' => $copie,
            'reponses' => $copie !== null ? $depot->reponsesDe($copie) : collect(),
            'indicateurs' => $depot->indicateurs(),
            'statuts' => StatutCopieExamen::cases(),
            'correcteurs' => $this->correcteurs(),
            'examens' => $tableau->examensProposes(),
        ]);
    }

    // ————————————————————————————————————————————————— la copie

    public function ouvrirLaCopie(string $uuid, DepotCopiesExamen $depot): void
    {
        $copie = $this->copie($depot, $uuid);

        $this->copieOuverte = $uuid;
        $this->appreciation = (string) $copie->feedback;
        $this->points = [];
        $this->commentaires = [];

        /*
         | Les champs sont amorcés avec la note DÉJÀ donnée, s'il y en a une.
         | Rouvrir une copie partiellement corrigée avec des champs vides ferait
         | perdre le travail au premier enregistrement — et rien ne le dirait.
         */
        foreach ($depot->reponsesDe($copie) as $reponse) {
            $this->points[(int) $reponse->position] = $reponse->points_awarded !== null
                ? (string) $reponse->points_awarded
                : '';
            $this->commentaires[(int) $reponse->position] = (string) $reponse->grader_comment;
        }

        $this->resetValidation();
    }

    public function refermerLaCopie(): void
    {
        $this->copieOuverte = null;
        $this->points = [];
        $this->commentaires = [];
        $this->appreciation = '';
        $this->resetValidation();
    }

    /**
     * S'attribue la copie avant de la corriger.
     */
    public function prendreLaCopie(string $uuid, CorrectionExamen $correction, DepotCopiesExamen $depot, AuditLogService $audit): void
    {
        $copie = $this->copie($depot, $uuid);
        $correcteur = Auth::guard('staff')->user();

        if ($correcteur === null) {
            return;
        }

        try {
            $correction->assigner($copie, $correcteur);
        } catch (RuntimeException $erreur) {
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'exam.assign',
            module: 'examens',
            acteur: $correcteur,
            cible: $copie,
            apres: ['assigned_to' => $correcteur->id],
            cibleLibelle: $copie->reference ?? 'Copie',
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Copie prise en charge.');
    }

    /**
     * Enregistre les notes saisies, sans rendre la copie.
     *
     * Séparé de la finalisation à dessein : une correction longue s'interrompt,
     * et perdre une heure de notation parce qu'on n'avait pas fini n'est pas
     * acceptable.
     */
    public function enregistrerLesNotes(CorrectionExamen $correction, DepotCopiesExamen $depot): void
    {
        if ($this->copieOuverte === null) {
            return;
        }

        $copie = $this->copie($depot, $this->copieOuverte);

        foreach ($depot->reponsesDe($copie) as $reponse) {
            $rang = (int) $reponse->position;
            $saisie = $this->points[$rang] ?? '';

            // Un champ laissé vide n'est pas un zéro : c'est « pas encore
            // noté ». Les confondre rendrait une copie incomplète comme une
            // copie nulle.
            if ($saisie === '') {
                continue;
            }

            if (! is_numeric($saisie)) {
                $this->addError('points.'.$rang, 'Indiquez un nombre de points.');

                continue;
            }

            try {
                $correction->noter(
                    $reponse,
                    (int) $saisie,
                    ($this->commentaires[$rang] ?? '') !== ''
                        ? $this->commentaires[$rang]
                        : null,
                );
            } catch (RuntimeException $erreur) {
                $this->toastError($erreur->getMessage());

                return;
            }
        }

        $this->toastSuccess('Notes enregistrées. La copie n\'est pas encore rendue.');
    }

    public function rendreLaCopie(CorrectionExamen $correction, DepotCopiesExamen $depot, AuditLogService $audit): void
    {
        if ($this->copieOuverte === null) {
            return;
        }

        // Les notes saisies sont enregistrées d'abord : rendre une copie sans
        // valider la dernière saisie perdrait la note en cours de frappe.
        $this->enregistrerLesNotes($correction, $depot);

        $copie = $this->copie($depot, $this->copieOuverte);

        try {
            $rendue = $correction->finaliser(
                $copie,
                Auth::guard('staff')->user(),
                $this->appreciation !== '' ? $this->appreciation : null,
            );
        } catch (RuntimeException $erreur) {
            /*
             | Le service refuse une copie dont une réponse attend encore une
             | note, et il DIT COMBIEN. C'est exactement ce qu'un correcteur a
             | besoin de lire — une 500 ne l'aiderait pas à trouver laquelle.
             */
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'exam.grade',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $rendue,
            apres: [
                'score_percent' => $rendue->score_percent,
                'passed' => $rendue->passed,
            ],
            cibleLibelle: $rendue->reference ?? 'Copie',
        );

        $this->refermerLaCopie();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Copie rendue : '.$rendue->score_percent.' %'
            .($rendue->passed === true ? ' — admis.' : ' — non admis.'));
    }

    // ————————————————————————————————————————————————— abandon

    public function demanderLAbandon(string $uuid): void
    {
        $this->aAbandonner = $uuid;
    }

    public function annulerLAbandon(): void
    {
        $this->aAbandonner = null;
    }

    public function abandonner(CorrectionExamen $correction, DepotCopiesExamen $depot, AuditLogService $audit): void
    {
        if ($this->aAbandonner === null) {
            return;
        }

        $copie = $this->copie($depot, $this->aAbandonner);

        try {
            $correction->abandonner($copie);
        } catch (RuntimeException $erreur) {
            $this->aAbandonner = null;
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'exam.abandon',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $copie,
            apres: ['status' => StatutCopieExamen::Abandonnee->value],
            cibleLibelle: $copie->reference ?? 'Copie',
        );

        $this->aAbandonner = null;
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);

        // Le mot du service, repris tel quel : ni réussite, ni échec.
        $this->toastSuccess('Copie sortie de la file. Elle ne compte ni dans les réussites, ni dans les échecs.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * Les comptes qui peuvent corriger.
     *
     * Guard `web` : tous les rôles y sont semés, `User::getDefaultGuardName()`
     * le documente. Passer `staff` lève `RoleDoesNotExist` au rendu.
     *
     * @return Collection<int, User>
     */
    private function correcteurs(): Collection
    {
        return User::query()
            ->role([UserRole::Mentor->value, UserRole::Admin->value], 'web')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);
    }

    private function copie(DepotCopiesExamen $depot, string $uuid): ExamAttempt
    {
        $copie = $depot->parUuid($uuid);

        abort_if($copie === null, 404);

        return $copie;
    }
}
