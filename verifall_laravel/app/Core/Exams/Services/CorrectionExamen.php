<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Application\Contracts\DepotExamens;
use App\Core\Exams\Exceptions\CopieNonCorrigeable;
use App\Enums\StatutCopieExamen;
use App\Events\ExamenReussi;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La file de correction — assigner, noter, rendre la note.
 *
 * Ce que ce service refuse, et pourquoi :
 *
 *   · noter une copie encore EN COMPOSITION : son auteur croit pouvoir la
 *     modifier ;
 *   · rouvrir une copie déjà CORRIGÉE : une note est un fait daté, pas un
 *     brouillon ;
 *   · finaliser tant qu'une réponse libre n'est pas lue : rendre une note
 *     partielle en la présentant comme définitive est pire que faire attendre.
 */
final class CorrectionExamen
{
    public function __construct(
        private readonly DepotCopiesExamen $copies,
        private readonly DepotExamens $examens,
        private readonly DepotBanqueQuestions $banque,
    ) {}

    /**
     * Confie la copie à un mentor.
     */
    public function assigner(ExamAttempt $copie, User $correcteur): ExamAttempt
    {
        $this->verifierQuElleSeCorrige($copie);

        return $this->copies->mettreAJour($copie, [
            'assigned_to' => $correcteur->id,
            'assigned_at' => now(),
            'status' => StatutCopieExamen::EnCorrection->value,
        ]);
    }

    /**
     * Note une réponse libre.
     *
     * Le nombre de points est borné au barème de la question : rien n'empêche
     * un formulaire de renvoyer 200 sur une question qui en vaut 10, et une
     * copie à 180 % de moyenne ne se remarque qu'après coup.
     */
    public function noter(ExamAnswer $reponse, int $points, ?string $commentaire = null): ExamAnswer
    {
        $copie = $reponse->loadMissing('copie')->copie;

        $this->verifierQuElleSeCorrige($copie);

        $accordes = max(0, min($points, $reponse->points_max));

        return $this->copies->mettreAJourUneReponse($reponse, [
            'points_awarded' => $accordes,
            // « Juste » veut dire « tout le barème » : une réponse à moitié
            // créditée n'est pas juste, et la compter comme telle gonflerait le
            // taux de réussite de la question.
            'is_correct' => $accordes === $reponse->points_max,
            'grader_comment' => $commentaire,
        ]);
    }

    /**
     * Rend la note : score, verdict, compteurs, événement.
     */
    public function finaliser(ExamAttempt $copie, ?User $correcteur = null, ?string $feedback = null): ExamAttempt
    {
        $this->verifierQuElleSeCorrige($copie);

        $reponses = $this->copies->reponsesDe($copie);

        $enAttente = $reponses->filter(fn (ExamAnswer $r): bool => $r->attendUneCorrection());

        if ($enAttente->isNotEmpty()) {
            throw new CopieNonCorrigeable(
                $enAttente->count().' réponse(s) attendent encore une note : la copie ne peut pas être rendue.'
            );
        }

        $points = (int) $reponses->sum('points_awarded');

        /*
         | Le pourcentage se calcule sur le barème FIGÉ de la copie, pas sur le
         | barème actuel de l'examen. C'est toute la raison d'être de
         | `max_points` : une question retirée après coup ne doit pas transformer
         | un 14/20 en 14/16.
         */
        $pourcentage = $copie->max_points === 0
            ? 0
            : (int) round($points / $copie->max_points * 100);

        $rendue = DB::transaction(function () use ($copie, $points, $pourcentage, $correcteur, $feedback): ExamAttempt {
            return $this->copies->mettreAJour($copie, [
                'score_points' => $points,
                'score_percent' => $pourcentage,
                'passed' => $pourcentage >= $copie->pass_score,
                'status' => StatutCopieExamen::Corrigee->value,
                'graded_by' => $correcteur?->id ?? $copie->assigned_to,
                'graded_at' => now(),
                'feedback' => $feedback ?? $copie->feedback,
            ]);
        });

        $this->rafraichirLesCompteurs($rendue, $reponses->pluck('question_bank_item_id')->all());

        if ($rendue->passed === true) {
            /*
             | Un événement, pas un appel. Le domaine Examens n'a aucune raison
             | de connaître les badges, l'XP ou les certificats : il annonce le
             | fait et laisse l'aval décider.
             */
            ExamenReussi::dispatch($rendue);
        }

        return $rendue;
    }

    /**
     * Abandon : la copie sort de la file sans note.
     *
     * Elle ne compte NI dans les réussites, NI dans les échecs. Traiter une
     * épreuve non rendue comme un échec ferait chuter un taux affiché sans que
     * personne ne comprenne pourquoi.
     */
    public function abandonner(ExamAttempt $copie): ExamAttempt
    {
        if ($copie->status->estFigee()) {
            throw new CopieNonCorrigeable('Cette copie est déjà close.');
        }

        return $this->copies->mettreAJour($copie, [
            'status' => StatutCopieExamen::Abandonnee->value,
        ]);
    }

    /**
     * @param  list<int|null>  $questionIds
     */
    private function rafraichirLesCompteurs(ExamAttempt $copie, array $questionIds): void
    {
        $examen = $copie->loadMissing('exam')->exam;

        if ($examen instanceof Exam) {
            $this->examens->recalculerLesCompteurs($examen);
        }

        /** @var list<int> $identifiants */
        $identifiants = array_values(array_unique(array_filter(
            $questionIds,
            static fn (?int $id): bool => $id !== null,
        )));

        $this->banque->recalculerLesStatistiquesPour($identifiants);
    }

    /**
     * @throws CopieNonCorrigeable
     */
    private function verifierQuElleSeCorrige(?ExamAttempt $copie): void
    {
        if (! $copie instanceof ExamAttempt) {
            throw new CopieNonCorrigeable('Copie introuvable.');
        }

        if (! $copie->status->dansLaFileDeCorrection()) {
            throw new CopieNonCorrigeable(
                'Cette copie est '.$copie->status->libelle().' : elle n\'est pas dans la file de correction.'
            );
        }
    }
}
