<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est une note de communauté.
 *
 * Trois états, et la distinction entre les deux premiers est TOUT le sujet :
 * une note qui manque de votes n'est pas une note rejetée. Les confondre
 * ferait passer un silence pour un jugement, et découragerait exactement les
 * gens qu'on veut voir écrire des notes.
 */
enum StatutNoteCommunaute: string
{
    /** Pas encore assez de votes, ou pont non mesurable. */
    case EnAttente = 'pending';

    /** A franchi le pont : jugée utile par des gens qui ne s'accordent pas. */
    case Publiee = 'published';

    /** Évaluée par un nombre suffisant de personnes, et jugée non utile. */
    case NonRetenue = 'not_helpful';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.note_communaute.libelle.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::EnAttente => 'vbadge vbadge-progress',
            self::Publiee => 'vbadge vbadge-true',
            self::NonRetenue => 'vbadge vbadge-doubt',
        };
    }

    /**
     * Ce qu'on dit à l'auteur, et à lui seul.
     *
     * Le texte de l'attente ne promet rien et n'accuse de rien : il décrit
     * l'état réel du mécanisme. « En cours d'examen » laisserait croire à une
     * décision humaine imminente, qui n'existe pas ici.
     */
    public function explication(): string
    {
        return (string) __('statuts.note_communaute.explication.'.$this->value);
    }
}
