<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Lms\Exceptions\CoursIndisponible;
use App\Core\Lms\Services\InscriptionAuCours;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Review;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fiche d'un cours — transposition de compte-user/cours.html.
 *
 * Sept onglets, et un bouton principal qui change de sens selon l'état :
 * s'inscrire, commencer, reprendre, voir son certificat. Le prototype appelle
 * cette fonction `mainCta()` et lui fait porter tout le parcours ; ici elle est
 * dérivée de l'inscription réelle.
 */
#[Layout('components.layouts.citizen', ['title' => "Cours · Vérif'All"])]
final class Cours extends Component
{
    use InteractsWithToasts;

    public string $slug = '';

    /** Onglet ouvert — dans l'URL, pour qu'un lien partagé retombe au bon endroit. */
    #[Url(as: 'onglet', except: 'apercu')]
    public string $onglet = 'apercu';

    /** Modale ouverte : '', 'gratuit' ou 'premium'. */
    public string $modale = '';

    /** Note en cours de saisie, 1 à 5. */
    public int $note = 0;

    public string $commentaire = '';

    public function mount(string $course): void
    {
        $this->slug = $course;

        $cours = $this->cours();

        // Un avis déjà donné se relit et se corrige : le prototype propose de
        // noter à chaque visite, ce qui laisserait croire à un second vote.
        $mien = Review::where('user_id', $this->utilisateur()->id)
            ->where('course_id', $cours->id)
            ->first();

        if ($mien !== null) {
            $this->note = $mien->rating;
            $this->commentaire = (string) $mien->comment;
        }
    }

    public function render(): View
    {
        $cours = $this->cours();

        return view('livewire.citizen.cours', [
            'cours' => $cours,
            'inscription' => $this->inscription(),
            'lecons' => $cours->lessons,
            'quiz' => $cours->quizzes,
            'exercices' => $cours->assignments,
            'avis' => $this->avis(),
            'certificat' => $this->certificat(),
            'avancement' => $this->avancement(),
            'prochaine' => $this->prochaineLecon(),
        ]);
    }

    public function choisirOnglet(string $onglet): void
    {
        $connus = ['apercu', 'curriculum', 'instructeur', 'avis', 'quiz', 'exercices', 'faq'];

        if (in_array($onglet, $connus, true)) {
            $this->onglet = $onglet;
        }
    }

    public function ouvrirModale(): void
    {
        if ($this->inscription() !== null) {
            return;
        }

        $this->modale = $this->cours()->estGratuit() ? 'gratuit' : 'premium';
    }

    public function fermerModale(): void
    {
        $this->modale = '';
    }

    /**
     * S'inscrire — gratuitement ou en dépensant des unités.
     *
     * Le service tranche : le coût est relu au moment du débit, et un
     * double-clic ne débite qu'une fois.
     */
    public function inscrire(InscriptionAuCours $inscriptions): void
    {
        $cours = $this->cours();

        try {
            $inscription = $inscriptions->inscrire($this->utilisateur(), $cours);
        } catch (SoldeInsuffisant) {
            $this->modale = '';
            $this->toastError('Solde insuffisant. Rechargez vos unités pour débloquer ce cours.');

            return;
        } catch (CoursIndisponible $e) {
            $this->modale = '';
            $this->toastError($e->getMessage());

            return;
        }

        $this->modale = '';
        unset($this->inscriptionChargee);

        $this->toastSuccess(
            $inscription->paid_units > 0
                ? 'Cours débloqué. Bon apprentissage !'
                : 'Vous êtes inscrit. Bon apprentissage !'
        );

        // Le solde de l'en-tête doit suivre le débit, sinon l'écran affiche
        // deux soldes différents pour le même compte.
        $this->dispatch('solde-modifie', solde: $this->utilisateur()->fresh()?->units);
    }

    /** Note cliquée dans le sélecteur d'étoiles. */
    public function noter(int $etoiles): void
    {
        $this->note = max(1, min(5, $etoiles));
    }

