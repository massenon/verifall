<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une session en direct. */
final class LiveClass extends Model
{
    protected $fillable = [
        'uuid', 'course_id', 'host_id', 'title', 'description',
        'starts_at', 'minutes', 'join_url', 'seats', 'is_published',
        'replay_url', 'replay_published_at', 'recording_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'minutes' => 'integer',
            'seats' => 'integer',
            'is_published' => 'boolean',
            'replay_published_at' => 'datetime',
            'recording_minutes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return HasMany<LiveClassRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(LiveClassRegistration::class);
    }

    public function estPassee(): bool
    {
        return $this->starts_at->addMinutes($this->minutes)->isPast();
    }

    /**
     * `upcoming` · `live` · `past` — DÉDUITS de l'heure, jamais stockés.
     *
     * Une colonne d'état mentirait dès le lendemain si personne ne repassait
     * derrière : une séance enregistrée « planifiée » resterait planifiée un
     * dimanche soir. C'est le genre d'écran qui se trompe tout seul.
     *
     * La formule vit ICI et nulle part ailleurs : l'écran web, l'écran mobile
     * et le filtre `?state=` de l'API l'employaient chacun de leur côté, et
     * trois copies d'une même règle finissent par ne plus dire la même chose.
     */
    public function etat(): string
    {
        if ($this->estPassee()) {
            return 'past';
        }

        return $this->starts_at->isPast() ? 'live' : 'upcoming';
    }

    /**
     * Le lien n'est donné qu'à l'approche de la séance.
     *
     * Un lien de visioconférence qui circule des semaines à l'avance finit
     * ailleurs que chez les inscrits.
     */
    public function lienDisponible(): bool
    {
        return ! $this->estPassee() && $this->starts_at->subMinutes(15)->isPast();
    }

    /**
     * La rediffusion est-elle regardable ?
     *
     * Trois conditions, et chacune écarte un cas réel :
     *
     *  · une adresse, sinon il n'y a rien à ouvrir — c'était l'état de TOUTES
     *    les séances avant cette version, d'où le bouton qui ramenait à la
     *    liste des tutoriels (B-06) ;
     *  · une date de publication ATTEINTE, pour qu'une séance enregistrée le
     *    mardi puisse n'être diffusée que le jeudi, une fois relue ;
     *  · la séance terminée, parce qu'une « rediffusion » d'une séance qui n'a
     *    pas encore eu lieu n'a pas de sens et signalerait une erreur de saisie.
     */
    public function rediffusionDisponible(): bool
    {
        return $this->replay_url !== null
            && $this->replay_url !== ''
            && $this->replay_published_at !== null
            && $this->replay_published_at->isPast()
            && $this->estPassee();
    }

    /**
     * Qui peut regarder la rediffusion.
     *
     * **Tout inscrit au cours**, et pas seulement les inscrits à la séance.
     * Arbitré au lot 1, et le contraire se défendait : les places sont
     * limitées, on pourrait vouloir réserver l'enregistrement à ceux qui
     * s'étaient inscrits.
     *
     * Ce serait pourtant punir la bonne raison de ne pas être venu. Les places
     * limitées bornent une salle en direct, pas l'accès à un cours qu'on a
     * commencé — et quelqu'un qui découvre le cours trois semaines plus tard
     * n'avait par définition aucun moyen de s'inscrire à la séance.
     *
     * Une séance HORS COURS (`course_id` nul) est ouverte à tout compte actif :
     * elle n'est rattachée à rien qui puisse borner l'accès.
     */
    public function rediffusionVisiblePar(?User $utilisateur): bool
    {
        if ($utilisateur === null || ! $this->rediffusionDisponible()) {
            return false;
        }

        if ($this->course_id === null) {
            return true;
        }

        return Enrollment::query()
            ->where('user_id', $utilisateur->id)
            ->where('course_id', $this->course_id)
            ->exists();
    }
}
