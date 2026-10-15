<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FournisseurSocial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une identité sociale rattachée à un compte. */
final class SocialAccount extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'provider', 'provider_user_id',
        'email', 'avatar_url', 'email_verified_by_provider',
        'linked_at', 'last_login_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => FournisseurSocial::class,
            'email_verified_by_provider' => 'boolean',
            'linked_at' => 'datetime',
            'last_login_at' => 'datetime',
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
}
