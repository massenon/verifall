<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutSignalementContenu;
use App\Models\ContentReport;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * La file de modération des contenus signalés.
 *
 * Elle est POLYMORPHE par construction : un sujet, une réponse et une note de
 * communauté arrivent dans la même file. Trois files séparées auraient produit
 * trois écrans, trois habitudes, et un type de contenu qu'on finit par ne plus
 * relever.
 */
interface DepotSignalementsContenu
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['type', 'propagation', 'motif', 'signalePar', 'statut', 'recuLe'];

    /**
     * @return LengthAwarePaginator<int, ContentReport>
     */
    public function paginer(
        ?StatutSignalementContenu $statut,
        ?string $type,
        int $parPage,
    ): LengthAwarePaginator;

    /**
     * Même règle de file que `paginer()` par défaut (flambée puis ancienneté),
     * mais bornée, triée et filtrée côté SQL pour la table DataTables.
     *
     * @return PageDeResultats<ContentReport>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    public function parUuid(string $uuid): ?ContentReport;

    /** @return array<string, int> */
    public function compteurs(): array;

    /**
     * Les types de contenu RÉELLEMENT signalés, pour n'offrir aucun filtre vide.
     *
     * @return list<string>
     */
    public function typesPresents(): array;

    public function trancher(
        ContentReport $signalement,
        StatutSignalementContenu $statut,
        User $acteur,
        ?string $note = null,
    ): ContentReport;
}
