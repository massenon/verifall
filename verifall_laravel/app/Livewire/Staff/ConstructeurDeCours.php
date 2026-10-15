<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Lms\Services\ConstructionDeCours;
use App\Core\Lms\Services\PiecesJointesDeLecon;
use App\Enums\TypeLecon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * « Constructeur de cours » — le plan d'un cours et son contenu.
 *
 * ## Un écran par cours, atteint par son slug
 *
 * Le constructeur n'est pas une liste : il travaille SUR un cours, et l'adresse
 * le dit (`/admin/cours/{slug}/constructeur`). On y revient, on la partage à un
 * relecteur, elle survit à un rechargement — ce qu'un tiroir ouvert depuis une
 * liste ne permet pas.
 *
 * ## Trois tiroirs, et pourquoi ils ne sont pas trois écrans
 *
 * Leçon, quiz, question. Ce sont trois formulaires distincts, mais un seul
 * geste de travail : on écrit un plan de cours d'un bout à l'autre, en revenant
 * sans cesse d'une leçon au quiz qui la suit. Les séparer en trois pages ferait
 * perdre le plan de vue à chaque aller-retour.
 *
 * L'éditeur de questions, lui, REMPLACE le plan à l'écran plutôt que de s'y
 * superposer : une question porte deux à six propositions, et un tiroir dans un
 * tiroir ne se referme jamais dans le bon ordre.
 *
 * ## Ce que ce composant ne fait pas
 *
 * Aucune écriture directe. Positions, purification du HTML et remplacement des
 * propositions vivent dans `ConstructionDeCours` — parce que la contrainte
 * `unique(course_id, position)` interdit l'échange naïf de deux rangs, et que
 * ce genre d'invariant ne survit pas dans un composant d'écran.
 */
