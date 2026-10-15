<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Niveau d'un cours — les trois paliers du prototype.
 *
 * Stocké en anglais, affiché en français : le jour où l'Académie sera traduite,
 * c'est le libellé qui changera, pas la base ni les URL de filtre.
 */
enum NiveauCours: string
{
    case Debutant = 'beginner';
    case Intermediaire = 'intermediate';
    case Avance = 'advanced';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('economie.niveau_cours.'.$this->value);
    }
}
