<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitPack;
use App\Models\User;

/**
 * Qui peut toucher à la grille tarifaire.
 *
 * Un pack n'appartient à personne : il n'y a donc pas de portée « les miens ».
 * Ce qui compte ici, c'est qu'une modification de prix soit un acte
 * d'administration — pas une case qu'un mentor coche en passant.
 */
final class UnitPackPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('unit_pack.view');
    }

    public function view(User $utilisateur, UnitPack $pack): bool
    {
        return $utilisateur->can('unit_pack.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('unit_pack.create');
    }

    public function update(User $utilisateur, UnitPack $pack): bool
    {
        return $utilisateur->can('unit_pack.update');
    }

    /**
     * Un pack déjà acheté ne se supprime pas.
     *
     * Effacer sa ligne rendrait illisibles les paiements qui le référencent :
     * on saurait qu'une somme a été encaissée, plus ce qu'elle achetait. Le
     * retrait de la vente se fait en désactivant, ce qui est réversible et
     * n'efface rien.
     */
    public function delete(User $utilisateur, UnitPack $pack): bool
    {
        return $utilisateur->can('unit_pack.delete')
            && ! $pack->payments()->exists();
    }
}
