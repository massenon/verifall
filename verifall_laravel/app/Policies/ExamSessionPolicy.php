<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\User;

/**
 * Qui planifie les sessions d'examen.
 *
 * Une session n'a pas de permissions propres dans la matrice, et ce n'est pas
 * un oubli : elle n'existe que par son épreuve. Les droits sont donc ceux de
 * `exam.*`, et la Policy le dit explicitement plutôt que de laisser deviner.
 *
 * `create` est adossé à `exam.update` : ajouter une date à une épreuve, c'est
 * la modifier. Le service refuse d'ailleurs de planifier sur une épreuve non
 * publiée — « on ne convoque personne à un examen qui n'est pas publié ».
 */
final class ExamSessionPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('exam.view');
    }

    public function view(User $utilisateur, ExamSession $session): bool
    {
        return $utilisateur->can('exam.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('exam.update');
    }

    public function update(User $utilisateur, ExamSession $session): bool
    {
        return $utilisateur->can('exam.update');
    }

    public function delete(User $utilisateur, ExamSession $session): bool
    {
        return $utilisateur->can('exam.archive');
    }
}
