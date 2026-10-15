<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotExamens;
use App\Enums\StatutExamen;
use App\Models\Course;
use App\Models\Exam;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate as PorteContract;
use Illuminate\Support\Facades\Gate;

/**
 * La liste des épreuves, telle que DataTables la reçoit (vague 1 Académie,
 * 14 septembre 2026 — contrat `docs/DATATABLES_CONTRAT.md`).
 *
 * Le mentor voit toutes les épreuves, comme dans la liste Livewire remplacée :
 * `ExamPolicy` écarte délibérément la borne « ses ressources » (un jury
 * corrige l'épreuve d'un autre). Ce qui le distingue de l'administrateur, ce
 * sont les ACTIONS proposées — ni publier, ni archiver.
 *
 * Pas d'export : aucune permission d'export n'existe pour les examens.
 */
final readonly class TableauDesExamens
{
    public function __construct(private DepotExamens $depot) {}

    /**
     * La période porte sur la date de CRÉATION : une épreuve en brouillon n'a
     * pas de date de publication, et un filtre qui l'écarterait d'office
     * cacherait justement celles qui restent à finir.
     *
     * @return array<string, list<string>|'date'>
     */
    public function filtresAutorises(): array
    {
        return [
            'cours' => Course::query()->pluck('uuid')->map(static fn (mixed $u): string => (string) $u)->values()->all(),
            'statut' => array_column(StatutExamen::cases(), 'value'),
            'cree_du' => 'date',
            'cree_au' => 'date',
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
        $porte->authorize('viewAny', Exam::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour les examens.");
        }

        $page = $this->depot->pourTableau($requete);

        $lignes = [];
        foreach ($page->lignes as $examen) {
            $lignes[] = $this->ligne($examen, $porte);
        }

        return [
            'draw' => $requete->draw,
            'recordsTotal' => $page->total,
            'recordsFiltered' => $page->filtres,
            'data' => $lignes,
            'exportTronque' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ligne(Exam $examen, PorteContract $porte): array
    {
        return [
            'uuid' => (string) $examen->uuid,
            'titre' => (string) $examen->title,
            'duree' => (int) $examen->duration_minutes,
            'tentatives' => (int) $examen->max_attempts,
            'cours' => $examen->course?->title,
            'seuil' => (int) $examen->pass_score,
            'sessions' => (int) $examen->getAttribute('sessions_count'),
            'candidats' => (int) $examen->candidates_count,
            // null et non zéro tant qu'aucune copie n'est corrigée.
            'tauxReussite' => (int) $examen->candidates_count > 0 ? $examen->tauxDeReussite() : null,
            'statut' => $examen->status->value,
            'statutLibelle' => $examen->status->libelle(),
            'creeLe' => $examen->created_at?->toDateString(),
            'creeLeTexte' => $examen->created_at?->translatedFormat('j M Y'),
            'publieLeTexte' => $examen->published_at?->translatedFormat('j M Y'),
            'actions' => [
                'sessions' => true,
                'publier' => $examen->status !== StatutExamen::Publie && $porte->allows('publish', $examen),
                'modifier' => $porte->allows('update', $examen),
                'archiver' => $examen->status !== StatutExamen::Archive && $porte->allows('archive', $examen),
            ],
        ];
    }
}
