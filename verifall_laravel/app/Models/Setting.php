<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un réglage de la plateforme.
 *
 * Le modèle ne DÉCHIFFRE rien de lui-même : c'est le service `Reglages` qui
 * s'en charge, parce que lui seul sait, par le catalogue, si la valeur est
 * secrète. Un cast `encrypted` sur la colonne s'appliquerait à toutes les
 * lignes — y compris au nom du site, qui n'a rien à cacher et que l'on veut
 * pouvoir lire en base.
 */
final class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_secret', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
