<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourseCategory;
use App\Models\User;

/**
 * Qui tient les catégories du catalogue.
 *
 * ## `lms_category` ne déclare que deux verbes, et la Policy en a cinq
 *
 * La matrice porte `lms_category => ['view', 'manage']`. Laravel, lui, appelle
 * `create`, `update` et `delete` : les trois se rabattent donc sur `manage`.
 *
 * Ce n'est pas un contournement, c'est la lecture fidèle de la matrice. Elle a
 * choisi un verbe unique parce que créer, renommer et supprimer un rayon
 * relèvent d'une seule responsabilité éditoriale — contrairement aux cours,
 * où `publish` est délibérément séparé de `update` puisque publier engage la
 * plateforme. Inventer `lms_category.create` ici contredirait cette décision,
 * et `CLAUDE.md` l'interdit : « un nouvel écran écrit sa Policy et sa garde de
 * route — il n'invente pas de permission ».
 *
 * Le mentor a `lms_category.view` et non `manage` : il range ses cours dans les
 * rayons existants, il ne redessine pas le catalogue.
 */
final class CourseCategoryPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('lms_category.view');
    }

    public function view(User $utilisateur, CourseCategory $categorie): bool
    {
        return $utilisateur->can('lms_category.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('lms_category.manage');
    }

    public function update(User $utilisateur, CourseCategory $categorie): bool
    {
        return $utilisateur->can('lms_category.manage');
    }

    public function delete(User $utilisateur, CourseCategory $categorie): bool
    {
        return $utilisateur->can('lms_category.manage');
    }
}
