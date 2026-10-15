<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

/**
 * Qui tient les épreuves.
 *
 * ## Huit verbes, et la matrice n'en donne que quatre au mentor
 *
 * `exam => ['view', 'create', 'update', 'publish', 'archive', 'assign', 'grade', 'delete']`.
 * Le mentor porte `view`, `create`, `update`, `grade` — il écrit des épreuves
 * et corrige des copies. Il ne PUBLIE pas, n'archive pas, n'assigne pas, ne
 * supprime pas.
 *
 * La logique est celle des cours : publier une épreuve, c'est convoquer des
 * candidats à une session certifiante. `assign` — confier une copie à un
 * correcteur — relève de l'organisation du travail, pas de la pédagogie.
 *
 * ## Pas de borne « ses ressources » ici, contrairement aux cours
 *
 * Une épreuve appartient à l'institution, pas à son rédacteur : c'est elle qui
 * délivre le certificat, et plusieurs mentors la corrigent. Reprendre la borne
 * des cours empêcherait un mentor de corriger l'épreuve d'un autre — ce qui est
 * précisément le fonctionnement normal d'un jury.
 */
final class ExamPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('exam.view');
    }

    public function view(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('exam.create');
    }

    public function update(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.update');
    }

    public function publish(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.publish');
    }

    public function archive(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.archive');
    }

    public function assign(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.assign');
    }

    public function grade(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.grade');
    }

    public function delete(User $utilisateur, Exam $examen): bool
    {
        return $utilisateur->can('exam.delete');
    }
}
