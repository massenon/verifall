<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\StatutInscription;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\UnitLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mes Tutoriels » — transposition de compte-user/mes-tutoriels.html.
 *
 * Le parcours personnel : ce qui est commencé, ce qui bloque, ce qui est fini.
 * Trois des cinq sections n'apparaissent que si elles ont quelque chose à dire
 * — un bandeau « Quiz à reprendre » vide donnerait le sentiment d'un travail en
 * attente qui n'existe pas.
 */
#[Layout('components.layouts.citizen', ['title' => "Mes Tutoriels · Vérif'All"])]
final class MesTutoriels extends Component
{
    /**
     * La clé du guide pratique ouvert ; vide = la liste.
     *
     * ## Pourquoi le guide s'ouvre ICI et non sur le site public
     *
     * « Lire le guide » pointait sur `route('public.tutorials')` : depuis son
     * espace, le citoyen était éjecté vers le site vitrine. Il y perdait son
     * en-tête, son solde d'unités, sa cloche — et devait revenir en arrière
     * pour retrouver son parcours. Le guide, lui, n'y était même pas : la page
     * publique liste des COURS, pas ces quatre fiches.
     *
     * ## Pourquoi un état d'URL et pas un lien vers une nouvelle route
     *
     * `#[Url]` donne à chaque guide une adresse partageable
     * (`?guide=verifier-une-image`) et fait fonctionner le bouton Précédent,
     * sans recharger la page : Livewire remplace le fragment, le gabarit
     * citoyen reste en place. C'est ce que demande « une page statique sans
     * recharger le site » — la sensation d'une page, le coût d'un onglet.
     *
     * Le contenu vient des fichiers de langue : il est FIGÉ, sans requête ni
     * table. Quatre fiches de quatre étapes ne méritent pas un modèle.
     */
    #[Url(as: 'guide', except: '')]
    public string $guide = '';

    /**
     * Les identifiants des quatre guides, dans l'ordre du fichier de langue.
     *
     * ## Pourquoi ils sont ICI et pas dans `lang/`
     *
     * Ils y ont d'abord été mis, et le test de couverture des traductions les a
     * refusés — à juste titre : il signale toute valeur anglaise identique au
     * français, et une clé l'est forcément.
     *
     * Le refus tombait juste pour une raison plus profonde que la parité. Ces
     * chaînes sont des **identifiants d'URL**, pas de la copie. Traduites,
     * `?guide=verifier-une-image` deviendrait `?guide=check-an-image` en
     * anglais : le même guide aurait deux adresses, un lien partagé cesserait
     * de fonctionner pour qui n'a pas la même langue, et changer un libellé
     * casserait des liens.
     *
     * Le fichier de langue porte donc la COPIE ; ce tableau porte l'identité.
     *
     * @var list<string>
     */
    private const CLES = [
        'verifier-une-image',
        'arnaque-recrutement',
        'lire-un-rapport',
        'audio-suspect',
    ];

    public function ouvrirLeGuide(string $cle): void
    {
        // Une clé inconnue ne fait rien plutôt que d'ouvrir un panneau vide :
        // c'est un paramètre d'URL, donc modifiable à la main.
        if ($this->trouverLeGuide($cle) !== null) {
            $this->guide = $cle;
        }
    }

    /**
     * Les guides, chacun muni de sa clé.
     *
     * Le rapprochement se fait par POSITION : les deux fichiers de langue
     * déclarent les quatre guides dans le même ordre, et le test de parité des
     * clés garantit qu'ils continuent de le faire.
     *
     * @return list<array<string, mixed>>
     */
    public function guides(): array
    {
        /** @var list<array<string, mixed>> $guides */
        $guides = (array) __('cit_mes_tutoriels.guides');

        foreach ($guides as $rang => $guide) {
            $guides[$rang]['cle'] = self::CLES[$rang] ?? '';
        }

        return $guides;
    }

    public function fermerLeGuide(): void
    {
        $this->guide = '';
    }

    /**
     * Le guide ouvert, ou `null`.
     *
     * @return array<string, mixed>|null
     */
    public function guideOuvert(): ?array
    {
        return $this->guide === '' ? null : $this->trouverLeGuide($this->guide);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function trouverLeGuide(string $cle): ?array
    {
        foreach ($this->guides() as $guide) {
            if ($guide['cle'] === $cle) {
                return $guide;
            }
        }

        return null;
    }

    public function render(ProgressionAcademie $progression): View
    {
        $utilisateur = $this->utilisateur();

        $inscriptions = Enrollment::with('course')
            ->where('user_id', $utilisateur->id)
            ->orderByDesc('last_activity_at')
            ->get();

        $enCours = $inscriptions->filter(
            fn (Enrollment $i): bool => $i->course !== null
                && $i->status !== StatutInscription::Termine
        )->values();

        $termines = $inscriptions->filter(
            fn (Enrollment $i): bool => $i->course !== null
                && $i->status === StatutInscription::Termine
        )->values();

        $scores = QuizAttempt::where('user_id', $utilisateur->id)->pluck('score');

        return view('livewire.citizen.mes-tutoriels', [
            'enCours' => $enCours,
            'termines' => $termines,
            'reprise' => $enCours->first(),
            'avancements' => $enCours->mapWithKeys(fn (Enrollment $i): array => [
                $i->course->id => $progression->avancement($utilisateur, $i->course),
            ]),
            'quizARefaire' => $this->quizARefaire($utilisateur),
            'exercicesDus' => $this->exercicesDus($utilisateur),
            'moyenneQuiz' => $scores->isEmpty() ? null : (int) round($scores->avg()),
            'unitesInvesties' => (int) abs((int) UnitLog::where('user_id', $utilisateur->id)
                ->where('delta', '<', 0)->sum('delta')),
            'unitesGagnees' => (int) UnitLog::where('user_id', $utilisateur->id)
                ->where('delta', '>', 0)->sum('delta'),
            'certificats' => Certificate::where('user_id', $utilisateur->id)->count(),
            'certificatsParCours' => Certificate::where('user_id', $utilisateur->id)
                ->get()->keyBy('course_id'),
        ]);
    }

    /**
     * Les quiz déjà tentés sans succès, dans un cours suivi.
     *
     * @return Collection<int, Quiz>
     */
    private function quizARefaire(User $utilisateur): Collection
    {
        $tentatives = QuizAttempt::where('user_id', $utilisateur->id)->get()->groupBy('quiz_id');

        return Quiz::with('course')
            ->whereIn('id', $tentatives->keys())
            ->get()
            ->filter(fn (Quiz $q): bool => ! $tentatives->get($q->id)->contains('passed', true))
            ->values();
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function exercicesDus(User $utilisateur): Collection
    {
        $inscrits = Enrollment::where('user_id', $utilisateur->id)->pluck('course_id');
        $rendus = AssignmentSubmission::where('user_id', $utilisateur->id)->pluck('assignment_id');

        return Assignment::with('course')
            ->whereIn('course_id', $inscrits)
            ->whereNotIn('id', $rendus)
            ->orderBy('course_id')->orderBy('position')
            ->get();
    }

    private function utilisateur(): User
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        return $utilisateur;
    }
}
