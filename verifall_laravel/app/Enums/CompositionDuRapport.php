<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ce qu'un rapport DIT dans son ensemble — un axe distinct du verdict.
 *
 * Contrat V-8, verrouillé par NEWINSTRUCTION24 : « un seul FAUX suffit à
 * rendre le rapport TROMPEUR » est explicitement écartée. `MIXTE` n'est PAS
 * une cinquième valeur de {@see VerdictFactCheck} — c'est la FORME de ce que
 * contient le rapport, calculée séparément.
 *
 * `analysis_reports.verdict` reste nullable : `NULL` ne signifie jamais
 * `MIXTE`, il signifie qu'aucun verdict factuel unique ne s'applique. C'est
 * `composition_status` qui porte cette information.
 */
enum CompositionDuRapport: string
{
    /** Tous les claims tranchés portent le même verdict (ou il n'y en a qu'un). */
    case Homogene = 'homogene';

    /** Au moins deux verdicts tranchés et distincts coexistent. */
    case Mixte = 'mixte';

    /** Aucun claim n'a pu être tranché — tous non vérifiables, ou aucun claim factuel. */
    case Insuffisante = 'insuffisante';

    public function libelle(): string
    {
        return (string) __('analyse.composition.'.$this->value);
    }

    /**
     * La phrase affichée là où un verdict unique afficherait
     * {@see VerdictFactCheck::explication()} — jamais appelée pour
     * `Homogene`, qui reste l'affaire du verdict lui-même.
     */
    public function explication(): string
    {
        return (string) __('analyse.composition_explication.'.$this->value);
    }

    /** Même usage que {@see VerdictFactCheck::couleurBarre()}. */
    public function couleurBarre(): string
    {
        return match ($this) {
            self::Mixte => '#D97706',
            self::Insuffisante, self::Homogene => '#2563EB',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Homogene => '', // le badge vient alors du verdict lui-même
            self::Mixte => 'vbadge vbadge-doubt',
            self::Insuffisante => 'vbadge vbadge-progress',
        };
    }

    /**
     * Même usage que {@see VerdictFactCheck::badgePublic()}, pour les deux
     * seuls cas où CETTE classe décide du badge — jamais pour `Homogene`,
     * qui reste l'affaire du verdict lui-même.
     *
     * Classes Tailwind EXISTANTES uniquement (build figé) : `bg-vinvest` est
     * déjà compilée pour Trompeur, et sa teinte ambrée convient à « nuance,
     * ne pas trancher d'un mot » — en créer une neuve serait inerte.
     */
    public function badgePublic(): string
    {
        $base = 'inline-flex items-center rounded-full text-white font-bold px-4 py-1.5';

        return $base.' '.match ($this) {
            self::Mixte => 'bg-vinvest',
            self::Insuffisante, self::Homogene => 'bg-primary',
        };
    }

    /*
    |---------------------------------------------------------------------------
    | Le Hero du rapport — jamais appelées pour `Homogene`
    |---------------------------------------------------------------------------
    |
    | `components/rapport/corps.blade.php` branche UNE SEULE FOIS, en tête du
    | Hero, entre `$rapport->verdict` et `$rapport->composition_status` :
    | ensuite, le même nom de méthode s'appelle sur l'un ou sur l'autre sans
    | qu'aucun `@if` supplémentaire ne coure dans le gabarit. C'est pour CE
    | contrat que ces six méthodes existent, à l'identique de celles de
    | {@see VerdictFactCheck}.
    |
    | Classes CSS réemployées, pas créées : `MIXTE` prend le ton « avertit,
    | ne tranche pas d'un mot » de Trompeur ; `INSUFFISANTE` prend le ton
    | neutre de NonVerifiable. Aucune classe neuve, et donc aucun risque
    | qu'elle soit inerte dans un build figé.
    */

    public function carteRapport(): string
    {
        return match ($this) {
            self::Mixte => 'vf-report-hero-warning',
            self::Insuffisante, self::Homogene => 'vf-report-hero-neutral',
        };
    }

    public function badgeRapport(): string
    {
        return match ($this) {
            self::Mixte => 'vf-badge-warning',
            self::Insuffisante, self::Homogene => 'vf-badge-neutral',
        };
    }

    public function texteRapport(): string
    {
        return match ($this) {
            self::Mixte => 'vf-text-warning',
            self::Insuffisante, self::Homogene => 'vf-text-neutral',
        };
    }

    public function medaillon(): string
    {
        return match ($this) {
            self::Mixte => 'warning',
            self::Insuffisante, self::Homogene => 'help',
        };
    }

    public function libelleRapport(): string
    {
        return (string) __('analyse.composition_rapport.libelle.'.$this->value);
    }

    public function phraseRapport(): string
    {
        return (string) __('analyse.composition_rapport.phrase.'.$this->value);
    }
}
