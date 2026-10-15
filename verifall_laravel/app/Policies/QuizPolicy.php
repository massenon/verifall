<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Quiz;
use App\Models\User;

/**
 * Qui écrit les quiz d'un cours.
 *
 * Même borne que les leçons, et pour la même raison : un quiz appartient à un
 * cours, et le mentor est cantonné aux siens.
 *
 * À ne pas confondre avec la BANQUE DE QUESTIONS (`question_bank.*`), qui est
 * un fonds commun servant les EXAMENS. Les deux stockent des questions et n'ont
 * ni le même usage ni les mêmes droits : un quiz de cours se corrige seul et
 * ne vaut aucun diplôme ; une question de banque est posée à tous les
 * candidats d'une session.
 */
final class QuizPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('quiz.view');
    }

    public function view(User $utilisateur, Quiz $quiz): bool
    {
        return $utilisateur->can('quiz.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('quiz.create');
    }

    public function update(User $utilisateur, Quiz $quiz): bool
    {
        return $utilisateur->can('quiz.update') && $this->surSonCours($utilisateur, $quiz);
    }

    public function delete(User $utilisateur, Quiz $quiz): bool
    {
        return $utilisateur->can('quiz.delete') && $this->surSonCours($utilisateur, $quiz);
    }

    private function surSonCours(User $utilisateur, Quiz $quiz): bool
    {
        if (! $utilisateur->hasRole(UserRole::Mentor->value) || $utilisateur->can('course.publish')) {
            return true;
        }

        $formateur = $quiz->course?->instructor_id;

        return $formateur === null || $formateur === $utilisateur->id;
    }
}
