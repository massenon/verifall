<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NiveauCours;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Models\Answer;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Le catalogue de l'Académie, repris du prototype.
 *
 * Les quatre cours, leurs 29 leçons, 7 quiz, 28 questions et 2 exercices ne
 * sont pas retapés à la main : ils sont EXTRAITS de `vf-store.js`, la source de
 * vérité du prototype, vers `data/lms-courses.json`. Recopier 800 lignes de
 * contenu pédagogique à la main, c'est garantir une coquille quelque part — et
 * une coquille dans une bonne réponse de quiz, personne ne la voit avant qu'un
 * apprenant soit noté faux à tort.
 *
 * Idempotent : rejouable sans dupliquer. Les cours sont retrouvés par leur
 * slug, leur contenu remplacé.
 */
final class AcademieSeeder extends Seeder
{
    /**
     * Le prototype affiche le même texte d'illustration sur toutes ses leçons.
     *
     * C'est une limite du prototype, pas un choix : le contenu pédagogique
     * réel s'écrira dans le studio mentor. Le reproduire tel quel plutôt que
     * d'inventer quatre-vingts pages de cours est la seule option honnête.
     */
    private const CORPS_PROVISOIRE = <<<'HTML'
        <p>Dans cette leçon, nous posons les bases avec des exemples concrets tirés de
        signalements réels traités par la plateforme. Prenez le temps de lire chaque
        encadré : les notions introduites ici seront réutilisées dans les quiz et les
        exercices.</p>
        HTML;

