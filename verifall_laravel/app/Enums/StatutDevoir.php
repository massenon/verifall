<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Un exercice remis, puis corrigé par un mentor.
 *
 * « Remis » compte déjà dans la progression : quelqu'un qui a fait le travail
 * ne doit pas voir son avancement bloqué parce qu'un mentor n'a pas encore eu
 * le temps de le lire. La correction change la note, pas l'avancement.
 */
enum StatutDevoir: string
{
    case Remis = 'submitted';
    case Valide = 'passed';
    case ARevoir = 'needs_work';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.devoir.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Remis => 'vbadge vbadge-progress',
            self::Valide => 'vbadge vbadge-true',
            self::ARevoir => 'vbadge vbadge-doubt',
        };
    }

    /** Compte-t-il dans l'avancement du cours ? */
    public function compteDansLAvancement(): bool
    {
        return $this !== self::ARevoir;
    }
}
