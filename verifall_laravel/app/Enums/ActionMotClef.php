<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ce qu'un mot surveillé déclenche.
 *
 * Deux actions, et la distinction est le cœur du dispositif. Bloquer tout ce
 * qui inquiète transformerait le forum en mur : sur un site de fact-checking,
 * les mots d'une rumeur sont précisément ceux qu'on vient signaler. Ne rien
 * bloquer laisserait passer l'injure et l'appel à la violence.
 */
enum ActionMotClef: string
{
    /** Le message n'est pas publié. Réservé à ce qui n'a aucune raison d'être. */
    case Bloquer = 'block';

    /** Le message est publié ET signalé à la modération, qui tranche. */
    case Signaler = 'flag';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('moderation.action_mot_clef.libelle.'.$this->value);
    }

    public function cleBadge(): string
    {
        return match ($this) {
            self::Bloquer => 'danger',
            self::Signaler => 'en-cours',
        };
    }

    public function explication(): string
    {
        return (string) __('moderation.action_mot_clef.explication.'.$this->value);
    }
}
