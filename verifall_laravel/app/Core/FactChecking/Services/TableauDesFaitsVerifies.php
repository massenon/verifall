<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\VerifiedFact;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * La base des faits tranchés, telle que la table DataTables « Fact checks » la reçoit.
 *
 * Migration du 14 septembre 2026, sur le contrat du pilote « Utilisateurs »
 * (`docs/DATATABLES_CONTRAT.md`). Ce service décide ce que le contrôleur ne
 * doit pas décider :
 *
 * 1. **Qui voit la liste** — `viewAny` de `VerifiedFactPolicy`, rejoué ici
 *    même si la route le garde déjà.
 * 2. **Personne n'exporte par cette porte** — `MatricePermissions` ne déclare
 *    aucune permission `knowledge_base.export`, et le contrat (§8) interdit
 *    d'adosser un export à la seule consultation sans décision du porteur.
 *    `export=1` répond donc 403. L'export CSV historique de l'écran
 *    (`FaitsVerifies::exporter`) n'est pas touché : il préexistait.
 * 3. **Ce qui sort** — une liste fermée de champs, construite champ par
 *    champ : ni `id`, ni `created_by`, ni l'analyse complète.
 * 4. **Quelles actions la ligne propose** — la réponse de la Policy ; le
 *    composant Livewire ré-autorise au clic.
 */
final readonly class TableauDesFaitsVerifies
{
    public function __construct(private DepotFaitsVerifies $depot) {}

    /**
     * Les filtres rapides, et leurs seules valeurs acceptées.
     *
     * Chacun s'appuie sur une colonne qui EXISTE : `verdict` (enum
     * `VerdictFactCheck`), `is_published`, `updated_at`. La matrice du contrat
     * citait aussi « catégorie » et « visibilité » : `verified_facts` ne porte
     * aucune catégorie, et la seule visibilité est la publication — déjà le
     * filtre « statut ». Ni l'un ni l'autre n'est donc proposé.
     *
     * @return array<string, list<string>|'date'>
     */
    public static function filtresAutorises(): array
    {
        return [
            'verdict' => array_column(VerdictFactCheck::cases(), 'value'),
            'statut' => ['publie', 'brouillon'],
            'maj_du' => 'date',
            'maj_au' => 'date',
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
        $porte->authorize('viewAny', VerifiedFact::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour les fact checks.");
        }

        $page = $this->depot->pourTableau($requete);

        // Deux décisions qui ne dépendent pas de la ligne : prises une fois.
        $peutCreer = $porte->allows('create', VerifiedFact::class);
        $peutPublier = $porte->allows('publish', VerifiedFact::class);

        $lignes = [];

        foreach ($page->lignes as $fait) {
            $lignes[] = [
                'uuid' => (string) $fait->uuid,
                'affirmation' => (string) $fait->title,
                'verdict' => $fait->verdict->value,
                'verdictLibelle' => $fait->verdict->libelle(),
                'sources' => $fait->nombreDeSources(),
                'reutilisations' => (int) $fait->hits,
                'misAJour' => $fait->updated_at?->toDateString(),
                'misAJourTexte' => $fait->updated_at?->translatedFormat('j M Y'),
                'statut' => $fait->is_published ? 'publie' : 'brouillon',
                'motsCles' => (string) $fait->keywords,
                'actions' => [
                    'apercu' => $porte->allows('view', $fait),
                    'modifier' => $porte->allows('update', $fait),
                    'dupliquer' => $peutCreer,
                    'publier' => $peutPublier && ! $fait->is_published,
                    'depublier' => $peutPublier && $fait->is_published,
                    'supprimer' => $porte->allows('delete', $fait),
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
