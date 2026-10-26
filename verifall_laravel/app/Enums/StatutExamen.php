<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Le cycle de vie d'un examen certifiant.
 *
 * Trois états seulement, et volontairement : c'est la colonne « Statut » de
 * `examens.html`, et la barre d'actions groupées n'offre que « Publier » et
 * « Archiver ». Un quatrième état inventé ici serait un filtre qui ne renvoie
 * jamais rien.
 *
 * ARCHIVÉ n'est pas SUPPRIMÉ : un examen archivé garde ses copies, ses notes et
 * ses sessions passées. Le supprimer effacerait le relevé de gens qui l'ont
 * passé.
 */
enum StatutExamen: string
{
    case Brouillon = 'draft';
    case Publie = 'published';
    case Archive = 'archived';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.examen.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Brouillon => 'pastille p-neutre',
            self::Publie => 'pastille p-bon',
            self::Archive => 'pastille p-neutre',
        };
    }

    /** Un examen ne se planifie et ne se passe que publié. */
    public function ouvertALaPassation(): bool
    {
        return $this === self::Publie;
    }
}
