<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Natures de consentement collectées (RGPD).
 *
 * Chaque type est recueilli SÉPARÉMENT : grouper les CGU et la newsletter dans
 * une seule case invaliderait le consentement marketing, qui doit être libre
 * et spécifique.
 */
enum ConsentType: string
{
    case Cgu = 'cgu';
    case Privacy = 'privacy';
    case Newsletter = 'newsletter';

    /**
     * Garder l'historique de mes consultations du Hub (arbitrage n° 178).
     * Facultatif, non coché par défaut, retirable à tout moment : le retrait
     * efface l'historique.
     */
    case HistoriqueHub = 'historique_hub';

    /**
     * Le consentement est-il indispensable à la création du compte ?
     *
     * Seul l'usage strictement nécessaire au service peut être obligatoire :
     * une newsletter obligatoire serait un consentement non libre, donc nul.
     */
    public function estObligatoire(): bool
    {
        return ! in_array($this, [self::Newsletter, self::HistoriqueHub], true);
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Cgu => "J'accepte les conditions générales d'utilisation",
            self::Privacy => "J'ai lu la politique de confidentialité",
            self::Newsletter => 'Je souhaite recevoir la lettre d\'information (facultatif)',
            self::HistoriqueHub => 'Garder l\'historique de mes lectures du Hub pendant 90 jours (facultatif)',
        };
    }

    /**
     * Version courante du texte présenté.
     *
     * Conservée avec chaque consentement : après une refonte des CGU, il faut
     * pouvoir dire à quelle version chaque citoyen a adhéré, et le cas échéant
     * en solliciter une nouvelle.
     */
    public function versionCourante(): string
    {
        return match ($this) {
            self::Cgu => '2026-01',
            self::Privacy => '2026-01',
            self::Newsletter => '2026-01',
            self::HistoriqueHub => '2026-10',
        };
    }
}
