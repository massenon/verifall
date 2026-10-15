<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Qui peut ouvrir un accès, et à qui.
 *
 * C'est la Policy la plus sensible du produit : elle garde la porte par
 * laquelle on entre dans le back-office. Un compte du personnel créé de travers
 * donne accès aux signalements, aux messages de contact et aux journaux —
 * c'est-à-dire à des adresses, des messages et des noms de personnes.
 *
 * ## Deux verbes de la matrice ne sont pas servis ici, et c'est délibéré
 *
 * `user.suspend` demande une colonne qui n'existe pas encore : la déclarer
 * autorisée alors que rien ne l'applique donnerait un droit fantôme, du genre
 * qu'on croit exercer.
 *
 * `user.delete` est plus grave. Supprimer un compte est un geste RGPD, déjà
 * servi côté citoyen par `SuppressionCompteService` : deux temps, une fenêtre
 * de rétractation, une anonymisation. En faire un bouton d'administration
 * créerait un second chemin, immédiat et irréversible, vers le même effet — et
 * ce serait celui-là que personne ne testerait.
 *
 * Le module utilisateurs complet reste au lot 7. Cette Policy sert la tranche
 * qui débloque : créer un compte, donner un rôle.
 */
final class UserPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('user.view');
    }

    public function view(User $utilisateur, User $cible): bool
    {
        return $utilisateur->can('user.view');
    }

    /**
     * Créer un compte suppose de pouvoir lui donner un rôle.
     *
     * Les deux droits sont exigés ensemble : `user.create` seul produirait des
     * comptes sans rôle — donc sans accès — que leur auteur ne pourrait pas
     * activer. Une porte qu'on ouvre sans pouvoir la déverrouiller.
     */
    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('user.create') && $utilisateur->can('role.manage');
    }

    public function update(User $utilisateur, User $cible): bool
    {
        return $utilisateur->can('user.update');
    }

    /**
     * Suspendre ou rétablir un compte.
     *
     * ## Trois refus, et le troisième est le moins évident
     *
     * `user.suspend` ne suffit pas. La Policy refuse aussi :
     *
     *   · **soi-même** — c'est la ligne du dessus dans une liste triée, et le
     *     geste se ferait depuis un compte qu'on vient de fermer ;
     *   · **un compte du PERSONNEL**, quand on ne détient pas `role.manage`.
     *     Sans cette borne, quiconque modère les citoyens pourrait fermer le
     *     compte d'un administrateur : la modération de contenu et
     *     l'administration des accès sont deux métiers, et la matrice les
     *     sépare déjà.
     *
     * Le service pose la même garde sur le cas « soi-même ». Le doublon est
     * voulu : la Policy protège l'écran, le service protège l'appel — et rien
     * ne dit que l'écran restera le seul appelant.
     */
    public function suspend(User $utilisateur, User $cible): bool
    {
        if (! $utilisateur->can('user.suspend') || $utilisateur->id === $cible->id) {
            return false;
        }

        /*
         | `getRoleNames()` lit la relation `roles` déjà chargée par le dépôt
         | DataTables (`DepotUtilisateursEloquent::pourTableau()`) — jamais
         | `$cible->roles()->...` : ce générateur de requête fraîche, appelé
         | une fois par ligne de la table, multipliait les requêtes avec la
         | taille de la page (un test le tient).
         */
        $cibleEstDuPersonnel = $cible->getRoleNames()->contains(fn (string $nom): bool => $nom !== UserRole::Citoyen->value);

        return ! $cibleEstDuPersonnel || $utilisateur->can('role.manage');
    }
}
