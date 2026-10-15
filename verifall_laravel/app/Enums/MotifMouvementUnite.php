<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pourquoi le solde a bougé.
 *
 * Un enum et non une chaîne libre : ces motifs sont écrits dans un journal
 * comptable que l'on relira pour expliquer un solde à quelqu'un qui le
 * conteste. Une variante orthographique y créerait une catégorie fantôme.
 */
enum MotifMouvementUnite: string
{
    case Recharge = 'recharge';

    /*
     | Le bonus offert À LA CRÉATION du compte, une seule fois.
     |
     | Il manquait, et son absence expliquait le sentiment d'une inscription
     | « pas finie » : on terminait les quatre étapes, on arrivait au tableau de
     | bord avec ZÉRO unité, et l'on ne pouvait vérifier quoi que ce soit. Le
     | réglage « Bonus de bienvenue » existait pourtant depuis des mois — lu par
     | personne.
     |
     | Distinct de la dotation mensuelle : celle-ci revient chaque mois, celui-là
     | ne s'accorde qu'une fois. Les confondre rendrait le journal comptable
     | incapable de répondre à « pourquoi ce compte a-t-il reçu deux fois ? ».
     */
    case Bienvenue = 'welcome';

    case DotationMensuelle = 'monthly_grant';
    case Verification = 'verification';
    case CoursPremium = 'premium_course';
    case Parrainage = 'referral';
    case AjustementAdmin = 'admin_adjustment';
    case Remboursement = 'refund';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('economie.motif.'.$this->value);
    }

    /**
     * Le libellé dans UNE SEULE langue, celle du repli, quelle que soit la
     * langue de la personne connectée.
     *
     * C'est celui qu'on ÉCRIT en base. `unit_logs.label` est une colonne
     * persistée, cherchée en `LIKE` et exportée en CSV : y écrire la langue de
     * l'écran donnerait un journal comptable panaché — mêmes opérations,
     * libellés en deux langues selon l'humeur du navigateur du jour — et la
     * recherche « Recharge » cesserait de trouver les lignes écrites en
     * anglais.
     *
     * Ce qui identifie une ligne reste `reason`, qui porte la valeur d'enum et
     * ne bouge jamais. Le libellé stocké n'est qu'une commodité de relecture ;
     * c'est `libelle()` qui traduit, À L'AFFICHAGE.
     */
    public function libelleCanonique(): string
    {
        return (string) __(
            'economie.motif.'.$this->value,
            [],
            (string) config('app.fallback_locale', 'fr'),
        );
    }

    /**
     * Ce motif crédite-t-il, par nature ?
     *
     * Sert à valider la cohérence d'un mouvement — un débit de « recharge »
     * signalerait une erreur d'appel, pas une situation métier.
     */
    public function crediteParNature(): bool
    {
        return match ($this) {
            self::Recharge, self::Bienvenue, self::DotationMensuelle, self::Parrainage, self::Remboursement => true,
            self::Verification, self::CoursPremium => false,
            // Un ajustement peut aller dans les deux sens : c'est sa raison
            // d'être, et c'est pourquoi il est audité à part.
            self::AjustementAdmin => true,
        };
    }
}
