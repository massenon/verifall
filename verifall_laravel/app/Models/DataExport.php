<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Archive de portabilité — droit d'accès du RGPD.
 *
 * Le fichier lui-même vit sur le disque PRIVÉ : il rassemble en un seul endroit
 * l'intégralité des données personnelles d'une personne, ce qui en fait l'objet
 * le plus sensible que produise l'application. Il n'est jamais servi
 * directement, seulement par une route signée et de courte durée.
 */
final class DataExport extends Model
{
    public const EN_ATTENTE = 'pending';

    public const PRET = 'ready';

    public const ECHEC = 'failed';

    protected $fillable = ['uuid', 'user_id', 'path', 'status', 'size_bytes', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estTelechargeable(): bool
    {
        return $this->status === self::PRET
            && $this->path !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Détruit le fichier ET la ligne.
     *
     * Les deux ensemble : une ligne sans fichier laisse croire à une archive
     * disponible, un fichier sans ligne devient un orphelin que plus rien ne
     * purge — et cet orphelin-là contient des données personnelles.
     */
    public function detruire(): void
    {
        if ($this->path !== null) {
            Storage::disk('local')->delete($this->path);
        }

        $this->delete();
    }
}
