<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est un signalement de contenu.
 *
 * Un signalement se TRAITE, il ne se supprime pas : effacer la trace, c'est
 * perdre le fait qu'une décision a été prise, et rendre impossible de
 * reconnaître un signaleur abusif.
 */
enum StatutSignalementContenu: string
{
    case EnAttente = 'pending';
    case Retenu = 'upheld';
    case Rejete = 'dismissed';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.signalement_contenu.'.$this->value);
    }

    /** Clé sémantique pour `<x-badge-statut>` côté back-office. */
    public function cleBadge(): string
    {
        return match ($this) {
            self::EnAttente => 'danger',
            self::Retenu => 'archive',
            self::Rejete => 'publie',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::EnAttente => 'vbadge vbadge-progress',
            self::Retenu => 'vbadge vbadge-false',
            self::Rejete => 'vbadge vbadge-true',
        };
    }
}
