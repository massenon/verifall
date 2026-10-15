<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ce que la communauté PENSE d'un signalement.
 *
 * À ne jamais confondre avec `VerdictFactCheck`, qui est ce que la plateforme
 * ÉTABLIT. Une majorité qui se trompe reste une majorité qui se trompe — c'est
 * même le mécanisme central de la désinformation. Deux enums distincts pour
 * que le code ne puisse pas les échanger par inadvertance.
 */
enum AvisCommunaute: string
{
    case Vrai = 'true';
    case Doute = 'doubt';
    case Faux = 'false';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('analyse.avis_communaute.'.$this->value);
    }

    /** Teinte de la barre — celles du prototype, reprises telles quelles. */
    public function couleur(): string
    {
        return match ($this) {
            self::Vrai => '#16A34A',
            self::Doute => '#D97706',
            self::Faux => '#DC2626',
        };
    }

    /** Colonne de compteur correspondante sur `threads`. */
    public function colonne(): string
    {
        return match ($this) {
            self::Vrai => 'votes_true',
            self::Doute => 'votes_doubt',
            self::Faux => 'votes_false',
        };
    }
}
