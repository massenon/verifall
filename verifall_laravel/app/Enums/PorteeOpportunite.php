<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Une opportunité est-elle béninoise ou internationale ?
 *
 * La distinction n'est pas décorative : quelqu'un qui cherche un emploi à
 * Cotonou et quelqu'un qui cherche une bourse à l'étranger ne lisent pas la
 * même liste. Les mélanger oblige chacun à trier ce qui ne le concerne pas.
 */
enum PorteeOpportunite: string
{
    case Benin = 'benin';
    case International = 'international';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('types.portee_opportunite.'.$this->value);
    }

    /** Ancre de la section, pour la navigation en tête de page. */
    public function ancre(): string
    {
        return match ($this) {
            self::Benin => 'opportunites-benin',
            self::International => 'opportunites-internationales',
        };
    }
}
