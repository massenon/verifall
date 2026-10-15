<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un portail officiel référencé.
 *
 * `is_official` est un drapeau EXPLICITE, jamais déduit du nom de domaine :
 * « gouv-benin.info » n'est pas « gouv.bj », et c'est précisément le piège
 * qu'on apprend aux gens à repérer. L'inférer en code reviendrait à commettre
 * l'erreur que la plateforme enseigne à éviter.
 */
final class HubPortal extends Model
{
    protected $fillable = [
        'uuid', 'name', 'url', 'category', 'description',
        'logo_path', 'is_official', 'position', 'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
