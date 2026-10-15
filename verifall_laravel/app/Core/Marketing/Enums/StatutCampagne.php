<?php

declare(strict_types=1);

namespace App\Core\Marketing\Enums;

/**
 * Où en est une campagne d'e-mailing.
 *
 * Trois états, et le troisième — « en cours » — n'est pas décoratif : c'est
 * lui qui distingue un envoi INTERROMPU d'un envoi jamais commencé. Sans lui,
 * une campagne coupée en plein milieu se relit comme un brouillon, et l'équipe
 * la relance en croyant que personne n'a rien reçu.
 *
 * La table stocke des chaînes ('draft', 'sending', 'sent') parce qu'elle
 * existait avant cet enum ; le code, lui, ne manipule plus que des cas.
 */
enum StatutCampagne: string
{
    case Brouillon = 'draft';
    case EnCours = 'sending';
    case Envoyee = 'sent';

    public static function depuis(?string $valeur): self
    {
        return self::tryFrom((string) $valeur) ?? self::Brouillon;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnCours => 'Envoi en cours',
            self::Envoyee => 'Envoyée',
        };
    }

    /**
     * Clé de `<x-badge-statut>` — jamais une classe CSS, sinon le vocabulaire
     * dérive d'une page à l'autre.
     */
    public function pastille(): string
    {
        return match ($this) {
            self::Brouillon => 'brouillon',
            self::EnCours => 'en-cours',
            self::Envoyee => 'publie',
        };
    }
}
