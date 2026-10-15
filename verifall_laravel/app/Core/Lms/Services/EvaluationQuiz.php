<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Core\Gamification\Services\JournalDXp;
use App\Enums\MotifGainXp;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Corrige un quiz, enregistre la tentative, accorde l'XP.
 *
 * Deux règles qui coûtent cher si on les oublie :
 *
 *   · L'XP N'EST ACCORDÉE QU'UNE FOIS par quiz. Le prototype tient un registre
 *     de clés pour cela (`xpGranted`) ; ici, l'existence d'une tentative
 *     réussie antérieure suffit. Sans ce garde-fou, repasser dix fois un quiz
 *     déjà validé rapporte dix fois les points, et le classement ne mesure plus
 *     que l'obstination.
 *   · UNE RÉPONSE MANQUANTE EST FAUSSE, jamais ignorée. Compter le score sur
 *     les seules questions répondues permettrait d'obtenir 100 % en répondant à
 *     une seule question.
 */
final class EvaluationQuiz
{
    public function __construct(
        private readonly ProgressionAcademie $progression,
        private readonly JournalDXp $journalXp,
    ) {}

    /**
     * @param  array<int, int|null>  $reponses  position de question → id de réponse choisie
     */
    public function corriger(
        User $utilisateur,
        Quiz $quiz,
        array $reponses,
        int $dureeSecondes = 0,
    ): QuizAttempt {
        $questions = $quiz->questions()->with('answers')->get();
        $total = $questions->count();

        if ($total === 0) {
            // Un quiz sans question ne se réussit pas : il ne devrait pas
            // exister, et le noter 100 % délivrerait un certificat pour rien.
            throw new \RuntimeException('Ce quiz ne contient aucune question.');
        }

        $dejaReussi = $this->progression->quizReussi($utilisateur, $quiz);

        return DB::transaction(function () use (
            $utilisateur, $quiz, $questions, $reponses, $dureeSecondes, $total, $dejaReussi
        ): QuizAttempt {
            $justes = 0;
            $detail = [];

            foreach ($questions as $question) {
                $choisie = $this->reponseChoisie($question, $reponses[$question->id] ?? null);
                $correcte = $choisie?->is_correct === true;

                if ($correcte) {
                    $justes++;
                }

                if ($choisie !== null) {
                    // Compteur par réponse : savoir QUELLE mauvaise réponse
                    // attire dit au mentor où sa leçon n'accroche pas.
                    $choisie->increment('picked_count');
                }

                $detail[$question->id] = [
                    'answer_id' => $choisie?->id,
                    'correct' => $correcte,
                ];
            }

            $score = (int) round($justes / $total * 100);
            $reussi = $score >= $quiz->pass_score;

            $tentative = QuizAttempt::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $utilisateur->id,
                'quiz_id' => $quiz->id,
                'score' => $score,
                'passed' => $reussi,
                'duration_s' => max(0, min($dureeSecondes, $quiz->secondes())),
                'given_answers' => $detail,
            ]);

            // XP à la PREMIÈRE réussite seulement.
            if ($reussi && ! $dejaReussi && $quiz->xp_reward > 0) {
                $this->journalXp->crediter(
                    $utilisateur,
                    (int) $quiz->xp_reward,
                    MotifGainXp::Quiz,
                    $quiz,
                );
            }

            // `$quiz->course` chargé explicitement : le laisser en accès
            // paresseux ajoute une requête par correction, et casse net dès
            // qu'on interdit le lazy loading.
            $this->progression->rafraichir($utilisateur, $quiz->loadMissing('course')->course);

            return $tentative;
        });
    }

    /**
     * La réponse retenue, si elle appartient bien à cette question.
     *
     * Le contrôle n'est pas paranoïaque : l'identifiant vient d'un formulaire,
     * et rien n'empêche d'y coller celui de la bonne réponse d'un autre quiz.
     */
    private function reponseChoisie(Question $question, int|string|null $idReponse): ?Answer
    {
        if ($idReponse === null || $idReponse === '') {
            return null;
        }

        return $question->answers->firstWhere('id', (int) $idReponse);
    }
}
