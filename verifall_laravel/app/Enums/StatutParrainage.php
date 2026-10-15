<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est un parrainage.
 *
 * Trois états, ceux du prototype : le filleul s'est inscrit, il a fait l'action
 * éligible, la récompense est versée. Il n'existe pas d'état « invitation
 * envoyée » — un partage WhatsApp ne se constate pas depuis un serveur, et
 * l'inventer afficherait des filleuls qui n'existent pas.
 */
enum StatutParrainage: string
{
    case Inscrit = 'registered';
    case Eligible = 'qualified';
    case Recompense = 'rewarded';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.parrainage.libelle.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Inscrit => 'vbadge vbadge-progress',
            self::Eligible => 'vbadge vbadge-doubt',
            self::Recompense => 'vbadge vbadge-true',
        };
    }

    /**
     * La clé sémantique du badge, pour `<x-badge-statut>`.
     *
     * Une CLÉ et non une classe CSS : le composant partagé refuse qu'on lui
     * passe une classe depuis l'appelant, précisément pour que le vocabulaire
     * ne dérive pas d'un écran à l'autre. `badge()` ci-dessus rend les classes
     * du côté citoyen ; le back-office a les siennes.
     */
    public function cleBadge(): string
    {
        return match ($this) {
            self::Inscrit => 'en-cours',
            self::Eligible => 'info',
            self::Recompense => 'publie',
        };
    }

    /**
     * Ce qu'il reste à faire, dit au FILLEUL.
     *
     * Le pendant de `explication()`, qui s'adresse au parrain. Les deux
     * décrivent le même état et disent des choses différentes : le parrain
     * attend, le filleul a encore un geste à faire. Une seule phrase pour les
     * deux aurait laissé l'un des deux devant un texte qui ne le concerne pas.
     */
    public function explicationFilleul(): string
    {
        return (string) __('statuts.parrainage.explication_filleul.'.$this->value);
    }

    /** Ce qu'il reste à faire, dit à celui qui attend. */
    public function explication(): string
    {
        return (string) __('statuts.parrainage.explication.'.$this->value);
    }
}
