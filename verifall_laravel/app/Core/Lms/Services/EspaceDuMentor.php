<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Enums\StatutInscription;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ce qu'un mentor voit de SES cours, et de personne d'autre.
 *
 * ## Le cadrage vit ICI, et pas dans les écrans
 *
 * Quatre écrans du module mentor partagent la même question de départ : « quels
 * cours m'appartiennent ? ». Répéter ce filtre dans chacun garantirait qu'un
 * jour l'un des quatre l'oublie — et un mentor verrait la cohorte d'un collègue.
 * Une fuite de ce genre ne fait aucun bruit : l'écran s'affiche, les chiffres
 * sont plausibles, et personne ne remarque qu'ils ne sont pas les bons.
 *
 * Toutes les lectures de ce service partent donc de {@see coursDuMentor()}.
 *
 * ## Un administrateur voit tout
 *
 * `$mentor` reste obligatoire, mais un compte qui détient `course.view` sans
 * être rattaché à un cours — un administrateur — obtient la vue complète. Le
 * discriminant est le rôle, pas la présence de données : un mentor sans cours
 * doit voir une liste VIDE, pas la liste de tout le monde.
 */
final class EspaceDuMentor
{
    /** Au-delà, l'export de cohorte n'est plus un fichier qu'on ouvre. */
    public const EXPORT_MAXIMUM = 5000;

    /**
     * Les identifiants des cours que ce compte peut voir.
     *
     * @return list<int>
     */
    public function idsDesCours(User $mentor, bool $toutVoir = false): array
    {
        $requete = Course::query();

        if (! $toutVoir) {
            $requete->where('instructor_id', $mentor->id);
        }

        /** @var list<int> */
        return $requete->pluck('id')->all();
    }

