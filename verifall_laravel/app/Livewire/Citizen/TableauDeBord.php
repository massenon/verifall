<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Gamification\Services\QuizDAccueil;
use App\Core\Gamification\Services\SerieDeVigilance;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\StatutInscription;
use App\Enums\StatutVerification;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LiveClass;
use App\Models\Thread;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tableau de bord citoyen — transposition de compte-user/dashboard.html.
 *
 * C'est la page d'arrivée après connexion, donc celle qui doit répondre à une
 * seule question : **qu'est-ce que j'ai à faire maintenant ?** Le prototype y
 * répond dans cet ordre — priorités, reprise d'apprentissage, activité,
 * dernières vérifications — et l'ordre ne bouge pas.
 *
 * Tout ce qui s'affiche ici est mesuré. Le prototype simulait ses chiffres dans
 * le `localStorage` ; aucun de ces nombres n'est recopié. Un compteur inventé
 * sur la page d'accueil d'une plateforme de vérification serait la pire des
 * contradictions.
 */
#[Layout('components.layouts.citizen', ['title' => "Tableau de bord · Vérif'All"])]
final class TableauDeBord extends Component
{
    use InteractsWithToasts;

    /** Réponse choisie dans la modale du quiz d'accueil. */
    public ?int $reponseAccueil = null;

    /** Commentaire affiché après validation ; null tant qu'on n'a pas validé. */
    public ?string $commentaireAccueil = null;

    public bool $accueilJuste = false;

    public function render(
        SerieDeVigilance $series,
        ProgressionAcademie $progression,
        QuizDAccueil $accueil,
    ): View {
        $utilisateur = $this->utilisateur();
        $reprise = $this->repriseDApprentissage($utilisateur, $progression);

        return view('livewire.citizen.tableau-de-bord', [
            'utilisateur' => $utilisateur,
            'serieVivante' => $series->estVivante($utilisateur),
            'priorites' => $this->priorites($utilisateur),
            'nombreDeVerifications' => Verification::where('user_id', $utilisateur->id)->count(),
            'coursSuivis' => Enrollment::where('user_id', $utilisateur->id)->count(),
            'badgesObtenus' => UserBadge::where('user_id', $utilisateur->id)->count(),
            'coursOffert' => $this->coursOffertDuMois(),
            'reprise' => $reprise,
            'activite' => $this->activiteSurTrenteJours($utilisateur),
            'verificationsRecentes' => $this->verificationsRecentes($utilisateur),
            'prochaineLiveClass' => $this->prochaineLiveClass(),
            'sujetsDuForum' => $this->sujetsDuForum(),
            'quizDAccueil' => $accueil,
            /*
             | UNE SEULE FENÊTRE À LA FOIS, et le QUIZ D'ACCUEIL passe devant.
             |
             | L'ordre a été inversé deux fois, et le raisonnement mérite d'être
             | écrit une bonne fois. Le quiz est ce qui doit s'ouvrir au sortir
             | de l'inscription : il enseigne un réflexe en trente secondes,
             | alors que l'invitation à vérifier suppose qu'on ait déjà une
             | rumeur sous la main.
             |
             | L'invitation n'est pas perdue pour autant : son drapeau vit en
             | session et elle s'ouvre dès que le quiz cesse d'être à poser,
             | c'est-à-dire au moment où il a été répondu ou reporté.
             */
            'poserLeQuiz' => $accueil->aPoser($utilisateur),
            'proposerPremiereVerification' => session('verifall.premiere_verification', false)
                && ! $accueil->aPoser($utilisateur),
            'campagne' => $this->campagneAAnnoncer($utilisateur, $accueil),
        ]);
    }

    /* ------------------------------------------------ modale d'accueil */

    public function validerLeQuizDAccueil(QuizDAccueil $accueil): void
    {
        if ($this->reponseAccueil === null) {
            $this->toastError('Choisissez une réponse avant de valider.');

            return;
        }

        $this->accueilJuste = $accueil->repondre($this->utilisateur(), $this->reponseAccueil);
        $this->commentaireAccueil = $accueil->commentaire($this->accueilJuste);

        if ($this->accueilJuste) {
            $this->dispatch('confetti');
        }
    }

