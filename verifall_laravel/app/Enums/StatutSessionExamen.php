<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * L'état d'une session d'examen planifiée.
 *
 * `examens.html` compte « 2 sessions ouvertes » : c'est cet état, et rien
 * d'autre, qui répond à ce chiffre.
 *
 * ANNULÉE et non supprimée : les gens déjà convoqués doivent pouvoir lire
 * pourquoi leur session n'a pas lieu. Une ligne effacée ne dit rien à personne.
 */
enum StatutSessionExamen: string
{
    case Planifiee = 'planned';
    case Ouverte = 'open';
    case Fermee = 'closed';
    case Annulee = 'cancelled';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.session_examen.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Planifiee => 'pastille p-info',
            self::Ouverte => 'pastille p-bon',
            self::Fermee => 'pastille p-neutre',
            self::Annulee => 'pastille p-critique',
        };
    }

    /** On ne s'inscrit qu'à une session qui n'est ni close ni annulée. */
    public function accepteDesInscriptions(): bool
    {
        return $this === self::Planifiee || $this === self::Ouverte;
    }

    /** On ne compose que dans une session ouverte par un surveillant. */
    public function accepteLaComposition(): bool
    {
        return $this === self::Ouverte;
    }
}
