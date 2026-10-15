<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NatureMessageContact;
use App\Enums\StatutMessageContact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un message reçu par le formulaire de contact public. */
final class ContactMessage extends Model
{
    protected $fillable = [
        'uuid', 'kind', 'name', 'email', 'phone', 'organisation', 'message',
        'donation_amount', 'donation_recurrence',
        'status', 'ip_hash', 'handled_by', 'handled_at', 'handling_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NatureMessageContact::class,
            'status' => StatutMessageContact::class,
            'handled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
