<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\EvaluationQuiz;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Quiz chronométré — transposition de compte-user/quiz.html.
 *
 * Une question à la fois, un compte à rebours, et une correction commentée à la
 * fin. L'explication est le cœur pédagogique : un quiz qui note sans expliquer
 * ne fait que sanctionner.
 *
 * Le chronomètre s'affiche côté navigateur mais ne DÉCIDE de rien : la fin du
 * temps déclenche une soumission, et c'est le serveur qui corrige. Un
 * compte à rebours qui vit dans la page se remet à zéro d'un F5.
 */
#[Layout('components.layouts.citizen', ['title' => "Quiz · Vérif'All"])]
final class PasserQuiz extends Component
{
    use InteractsWithToasts;

    public string $slugCours = '';

    public string $uuidQuiz = '';

    /** Index de la question affichée, à partir de 0. */
    public int $index = 0;

    /**
     * Réponses choisies : id de question → id de réponse.
     *
     * @var array<int, int>
     */
    public array $reponses = [];

    /** uuid de la tentative une fois corrigée — l'écran de résultat. */
    public ?string $tentative = null;

    /**
     * Pourquoi le quiz ne s'ouvre pas — `null` quand il s'ouvre.
     *
     * ## Ce que cette propriété remplace, et pourquoi
     *
     * Deux `abort(403)` nus. Le premier pour un cours auquel on n'est pas
     * inscrit, le second — de loin le plus fréquent — tant qu'une seule leçon
     * du cours n'a pas été lue.
     *
     * La condition elle-même est juste et reste en place : un quiz vérifie ce
     * qui a été enseigné, il ne le remplace pas. **C'est la réponse qui était
     * fautive.** Une page d'erreur nue ne dit ni ce qui manque, ni combien il en
     * reste, ni où aller — alors que le serveur, lui, sait exactement quelle
     * leçon bloque : `prochaineLecon()` la rend.
     *
     * Refuser en connaissant la raison et ne pas la donner, c'est transformer
     * une règle pédagogique en panne.
     *
     * ## Pourquoi un ÉTAT et pas une redirection
     *
     * Rediriger vers la leçon manquante enverrait la personne ailleurs sans un
     * mot : les toasts de ce projet sont des événements de navigateur
     * (`InteractsWithToasts`), ils ne survivent pas à un changement de page. On
     * atterrirait sur une leçon quelconque sans savoir pourquoi — un second
     * mystère à la place du premier.
     *
     * L'écran explique donc sur place, et propose le lien. La personne décide.
     *
     * @var null|'non-inscrit'|'lecons-restantes'
     */
    public ?string $blocage = null;

    public function mount(string $course, string $quiz): void
    {
        $this->slugCours = $course;
        $this->uuidQuiz = $quiz;

        $leQuiz = $this->quiz();

        /*
         | Celui-ci reste un 404, et c'est volontaire : un quiz qui n'appartient
         | pas à ce cours est une URL fabriquée, pas un parcours interrompu. Il
         | n'y a rien à expliquer à qui ne s'est pas trompé de bonne foi.
         */
        abort_if($leQuiz->course_id !== $this->cours()->id, 404);

        if ($this->inscription() === null) {
            $this->blocage = 'non-inscrit';

            return;
        }

        // Le quiz n'est ouvert qu'une fois les leçons lues : il vérifie ce qui
        // a été enseigné, il ne le remplace pas.
        if ($this->leconManquante() !== null) {
            $this->blocage = 'lecons-restantes';

            return;
        }

        /*
         | UNE SEULE FOIS. La version précédente écrasait le départ à chaque
         | montage : appuyer sur F5 à cinq secondes de la fin remettait le
         | compteur à zéro, côté client comme côté serveur, avec les réponses
         | toujours en mémoire du composant. Le chronomètre ne contraignait rien.
         */
        if (! session()->has($this->cleDebut())) {
            session()->put($this->cleDebut(), now()->timestamp);
        }
    }

    /**
     * La marge accordée au trajet réseau.
     *
     * Une soumission met du temps à arriver, et sur une connexion 3G ce trajet
     * se compte en secondes. Sans cette marge, on punirait la connexion de
     * quelqu'un plutôt que son retard.
     */
    private const MARGE_RESEAU = 30;

    /** Secondes écoulées depuis le départ enregistré CÔTÉ SERVEUR. */
    private function secondesEcoulees(): int
    {
        $debut = (int) session()->get($this->cleDebut(), now()->timestamp);

        return max(0, now()->timestamp - $debut);
    }

    /**
     * Le temps est-il dépassé, marge comprise ?
     *
     * Publique parce que la vue s'en sert pour dire « temps écoulé » plutôt que
     * de laisser un bouton qui ne marchera pas.
     */
    public function tempsDepasse(): bool
    {
        $limite = $this->quiz()->secondes();

        // Un quiz sans durée déclarée n'a pas d'échéance à opposer.
        if ($limite <= 0) {
            return false;
        }

        return $this->secondesEcoulees() > $limite + self::MARGE_RESEAU;
    }

    /**
     * La première leçon non lue du cours, ou `null` si tout est lu.
     *
     * Elle porte deux rôles : elle décide de l'ouverture du quiz, et elle est
     * le lien que l'écran de blocage propose. Un seul appel pour les deux —
     * refaire la requête pour l'affichage donnerait deux vérités possibles.
     */
    public function leconManquante(): ?Lesson
    {
        return app(ProgressionAcademie::class)
            ->prochaineLecon($this->utilisateur(), $this->cours());
    }

