<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ce qu'une preuve dit de l'affirmation.
 */
enum PositionPreuve: string
{
    case Confirme = 'supports';
    case Contredit = 'refutes';
    case Neutre = 'neutral';

    /**
     * Deux moteurs ont noté cette preuve différemment.
     *
     * Son poids est divisé plutôt qu'annulé : un désaccord d'interprétation
     * n'invalide pas la source, il invite à s'en méfier.
     */
    case Contestee = 'contested';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('analyse.position_preuve.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Confirme => 'vbadge vbadge-true',
            self::Contredit => 'vbadge vbadge-false',
            self::Neutre => 'vbadge vbadge-progress',
            self::Contestee => 'vbadge vbadge-doubt',
        };
    }
}
