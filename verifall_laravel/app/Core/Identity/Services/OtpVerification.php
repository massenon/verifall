<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

/**
 * Issue d'une vérification de code.
 *
 * Un enum plutôt qu'un booléen : l'appelant doit pouvoir journaliser
 * précisément (un rejeu n'est pas une faute de frappe) tout en affichant un
 * message générique à l'écran.
 */
enum OtpVerification: string
{
    case Valide = 'valide';
    case Invalide = 'invalide';
    case Expire = 'expire';
    case Absent = 'absent';
    case TropDeTentatives = 'trop_de_tentatives';

    public function estValide(): bool
    {
        return $this === self::Valide;
    }

    /**
     * Message destiné au citoyen.
     *
     * Volontairement peu bavard : dire « aucun code en attente » révélerait
     * qu'aucune demande n'a été faite pour cette adresse.
     */
    public function message(): string
    {
        return match ($this) {
            self::Valide => 'Code vérifié.',
            self::TropDeTentatives => 'Trop de tentatives. Demandez un nouveau code.',
            self::Expire => 'Ce code a expiré. Demandez-en un nouveau.',
            self::Invalide, self::Absent => 'Code incorrect. Vérifiez votre saisie.',
        };
    }

    /**
     * Sévérité pour le journal de sécurité.
     */
    public function severite(): string
    {
        return match ($this) {
            self::Valide => 'info',
            self::TropDeTentatives => 'critical',
            default => 'warning',
        };
    }
}