    /**
     * Publie ou corrige son avis.
     *
     * Réservé aux inscrits : un avis sur un cours qu'on n'a pas suivi ne dit
     * rien du cours, et la note affichée en catalogue est ce sur quoi les gens
     * se décident.
     */
    public function publierAvis(): void
    {
        $cours = $this->cours();

        if ($this->inscription() === null) {
            $this->toastWarning('Inscrivez-vous au cours pour donner votre avis.');

            return;
        }

        $this->validate(
            [
                'note' => ['required', 'integer', 'min:1', 'max:5'],
                'commentaire' => ['nullable', 'string', 'max:1000'],
            ],
            ['note.min' => 'Choisissez une note entre 1 et 5 étoiles.'],
        );

        Review::updateOrCreate(
            ['user_id' => $this->utilisateur()->id, 'course_id' => $cours->id],
            [
                'uuid' => (string) Str::uuid(),
                'rating' => $this->note,
                'comment' => trim($this->commentaire) ?: null,
                'is_visible' => true,
            ],
        );

        $this->recalculerLaNote($cours);

        $this->toastSuccess('Merci, votre avis est publié.');
    }

    /**
     * Recalcule la note affichée du cours.
     *
     * Dénormalisée exprès : la moyenne se lirait sinon à chaque carte du
     * catalogue, sur la page la plus visitée de l'Académie. Recalculée ici,
     * c'est-à-dire à l'écriture, qui est rare.
     */
    private function recalculerLaNote(Course $cours): void
    {
        $visibles = Review::where('course_id', $cours->id)->where('is_visible', true);

        $cours->forceFill([
            'rating' => round((float) $visibles->avg('rating'), 1),
            'reviews_count' => $visibles->count(),
        ])->save();
    }

    /**
     * @return Collection<int, Review>
     */
    private function avis(): Collection
    {
        return Review::with('user:id,name')
            ->where('course_id', $this->cours()->id)
            ->where('is_visible', true)
            ->whereNotNull('comment')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    #[Computed(persist: true)]
    public function coursCharge(): Course
    {
        return Course::with(['lessons', 'quizzes', 'assignments', 'instructor:id,name'])
            ->where('slug', $this->slug)
            ->where('is_published', true)
            ->firstOrFail();
    }

    private function cours(): Course
    {
        return $this->coursCharge();
    }

    #[Computed]
    public function inscriptionChargee(): ?Enrollment
    {
        return Enrollment::where('user_id', $this->utilisateur()->id)
            ->where('course_id', $this->cours()->id)
            ->first();
    }

    public function inscription(): ?Enrollment
    {
        return $this->inscriptionChargee();
    }

    public function avancement(): int
    {
        return $this->inscription()?->progress ?? 0;
    }

    private function certificat(): ?Certificate
    {
        return Certificate::where('user_id', $this->utilisateur()->id)
            ->where('course_id', $this->cours()->id)
            ->whereNull('revoked_at')
            ->first();
    }

    private function prochaineLecon(): ?Lesson
    {
        if ($this->inscription() === null) {
            return null;
        }

        return app(ProgressionAcademie::class)->prochaineLecon($this->utilisateur(), $this->cours());
    }

    /** Cette leçon est-elle ouverte ? La vue s'en sert pour le cadenas. */
    public function leconOuverte(Lesson $lecon): bool
    {
        return $this->inscription() !== null
            && app(ProgressionAcademie::class)->leconOuverte($this->utilisateur(), $lecon);
    }

    public function leconAcquise(Lesson $lecon): bool
    {
        return app(ProgressionAcademie::class)->leconAcquise($this->utilisateur(), $lecon);
    }

    /**
     * Un quiz ne s'ouvre qu'une fois toutes les leçons lues.
     *
     * Le prototype le tient ainsi, et c'est cohérent avec le sens d'un quiz :
     * il vérifie ce qui a été enseigné, il ne le remplace pas.
     */
    public function quizOuvert(Quiz $quiz): bool
    {
        if ($this->inscription() === null) {
            return false;
        }

        return app(ProgressionAcademie::class)->prochaineLecon($this->utilisateur(), $this->cours()) === null;
    }

    public function quizReussi(Quiz $quiz): bool
    {
        return app(ProgressionAcademie::class)->quizReussi($this->utilisateur(), $quiz);
    }

    public function exerciceRemis(Assignment $exercice): bool
    {
        return app(ProgressionAcademie::class)->remiseDe($this->utilisateur(), $exercice) !== null;
    }

    public function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
