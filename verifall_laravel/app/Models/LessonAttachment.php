<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un document joint à une leçon : PDF, tableur, document, image.
 *
 * ## Le fichier vit sur le disque PRIVÉ
 *
 * Un cours PAYANT dont les PDF sont servis par une URL devinable est un cours
 * vendu une fois et diffusé cent. Le fichier ne transite donc que par une route
 * signée qui vérifie l'inscription — même dispositif que les pièces KYC, pour
 * une raison différente : là c'était la vie privée, ici c'est la valeur du cours.
 *
 * `path` est CACHÉ de toute sérialisation : un `toArray()` traîne partout —
 * ressource d'API, `dd()`, journal d'exception — et n'a pas à recracher
 * l'emplacement réel des supports payants.
 *
 * ## Pourquoi `original_name` est conservé
 *
 * Le fichier sur le disque porte un nom aléatoire. Sans le nom d'origine, la
 * liste afficherait des uuid et personne ne saurait lequel ouvrir. Il sert à
 * l'AFFICHAGE seulement, jamais à composer un chemin.
 */
final class LessonAttachment extends Model
{
    protected $fillable = [
        'uuid', 'lesson_id', 'path', 'original_name', 'mime', 'size_bytes', 'position',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lecon(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /**
     * L'icône Material qui dit d'un coup d'œil de quoi il s'agit.
     *
     * Sur le TYPE MIME et non sur l'extension du nom d'origine : une extension
     * se renomme, le type est celui que le serveur a constaté au dépôt.
     */
    public function icone(): string
    {
        return match (true) {
            str_contains($this->mime, 'pdf') => 'picture_as_pdf',
            str_contains($this->mime, 'sheet'), str_contains($this->mime, 'excel'), str_contains($this->mime, 'csv') => 'table_view',
            str_contains($this->mime, 'word'), str_contains($this->mime, 'document') => 'description',
            str_starts_with($this->mime, 'image/') => 'image',
            default => 'attach_file',
        };
    }

    /** Le poids, en unités lisibles. « 2 411 823 octets » ne dit rien à personne. */
    public function poidsLisible(): string
    {
        $octets = $this->size_bytes;
        $unites = ['o', 'Ko', 'Mo'];
        $rang = 0;

        while ($octets >= 1024 && $rang < count($unites) - 1) {
            $octets = (int) round($octets / 1024);
            $rang++;
        }

        return number_format($octets, 0, ',', ' ').' '.$unites[$rang];
    }
}
