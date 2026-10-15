<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est un dossier de vérification d'identité.
 */
enum StatutDossierKyc: string
{
    case EnAttente = 'pending';
    case Approuve = 'approved';
    case Refuse = 'rejected';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente d’examen',
            self::Approuve => 'Identité vérifiée',
            self::Refuse => 'Refusé',
        };
    }

    /**
     * Ce que la personne lit sur son profil.
     *
     * Le refus dit qu'on peut REDÉPOSER : un refus qui ressemble à une porte
     * fermée fait abandonner alors qu'il s'agit le plus souvent d'une photo
     * illisible.
     */
    public function messageAuCitoyen(): string
    {
        return match ($this) {
            self::EnAttente => 'Votre dossier est en cours d’examen. Vous serez prévenu de la décision.',
            self::Approuve => 'Votre identité est vérifiée.',
            self::Refuse => 'Votre dossier a été refusé. Vous pouvez en déposer un nouveau.',
        };
    }

    public function estOuvert(): bool
    {
        return $this === self::EnAttente;
    }

    /** Classe de pastille, prise dans `staff.css`. Aucune classe nouvelle. */
    public function pastille(): string
    {
        return match ($this) {
            self::EnAttente => 'st-badge st-badge-warn',
            self::Approuve => 'st-badge st-badge-pub',
            self::Refuse => 'st-badge st-badge-danger',
        };
    }
}
