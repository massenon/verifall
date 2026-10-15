<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie d'une recharge.
 *
 * Machine à états volontairement étroite : depuis `EnAttente` on ne peut aller
 * que vers `Reussi`, `Echoue` ou `Expire`, et depuis un état final nulle part.
 * C'est ce qui empêche un rappel tardif de « ré-réussir » un paiement déjà
 * abandonné — et donc de créditer deux fois.
 */
enum StatutPaiement: string
{
    case EnAttente = 'pending';
    case Reussi = 'succeeded';
    case Echoue = 'failed';
    case Expire = 'expired';

    public function estFinal(): bool
    {
        return $this !== self::EnAttente;
    }

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.paiement.'.$this->value);
    }

    /**
     * Classe de pastille du design system (vbadge).
     */
    public function badge(): string
    {
        return match ($this) {
            self::EnAttente => 'vbadge vbadge-progress',
            self::Reussi => 'vbadge vbadge-true',
            self::Echoue, self::Expire => 'vbadge vbadge-false',
        };
    }
}
