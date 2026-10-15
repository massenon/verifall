<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\FactChecking\Services\DomaineDeLaSource;
use App\Enums\PositionPreuve;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une source consultée, telle qu'elle figure dans « Sources et preuves ».
 */
final class ReportEvidence extends Model
{
    /*
     | Table déclarée explicitement : « evidence » étant indénombrable en
     | anglais, Laravel en déduit `report_evidence` au singulier et ne trouve
     | pas la table. Le deviner coûte une erreur incompréhensible en production.
     */
    protected $table = 'report_evidences';

    protected $fillable = [
        'verification_id', 'claim_id', 'source_url', 'source_name',
        'url_finale', 'url_canonique', 'domaine', 'editeur', 'role', 'pertinence',
        'snippet', 'stance', 'reliability', 'published_at', 'fetched',
        'recupere_le', 'valide_du', 'valide_au',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stance' => PositionPreuve::class,
            'reliability' => 'integer',
            'pertinence' => 'integer',
            'fetched' => 'boolean',
            'published_at' => 'datetime',
            'recupere_le' => 'datetime',
            'valide_du' => 'date',
            'valide_au' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    /**
     * @return BelongsTo<Claim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * Domaine affiché à côté du nom — le lecteur doit voir d'où ça vient.
     *
     * Passe par {@see DomaineDeLaSource} et non par un `parse_url()` nu : les
     * sources que Gemini rapporte portent une adresse de REDIRECTEUR, et
     * l'hôte brut affichait « vertexaisearch.cloud.google.com » sous chacune
     * d'elles. Vingt cartes identiques ne disent pas d'où vient la preuve — et
     * sur le rapport imprimé, où l'adresse est écrite en toutes lettres pour
     * pouvoir être retrouvée, elles ne prouvent rien du tout.
     */
    /**
     * Cette source a-t-elle été OUVERTE, ou seulement citée ?
     *
     * Un moteur sans accès web nomme des domaines de mémoire. C'est parfois
     * utile — citer `travail.gouv.bj` comme canal légitime rend service — mais
     * ce n'est pas un recoupement, et le rapport ne doit pas la compter comme
     * tel. Voir la migration `distinguer_les_preuves_recuperees`.
     */
    public function aEteOuverte(): bool
    {
        // `?? true` : les lignes antérieures à la colonne viennent de moteurs
        // qui récupéraient réellement.
        return (bool) ($this->fetched ?? true);
    }

    /**
     * Le domaine à AFFICHER à côté du nom.
     *
     * Phase 5 : la colonne `domaine` (éditeur ENREGISTRABLE, calculé une
     * fois à l'écriture) prime désormais sur le calcul à la volée — c'est le
     * même domaine, et le relire coûte moins cher que le recalculer à
     * chaque affichage. Les lignes antérieures à la phase 5, où la colonne
     * est `NULL`, retombent sur l'ancien calcul direct.
     */
    public function domaineAffiche(): string
    {
        return $this->domaine ?? app(DomaineDeLaSource::class)->pour($this->source_url, $this->source_name);
    }
}