    public function render(): View
    {
        /*
         | Le rendu bloqué s'arrête ici. Charger les questions, la tentative en
         | cours et le certificat pour un écran qui n'affiche qu'un message
         | serait quatre requêtes pour rien — et `resultat()` suppose une
         | inscription, qui est précisément ce qui peut manquer.
         */
        if ($this->blocage !== null) {
            return view('livewire.citizen.passer-quiz', [
                'cours' => $this->cours(),
                'quiz' => $this->quiz(),
                'leconManquante' => $this->blocage === 'lecons-restantes' ? $this->leconManquante() : null,
                'questions' => collect(),
                'question' => null,
                'total' => 0,
                'dejaReussi' => false,
                'resultat' => null,
                'certificat' => null,
                'secondes' => 0,
            ]);
        }

        $quiz = $this->quiz();
        $questions = $quiz->questions()->with('answers')->get();

        return view('livewire.citizen.passer-quiz', [
            'leconManquante' => null,
            'cours' => $this->cours(),
            'quiz' => $quiz,
            'questions' => $questions,
            'question' => $questions[$this->index] ?? null,
            'total' => $questions->count(),
            'dejaReussi' => app(ProgressionAcademie::class)->quizReussi($this->utilisateur(), $quiz),
            'resultat' => $this->resultat(),
            'certificat' => $this->certificat(),
            'secondes' => $quiz->secondes(),
        ]);
    }

    public function choisir(int $idQuestion, int $idReponse): void
    {
        $this->reponses[$idQuestion] = $idReponse;
    }

    public function precedent(): void
    {
        $this->index = max(0, $this->index - 1);
    }

    public function suivant(EvaluationQuiz $evaluation): void
    {
        $questions = $this->quiz()->questions;
        $courante = $questions[$this->index] ?? null;

        if ($courante === null) {
            return;
        }

        if (! isset($this->reponses[$courante->id])) {
            $this->toastError('Choisissez une réponse avant de continuer.');

            return;
        }

        if ($this->index < $questions->count() - 1) {
            $this->index++;

            return;
        }

        $this->soumettre($evaluation);
    }

    /**
     * Corrige et affiche le résultat.
     *
     * Appelable aussi par le navigateur à la fin du chronomètre : les questions
     * restées sans réponse comptent alors comme fausses, ce que l'écran annonce
     * avant de commencer.
     */
    public function soumettre(EvaluationQuiz $evaluation): void
    {
        if ($this->tentative !== null) {
            return;
        }

        /*
         | LE TEMPS EST OPPOSABLE, ou ce n'est pas un temps.
         |
         | Sans ce contrôle, la durée n'était qu'ENREGISTRÉE : `soumettre()` la
         | transmettait pour la stocker, et rien ne la comparait à la limite du
         | quiz. On pouvait rendre une copie une heure plus tard, à plein
         | crédit — et, combiné à la réinitialisation du départ, le chronomètre
         | n'était qu'un décor.
         |
         | Le refus est explicite : une copie effacée en silence laisse croire
         | à un bug, et pousse à recommencer.
         */
        if ($this->tempsDepasse()) {
            $this->toastError('Le temps imparti est écoulé : cette copie ne peut plus être rendue.');

            return;
        }

        $debut = (int) session()->get($this->cleDebut(), now()->timestamp);

        $tentative = $evaluation->corriger(
            $this->utilisateur(),
            $this->quiz(),
            $this->reponses,
            now()->timestamp - $debut,
        );

        $this->tentative = $tentative->uuid;

        /*
         | Le départ est purgé une fois la copie rendue. Sans cela, une seconde
         | tentative héritait du chronomètre de la première et naissait déjà
         | expirée — le correctif du départ unique aurait fermé la porte dans
         | l'autre sens.
         */
        session()->forget($this->cleDebut());

        if ($tentative->passed) {
            // Confettis : le prototype les déclenche à la réussite, et c'est
            // le seul moment du produit où l'on célèbre quelque chose.
            $this->dispatch('confetti');
            $this->toastSuccess('Quiz réussi !');

            return;
        }

        $this->toastWarning('Il s\'en faut de peu. Relisez la correction, puis réessayez.');
    }

    /** Recommence à zéro — une tentative ratée n'enferme personne. */
    public function reessayer(): void
    {
        $this->reset(['index', 'reponses', 'tentative']);
        session()->put($this->cleDebut(), now()->timestamp);
    }

    private function resultat(): ?QuizAttempt
    {
        return $this->tentative === null
            ? null
            : QuizAttempt::where('uuid', $this->tentative)->first();
    }

    private function certificat(): ?Certificate
    {
        return Certificate::where('user_id', $this->utilisateur()->id)
            ->where('course_id', $this->cours()->id)
            ->whereNull('revoked_at')
            ->first();
    }

    private function cleDebut(): string
    {
        return 'quiz.debut.'.$this->uuidQuiz;
    }

    private function cours(): Course
    {
        return Course::where('slug', $this->slugCours)->firstOrFail();
    }

    private function quiz(): Quiz
    {
        return Quiz::with('questions.answers')->where('uuid', $this->uuidQuiz)->firstOrFail();
    }

    private function inscription(): ?Enrollment
    {
        return Enrollment::where('user_id', $this->utilisateur()->id)
            ->where('course_id', $this->cours()->id)
            ->first();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
