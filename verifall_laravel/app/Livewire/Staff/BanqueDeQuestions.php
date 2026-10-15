<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Exams\Services\BanqueDeQuestions as ServiceBanque;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutQuestion;
use App\Enums\TypeQuestion;
use App\Models\Course;
use App\Models\QuestionBankItem;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * « Banque de Questions » — le fonds commun des examens.
 *
 * ## Presque rien à écrire, et c'est le sujet
 *
 * `DepotBanqueQuestions` (10 méthodes) et `Core\Exams\Services\BanqueDeQuestions`
 * (7 méthodes) existaient déjà, complets : création, propositions, publication
 * sous condition, mise en révision, archivage, statistiques. **Il manquait un
 * écran.** Le fonds ne pouvait s'alimenter que par la console.
 *
 * Ce composant n'ajoute donc aucune règle : il branche. Toute logique qu'on
 * serait tenté d'écrire ici existe déjà à côté, testée.
 *
 * ## À ne pas confondre avec les quiz de cours
 *
 * Un quiz de cours (`Quiz`, `Question`, `Answer`) se corrige seul, ne vaut
 * aucun diplôme et vit dans le constructeur. Une question de banque est posée
 * à tous les candidats d'une session d'examen. Deux tables, deux droits, deux
 * écrans — et la confusion entre les deux coûterait cher : publier une question
 * de banque, c'est l'exposer à un examen certifiant.
 *
 * ## Publier peut ÉCHOUER, et c'est voulu
 *
 * `publier()` appelle `verifierQuElleTientDebout()` et lève si la question est
 * incomplète. L'écran rattrape et affiche la raison, plutôt que de laisser
 * remonter une 500 : une question sans bonne réponse posée en examen note
 * faux tout le monde.
 */
