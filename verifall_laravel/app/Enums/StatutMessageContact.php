<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est un message reçu par le formulaire de contact.
 *
 * Un message se TRAITE, il ne se supprime pas. Quelqu'un qui écrit à une
 * plateforme de fact-checking signale parfois une intox : effacer la trace,
 * c'est perdre le signalement et l'impossibilité de dire ce qui en a été fait.
 */
enum StatutMessageContact: string
{
    case Nouveau = 'new';
    case EnCours = 'in_progress';
    case Traite = 'handled';
    case Indesirable = 'spam';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.message_contact.'.$this->value);
    }

    /**
     * Clé sémantique pour `<x-badge-statut>` — jamais une classe CSS.
     *
     * Passer la classe depuis la vue ferait dériver le vocabulaire d'un écran
     * à l'autre : c'est la raison d'être du composant.
     */
    public function cleBadge(): string
    {
        return match ($this) {
            self::Nouveau => 'danger',
            self::EnCours => 'en-cours',
            self::Traite => 'publie',
            self::Indesirable => 'archive',
        };
    }

    /** @return list<self> */
    public static function ouverts(): array
    {
        return [self::Nouveau, self::EnCours];
    }
}
