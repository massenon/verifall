<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NiveauCours;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un cours de l'Académie.
 */
final class Course extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'slug', 'title', 'description', 'category', 'course_category_id', 'level',
        'duration_label', 'cover_path', 'cost_units', 'outcomes', 'prerequisites',
        'language', 'xp_reward', 'meta_title', 'meta_description', 'meta_keywords',
        'instructor_id', 'delivers_certificate',
        'is_published', 'published_at', 'free_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => NiveauCours::class,
            'outcomes' => 'array',
            'prerequisites' => 'array',
            'xp_reward' => 'integer',
            'cost_units' => 'integer',
            'rating' => 'float',
            'reviews_count' => 'integer',
            'students_count' => 'integer',
            'delivers_certificate' => 'boolean',
            'is_published' => 'boolean',
            // Hors $fillable, comme `alerted_at` : c'est le job d'annonce qui
            // l'horodate, jamais un formulaire.
            'announced_at' => 'datetime',
            'published_at' => 'datetime',
            'free_until' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * La catégorie, depuis le lot 6.
     *
     * `category` — la colonne texte — reste la valeur que lisent le catalogue
     * citoyen et l'API v1. Elle est tenue à jour depuis cette relation à chaque
     * écriture du back-office, et disparaîtra quand ces trois lecteurs auront
     * basculé. Écrire dans l'une sans l'autre les ferait diverger en silence.
     *
     * @return BelongsTo<CourseCategory, $this>
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    /**
     * @return HasMany<Quiz, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class)->orderBy('position');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('position');
    }

    /**
     * Les certificats délivrés pour ce cours.
     *
     * Utilisée par l'écran de statistiques, qui écarte les révoqués : un
     * certificat retiré ne prouve plus rien, et le compter gonflerait le seul
     * chiffre censé mesurer l'aboutissement.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Ce que ce cours coûte AUJOURD'HUI.
     *
     * Le « cours offert du mois » est une date de fin, pas un drapeau : passée
     * l'échéance le tarif revient de lui-même. Un booléen qu'il faut penser à
     * retirer resterait allumé, et le cours resterait gratuit indéfiniment.
     */
    public function coutActuel(): int
    {
        if ($this->estOffert()) {
            return 0;
        }

        return $this->cost_units;
    }

    /** Offert en ce moment — indépendamment de son prix affiché. */
    public function estOffert(): bool
    {
        return $this->free_until !== null && $this->free_until->isFuture();
    }

    public function estGratuit(): bool
    {
        return $this->coutActuel() === 0;
    }

    /**
     * Nombre d'étapes à franchir pour terminer.
     *
     * Leçons, quiz et exercices comptent CHACUN pour une étape, comme dans le
     * prototype. Pondérer les quiz plus lourd que les leçons donnerait un
     * avancement plus « juste » sur le papier, et illisible à l'écran : la
     * barre avancerait de sauts inégaux sans que personne comprenne pourquoi.
     */
    public function nombreDEtapes(): int
    {
        return $this->lessons()->count()
            + $this->quizzes()->count()
            + $this->assignments()->count();
    }
}
