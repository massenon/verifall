<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est quelqu'un dans un cours.
 *
 * Le quatrième état du catalogue — « pas inscrit » — n'est PAS une valeur :
 * c'est l'absence de ligne. Le stocker créerait des inscriptions fantômes pour
 * chaque personne qui a seulement regardé la fiche d'un cours, et fausserait
 * le nombre d'inscrits affiché.
 */
enum StatutInscription: string
{
    case Inscrit = 'enrolled';
    case EnCours = 'in_progress';
    case Termine = 'completed';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.inscription.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Inscrit => 'vbadge vbadge-progress',
            self::EnCours => 'vbadge vbadge-progress',
            self::Termine => 'vbadge vbadge-true',
        };
    }
}
