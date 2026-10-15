<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une famille de sources — « agences de presse », « institutions »… */
final class SourceCategory extends Model
{
    protected $fillable = [
        'uuid', 'name', 'slug', 'description', 'default_credibility', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_credibility' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<TrustedSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(TrustedSource::class);
    }
}