#[Layout('components.layouts.staff', ['title' => "Banque de questions · Vérif'All"])]
final class BanqueDeQuestions extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'banque-de-questions-modifiee';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'type', except: '')]
    public string $filtreType = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    #[Url(as: 'cours', except: '')]
    public string $filtreCours = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $enonce = '';

    public string $type = 'qcm';

    public string $explication = '';

    public string $points = '10';

    public string $coursUuid = '';

    /** @var list<array{label: string, correcte: bool}> */
    public array $propositions = [];

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreType', 'filtreStatut', 'filtreCours');
    }

    public function mount(): void
    {
        $this->authorize('viewAny', QuestionBankItem::class);
    }

    public function render(DepotBanqueQuestions $depot): View
    {
        return view('livewire.staff.banque-de-questions', [
            'indicateurs' => $depot->indicateurs(),
            'types' => TypeQuestion::cases(),
            'statuts' => StatutQuestion::cases(),
            'cours' => $this->cours(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', QuestionBankItem::class);

        $this->reset('enEdition', 'enonce', 'explication', 'coursUuid');
        $this->type = TypeQuestion::Qcm->value;
        $this->points = '10';
        $this->propositions = [
            ['label' => '', 'correcte' => true],
            ['label' => '', 'correcte' => false],
        ];
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $question = $this->question($uuid);
        $this->authorize('update', $question);

        $this->enEdition = $uuid;
        $this->enonce = (string) $question->prompt;
        $this->type = $question->type instanceof TypeQuestion ? $question->type->value : (string) $question->type;
        $this->explication = (string) $question->explanation;
        $this->points = (string) $question->points;
        $this->coursUuid = (string) ($question->course?->uuid ?? '');
        $this->propositions = $question->options()
            ->orderBy('position')
            ->get()
            ->map(fn ($option): array => [
                'label' => (string) $option->label,
                'correcte' => (bool) $option->is_correct,
            ])
            ->all();

        // Une question importée sans proposition ne doit pas ouvrir un
        // formulaire vide : deux lignes disent ce qu'on attend.
        if ($this->propositions === []) {
            $this->propositions = [
                ['label' => '', 'correcte' => true],
                ['label' => '', 'correcte' => false],
            ];
        }

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    public function ajouterUneProposition(): void
    {
        if (count($this->propositions) >= 6) {
            return;
        }

        $this->propositions[] = ['label' => '', 'correcte' => false];
    }

    public function retirerUneProposition(int $rang): void
    {
        if (count($this->propositions) <= 2) {
            return;
        }

        unset($this->propositions[$rang]);
        $this->propositions = array_values($this->propositions);
    }

    public function enregistrer(ServiceBanque $banque, AuditLogService $audit): void
    {
        $donnees = $this->validate();

        $coursId = $donnees['coursUuid'] !== ''
            ? $this->cours()->firstWhere('uuid', $donnees['coursUuid'])?->id
            : null;

        if ($this->enEdition !== null) {
            $question = $this->question($this->enEdition);
            $this->authorize('update', $question);

            $banque->mettreAJour($question, [
                'prompt' => $donnees['enonce'],
                'type' => TypeQuestion::from($donnees['type']),
                'explanation' => $donnees['explication'] !== '' ? $donnees['explication'] : null,
                'points' => (int) $donnees['points'],
                'course_id' => $coursId,
            ]);
            $action = 'question_bank.update';
        } else {
            $this->authorize('create', QuestionBankItem::class);

            $question = $banque->enregistrer([
                'course_id' => $coursId,
                'type' => TypeQuestion::from($donnees['type']),
                'prompt' => $donnees['enonce'],
                'explanation' => $donnees['explication'] !== '' ? $donnees['explication'] : null,
                'points' => (int) $donnees['points'],
            ], Auth::guard('staff')->user());
            $action = 'question_bank.create';
        }

        /*
         | Les propositions PASSENT PAR LE SERVICE, qui les remplace en bloc.
         | Une mise à jour partielle laisserait une ancienne bonne réponse à
         | côté de la nouvelle — le contrat du dépôt le dit noir sur blanc.
         */
        $banque->definirLesPropositions($question, array_map(
            static fn (array $p): array => [
                'label' => trim($p['label']),
                'is_correct' => (bool) $p['correcte'],
            ],
            array_values(array_filter(
                $this->propositions,
                static fn (array $p): bool => trim($p['label']) !== '',
            )),
        ));

        $audit->record(
            action: $action,
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $question,
            apres: ['prompt' => $question->prompt, 'type' => $donnees['type'], 'points' => (int) $donnees['points']],
            cibleLibelle: Str::limit($question->prompt, 60),
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->fermerLeTiroir();
        $this->toastSuccess($action === 'question_bank.create'
            ? 'Question ajoutée au fonds, en brouillon.'
            : 'Question mise à jour.');
    }

    /**
     * Publier — le geste qui expose la question à un examen certifiant.
     */
    public function publier(string $uuid, ServiceBanque $banque, AuditLogService $audit): void
    {
        $question = $this->question($uuid);
        $this->authorize('publish', $question);

        try {
            $banque->publier($question);
        } catch (RuntimeException $erreur) {
            /*
             | Le service refuse une question qui ne tient pas debout. Rattrapé
             | plutôt que laissé remonter en 500 : c'est une réponse à
             | l'utilisateur, et il doit lire LAQUELLE des conditions manque.
             */
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'question_bank.publish',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $question,
            apres: ['status' => StatutQuestion::Publiee->value],
            cibleLibelle: Str::limit($question->prompt, 60),
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Question publiée : elle entre dans les tirages d\'examen.');
    }

    public function mettreEnRevision(string $uuid, ServiceBanque $banque, AuditLogService $audit): void
    {
        $question = $this->question($uuid);
        $this->authorize('publish', $question);

        $banque->marquerAReviser($question);

        $audit->record(
            action: 'question_bank.to_review',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $question,
            apres: ['status' => StatutQuestion::AReviser->value],
            cibleLibelle: Str::limit($question->prompt, 60),
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        // « Retirée des tirages » et non « dépubliée » : les copies déjà
        // rendues gardent la question, et leur note ne bouge pas.
        $this->toastSuccess('Question retirée des tirages. Les copies rendues ne changent pas.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->question($uuid));
        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(DepotBanqueQuestions $depot, AuditLogService $audit): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        $question = $this->question($this->aSupprimer);
        $this->authorize('delete', $question);

        $audit->record(
            action: 'question_bank.delete',
            module: 'examens',
            acteur: Auth::guard('staff')->user(),
            cible: $question,
            avant: $question->only(['prompt', 'type', 'status']),
            cibleLibelle: Str::limit($question->prompt, 60),
        );

        $depot->supprimer($question);

        $this->aSupprimer = null;
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Question retirée du fonds.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'enonce' => ['required', 'string', 'min:5', 'max:2000'],
            'type' => ['required', Rule::enum(TypeQuestion::class)],
            'explication' => ['nullable', 'string', 'max:2000'],
            /*
             | Bornés à 100. Une question à 1 000 points écraserait toutes les
             | autres d'une session : la note finale ne dépendrait plus que
             | d'elle, sans que le barème affiché le laisse voir.
             */
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'coursUuid' => ['nullable', 'string', Rule::in($this->cours()->pluck('uuid')->all())],
            'propositions' => ['required', 'array', 'min:2', 'max:6'],
            'propositions.*.label' => ['required', 'string', 'max:500'],
            'propositions.*.correcte' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'enonce' => 'énoncé',
            'type' => 'type de question',
            'explication' => 'explication',
            'points' => 'points',
            'coursUuid' => 'cours rattaché',
            'propositions' => 'propositions',
            'propositions.*.label' => 'proposition',
        ];
    }

    /**
     * @return Collection<int, Course>
     */
    private function cours(): Collection
    {
        return Course::query()->orderBy('title')->get(['id', 'uuid', 'title']);
    }

    private function question(string $uuid): QuestionBankItem
    {
        $question = app(DepotBanqueQuestions::class)->parUuid($uuid);

        abort_if($question === null, 404);

        return $question;
    }
}