    public function run(): void
    {
        $chemin = __DIR__.'/data/lms-courses.json';

        if (! is_file($chemin)) {
            throw new RuntimeException("Contenu de l'Académie introuvable : {$chemin}");
        }

        /** @var list<array<string, mixed>> $cours */
        $cours = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        foreach ($cours as $donnees) {
            DB::transaction(fn () => $this->planterUnCours($donnees));
        }

        $this->command?->info(sprintf(
            '  %d cours · %d leçons · %d quiz · %d questions · %d exercices.',
            Course::count(),
            Lesson::count(),
            Quiz::count(),
            Question::count(),
            Assignment::count(),
        ));
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function planterUnCours(array $donnees): void
    {
        $formateur = $this->formateur((string) ($donnees['instructor']['name'] ?? ''));

        $cours = Course::withTrashed()->firstOrNew(['slug' => (string) $donnees['slug']]);

        $cours->forceFill([
            'uuid' => $cours->uuid ?? (string) Str::uuid(),
            'title' => (string) $donnees['title'],
            'description' => (string) $donnees['desc'],
            'category' => (string) $donnees['category'],
            'level' => $this->niveau((string) $donnees['level'])->value,
            'duration_label' => (string) $donnees['duration'],
            'cover_path' => $this->couverture((string) ($donnees['cover'] ?? '')),
            'cost_units' => (int) ($donnees['cost'] ?? 0),
            'outcomes' => $donnees['outcomes'] ?? [],
            'instructor_id' => $formateur?->id,
            'delivers_certificate' => (bool) ($donnees['certificate'] ?? false),
            /*
             | Le « cours offert du mois » du tableau de bord n'est pas un
             | drapeau mais une échéance : la gratuité s'éteint d'elle-même à la
             | fin du mois, sans que personne n'ait à y repenser.
             */
            'free_until' => ($donnees['free_until'] ?? null) === 'fin-du-mois'
                ? now()->endOfMonth()
                : null,
            /*
             | La NOTE et le NOMBRE D'INSCRITS ne sont PAS semés.
             |
             | Le prototype écrit « ★ 4,8 · 1 245 apprenants ». Les reprendre
             | afficherait, sur une plateforme de fact-checking, une moyenne
             | calculée sur aucun avis et une audience qui n'existe pas — donc
             | un indicateur qu'on ne mesure pas, présenté comme un fait.
             |
             | Ces deux colonnes sont dénormalisées et recalculées à l'écriture
             | d'un avis ou d'une inscription. Elles partent donc de zéro, et
             | l'écran dit « Nouveau » tant que personne n'a noté.
             */
            'rating' => 0,
            'students_count' => 0,
            'is_published' => true,
            'published_at' => $cours->published_at ?? now(),
            'deleted_at' => null,
        ])->save();

        /*
         | Contenu REMPLACÉ, pas complété. Un seeder qui ajoute sans nettoyer
         | double les leçons au second passage, et l'avancement d'un cours
         | passe silencieusement de 100 % à 50 % pour tous ses apprenants.
         */
        $cours->lessons()->delete();
        $cours->quizzes()->delete();
        $cours->assignments()->delete();

        $this->planterLesLecons($cours, $donnees['lessons'] ?? []);
        $this->planterLesQuiz($cours, $donnees['quizzes'] ?? []);
        $this->planterLesExercices($cours, $donnees['assignments'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $lecons
     */
    private function planterLesLecons(Course $cours, array $lecons): void
    {
        foreach ($lecons as $rang => $lecon) {
            $type = ($lecon['type'] ?? 'text') === 'video' ? TypeLecon::Video : TypeLecon::Texte;

            Lesson::create([
                'uuid' => (string) Str::uuid(),
                'course_id' => $cours->id,
                'position' => $rang + 1,
                'title' => (string) $lecon['title'],
                'type' => $type->value,
                'minutes' => (int) ($lecon['minutes'] ?? 1),
                'content' => self::CORPS_PROVISOIRE,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $quiz
     */
    private function planterLesQuiz(Course $cours, array $quiz): void
    {
        foreach ($quiz as $rang => $donnees) {
            $enregistre = Quiz::create([
                'uuid' => (string) Str::uuid(),
                'course_id' => $cours->id,
                'position' => $rang + 1,
                'title' => (string) $donnees['title'],
                'minutes' => (int) ($donnees['minutes'] ?? 5),
                'pass_score' => (int) ($donnees['passScore'] ?? 70),
                'xp_reward' => (int) ($donnees['xp'] ?? 0),
            ]);

            foreach ($donnees['questions'] ?? [] as $numero => $question) {
                $enregistree = Question::create([
                    'uuid' => (string) Str::uuid(),
                    'quiz_id' => $enregistre->id,
                    'position' => $numero + 1,
                    'prompt' => (string) $question['q'],
                    'explanation' => (string) ($question['xai'] ?? ''),
                ]);

                $bonne = (int) $question['answer'];

                foreach ($question['options'] ?? [] as $index => $libelle) {
                    Answer::create([
                        'question_id' => $enregistree->id,
                        'position' => $index + 1,
                        'label' => (string) $libelle,
                        'is_correct' => $index === $bonne,
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $exercices
     */
    private function planterLesExercices(Course $cours, array $exercices): void
    {
        foreach ($exercices as $rang => $exercice) {
            Assignment::create([
                'uuid' => (string) Str::uuid(),
                'course_id' => $cours->id,
                'position' => $rang + 1,
                'title' => (string) $exercice['title'],
                'description' => (string) $exercice['desc'],
                'minutes' => (int) ($exercice['minutes'] ?? 30),
            ]);
        }
    }

    private function niveau(string $libelle): NiveauCours
    {
        return match ($libelle) {
            'Intermédiaire' => NiveauCours::Intermediaire,
            'Avancé' => NiveauCours::Avance,
            default => NiveauCours::Debutant,
        };
    }

    /**
     * Chemin d'image relatif au prototype → chemin servi par l'application.
     */
    private function couverture(string $chemin): ?string
    {
        $nom = basename($chemin);

        return $nom !== '' ? 'assets/images/courses/'.$nom : null;
    }

    /**
     * Le formateur, s'il a un compte.
     *
     * Les cours du prototype citent des personnes de l'équipe. Celles qui ont
     * un compte sont rattachées ; les autres ne bloquent rien — un cours sans
     * formateur enregistré reste un cours.
     */
    private function formateur(string $nom): ?User
    {
        if ($nom === '') {
            return null;
        }

        return User::where('name', $nom)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::Mentor->value, UserRole::Admin->value,
            ]))
            ->first()
            ?? User::where('name', $nom)->first();
    }
}
