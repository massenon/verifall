<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\TrustedSource;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Support\Collection;

/**
 * Le référentiel des sources, vu par le moteur d'analyse.
 *
 * ## Pourquoi un dépôt ici, et pas de l'Eloquent dans l'adaptateur
 *
 * Deux des quatre critères de `CLAUDE.md` sont réunis, et un seul suffirait :
 *
 *  · la lecture est **cachée** — elle a lieu à chaque analyse de lien, sur une
 *    table qui change quelques fois par mois. La requête et son invalidation ne
 *    se dupliquent pas entre l'adaptateur et l'écran d'administration ;
 *  · elle est **partagée** — le moteur la consulte, le back-office l'écrit.
 *
 * ## Le contrat ne rend PAS un modèle au moteur, sauf pour une raison
 *
 * `estFiable()` répond par un booléen : c'est tout ce dont la décision a besoin,
 * et un adaptateur qui recevrait le modèle finirait par lire `notes`, qui est
 * une note interne à la rédaction. `trouver()` existe séparément, pour l'écran
 * et pour le raisonnement affiché au citoyen — jamais pour la note.
 */
interface DepotSourcesFiables
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['nom', 'domaine', 'credibilite', 'effet', 'famille'];

    /**
     * Valeur du filtre « famille » qui désigne une source sans catégorie.
     *
     * Ne peut pas collider avec un UUID réel de `SourceCategory` : les deux
     * n'ont pas le même format.
     */
    public const SANS_FAMILLE = 'sans';

    /**
     * Les familles RÉELLEMENT en usage — n'offrir aucun filtre vide.
     *
     * @return list<string> UUID de `SourceCategory`
     */
    public function uuidsDesFamilles(): array;

    /**
     * Le référentiel, pour la table DataTables du back-office.
     *
     * @return PageDeResultats<TrustedSource>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * La fiche active correspondant à ce domaine, s'il y en a une.
     *
     * Le domaine est normalisé par l'implémentation : l'appelant passe ce que
     * `parse_url()` lui a rendu, sans avoir à savoir que `www.` doit tomber.
     *
     * Les sous-domaines remontent à leur domaine parent : `edition.cnn.com`
     * trouve la fiche de `cnn.com`. Sans cela, référencer une source
     * exigerait d'en énumérer toutes les rubriques.
     */
    public function trouverParDomaine(string $domaine): ?TrustedSource;

    /**
     * Ce domaine est-il référencé ET actif ?
     *
     * La question que pose le moteur, et la seule.
     */
    public function estFiable(string $domaine): bool;

    /**
     * Tout le référentiel actif, groupé par famille, pour la page publique.
     *
     * ## Pourquoi cette méthode existe ici plutôt qu'en Eloquent dans l'écran
     *
     * Même raison que les deux précédentes, et un critère de plus : la lecture
     * devient **publique**. Elle sert une page ouverte à tous, sur 400 lignes,
     * et elle réemploie exactement le même cache que le moteur — pas une
     * seconde requête, pas une seconde clé à invalider. Le jour où la rédaction
     * amende une source, `oublier()` rafraîchit les deux d'un coup.
     *
     * ## Ce qu'elle ne rend pas
     *
     * `notes` est une note INTERNE à la rédaction. Le contrat rend le modèle,
     * comme `trouverParDomaine()`, mais la vue publique n'en affiche ni la note
     * ni la crédibilité chiffrée — voir le composant, qui explique pourquoi.
     *
     * @return Collection<string, Collection<int, TrustedSource>>
     *                                                            clé : le nom de la famille
     */
    public function parFamille(): Collection;

    /** Vide le cache. Appelé à chaque écriture du back-office. */
    public function oublier(): void;
}
