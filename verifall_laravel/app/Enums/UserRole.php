<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rôles de la plateforme (RBAC_ROLES_PERMISSIONS).
 *
 * Le nom stocké en base est la valeur de l'enum : le renommer casserait les
 * lignes de `model_has_roles` déjà écrites. Les libellés d'affichage vivent
 * dans label(), jamais en dur dans une vue.
 */
enum UserRole: string
{
    case Admin = 'Admin';
    case Mentor = 'Mentor';
    case Moderateur = 'Modérateur';
    case Citoyen = 'Citoyen';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Mentor => 'Mentor',
            self::Moderateur => 'Modérateur',
            self::Citoyen => 'Citoyen',
        };
    }

    /**
     * Ce que le rôle recouvre, en une phrase.
     *
     * Ajoutée au lot 3 pour les deux écrans qui l'attribuent : « Utilisateurs »
     * et « Rôles & permissions ». Elle vit ICI et non dans les gabarits — deux
     * descriptions du même rôle finiraient par se contredire, et c'est
     * exactement au moment de cocher une case qu'on a besoin qu'elles disent la
     * même chose.
     *
     * Chacune nomme la LIMITE du rôle autant que son périmètre : c'est ce qui
     * manque le plus quand on hésite entre deux cases.
     */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Accès complet, y compris la sécurité, la configuration et les autres comptes.',
            self::Mentor => 'Produit et corrige le contenu pédagogique, cantonné à ses propres cours. Ne publie pas.',
            self::Moderateur => 'Forum et signalements. Rend un verdict, ne supprime pas et ne voit pas les comptes.',
            self::Citoyen => "Aucun accès au back-office. C'est l'absence de rôle du personnel.",
        };
    }

    /**
     * Le rôle donne-t-il accès au back-office ?
     *
     * Un citoyen n'a AUCUN accès staff : c'est la ligne de séparation entre
     * les deux guards, et elle se lit ici plutôt que dans un middleware.
     */
    public function accedeAuBackOffice(): bool
    {
        return $this !== self::Citoyen;
    }

    /**
     * Le rôle voit-il l'intégralité des ressources, ou seulement les siennes ?
     *
     * Le Mentor est cantonné à ses propres cours et apprenants : la portée est
     * appliquée dans les Repositories, jamais seulement dans l'interface.
     */
    public function porteeGlobale(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Shell dans lequel le rôle atterrit après connexion.
     */
    public function routeApresConnexion(): string
    {
        return $this === self::Citoyen ? 'citizen.dashboard' : 'staff.dashboard';
    }
}
