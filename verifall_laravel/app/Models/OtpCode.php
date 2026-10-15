<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Code à usage unique.
 *
 * Modèle volontairement pauvre : toute la logique vit dans OtpService. Il ne
 * porte aucun accesseur qui exposerait le code, celui-ci n'existant en clair
 * qu'au moment de son émission.
 *
 * @property string $identity_hash
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class OtpCode extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'identity_hash',
        'purpose',
        'code_hash',
        'expires_at',
        'ip_hash',
    ];

    /**
     * consumed_at et attempts sont hors $fillable : seul le service les écrit,
     * via forceFill ou increment. Un appelant ne doit pas pouvoir créer un
     * code déjà consommé, ni remettre le compteur de tentatives à zéro.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function estUtilisable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
