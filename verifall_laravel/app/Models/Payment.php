<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperateurMobileMoney;
use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande de recharge Mobile Money.
 *
 * Le montant et le nombre d'unités sont figés à l'initiation, DEPUIS LA
 * CONFIGURATION SERVEUR. Ils ne sont jamais relus du client au moment de
 * créditer : sinon il suffirait de rejouer la requête avec un autre nombre
 * pour se créditer ce qu'on veut.
 */
final class Payment extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'provider', 'pack',
        'amount_fcfa', 'units', 'status', 'provider_ref',
        'phone_encrypted', 'failure_reason', 'settled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutPaiement::class,
            'provider' => OperateurMobileMoney::class,
            'amount_fcfa' => 'integer',
            'units' => 'integer',
            'settled_at' => 'datetime',

            // Donnée personnelle : chiffrée au repos, comme le téléphone du
            // profil. Une fuite de la base ne livre alors aucun numéro.
            'phone_encrypted' => 'encrypted',
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

    public function estEnAttente(): bool
    {
        return $this->status === StatutPaiement::EnAttente;
    }

    /**
     * L'attente a-t-elle dépassé le délai raisonnable ?
     *
     * Au-delà, l'écran cesse de tourner et propose de réessayer : une roue qui
     * tourne indéfiniment laisse croire que le paiement va aboutir.
     */
    public function aExpire(): bool
    {
        return $this->estEnAttente()
            && $this->created_at !== null
            && $this->created_at->addMinutes((int) config('economy.momo.timeout_minutes', 5))->isPast();
    }

    /** Numéro masqué pour l'affichage — « 97 ** ** 12 ». */
    public function numeroMasque(): string
    {
        $numero = preg_replace('/\D+/', '', (string) $this->phone_encrypted) ?? '';

        if (mb_strlen($numero) < 4) {
            return '—';
        }

        return mb_substr($numero, 0, 2).' ** ** '.mb_substr($numero, -2);
    }
}
