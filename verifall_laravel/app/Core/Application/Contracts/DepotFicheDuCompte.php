<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\KycSubmission;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Database\Eloquent\Model;

/**
 * Les lectures de la fiche d'un compte (admin/utilisateurs/{uuid}).
 *
 * ## Pourquoi un dépôt
 *
 * Trois des quatre critères de `CLAUDE.md` sont vrais à la fois : les
 * vérifications, le journal d'audit et les mouvements d'unités d'un compte sont
 * des volumes **non bornés** (un compte repris de l'ancienne plateforme porte
 * déjà 123 vérifications), et la fiche **compose une douzaine d'agrégats**.
 *
 * ## Un dépôt, douze onglets
 *
 * Chaque onglet est une DataTable côté serveur (`docs/DATATABLES_CONTRAT.md`,
 * section « Fiche profil »). Les onglets ne diffèrent que par leur requête de
 * base, leurs colonnes triables et leurs filtres : un dépôt par onglet
 * recopierait douze fois la même pagination.
 *
 * Les clés ci-dessous sont PUBLIQUES (les `data` des colonnes DataTables) ;
 * les noms SQL restent dans l'implémentation.
 */
interface DepotFicheDuCompte
{
    /**
     * Colonnes triables, par onglet. Les clés de ce tableau sont aussi la liste
     * fermée des onglets : la route les confronte à `whereIn`.
     */
    public const COLONNES_TRIABLES = [
        // — l'activité du compte
        'verifications' => ['affirmation', 'type', 'statut', 'cout', 'creeLe'],
        'cours' => ['statut', 'progression', 'unites', 'inscritLe', 'derniereActivite', 'termineLe'],
        'quiz' => ['score', 'reussi', 'duree', 'passeLe'],
        'devoirs' => ['statut', 'note', 'remisLe', 'corrigeLe'],
        'examens' => ['tentative', 'statut', 'score', 'reussi', 'soumisLe'],
        'certificats' => ['code', 'cours', 'delivreLe'],
        'unites' => ['date', 'mouvement', 'solde', 'libelle'],
        'points' => ['date', 'mouvement', 'solde', 'libelle'],
        'paiements' => ['date', 'pack', 'montant', 'unites', 'statut'],
        'forum' => ['sujet', 'reponses', 'creeLe'],
        'journal' => ['date', 'action', 'module'],
        // — le périmètre d'un membre du personnel
        'verifications-assignees' => ['affirmation', 'statut', 'assigneeLe', 'creeLe'],
        'cours-encadres' => ['titre', 'publie', 'inscrits', 'creeLe'],
        'corrections-devoirs' => ['statut', 'note', 'corrigeLe'],
        'corrections-examens' => ['statut', 'score', 'assigneeLe', 'corrigeLe'],
        'faits-rediges' => ['titre', 'verdict', 'publie', 'reutilisations', 'creeLe'],
    ];

    /**
     * Une page de l'onglet demandé, pour CE compte, filtrée, triée, bornée.
     *
     * Les relations affichées sont chargées d'avance : aucune requête par
     * ligne (le dépôt interdit le chargement paresseux hors production).
     *
     * @param  key-of<self::COLONNES_TRIABLES>  $onglet
     * @return PageDeResultats<Model>
     */
    public function pourOnglet(string $onglet, User $compte, RequeteDataTables $requete): PageDeResultats;

    /**
     * Le nombre de lignes de chaque onglet demandé, pour les pastilles.
     *
     * @param  list<string>  $onglets
     * @return array<string, int>
     */
    public function compteurs(User $compte, array $onglets): array;

    /** Le compte, avec ce que la carte d'identité affiche. Null s'il n'existe plus. */
    public function compte(string $uuid): ?User;

    /** Le dossier KYC le plus récent, métadonnées seules — jamais les pièces. */
    public function dernierDossierKyc(User $compte): ?KycSubmission;
}
