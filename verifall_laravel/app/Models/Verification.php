<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EtapePipeline;
use App\Enums\StatutVerification;
use App\Enums\ThematiqueVerification;
use App\Enums\TypeContenu;
use App\Observers\ObservateurVerification;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une demande de vérification.
 */
#[ObservedBy(ObservateurVerification::class)]
final class Verification extends Model
{
    /*
     | Suppression DOUCE : une vérification a été demandée par quelqu'un et lui a
     | coûté une unité. L'effacer franchement retirerait une ligne de son
     | historique sans qu'il puisse le constater autrement que par une absence.
     */
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'type', 'status', 'claim',
        'context', 'file_path', 'cost_units',
        'content_fingerprint', 'reused_from_id', 'guest_hash',
        'attempts', 'retry_after', 'retry_reason',
        'theme', 'assigned_to', 'assigned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeContenu::class,
            'status' => StatutVerification::class,
            'theme' => ThematiqueVerification::class,
            'assigned_at' => 'datetime',
            'cost_units' => 'integer',
            'pipeline_step' => 'integer',
            'attempts' => 'integer',
            'retry_after' => 'datetime',
            'refunded_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Le membre du personnel chargé de cette vérification.
     *
     * Distinct de `user()`, qui est la personne ayant DEMANDÉ l'analyse. La
     * colonne « Assigné à » du tableau de bord était décorative dans le
     * prototype : rien ne portait la prise en charge d'une analyse par un
     * journaliste.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * La personne qui a demandé l'analyse.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<AnalysisReport, $this>
     */
    public function report(): HasOne
    {
        return $this->hasOne(AnalysisReport::class);
    }

    /**
     * @return HasMany<EngineRun, $this>
     */
    public function engineRuns(): HasMany
    {
        return $this->hasMany(EngineRun::class);
    }

    /**
     * Les affirmations, questions, opinions ou prédictions extraites du
     * contenu soumis — ordonnées comme elles apparaissent dedans.
     *
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class)->orderBy('position');
    }

    /**
     * @return HasMany<ReportEvidence, $this>
     */
    public function evidences(): HasMany
    {
        return $this->hasMany(ReportEvidence::class);
    }

    /**
     * Analyse dont la réponse a été reprise, s'il y en a une.
     *
     * @return BelongsTo<Verification, $this>
     */
    public function reprisDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reused_from_id');
    }

    /** La réponse vient-elle d'une analyse antérieure ? */
    public function estUneReprise(): bool
    {
        return $this->reused_from_id !== null;
    }

    /**
     * Étape atteinte, si l'analyse a commencé.
     */
    public function etape(): ?EtapePipeline
    {
        // L'attribut peut manquer sur une instance fraichement creee : la
        // valeur par defaut vit en base, pas dans l'objet.
        return EtapePipeline::tryFrom((int) ($this->pipeline_step ?? 0));
    }

    /**
     * Avancement affiché, de 0 à 100.
     *
     * Une analyse terminée affiche 100 même si la dernière étape n'a pas été
     * inscrite — une barre bloquée à 83 % sur un rapport disponible ferait
     * croire à un incident.
     */
    public function avancement(): int
    {
        return $this->estTerminee() ? 100 : ($this->etape()?->pourcentage() ?? 0);
    }

    /** Une reprise est-elle programmée ? */
    public function attendUneReprise(): bool
    {
        return $this->status === StatutVerification::EnAttenteDeReprise;
    }

    /**
     * Minutes restantes avant la prochaine tentative.
     *
     * Au moins une : annoncer « dans 0 minute » alors que rien ne bouge serait
     * pire que de ne rien annoncer.
     */
    public function minutesAvantReprise(): int
    {
        if ($this->retry_after === null) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInMinutes($this->retry_after, absolute: false)));
    }

    /**
     * Une reprise est-elle déjà programmée POUR PLUS TARD ?
     *
     * Sert à reconnaître un job périmé : quand une relance manuelle a doublé la
     * reprise automatique, l'ancien job se réveille pour rien. L'exécuter
     * consommerait une tentative sans qu'aucun moteur n'ait eu le temps de se
     * remettre.
     */
    public function repriseProgrammeePlusTard(): bool
    {
        return $this->attendUneReprise()
            && $this->retry_after !== null
            && $this->retry_after->isAfter(now()->addSeconds(30));
    }

    /** La reprise peut-elle être relancée à la main dès maintenant ? */
    public function reprisePossible(): bool
    {
        return $this->attendUneReprise()
            && ($this->retry_after === null || $this->retry_after->isPast());
    }

    public function estTerminee(): bool
    {
        return $this->status->estFinal();
    }

    /**
     * Référence affichée — « VF-000123 ».
     *
     * Le préfixe était `V-` et les maquettes disaient `VF-` depuis l'origine.
     * Aligné le 25 septembre 2026, sur décision du porteur du projet : `V-` se
     * lit comme un numéro de version, `VF-` désigne Vérif'All.
     *
     * Le changement est sans risque de rupture : les vingt-quatre appelants
     * passent tous par cette méthode, aucun littéral `'V-'` n'est écrit
     * ailleurs, et rien n'INDEXE cette chaîne — les URL et les liaisons en base
     * emploient l'`uuid`. Les libellés déjà écrits dans le journal des unités
     * gardent leur ancienne forme : ce sont des écritures comptables passées,
     * qu'on ne réécrit pas.
     *
     * À savoir tout de même : `VF-` désigne AUSSI les numéros de certificat
     * dans les données reprises de l'ancienne base (`certificate_number` vaut
     * « VF-0001 »). Deux objets différents partagent donc le préfixe. Sans
     * conséquence fonctionnelle — rien ne cherche par référence — mais à
     * connaître avant de l'imprimer sur une pièce servant de preuve.
     */
    public function reference(): string
    {
        return 'VF-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Aperçu de l'affirmation, pour les listes. */
    public function apercu(int $longueur = 90): string
    {
        return mb_strimwidth(trim($this->claim), 0, $longueur, '…');
    }
}