#[Layout('components.layouts.staff', ['title' => "Constructeur de cours · Vérif'All"])]
final class ConstructeurDeCours extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    #[Locked]
    public string $slug = '';

    // — quel tiroir est ouvert
    public bool $tiroirLecon = false;

    public bool $tiroirQuiz = false;

    #[Locked]
    public ?string $leconEnEdition = null;

    #[Locked]
    public ?string $quizEnEdition = null;

    /** Le quiz dont on édite les questions ; remplace le plan à l'écran. */
    #[Locked]
    public ?string $quizOuvert = null;

    #[Locked]
    public ?string $questionEnEdition = null;

    public bool $tiroirQuestion = false;

    #[Locked]
    public ?string $aSupprimer = null;

    /** 'lecon' | 'quiz' | 'question' */
    #[Locked]
    public string $typeASupprimer = 'lecon';

    // — formulaire leçon
    public string $leconTitre = '';

    public string $leconType = 'text';

    public string $leconMinutes = '5';

    public string $leconContenu = '';

    public string $leconVideo = '';

    public string $leconAudio = '';

    /** Le document en cours de dépôt sur la leçon ouverte. */
    public ?TemporaryUploadedFile $piece = null;

    // — formulaire quiz
    public string $quizTitre = '';

    public string $quizMinutes = '5';

    public string $quizSeuil = '70';

    public string $quizXp = '0';

    // — formulaire question
    public string $questionEnonce = '';

    public string $questionExplication = '';

    /** @var list<array{label: string, correcte: bool}> */
    public array $propositions = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        $this->authorize('update', $this->cours());
    }

    public function render(): View
    {
        $cours = $this->cours();

        return view('livewire.staff.constructeur-de-cours', [
            'cours' => $cours,
            // `with('pieces')` : sans lui, l'écran ferait une requête par
            // leçon pour lister les documents — le N+1 classique d'un
            // constructeur qui affiche vingt leçons.
            'lecons' => $cours->lessons()->with('pieces')->orderBy('position')->get(),
            'quiz' => $cours->quizzes()->withCount('questions')->orderBy('position')->get(),
            'quizCourant' => $this->quizOuvert !== null ? $this->quizCourant() : null,
            'questions' => $this->quizOuvert !== null
                ? $this->quizCourant()->questions()->with('answers')->orderBy('position')->get()
                : collect(),
            'types' => TypeLecon::cases(),
        ]);
    }

    // ————————————————————————————————————————————————— leçons

    public function ouvrirCreationLecon(): void
    {
        $this->authorize('create', Lesson::class);

        $this->reset('leconEnEdition', 'leconTitre', 'leconContenu', 'leconVideo', 'leconAudio', 'piece');
        $this->leconType = TypeLecon::Texte->value;
        $this->leconMinutes = '5';
        $this->resetValidation();
        $this->tiroirLecon = true;

        $this->dispatch('contenu-lecon-charge', html: '');
    }

    public function ouvrirEditionLecon(string $uuid): void
    {
        $lecon = $this->lecon($uuid);
        $this->authorize('update', $lecon);

        $this->leconEnEdition = $uuid;
        $this->leconTitre = (string) $lecon->title;
        $this->leconType = $lecon->type instanceof TypeLecon ? $lecon->type->value : (string) $lecon->type;
        $this->leconMinutes = (string) $lecon->minutes;
        $this->leconContenu = (string) $lecon->content;
        $this->leconVideo = (string) $lecon->video_url;
        $this->leconAudio = (string) $lecon->audio_url;
        $this->piece = null;

        $this->resetValidation();
        $this->tiroirLecon = true;

        /*
         | L'hôte de Quill est en `wire:ignore` : Livewire n'y touche plus une
         | fois monté. Sans cet événement, ouvrir une seconde leçon afficherait
         | le contenu de la première — et l'enregistrer l'écraserait par elle.
         */
        $this->dispatch('contenu-lecon-charge', html: $this->leconContenu);
    }

    /**
     * Attache un document à la leçon OUVERTE.
     *
     * ## Pourquoi le dépôt est un geste à part, hors du formulaire
     *
     * Une leçon qui n'existe pas encore n'a pas d'identifiant : on ne peut rien
     * lui attacher. Mêler le dépôt à l'enregistrement obligerait à garder les
     * fichiers en mémoire jusqu'à la création, et à les perdre si la validation
     * échoue.
     *
     * Le document part donc dès qu'il est choisi, et seulement en ÉDITION.
     */
    public function attacherLaPiece(PiecesJointesDeLecon $pieces, AuditLogService $audit): void
    {
        if ($this->leconEnEdition === null || $this->piece === null) {
            return;
        }

        // La même Policy que la modification de la leçon : joindre un document
        // à une leçon, c'est la modifier.
        $this->authorize('update', $this->lecon($this->leconEnEdition));

        $this->validate([
            'piece' => [
                'required', 'file',
                'mimes:'.implode(',', PiecesJointesDeLecon::EXTENSIONS),
                'max:'.PiecesJointesDeLecon::TAILLE_MAXIMUM_KO,
            ],
        ], attributes: ['piece' => 'document']);

        $lecon = $this->lecon($this->leconEnEdition);

        try {
            $document = $pieces->attacher($lecon, $this->piece);
        } catch (RuntimeException $refus) {
            $this->addError('piece', $refus->getMessage());

            return;
        }

        $this->reset('piece');

        $audit->record(
            action: 'lesson.attachment.add',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $lecon,
            apres: ['document' => $document->original_name],
            cibleLibelle: $lecon->title,
        );

        $this->toastSuccess('Document joint.');
    }

    /**
     * Retire un document, du disque ET de la base.
     *
     * Pas de fenêtre de confirmation : un document joint se redépose en dix
     * secondes, et une modale pour chaque pièce ferait de la mise en place d'un
     * cours un chemin de croix. La suppression d'une LEÇON, elle, en garde une.
     */
    public function detacherLaPiece(string $uuid, PiecesJointesDeLecon $pieces, AuditLogService $audit): void
    {
        $document = LessonAttachment::query()->with('lecon')->where('uuid', $uuid)->first();

        if ($document?->lecon === null) {
            return;
        }

        $this->authorize('update', $document->lecon);

        /*
         | La pièce doit appartenir à CE cours. La Policy ci-dessus autorise à
         | modifier LA LEÇON de la pièce ; elle ne dit rien du cours ouvert à
         | l'écran. Sans ce contrôle, un uuid deviné effacerait le support d'un
         | autre cours du même formateur.
         */
        abort_if($document->lecon->course_id !== $this->cours()->id, 403);

        $nom = $document->original_name;
        $lecon = $document->lecon;

        $pieces->detacher($document);

        $audit->record(
            action: 'lesson.attachment.remove',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $lecon,
            avant: ['document' => $nom],
            cibleLibelle: (string) $lecon?->title,
        );

        $this->toastSuccess('Document retiré.');
    }

    public function fermerLeTiroirLecon(): void
    {
        $this->tiroirLecon = false;
        $this->leconEnEdition = null;
        $this->resetValidation();
    }

    public function enregistrerLaLecon(ConstructionDeCours $construction, AuditLogService $audit): void
    {
        $donnees = $this->validate($this->reglesDeLecon());

        $charge = [
            'titre' => $donnees['leconTitre'],
            'type' => $donnees['leconType'],
            'minutes' => (int) $donnees['leconMinutes'],
            'contenu' => $donnees['leconContenu'] !== '' ? $donnees['leconContenu'] : null,
            'video' => $donnees['leconVideo'] !== '' ? $donnees['leconVideo'] : null,
            'audio' => ($donnees['leconAudio'] ?? '') !== '' ? $donnees['leconAudio'] : null,
        ];

        if ($this->leconEnEdition !== null) {
            $lecon = $this->lecon($this->leconEnEdition);
            $this->authorize('update', $lecon);
            $construction->modifierUneLecon($lecon, $charge);
            $action = 'lesson.update';
        } else {
            $this->authorize('create', Lesson::class);
            $lecon = $construction->ajouterUneLecon($this->cours(), $charge);
            $action = 'lesson.create';
        }

        $audit->record(
            action: $action,
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $lecon,
            apres: $lecon->only(['title', 'type', 'minutes', 'position']),
            cibleLibelle: $this->cours()->title.' — '.$lecon->title,
        );

        $this->fermerLeTiroirLecon();
        $this->toastSuccess($action === 'lesson.create' ? 'Leçon ajoutée.' : 'Leçon mise à jour.');
    }

    public function deplacerLaLecon(string $uuid, string $sens, ConstructionDeCours $construction): void
    {
        $lecon = $this->lecon($uuid);
        $this->authorize('update', $lecon);

        if (! in_array($sens, ['haut', 'bas'], true)) {
            return;
        }

        /** @var 'haut'|'bas' $sens */
        $construction->deplacerUneLecon($lecon, $sens);
    }

    // ————————————————————————————————————————————————— quiz

    public function ouvrirCreationQuiz(): void
    {
        $this->authorize('create', Quiz::class);

        $this->reset('quizEnEdition', 'quizTitre');
        $this->quizMinutes = '5';
        $this->quizSeuil = '70';
        $this->quizXp = '0';
        $this->resetValidation();
        $this->tiroirQuiz = true;
    }

    public function ouvrirEditionQuiz(string $uuid): void
    {
        $quiz = $this->quiz($uuid);
        $this->authorize('update', $quiz);

        $this->quizEnEdition = $uuid;
        $this->quizTitre = (string) $quiz->title;
        $this->quizMinutes = (string) $quiz->minutes;
        $this->quizSeuil = (string) $quiz->pass_score;
        $this->quizXp = (string) $quiz->xp_reward;

        $this->resetValidation();
        $this->tiroirQuiz = true;
    }

    public function fermerLeTiroirQuiz(): void
    {
        $this->tiroirQuiz = false;
        $this->quizEnEdition = null;
        $this->resetValidation();
    }

    public function enregistrerLeQuiz(ConstructionDeCours $construction, AuditLogService $audit): void
    {
        $donnees = $this->validate($this->reglesDeQuiz());

        $charge = [
            'titre' => $donnees['quizTitre'],
            'minutes' => (int) $donnees['quizMinutes'],
            'seuil' => (int) $donnees['quizSeuil'],
            'xp' => (int) $donnees['quizXp'],
        ];

        if ($this->quizEnEdition !== null) {
            $quiz = $this->quiz($this->quizEnEdition);
            $this->authorize('update', $quiz);
            $construction->modifierUnQuiz($quiz, $charge);
            $action = 'quiz.update';
        } else {
            $this->authorize('create', Quiz::class);
            $quiz = $construction->ajouterUnQuiz($this->cours(), $charge);
            $action = 'quiz.create';
        }

        $audit->record(
            action: $action,
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $quiz,
            apres: $quiz->only(['title', 'minutes', 'pass_score', 'xp_reward']),
            cibleLibelle: $this->cours()->title.' — '.$quiz->title,
        );

        $this->fermerLeTiroirQuiz();
        $this->toastSuccess($action === 'quiz.create' ? 'Quiz ajouté.' : 'Quiz mis à jour.');
    }

    // ————————————————————————————————————————————————— questions

    public function ouvrirLesQuestions(string $uuid): void
    {
        $this->authorize('update', $this->quiz($uuid));

        $this->quizOuvert = $uuid;
        $this->fermerLeTiroirQuestion();
    }

    public function refermerLesQuestions(): void
    {
        $this->quizOuvert = null;
        $this->fermerLeTiroirQuestion();
    }

    public function ouvrirCreationQuestion(): void
    {
        $this->authorize('update', $this->quizCourant());

        $this->reset('questionEnEdition', 'questionEnonce', 'questionExplication');
        // Deux propositions d'emblée : c'est le minimum qu'une question exige,
        // et un formulaire vide n'apprend pas ce qu'on attend.
        $this->propositions = [
            ['label' => '', 'correcte' => true],
            ['label' => '', 'correcte' => false],
        ];
        $this->resetValidation();
        $this->tiroirQuestion = true;
    }

    public function ouvrirEditionQuestion(string $uuid): void
    {
        $question = $this->question($uuid);
        $this->authorize('update', $this->quizCourant());

        $this->questionEnEdition = $uuid;
        $this->questionEnonce = (string) $question->prompt;
        $this->questionExplication = (string) $question->explanation;
        $this->propositions = $question->answers()
            ->orderBy('position')
            ->get()
            ->map(fn ($reponse): array => [
                'label' => (string) $reponse->label,
                'correcte' => (bool) $reponse->is_correct,
            ])
            ->all();

        $this->resetValidation();
        $this->tiroirQuestion = true;
    }

    public function fermerLeTiroirQuestion(): void
    {
        $this->tiroirQuestion = false;
        $this->questionEnEdition = null;
        $this->resetValidation();
    }

    public function ajouterUneProposition(): void
    {
        // Six au plus : au-delà, la question ne tient plus sur un téléphone, et
        // le prototype n'en affiche jamais davantage.
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

    public function enregistrerLaQuestion(ConstructionDeCours $construction, AuditLogService $audit): void
    {
        $quiz = $this->quizCourant();
        $this->authorize('update', $quiz);

        $this->validate($this->reglesDeQuestion());

        try {
            $question = $construction->enregistrerUneQuestion(
                quiz: $quiz,
                question: $this->questionEnEdition !== null ? $this->question($this->questionEnEdition) : null,
                enonce: $this->questionEnonce,
                explication: $this->questionExplication !== '' ? $this->questionExplication : null,
                propositions: $this->propositions,
            );
        } catch (RuntimeException $erreur) {
            /*
             | Les deux gardes du service — au moins deux propositions, au moins
             | une correcte — remontent ici plutôt qu'en 500. Ce sont des
             | réponses à l'utilisateur, pas des pannes : une question sans
             | bonne réponse est notée fausse quoi que le citoyen coche.
             */
            $this->addError('propositions', $erreur->getMessage());

            return;
        }

        $audit->record(
            action: $this->questionEnEdition === null ? 'quiz.question_create' : 'quiz.question_update',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $question,
            apres: ['prompt' => $question->prompt, 'propositions' => count($this->propositions)],
            cibleLibelle: $quiz->title,
        );

        $this->fermerLeTiroirQuestion();
        $this->toastSuccess('Question enregistrée.');
    }

    // ————————————————————————————————————————————————— suppression

    public function demanderLaSuppression(string $type, string $uuid): void
    {
        if (! in_array($type, ['lecon', 'quiz', 'question'], true)) {
            return;
        }

        match ($type) {
            'lecon' => $this->authorize('delete', $this->lecon($uuid)),
            'quiz' => $this->authorize('delete', $this->quiz($uuid)),
            'question' => $this->authorize('update', $this->quizCourant()),
        };

        $this->typeASupprimer = $type;
        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(ConstructionDeCours $construction, AuditLogService $audit): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        $uuid = $this->aSupprimer;
        $acteur = Auth::guard('staff')->user();

        match ($this->typeASupprimer) {
            'lecon' => (function () use ($uuid, $construction, $audit, $acteur): void {
                $lecon = $this->lecon($uuid);
                $this->authorize('delete', $lecon);
                $audit->record('lesson.delete', 'academie', $acteur, $lecon,
                    avant: $lecon->only(['title', 'position']), cibleLibelle: $lecon->title);
                $construction->supprimerUneLecon($lecon);
            })(),
            'quiz' => (function () use ($uuid, $construction, $audit, $acteur): void {
                $quiz = $this->quiz($uuid);
                $this->authorize('delete', $quiz);
                $audit->record('quiz.delete', 'academie', $acteur, $quiz,
                    avant: $quiz->only(['title']) + ['questions' => $quiz->questions()->count()],
                    cibleLibelle: $quiz->title);
                $construction->supprimerUnQuiz($quiz);
                if ($this->quizOuvert === $uuid) {
                    $this->quizOuvert = null;
                }
            })(),
            'question' => (function () use ($uuid, $construction, $audit, $acteur): void {
                $question = $this->question($uuid);
                $this->authorize('update', $this->quizCourant());
                $audit->record('quiz.question_delete', 'academie', $acteur, $question,
                    avant: $question->only(['prompt']), cibleLibelle: $this->quizCourant()->title);
                $construction->supprimerUneQuestion($question);
            })(),
            default => null,
        };

        $this->aSupprimer = null;
        $this->toastSuccess('Élément supprimé.');
    }

    // ————————————————————————————————————————————————— règles

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reglesDeLecon(): array
    {
        return [
            'leconTitre' => ['required', 'string', 'min:3', 'max:180'],
            'leconType' => ['required', Rule::enum(TypeLecon::class)],
            'leconMinutes' => ['required', 'integer', 'min:1', 'max:600'],
            /*
             | Le contenu n'est obligatoire que pour une leçon TEXTE : une leçon
             | vidéo se suffit de son adresse, et exiger les deux forcerait à
             | écrire un paragraphe de remplissage sous chaque vidéo.
             */
            'leconContenu' => [Rule::requiredIf($this->leconType === TypeLecon::Texte->value), 'string', 'nullable'],
            'leconVideo' => [
                Rule::requiredIf($this->leconType === TypeLecon::Video->value),
                'nullable', 'url', 'max:500',
            ],
            'leconAudio' => [
                Rule::requiredIf($this->leconType === TypeLecon::Audio->value),
                'nullable', 'url', 'max:500',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reglesDeQuiz(): array
    {
        return [
            'quizTitre' => ['required', 'string', 'min:3', 'max:180'],
            'quizMinutes' => ['required', 'integer', 'min:1', 'max:180'],
            /*
             | Le seuil est borné à 50-100. En dessous de 50, « réussir » voudrait
             | dire se tromper plus souvent qu'avoir juste — et le certificat que
             | ce quiz contribue à délivrer ne vaudrait plus rien.
             */
            'quizSeuil' => ['required', 'integer', 'min:50', 'max:100'],
            'quizXp' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reglesDeQuestion(): array
    {
        return [
            'questionEnonce' => ['required', 'string', 'min:5', 'max:1000'],
            'questionExplication' => ['nullable', 'string', 'max:2000'],
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
            'leconTitre' => 'titre de la leçon',
            'leconType' => 'type',
            'leconAudio' => 'adresse de l’audio',
            'leconMinutes' => 'durée',
            'leconContenu' => 'contenu',
            'leconVideo' => 'adresse de la vidéo',
            'quizTitre' => 'titre du quiz',
            'quizMinutes' => 'durée',
            'quizSeuil' => 'seuil de réussite',
            'quizXp' => 'points d\'expérience',
            'questionEnonce' => 'énoncé',
            'questionExplication' => 'explication',
            'propositions' => 'propositions',
            'propositions.*.label' => 'proposition',
        ];
    }

    // ————————————————————————————————————————————————— accès

    private function cours(): Course
    {
        return Course::query()->where('slug', $this->slug)->firstOrFail();
    }

    /**
     * Toujours borné AU COURS COURANT.
     *
     * Sans ce `where`, un uuid de leçon appartenant à un autre cours passerait :
     * la Policy vérifie le formateur, pas l'appartenance au cours affiché. Un
     * mentor pourrait réordonner le plan d'un cours qu'il porte depuis l'écran
     * d'un autre — et le journal d'audit ne le dirait qu'après coup.
     */
    private function lecon(string $uuid): Lesson
    {
        return Lesson::query()
            ->where('course_id', $this->cours()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function quiz(string $uuid): Quiz
    {
        return Quiz::query()
            ->where('course_id', $this->cours()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function quizCourant(): Quiz
    {
        return $this->quiz((string) $this->quizOuvert);
    }

    private function question(string $uuid): Question
    {
        return Question::query()
            ->where('quiz_id', $this->quizCourant()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