    /**
     * « Plus tard » sur la modale de première vérification.
     *
     * Le drapeau est RETIRÉ de la session : sans cela, la fenêtre reviendrait
     * à chaque affichage du tableau de bord, et une invitation qu'on ne peut
     * pas refuser cesse d'en être une.
     */
    public function reporterPremiereVerification(): void
    {
        session()->forget('verifall.premiere_verification');
    }

    /** « Lancer ma première vérification » — vers l'écran prévu pour ça. */
    public function allerVerifier(): mixed
    {
        session()->forget('verifall.premiere_verification');

        return $this->redirect(route('citizen.verify'), navigate: true);
    }

    public function reporterLeQuizDAccueil(QuizDAccueil $accueil): void
    {
        $accueil->reporter($this->utilisateur());

        $this->toastInfo('Pas de souci, nous vous le rappellerons plus tard.');
        $this->dispatch('fermer-modale', id: 'modal-onboarding');
    }

    /** L'annonce n'est montrée qu'une fois : on note la vue dès l'ouverture. */
    public function campagneVue(): void
    {
        $this->utilisateur()->forceFill(['campaign_seen_at' => now()])->save();
    }

    /* ----------------------------------------------------- les données */

    /**
     * « À traiter maintenant ».
     *
     * Trois sources, dans l'ordre où elles pressent : un quiz échoué bloque la
     * suite d'un cours, un exercice non rendu attend une correction, une
     * analyse en cours attend seulement du temps.
     *
     * @return list<array{icone: string, texte: string, lien: string, action: string}>
     */
    private function priorites(User $utilisateur): array
    {
        $items = [];

        $quizEchoues = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('courses', 'courses.id', '=', 'quizzes.course_id')
            ->where('quiz_attempts.user_id', $utilisateur->id)
            ->select('quizzes.title', 'quizzes.uuid as quiz_uuid', 'courses.slug as cours')
            ->groupBy('quizzes.id', 'quizzes.title', 'quizzes.uuid', 'courses.slug')
            ->havingRaw('MAX(quiz_attempts.passed) = 0')
            ->limit(4)
            ->get();

        foreach ($quizEchoues as $quiz) {
            $items[] = [
                'icone' => '🟠',
                'texte' => 'Quiz à reprendre — '.$quiz->title,
                'lien' => route('citizen.quiz.take', ['course' => $quiz->cours, 'quiz' => $quiz->quiz_uuid]),
                'action' => 'Reprendre',
            ];
        }

        $exercicesDus = DB::table('assignments')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->join('enrollments', function ($jointure) use ($utilisateur): void {
                $jointure->on('enrollments.course_id', '=', 'courses.id')
                    ->where('enrollments.user_id', '=', $utilisateur->id);
            })
            ->leftJoin('assignment_submissions', function ($jointure) use ($utilisateur): void {
                $jointure->on('assignment_submissions.assignment_id', '=', 'assignments.id')
                    ->where('assignment_submissions.user_id', '=', $utilisateur->id);
            })
            ->whereNull('assignment_submissions.id')
            ->select('assignments.title')
            ->limit(4)
            ->get();

        foreach ($exercicesDus as $exercice) {
            $items[] = [
                'icone' => '🟡',
                'texte' => 'Exercice à rendre — '.$exercice->title,
                'lien' => route('citizen.exercises'),
                'action' => 'Rendre',
            ];
        }

        $enCours = Verification::where('user_id', $utilisateur->id)
            ->whereIn('status', StatutVerification::valeursEnCours())
            ->latest()
            ->limit(4)
            ->get();

        foreach ($enCours as $verification) {
            $items[] = [
                'icone' => '🔵',
                'texte' => 'Vérification en cours — '.mb_strimwidth((string) $verification->claim, 0, 41, '…'),
                'lien' => route('citizen.verification', $verification),
                'action' => 'Suivre',
            ];
        }

        return array_slice($items, 0, 4);
    }

