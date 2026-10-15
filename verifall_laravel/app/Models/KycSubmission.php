<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutDossierKyc;
use App\Enums\TypePieceIdentite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un dossier de vérification d'identité.
 *
 * ## Ce que ce modèle ne porte JAMAIS
 *
 * Le numéro de pièce en clair. `document_number_hash` est un HMAC, comparable
 * mais pas relisible : il sert uniquement à reconnaître qu'une même pièce est
 * présentée par deux comptes. Conserver les numéros produirait un fichier de
 * pièces d'identité que rien n'oblige à détenir.
 *
 * ## Les pièces s'effacent, le dossier reste
 *
 * Les trois chemins sont sur le disque PRIVÉ et passent à `null` à la purge,
 * `purged_at` faisant foi. Ce qui subsiste — type, haché, décision, motif,
 * examinateur, date — **prouve que le contrôle a eu lieu** sans conserver le
 * scan. C'est la seule façon d'être à la fois auditable et défendable en cas
 * de fuite.
 */
final class KycSubmission extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'document_type', 'document_number_hash',
        'front_path', 'back_path', 'selfie_path',
        'status', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'purged_at',
    ];

    /**
     * Les chemins sont CACHÉS de toute sérialisation.
     *
     * Sans cela, un `toArray()` — une ressource d'API, un `dd()`, un journal
     * d'exception — recracherait l'emplacement exact des scans de pièces
     * d'identité. Ils ne servent qu'au contrôleur de téléchargement.
     *
     * @var list<string>
     */
    protected $hidden = ['front_path', 'back_path', 'selfie_path', 'document_number_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => TypePieceIdentite::class,
            'status' => StatutDossierKyc::class,
            'reviewed_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function citoyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function examinateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Les pièces sont-elles encore sur le disque ? */
    public function piecesDisponibles(): bool
    {
        return $this->purged_at === null && $this->front_path !== null;
    }

    /**
     * Les pièces déposées, par rôle, pour l'écran d'examen.
     *
     * @return array<string, string> rôle => chemin
     */
    public function pieces(): array
    {
        return array_filter([
            'recto' => $this->front_path,
            'verso' => $this->back_path,
            'selfie' => $this->selfie_path,
        ], static fn (?string $chemin): bool => $chemin !== null && $chemin !== '');
    }

    /**
     * @param  Builder<KycSubmission>  $requete
     * @return Builder<KycSubmission>
     */
    public function scopeEnAttente(Builder $requete): Builder
    {
        return $requete->where('status', StatutDossierKyc::EnAttente->value);
    }
}
