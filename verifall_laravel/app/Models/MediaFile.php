<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Une image de la médiathèque. Le fichier est sur le disque privé, jamais en URL publique. */
final class MediaFile extends Model
{
    public const DISQUE = 'local';

    public const OCTETS_MAX = 4 * 1024 * 1024;

    /** Formats acceptés. Pas de SVG : un SVG est du code. */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    protected $fillable = ['uuid', 'user_id', 'chemin', 'nom_origine', 'mime', 'octets', 'largeur', 'hauteur', 'texte_alternatif'];

    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Lien temporaire (30 min) vers le fichier : la console le redemande à chaque rendu. */
    public function lienTemporaire(): string
    {
        return URL::temporarySignedRoute('staff.media.file', now()->addMinutes(30), ['media' => $this->uuid]);
    }
}