    /**
     * Le cours offert du mois.
     *
     * Il n'est pas choisi ici : il l'est en donnant une date à `free_until`.
     * Aucun cours en promotion ⇒ la bannière ne s'affiche pas. Annoncer une
     * offre qui n'existe pas serait une promesse en l'air.
     */
    private function coursOffertDuMois(): ?Course
    {
        return Course::query()
            ->where('is_published', true)
            ->whereNotNull('free_until')
            ->whereDate('free_until', '>=', now()->toDateString())
            ->orderBy('free_until')
            ->first();
    }

    /**
     * « Continuer votre apprentissage ».
     *
     * @return array{cours: Course, avancement: int, prochaineLecon: ?Lesson}|null
     */
    private function repriseDApprentissage(User $utilisateur, ProgressionAcademie $progression): ?array
    {
        $inscription = Enrollment::with('course')
            ->where('user_id', $utilisateur->id)
            ->whereIn('status', [StatutInscription::EnCours->value, StatutInscription::Inscrit->value])
            ->orderByDesc('last_activity_at')
            ->first();

        if ($inscription === null || $inscription->course === null) {
            return null;
        }

        return [
            'cours' => $inscription->course,
            'avancement' => $progression->avancement($utilisateur, $inscription->course),
            'prochaineLecon' => $progression->prochaineLecon($utilisateur, $inscription->course),
        ];
    }

    /**
     * Trente jours d'activité, un point par jour.
     *
     * Les jours sans rien valent zéro et restent dans la série : un graphique
     * qui saute les jours vides ment sur la régularité, qui est justement ce
     * qu'il montre.
     *
     * @return array{jours: list<int>, verifications: list<int>, lecons: list<int>}
     */
    private function activiteSurTrenteJours(User $utilisateur): array
    {
        $depuis = now()->subDays(29)->startOfDay();

        $verifications = Verification::where('user_id', $utilisateur->id)
            ->where('created_at', '>=', $depuis)
            ->get(['created_at'])
            ->countBy(fn (Verification $v): string => $v->created_at->toDateString());

        $lecons = LessonCompletion::where('user_id', $utilisateur->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $depuis)
            ->get(['completed_at'])
            ->countBy(fn (LessonCompletion $l): string => $l->completed_at->toDateString());

        $jours = [];
        $sequenceVerifs = [];
        $sequenceLecons = [];

        for ($i = 29; $i >= 0; $i--) {
            $jour = now()->subDays($i);
            $cle = $jour->toDateString();
            $jours[] = (int) $jour->day;
            $sequenceVerifs[] = (int) ($verifications[$cle] ?? 0);
            $sequenceLecons[] = (int) ($lecons[$cle] ?? 0);
        }

        return ['jours' => $jours, 'verifications' => $sequenceVerifs, 'lecons' => $sequenceLecons];
    }

    /**
     * @return Collection<int, Verification>
     */
    private function verificationsRecentes(User $utilisateur)
    {
        return Verification::with('report')
            ->where('user_id', $utilisateur->id)
            ->latest()
            ->limit(4)
            ->get();
    }

    private function prochaineLiveClass(): ?LiveClass
    {
        return LiveClass::with('host')
            ->where('is_published', true)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * @return Collection<int, Thread>
     */
    private function sujetsDuForum()
    {
        return Thread::with('author')
            ->withCount('replies')
            ->orderByDesc('last_activity_at')
            ->limit(3)
            ->get();
    }

    /**
     * L'annonce de campagne, une seule fois, et seulement s'il y en a une.
     *
     * Elle n'est pas écrite ici : c'est le dernier billet du blog marqué comme
     * annonce. Rien à annoncer ⇒ pas de modale.
     */
    private function campagneAAnnoncer(User $utilisateur, QuizDAccueil $accueil): ?BlogPost
    {
        // Une seule modale à l'arrivée : le quiz d'accueil passe devant.
        if ($utilisateur->campaign_seen_at !== null || $accueil->aPoser($utilisateur)) {
            return null;
        }

        return BlogPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();
    }

    private function utilisateur(): User
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        return $utilisateur;
    }
}
