<?php

declare(strict_types=1);

namespace App\Core\Economy\Services;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Enums\MotifMouvementUnite;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Le journal des unités de TOUS les comptes, tel que la table DataTables le reçoit.
 *
 * Migration du 14 septembre 2026 (`docs/DATATABLES_CONTRAT.md`).
 *
 * - **Qui voit** : la Gate `journal-des-unites` (`ai_unit.view` OU
 *   `security.unit_logs`), rejouée ici — la même que la route et que le
 *   `mount()` du composant.
 * - **Pas d'export** : ni `ai_unit` ni `security` ne déclarent de verbe
 *   `export` dans `MatricePermissions` (contrat §8) ; `export=1` répond 403.
 * - **Aucune action de ligne** : le journal est comptable, il ne se modifie ni
 *   ne se supprime. Le seul geste d'écriture — l'ajustement — est un bouton
 *   d'en-tête, et il fait recharger la table.
 * - **Ce qui sort** : date, titulaire (nom et adresse, comme l'ancienne
 *   liste), motif, libellé affiché, mouvement, solde. Jamais l'`id` du
 *   mouvement ni celui du compte — donc pas la référence « T-000123 »,
 *   qui n'est que l'`id` mis en forme.
 */
final readonly class TableauDuJournalDesUnites
{
    public function __construct(private UnitLogRepositoryInterface $journal) {}

    /**
     * Les filtres rapides, adossés à des colonnes réelles.
     *
     * « Opération » de la matrice = `sens` (signe de `delta`) et `motif`
     * (`reason`, enum `MotifMouvementUnite`) — les deux filtres historiques de
     * l'écran, sous leurs noms d'adresse. « Période » = `created_at`.
     *
     * « Outil » et « statut » n'ont AUCUNE colonne dans `unit_logs` : un
     * mouvement n'a pas de statut (il est écrit ou il n'existe pas), et
     * l'origine polymorphe (`source_type`) désigne un paiement, une
     * vérification ou un cours, pas un outil. Ni l'un ni l'autre n'est proposé.
     *
     * @return array<string, list<string>|'date'>
     */
    public static function filtresAutorises(): array
    {
        return [
            'sens' => ['credit', 'debit'],
            'motif' => array_column(MotifMouvementUnite::cases(), 'value'),
            'periode_du' => 'date',
            'periode_au' => 'date',
        ];
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(RequeteDataTables $requete, User $acteur): array
    {
        Gate::forUser($acteur)->authorize('journal-des-unites');

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour le journal des unités.");
        }

        $page = $this->journal->pourTableau($requete);

        $lignes = [];

        foreach ($page->lignes as $mouvement) {
            $titulaire = $mouvement->user;

            $lignes[] = [
                'uuid' => (string) $mouvement->uuid,
                'date' => $mouvement->created_at?->toDateString(),
                'dateTexte' => $mouvement->created_at?->translatedFormat('j M Y · H:i'),
                'compte' => $titulaire !== null ? [
                    'nom' => (string) $titulaire->name,
                    'email' => (string) $titulaire->email,
                ] : null,
                'motif' => $mouvement->reason->value,
                'motifLibelle' => $mouvement->reason->libelle(),
                'libelle' => $mouvement->libelleAffiche(),
                'mouvement' => (int) $mouvement->delta,
                'soldeApres' => (int) $mouvement->balance_after,
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
