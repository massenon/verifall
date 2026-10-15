<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;

/**
 * La lecture des comptes pour la liste d'administration.
 *
 * ## Pourquoi un dépôt ici, et pas ailleurs
 *
 * `CLAUDE.md` rend le dépôt obligatoire dès qu'une lecture porte sur un volume
 * **non borné**, et cite nommément les utilisateurs. Jusqu'au pilote DataTables
 * (14 septembre 2026), la liste vivait dans `Staff\Utilisateurs::lignes()` :
 * vingt lignes paginées, la règle tolérait mal mais le coût restait fixe. Le
 * traitement côté serveur fait porter la recherche, le tri, les filtres ET
 * l'export sur le jeu entier — c'est exactement le cas que la règle visait.
 *
 * Les clés ci-dessous sont PUBLIQUES : ce sont les `data` des colonnes
 * DataTables. Les noms SQL restent dans l'implémentation, et c'est ce qui rend
 * l'injection d'une colonne impossible par construction — le navigateur ne
 * nomme jamais une colonne de la base.
 */
interface DepotUtilisateurs
{
    /** Colonnes que l'écran peut trier. */
    public const COLONNES_TRIABLES = ['nom', 'email', 'unites', 'points', 'inscritLe', 'derniereConnexion'];

    /** Colonnes que SearchBuilder peut interroger. */
    public const COLONNES_SEARCHBUILDER = ['nom', 'email', 'profession', 'unites', 'points', 'inscritLe', 'derniereConnexion'];

    /**
     * Une page de comptes, filtrée, triée, bornée — et ses deux compteurs.
     *
     * Les rôles et l'auteur d'une éventuelle suspension sont chargés d'avance :
     * le dépôt interdit le chargement paresseux hors production, et la ligne
     * affiche les deux.
     *
     * @return PageDeResultats<User>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * Parmi ces comptes, ceux dont un dossier KYC attend un examen.
     *
     * Une requête pour la page, et non une par ligne.
     *
     * @param  list<int>  $identifiants
     * @return list<int>
     */
    public function avecDossierKycEnAttente(array $identifiants): array;
}
