<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutInscriptionExamen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'inscription d'un candidat à une session d'examen.
 *
 * Deux faits ne sont PAS des statuts, et se lisent sur des dates :
 * la convocation est partie (`convocation_sent_at`), le candidat s'est présenté
 * (`attended_at`). Les dupliquer dans l'énumération aurait donné deux sources
 * pour le même fait, et c'est le taux de présence affiché qui aurait menti.
 */
final class ExamRegistration extends Model
{
    protected $fillable = [
        'uuid', 'exam_session_id', 'user_id', 'status',
        'convocation_code', 'convocation_sent_at',
        'deferral_requested_at', 'deferral_reason', 'attended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutInscriptionExamen::class,
            'convocation_sent_at' => 'datetime',
            'deferral_requested_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convocationEnvoyee(): bool
    {
        return $this->convocation_sent_at !== null;
    }

    /** Ce que la colonne « Convocation » affiche. */
    public function libelleConvocation(): string
    {
        return $this->convocationEnvoyee() ? 'Envoyée' : 'À envoyer';
    }

    public function aEteVu(): bool
    {
        return $this->attended_at !== null;
    }
}
