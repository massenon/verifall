<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\VerdictFactCheck;
use App\Models\VerifiedFact;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Base des faits tranchés par l'équipe.
 *
 * Cette table est lue AVANT tout appel payant : c'est elle qui évite de
 * relancer douze moteurs sur une rumeur déjà démentie. Le service ne connaît
 * que ce contrat, jamais Eloquent.
 */
interface DepotFaitsVerifies
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['verdict', 'reutilisations', 'misAJour', 'statut'];

    /** Colonnes que SearchBuilder peut interroger. */
    public const COLONNES_SEARCHBUILDER = ['motsCles', 'reutilisations'];

    /**
     * La base des faits, pour la table DataTables du back-office.
     *
     * @return PageDeResultats<VerifiedFact>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): VerifiedFact;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(VerifiedFact $fait, array $attributs): VerifiedFact;

    public function supprimer(VerifiedFact $fait): void;

    public function parUuid(string $uuid): ?VerifiedFact;

    /**
     * Liste paginée du back-office.
     *
     * @param  ''|'publie'|'brouillon'  $statut
     * @return LengthAwarePaginator<int, VerifiedFact>
     */
    public function lister(
        string $recherche = '',
        ?VerdictFactCheck $verdict = null,
        string $statut = '',
        int $parPage = 12,
    ): LengthAwarePaginator;

    /**
     * Tous les faits PUBLIÉS, pour le rapprochement par mots-clés.
     *
     * Rendus en une fois et mis en cache : la comparaison se fait en PHP sur
     * une table curée à la main, et elle tourne à CHAQUE soumission. La relire
     * en base à chaque fois ferait payer une requête à tout le monde pour une
     * table qui bouge quelques fois par jour.
     *
     * @return Collection<int, VerifiedFact>
     */
    public function publies(): Collection;

    /** Correspondance exacte sur l'empreinte — le cas le plus sûr. */
    public function parEmpreinte(string $empreinte): ?VerifiedFact;

    /**
     * Indicateurs de l'en-tête.
     *
     * @return array{publies: int, dementis: int, sources_multiples: int, anciennete_jours: float}
     */
    public function indicateurs(): array;

    /**
     * Vide le cache de lecture.
     *
     * Appelé à chaque écriture : servir une base d'il y a dix minutes ferait
     * refacturer une analyse que l'équipe vient justement de rendre gratuite.
     */
    public function oublierLeCache(): void;
}