    /**
     * Les cours du mentor, avec de quoi remplir « Mes Cours ».
     *
     * @return Collection<int, Course>
     */
    public function coursDuMentor(User $mentor, bool $toutVoir = false): Collection
    {
        return Course::query()
            ->when(! $toutVoir, fn (Builder $q) => $q->where('instructor_id', $mentor->id))
            ->withCount(['lessons', 'enrollments'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * La cohorte : une ligne par inscription, tous cours confondus.
     *
     * ## Une ligne par INSCRIPTION, pas par personne
     *
     * Quelqu'un inscrit à trois cours d'un même mentor apparaît trois fois, et
     * c'est voulu : la question posée sur cet écran est « où en est cette
     * personne DANS CE COURS », pas « qui suis-je en train de former ». Fusionner
     * les lignes obligerait à choisir un avancement parmi trois.
     *
     * @param  string  $recherche  nom ou adresse
     * @param  string  $coursUuid  '' pour tous
     * @param  string  $etat  '' | 'en-cours' | 'termine' | 'decroche'
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function cohorte(
        User $mentor,
        bool $toutVoir = false,
        string $recherche = '',
        string $coursUuid = '',
        string $etat = '',
        int $parPage = 20,
    ): LengthAwarePaginator {
        return $this->requeteDeCohorte($mentor, $toutVoir, $recherche, $coursUuid, $etat)
            ->paginate($parPage);
    }

    /**
     * Les mêmes lignes, sans pagination — pour l'export de cohorte.
     *
     * Bornée : un export qui rend cinquante mille lignes n'est plus un fichier
     * qu'on ouvre, et il tombe en mémoire avant d'être écrit.
     *
     * @return Collection<int, Enrollment>
     */
    public function pourExport(
        User $mentor,
        bool $toutVoir = false,
        string $recherche = '',
        string $coursUuid = '',
        string $etat = '',
    ): Collection {
        return $this->requeteDeCohorte($mentor, $toutVoir, $recherche, $coursUuid, $etat)
            ->limit(self::EXPORT_MAXIMUM)
            ->get();
    }

    /**
     * Le meilleur score de quiz de chaque inscrit, par cours.
     *
     * ## Chargé EN UNE FOIS, indexé par « user_id:course_id »
     *
     * L'alternative — interroger les tentatives depuis chaque ligne du tableau —
     * ferait vingt requêtes par page, et le dépôt interdit de toute façon le
     * chargement paresseux. Une jointure sur `quizzes` suffit : le quiz porte
     * `course_id`, la tentative porte `quiz_id`.
     *
     * @param  \Illuminate\Support\Collection<int, Enrollment>|Collection<int, Enrollment>  $inscriptions
     * @return array<string, int>
     */
    public function meilleursScores($inscriptions): array
    {
        if ($inscriptions->isEmpty()) {
            return [];
        }

        /*
         | `DB::table()` et non le modèle : les colonnes rendues ici — un
         | `course_id` emprunté à la jointure, un `meilleur` calculé — n'existent
         | pas sur `QuizAttempt`. Les lire depuis le modèle marcherait à
         | l'exécution et mentirait au typage ; l'analyse statique le refuse, à
         | juste titre. Une agrégation n'est pas une entité.
         */
        $lignes = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereIn('quiz_attempts.user_id', $inscriptions->pluck('user_id')->unique()->all())
            ->whereIn('quizzes.course_id', $inscriptions->pluck('course_id')->unique()->all())
            ->groupBy('quiz_attempts.user_id', 'quizzes.course_id')
            ->selectRaw('quiz_attempts.user_id, quizzes.course_id, MAX(quiz_attempts.score) as meilleur')
            ->get();

        $scores = [];

        foreach ($lignes as $ligne) {
            $scores[$ligne->user_id.':'.$ligne->course_id] = (int) $ligne->meilleur;
        }

        return $scores;
    }

    /**
     * « Performance » : une ligne par cours du mentor.
     *
     * @return list<array{cours: Course, apprenants: int, completion: int, scoreMoyen: ?int, note: ?float, avis: int}>
     */
    public function performance(User $mentor, bool $toutVoir = false): array
    {
        $cours = $this->coursDuMentor($mentor, $toutVoir);

        if ($cours->isEmpty()) {
            return [];
        }

        $ids = $cours->pluck('id')->all();

        // Trois agrégats, trois requêtes bornées — et non trois requêtes PAR
        // cours. La différence se voit à partir de dix cours.
        $termines = Enrollment::query()
            ->whereIn('course_id', $ids)
            ->where('status', StatutInscription::Termine->value)
            ->groupBy('course_id')
            ->selectRaw('course_id, COUNT(*) as total')
            ->pluck('total', 'course_id');

        $scores = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereIn('quizzes.course_id', $ids)
            ->groupBy('quizzes.course_id')
            ->selectRaw('quizzes.course_id, AVG(quiz_attempts.score) as moyenne')
            ->pluck('moyenne', 'course_id');

        $lignes = [];

        foreach ($cours as $unCours) {
            $inscrits = (int) $unCours->enrollments_count;
            $fini = (int) ($termines[$unCours->id] ?? 0);

            $lignes[] = [
                'cours' => $unCours,
                'apprenants' => $inscrits,
                // Zéro inscrit donne 0 % et non une division par zéro : un cours
                // qui vient d'être publié n'a pas « échoué », il n'a pas commencé.
                'completion' => $inscrits > 0 ? (int) round($fini / $inscrits * 100) : 0,
                'scoreMoyen' => isset($scores[$unCours->id]) ? (int) round((float) $scores[$unCours->id]) : null,
                'note' => $unCours->rating > 0 ? (float) $unCours->rating : null,
                'avis' => (int) $unCours->reviews_count,
            ];
        }

        return $lignes;
    }

    /**
     * « Mes contenus » : leçons, quiz et classes en direct, tous cours confondus.
     *
     * Une liste plate plutôt qu'un arbre par cours : la question qu'on se pose
     * ici est « qu'est-ce que j'ai produit, et qu'est-ce qui traîne en
     * brouillon », pas « comment ce cours est-il structuré » — le constructeur
     * de cours répond déjà à la seconde.
     *
     * @param  string  $format  '' | 'lecon' | 'quiz' | 'direct'
     * @return list<array{titre: string, format: string, cours: string, duree: ?int, publie: bool, lien: ?string}>
     */
    public function contenus(User $mentor, bool $toutVoir = false, string $format = ''): array
    {
        $ids = $this->idsDesCours($mentor, $toutVoir);

        if ($ids === []) {
            return [];
        }

        $lignes = [];

        if ($format === '' || $format === 'lecon') {
            $lecons = DB::table('lessons')
                ->join('courses', 'courses.id', '=', 'lessons.course_id')
                ->whereIn('lessons.course_id', $ids)
                ->orderBy('courses.title')
                ->orderBy('lessons.position')
                ->get(['lessons.title', 'lessons.minutes', 'courses.title as cours', 'courses.slug']);

            foreach ($lecons as $lecon) {
                $lignes[] = [
                    'titre' => (string) $lecon->title,
                    'format' => 'Leçon',
                    'cours' => (string) $lecon->cours,
                    'duree' => (int) $lecon->minutes,
                    // Une leçon suit la publication de son cours : elle n'a pas
                    // d'état propre au modèle.
                    'publie' => true,
                    'lien' => (string) $lecon->slug,
                ];
            }
        }

        if ($format === '' || $format === 'quiz') {
            $quiz = DB::table('quizzes')
                ->join('courses', 'courses.id', '=', 'quizzes.course_id')
                ->whereIn('quizzes.course_id', $ids)
                ->orderBy('courses.title')
                ->get(['quizzes.title', 'quizzes.minutes', 'courses.title as cours', 'courses.slug']);

            foreach ($quiz as $unQuiz) {
                $lignes[] = [
                    'titre' => (string) $unQuiz->title,
                    'format' => 'Quiz',
                    'cours' => (string) $unQuiz->cours,
                    'duree' => (int) $unQuiz->minutes,
                    'publie' => true,
                    'lien' => (string) $unQuiz->slug,
                ];
            }
        }

        if ($format === '' || $format === 'direct') {
            $directs = LiveClass::query()
                ->whereIn('course_id', $ids)
                ->with('course:id,title,slug')
                ->orderByDesc('starts_at')
                ->get();

            foreach ($directs as $direct) {
                $lignes[] = [
                    'titre' => (string) $direct->title,
                    'format' => 'Classe en direct',
                    'cours' => (string) ($direct->course?->title ?? '—'),
                    'duree' => (int) $direct->minutes,
                    'publie' => (bool) $direct->is_published,
                    'lien' => (string) ($direct->course?->slug ?? ''),
                ];
            }
        }

        return $lignes;
    }

    /**
     * @return Builder<Enrollment>
     */
    private function requeteDeCohorte(
        User $mentor,
        bool $toutVoir,
        string $recherche,
        string $coursUuid,
        string $etat,
    ): Builder {
        $ids = $this->idsDesCours($mentor, $toutVoir);

        return Enrollment::query()
            /*
             | `whereIn` sur une liste VIDE rend zéro ligne, et c'est la bonne
             | réponse : un mentor sans cours n'a pas d'étudiants. Le piège
             | serait de sauter le filtre quand la liste est vide — il rendrait
             | alors la cohorte de toute la plateforme.
             */
            ->whereIn('course_id', $ids)
            ->with(['user:id,uuid,name,email', 'course:id,uuid,title'])
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $terme = '%'.$recherche.'%';
                $q->whereHas('user', fn (Builder $sous) => $sous
                    ->where('name', 'like', $terme)
                    ->orWhere('email', 'like', $terme));
            })
            ->when($coursUuid !== '', fn (Builder $q) => $q
                ->whereHas('course', fn (Builder $sous) => $sous->where('uuid', $coursUuid)))
            ->when($etat === 'termine', fn (Builder $q) => $q
                ->where('status', StatutInscription::Termine->value))
            ->when($etat === 'en-cours', fn (Builder $q) => $q
                ->where('status', StatutInscription::EnCours->value))
            /*
             | « Décroché » n'est pas un statut en base : c'est une inscription
             | commencée, pas terminée, et muette depuis un mois. La dériver
             | plutôt que la stocker évite un état de plus à tenir à jour — et
             | surtout un état qui deviendrait faux dès qu'on change le seuil.
             */
            ->when($etat === 'decroche', fn (Builder $q) => $q
                ->whereNull('completed_at')
                ->where('progress', '>', 0)
                ->where(fn (Builder $sous) => $sous
                    ->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', now()->subMonth())))
            // Les moins avancés en tête : cet écran sert à repérer qui décroche.
            ->orderBy('progress')
            ->orderByDesc('id');
    }
}
