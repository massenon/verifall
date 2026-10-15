<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

/**
 * Qui tient le catalogue de l'Académie.
 *
 * ## Deux règles, et la seconde est celle qui compte
 *
 * **`publish` n'est pas `update`.** La matrice déclare six verbes pour `course`
 * et n'en donne que deux au mentor : `create` et `update`. Publier engage la
 * plateforme — un cours mis en ligne s'affiche au catalogue, se vend en unités
 * et délivre un certificat. Cette décision reste à l'administrateur.
 *
 * **Le mentor est cantonné à SES ressources.** La matrice l'écrit en toutes
 * lettres. Sans cette borne, `course.update` laisserait n'importe quel mentor
 * réécrire le cours d'un autre — et le journal d'audit ne le dirait qu'après.
 *
 * ## Ce que cette Policy ne fait PAS
 *
 * Elle ne filtre pas les listes. `viewAny` répond « peut ouvrir l'écran », pas
 * « voit quoi ». La restriction du mentor à ses cours est dans la requête de
 * `Staff\Cours` : une Policy autorise une action sur une ligne qu'on lui
 * présente, elle n'a aucune prise sur ce qu'une requête ramène.
 */
final class CoursePolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('course.view');
    }

    public function view(User $utilisateur, Course $cours): bool
    {
        return $utilisateur->can('course.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('course.create');
    }

    public function update(User $utilisateur, Course $cours): bool
    {
        return $utilisateur->can('course.update') && $this->leSien($utilisateur, $cours);
    }

    public function publish(User $utilisateur, Course $cours): bool
    {
        return $utilisateur->can('course.publish');
    }

    public function archive(User $utilisateur, Course $cours): bool
    {
        return $utilisateur->can('course.archive');
    }

    public function delete(User $utilisateur, Course $cours): bool
    {
        return $utilisateur->can('course.delete');
    }

    /**
     * Un mentor n'agit que sur les cours dont il est le formateur.
     *
     * L'administrateur, qui porte `course.publish`, n'est pas concerné : il
     * tient le catalogue entier, c'est son rôle.
     *
     * Un cours SANS formateur reste ouvert au mentor : c'est le cas des cours
     * semés avant que l'attribution existe, et les interdire à tout le monde
     * les rendrait immodifiables sans passer par la console.
     */
    private function leSien(User $utilisateur, Course $cours): bool
    {
        if (! $utilisateur->hasRole(UserRole::Mentor->value) || $utilisateur->can('course.publish')) {
            return true;
        }

        return $cours->instructor_id === null || $cours->instructor_id === $utilisateur->id;
    }
}
