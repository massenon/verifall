<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotifMouvementUnite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une ligne du journal des unités.
 *
 * Écrite UNIQUEMENT par EconomyService, dans la même transaction que le solde.
 * Aucune mise à jour n'est prévue : un mouvement corrigé se corrige par un
 * mouvement inverse, comme en comptabilité. Réécrire l'histoire priverait le
 * journal de sa seule utilité.
 */
final class UnitLog extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'delta', 'balance_after',
        'reason', 'label', 'source_type', 'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'balance_after' => 'integer',
            'reason' => MotifMouvementUnite::class,
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function estCredit(): bool
    {
        return $this->delta > 0;
    }

    /** Référence courte affichée dans le tableau — « T-000123 ». */
    public function reference(): string
    {
        return 'T-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Montant signé, formaté — « +25 » ou « −3 ». */
    public function montantAffiche(): string
    {
        return ($this->estCredit() ? '+' : '−').abs($this->delta);
    }

    /**
     * Le libellé à AFFICHER, traduit — le stocké ne l'est pas.
     *
     * `label` est écrit une fois pour toutes dans la langue de repli, parce
     * qu'une colonne cherchée en `LIKE` et exportée en CSV ne peut pas changer
     * de langue selon le lecteur. La traduction se fait donc ici, au moment de
     * l'affichage, à partir de `reason`.
     *
     * DEUX CAS, ET ILS SE DISTINGUENT DE FAÇON SÛRE. Quand le libellé stocké
     * est exactement celui qu'aurait produit le motif, il a été engendré
     * automatiquement : on peut le traduire sans rien perdre. Quand il en
     * diffère, un appelant l'a fourni — le motif d'un ajustement administrateur,
     * par exemple — et ce texte-là est une donnée, pas un gabarit : le
     * traduire reviendrait à réécrire ce que quelqu'un a saisi.
     */
    public function libelleAffiche(): string
    {
        // `reason` est DÉJÀ casté en enum par le modèle. Le forcer en chaîne
        // au passage lançait une erreur de conversion, et sur la vue plutôt
        // qu'ici — l'endroit où l'on cherche en dernier.
        $motif = $this->reason instanceof MotifMouvementUnite
            ? $this->reason
            : MotifMouvementUnite::tryFrom((string) $this->reason);

        if ($motif === null) {
            return (string) $this->label;
        }

        return $this->label === $motif->libelleCanonique()
            ? $motif->libelle()
            : (string) $this->label;
    }
}
