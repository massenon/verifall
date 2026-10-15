<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lesson;
use App\Models\User;

/**
 * Qui écrit les leçons.
 *
 * Une leçon n'existe pas seule : elle appartient à un cours, et c'est le cours
 * qui décide. Le mentor cantonné à ses ressources ne doit pas pouvoir ajouter
 * une leçon au cours d'un autre — l'oublier ici rendrait la borne posée sur
 * `CoursePolicy` contournable par le constructeur.
 *
 * `lesson` n'a pas de verbe `publish` dans la matrice, et c'est cohérent : une
 * leçon est visible dès que son cours l'est. C'est la publication DU COURS qui
 * met le contenu en ligne, en une seule décision.
 */
final class LessonPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('lesson.view');
    }

    public function view(User $utilisateur, Lesson $lecon): bool
    {
        return $utilisateur->can('lesson.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('lesson.create');
    }

    public function update(User $utilisateur, Lesson $lecon): bool
    {
        return $utilisateur->can('lesson.update') && $this->surSonCours($utilisateur, $lecon);
    }

    public function delete(User $utilisateur, Lesson $lecon): bool
    {
        return $utilisateur->can('lesson.delete') && $this->surSonCours($utilisateur, $lecon);
    }

    /** La leçon appartient-elle à un cours que ce mentor porte ? */
    private function surSonCours(User $utilisateur, Lesson $lecon): bool
    {
        if (! $utilisateur->hasRole(UserRole::Mentor->value) || $utilisateur->can('course.publish')) {
            return true;
        }

        $formateur = $lecon->course?->instructor_id;

        return $formateur === null || $formateur === $utilisateur->id;
    }
}
