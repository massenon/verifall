<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un quiz, tel qu'on le PASSE.
 *
 * LE POINT CAPITAL : `is_correct` n'est jamais sérialisé, et l'explication non
 * plus. La correction reste serveur (`EvaluationQuiz`) ; livrer les bonnes
 * réponses au client rendrait tout quiz réussissable à 100 % avec un
 * intercepteur de trafic, et un certificat obtenu ainsi ne vaudrait plus rien
 * — pour personne, y compris pour ceux qui l'ont vraiment passé.
 *
 * L'explication pédagogique — la carte XAI du prototype — arrive avec la
 * réponse à la tentative, une fois la copie rendue.
 */
final class QuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quiz $quiz */
        $quiz = $this->resource;

        return [
            'uuid' => $quiz->uuid,
            'titre' => $quiz->title,
            'minutes' => $quiz->minutes,
            'score_requis' => $quiz->pass_score,
            'xp' => $quiz->xp_reward,
            'questions' => $this->whenLoaded('questions', fn () => $quiz->questions->map(
                fn (Question $question): array => [
                    'id' => $question->uuid,
                    'position' => $question->position,
                    'intitule' => $question->prompt,
                    'reponses' => $question->answers->map(fn (Answer $reponse): array => [
                        /*
                         | L'identifiant entier de la réponse est le SEUL que
                         | le client puisse renvoyer : la table `answers` n'a
                         | pas d'uuid, et lui en ajouter un migrerait une table
                         | de production pour un besoin que rien d'autre n'a.
                         | Il n'énumère rien d'utile — une réponse n'existe que
                         | dans la question qui la porte, et la question n'est
                         | servie qu'à qui est inscrit au cours.
                         */
                        'id' => $reponse->id,
                        'position' => $reponse->position,
                        'libelle' => $reponse->label,
                    ])->values(),
                ]
            )->values()),
        ];
    }
}
