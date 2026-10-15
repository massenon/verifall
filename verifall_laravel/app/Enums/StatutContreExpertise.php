<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est une demande de contre-expertise.
 *
 * ## Quatre états, et pourquoi pas trois
 *
 * On serait tenté de s'arrêter à « en attente / traitée ». Ce serait perdre la
 * seule information que la personne attend vraiment : **avions-nous tort ?**
 *
 * `VerdictMaintenu` et `VerdictCorrige` sont donc deux issues distinctes, et
 * c'est délibérément inconfortable. Une plateforme de fact-checking qui ne sait
 * pas compter ses propres corrections n'a aucun moyen de dire si sa méthode
 * s'améliore — et le journal public des corrections
 * (`docs/…/TRANSPARENCE`) tire sa crédibilité de ce comptage.
 */
enum StatutContreExpertise: string
{
    case EnAttente = 'pending';

    /** Examinée : le verdict d'origine tient. */
    case VerdictMaintenu = 'upheld';

    /** Examinée : nous nous étions trompés, le rapport a été repris. */
    case VerdictCorrige = 'corrected';

    /**
     * Écartée sans examen de fond — demande vide, hors sujet, ou doublon.
     *
     * Distinct de `VerdictMaintenu` : « nous avons regardé et nous maintenons »
     * n'est pas « nous n'avons pas regardé ». Les confondre gonflerait
     * artificiellement le taux de verdicts confirmés.
     */
    case Ecartee = 'dismissed';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::VerdictMaintenu => 'Verdict maintenu',
            self::VerdictCorrige => 'Verdict corrigé',
            self::Ecartee => 'Écartée',
        };
    }

    /** Ce que la personne qui a demandé doit lire, sans jargon. */
    public function messageAuDemandeur(): string
    {
        return match ($this) {
            self::EnAttente => 'Votre demande est enregistrée : un journaliste va reprendre ce rapport.',
            self::VerdictMaintenu => 'Un journaliste a repris le dossier et confirme le verdict.',
            self::VerdictCorrige => 'Vous aviez raison : le rapport a été corrigé.',
            self::Ecartee => 'Votre demande n’a pas pu être examinée en l’état.',
        };
    }

    /** La demande attend-elle encore une décision humaine ? */
    public function estOuverte(): bool
    {
        return $this === self::EnAttente;
    }

    /** Classe de pastille, prise dans `staff.css`. Aucune classe nouvelle. */
    public function pastille(): string
    {
        return match ($this) {
            self::EnAttente => 'st-badge st-badge-warn',
            self::VerdictMaintenu => 'st-badge st-badge-info',
            self::VerdictCorrige => 'st-badge st-badge-danger',
            self::Ecartee => 'st-badge st-badge-arch',
        };
    }
}
