<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les périodes proposées par le filtre global du tableau de bord.
 *
 * La liste est celle de la directive de refonte (§7) et elle est CLOSE : une
 * valeur inconnue arrivée par l'adresse (`?periode=n-importe-quoi`) retombe sur
 * le défaut au lieu de produire une fenêtre de calcul imprévue.
 */
enum PeriodeTableauDeBord: string
{
    case Aujourdhui = 'today';
    case SeptJours = '7d';
    case TrenteJours = '30d';
    case QuatreVingtDixJours = '90d';
    case CetteAnnee = 'year';
    case Personnalisee = 'custom';

    /** Le défaut : assez long pour lisser un creux, assez court pour rester actuel. */
    public const DEFAUT = self::TrenteJours;

    public function libelle(): string
    {
        return match ($this) {
            self::Aujourdhui => "Aujourd'hui",
            self::SeptJours => '7 derniers jours',
            self::TrenteJours => '30 derniers jours',
            self::QuatreVingtDixJours => '90 derniers jours',
            self::CetteAnnee => 'Cette année',
            self::Personnalisee => 'Personnalisée',
        };
    }
}
