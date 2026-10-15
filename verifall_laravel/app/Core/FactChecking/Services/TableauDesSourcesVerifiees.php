<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Models\TrustedSource;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Le référentiel des sources, tel que la table DataTables « Sources vérifiées » le reçoit.
 *
 * Migration du 14 septembre 2026 (`docs/DATATABLES_CONTRAT.md`).
 *
 * - **Qui voit** : `viewAny` de `TrustedSourcePolicy`, rejoué ici.
 * - **Pas d'export** : aucune permission `trusted_source.export` dans
 *   `MatricePermissions` (contrat §8) ; `export=1` répond 403.
 * - **Ce qui sort** : nom, domaine, famille, crédibilité, état. Jamais la
 *   note éditoriale — interne à la rédaction, elle reste cherchable mais
 *   n'est pas renvoyée.
 */
final readonly class TableauDesSourcesVerifiees
{
    public function __construct(private DepotSourcesFiables $depot) {}

    /**
     * Les filtres rapides, adossés à des colonnes réelles.
     *
     * - `famille` → `source_category_id` (UUID d'une famille existante, ou
     *   « sans ») — c'est la « catégorie » de la matrice du contrat ;
     * - `etat` → `is_active`, sous son nom d'adresse historique (`?etat=`) ;
     * - `confiance` → `credibility`, en trois tranches affichées avec leurs
     *   bornes (80–100, 50–79, 0–49) : la colonne est un entier, et une liste
     *   de cent valeurs ne serait pas un filtre rapide.
     *
     * « Type », cité par la matrice, n'a AUCUNE colonne dans `trusted_sources` :
     * il n'est pas proposé.
     *
     * @return array<string, list<string>|'date'>
     */
    public function filtresAutorises(): array
    {
        return [
            'famille' => [...$this->depot->uuidsDesFamilles(), DepotSourcesFiables::SANS_FAMILLE],
            'etat' => ['actives', 'inactives'],
            'confiance' => ['elevee', 'moyenne', 'faible'],
        ];
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(RequeteDataTables $requete, User $acteur): array
    {
        $porte = Gate::forUser($acteur);
        $porte->authorize('viewAny', TrustedSource::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour le référentiel des sources.");
        }

        $page = $this->depot->pourTableau($requete);

        $lignes = [];

        foreach ($page->lignes as $source) {
            $peutModifier = $porte->allows('update', $source);

            $lignes[] = [
                'uuid' => (string) $source->uuid,
                'nom' => (string) $source->name,
                'domaine' => (string) $source->domain,
                'famille' => $source->categorie?->name,
                'credibilite' => (int) $source->credibility,
                'effet' => $source->is_active ? 'reconnue' : 'ignoree',
                'actions' => [
                    'modifier' => $peutModifier,
                    'desactiver' => $peutModifier && $source->is_active,
                    'reactiver' => $peutModifier && ! $source->is_active,
                    'supprimer' => $porte->allows('delete', $source),
                ],
            ];
        }

        return [
            'draw' => $requete->draw,
            'recordsTotal' => $page->total,
            'recordsFiltered' => $page->filtres,
            'data' => $lignes,
            'exportTronque' => false,
        ];
    }
}
