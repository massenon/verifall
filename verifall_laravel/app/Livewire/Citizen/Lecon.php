<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\ProgressionAcademie;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Lecteur de leçon — transposition de compte-user/lecon.html.
 *
 * Le chronomètre est la pièce sérieuse de cet écran. Sans temps de lecture
 * minimal, terminer un cours se réduit à cliquer six fois sur « Suivant », et
 * le certificat ne vaut rien — ni pour celui qui le montre, ni pour celui qui
 * le lit.
 *
 * Il est tenu CÔTÉ NAVIGATEUR parce qu'il doit se mettre en pause quand
 * l'onglet passe en arrière-plan — le serveur ne voit pas cela — mais il est
 * REVÉRIFIÉ côté serveur : un compteur qui vit dans la page se falsifie en
 * trois lignes de console.
 */
#[Layout('components.layouts.citizen', ['title' => "Leçon · Vérif'All"])]
final class Lecon extends Component
{
    use InteractsWithToasts;

    public string $slugCours = '';

    public string $uuidLecon = '';

    /**
     * Secondes de lecture annoncées par la page.
     *
     * Le nom dit ce que c'est : une DÉCLARATION du client, pas une mesure de
     * confiance. Le serveur la borne au temps réellement écoulé depuis
     * l'ouverture, ce qu'un script de console ne peut pas contrefaire.
     */
    public int $secondesAnnoncees = 0;

    /** Horodatage d'ouverture, en session : il ne doit pas venir du client. */
    public function mount(string $course, string $lesson): void
    {
        $this->slugCours = $course;
        $this->uuidLecon = $lesson;

        $lecon = $this->lecon();

        // Un uuid deviné ne doit rien ouvrir : ni une leçon d'un autre cours,
        // ni une leçon d'un cours auquel on n'est pas inscrit.
        abort_if($lecon->course_id !== $this->cours()->id, 404);
        abort_unless($this->inscription() !== null, 403);
        abort_unless(app(ProgressionAcademie::class)->leconOuverte($this->utilisateur(), $lecon), 403);

        session()->put($this->cleOuverture(), now()->timestamp);
    }

    /**
     * L'adresse SIGNÉE d'un document, régénérée à chaque rendu.
     *
     * La signature prouve que le lien vient de nous ; le contrôleur vérifie en
     * plus l'inscription, parce qu'un lien signé se transmet — et qu'un lien
     * transmis ne doit pas ouvrir les supports d'un cours payant à qui ne l'a
     * pas suivi.
     *
     * Elle n'expire pas : un support se consulte tout au long de la formation,
     * et un lien périmé au milieu d'un module ferait recharger pour rien. Ici
     * c'est le DROIT qui protège, pas la durée.
     */
    public function lienDuDocument(LessonAttachment $document): string
    {
        return url()->signedRoute('citizen.lesson-attachment', ['piece' => $document->uuid]);
    }

    public function render(): View
    {
        $cours = $this->cours();
        $lecon = $this->lecon();
        $progression = app(ProgressionAcademie::class);

        return view('livewire.citizen.lecon', [
            'cours' => $cours,
            'lecon' => $lecon,
            'lecons' => $cours->lessons,
            'acquise' => $progression->leconAcquise($this->utilisateur(), $lecon),
            'avancement' => $this->inscription()?->progress ?? 0,
            'precedente' => $this->voisine(-1),
            'suivante' => $this->voisine(1),
            'secondesRequises' => ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES,
            // Les supports de la leçon. Chargés ici plutôt que dans la vue :
            // la règle 1 veut qu'aucune requête ne parte d'un gabarit.
            'documents' => $lecon->pieces,
        ]);
    }

    /**
     * Valide la leçon.
     *
     * Le temps retenu est le PLUS PETIT entre ce que la page annonce et ce que
     * l'horloge du serveur autorise. Faire confiance au client permettrait de
     * valider un cours entier en une seconde ; ignorer le client rendrait la
     * pause d'onglet inopérante et punirait quelqu'un qui a vraiment lu.
     */
    public function terminer(ProgressionAcademie $progression): void
    {
        $ouverture = (int) session()->get($this->cleOuverture(), now()->timestamp);
        $ecoulees = max(0, now()->timestamp - $ouverture);

        $retenues = min($this->secondesAnnoncees, $ecoulees);

        // Relevé AVANT : c'est la transition qui se félicite, pas l'état. Sans
        // ce relevé, rouvrir la dernière leçon d'un cours déjà fini rejouerait
        // l'annonce de la prime à chaque passage.
        $primeDejaVersee = $progression->primeDejaVersee($this->utilisateur(), $this->cours());

        if (! $progression->marquerLeconLue($this->utilisateur(), $this->lecon(), $retenues)) {
            $this->toastWarning(sprintf(
                'Prenez le temps de lire : %d secondes de lecture active sont nécessaires.',
                ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES,
            ));

            return;
        }

        $prime = (int) $this->cours()->xp_reward;

        if (! $primeDejaVersee && $prime > 0 && $progression->primeDejaVersee($this->utilisateur(), $this->cours())) {
            // Le cours vient de se terminer sur cette leçon-ci. Le dire ici est
            // le seul endroit où la personne est encore devant l'écran au
            // moment où les XP tombent.
            $this->toastSuccess(__('cit_cours.cours_termine_avec_prime', ['xp' => $prime]));
        } else {
            $this->toastSuccess('Leçon terminée.');
        }

        $suivante = $this->voisine(1);

        if ($suivante !== null) {
            $this->redirectRoute('citizen.lesson', [
                'course' => $this->cours(),
                'lesson' => $suivante,
            ], navigate: true);

            return;
        }

        // Dernière leçon : on ramène à la fiche, où le quiz vient de s'ouvrir.
        $this->redirectRoute('citizen.course', ['course' => $this->cours()], navigate: true);
    }

    private function voisine(int $decalage): ?Lesson
    {
        $position = $this->lecon()->position + $decalage;

        return Lesson::where('course_id', $this->cours()->id)
            ->where('position', $position)
            ->first();
    }

    private function cleOuverture(): string
    {
        return 'lecon.ouverte.'.$this->uuidLecon;
    }

    private function cours(): Course
    {
        return Course::with('lessons')->where('slug', $this->slugCours)->firstOrFail();
    }

    private function lecon(): Lesson
    {
        return Lesson::where('uuid', $this->uuidLecon)->firstOrFail();
    }

    private function inscription(): ?Enrollment
    {
        return Enrollment::where('user_id', $this->utilisateur()->id)
            ->where('course_id', $this->cours()->id)
            ->first();
    }

    /** Exposée à la vue pour le curriculum latéral. */
    public function leconAcquise(Lesson $lecon): bool
    {
        return app(ProgressionAcademie::class)->leconAcquise($this->utilisateur(), $lecon);
    }

    public function leconOuverte(Lesson $lecon): bool
    {
        return app(ProgressionAcademie::class)->leconOuverte($this->utilisateur(), $lecon);
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
