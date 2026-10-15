<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Enums\StatutCopieExamen;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * La file de correction, telle que DataTables la reçoit (vague 1 Académie,
 * 14 septembre 2026 — contrat `docs/DATATABLES_CONTRAT.md`).
 *
 * ## Qui voit quoi
 *
 * La garde est une PERMISSION (`exam.grade`), comme la route et le `mount()` du
 * composant. Le mentor voit la file ENTIÈRE, copies des autres correcteurs
 * comprises : c'est ce que montrait la liste Livewire (filtre « Correcteur :
 * tous » par défaut), et ce que dit `ExamPolicy` — un jury corrige l'épreuve
 * d'un autre. « Les miennes » reste un FILTRE, pas un périmètre.
 *
 * ## Ce qui ne sort pas
 *
 * Aucune réponse de candidat, aucun commentaire, aucune appréciation, aucun
 * fichier : la ligne porte la référence, le candidat, l'épreuve, l'attente et
 * la note une fois rendue. La copie elle-même s'ouvre dans le composant
 * Livewire, qui la charge à la demande.
 *
 * Pas d'export : aucune permission d'export n'existe pour les copies.
 */
final readonly class TableauDeLaCorrection
{
    public function __construct(private DepotCopiesExamen $depot) {}

    /**
     * La période porte sur la date de REMISE : c'est celle qui fait une copie
     * urgente, et la seule que la file affiche.
     *
     * @return array<string, list<string>|'date'>
     */
    public function filtresAutorises(): array
    {
        return [
            'examen' => array_map('strval', array_keys($this->examensProposes())),
            'statut' => array_column(StatutCopieExamen::cases(), 'value'),
            // « moi » ou l'un des comptes qui peuvent corriger — la même liste
            // que le sélecteur du composant (guard `web`, où les rôles sont semés).
            'correcteur' => array_merge(['moi'], User::query()
                ->role([UserRole::Mentor->value, UserRole::Admin->value], 'web')
                ->pluck('uuid')
                ->map(static fn (mixed $u): string => (string) $u)
                ->values()
                ->all()),
            'soumise_du' => 'date',
            'soumise_au' => 'date',
        ];
    }

    /**
     * Les épreuves du filtre « Examen » : la liste du sélecteur ET la liste
     * blanche du serveur, d'une seule source.
     *
     * @return array<string, string> uuid => titre
     */
    public function examensProposes(): array
    {
        $examens = [];

        foreach (Exam::query()->orderBy('title')->get(['uuid', 'title']) as $examen) {
            $examens[(string) $examen->uuid] = (string) $examen->title;
        }

        return $examens;
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(RequeteDataTables $requete, User $acteur): array
    {
        if (! $acteur->can('exam.grade')) {
            throw new AuthorizationException("La file de correction demande l'habilitation exam.grade.");
        }

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour les copies d'examen.");
        }

        // « Les miennes » : traduit en UUID ici, pour que le dépôt ne connaisse
        // ni la session ni l'acteur.
        if (($requete->filtres['correcteur'] ?? null) === 'moi') {
            $requete = new RequeteDataTables(
                draw: $requete->draw,
                debut: $requete->debut,
                longueur: $requete->longueur,
                recherche: $requete->recherche,
                tri: $requete->tri,
                filtres: ['correcteur' => (string) $acteur->uuid] + $requete->filtres,
                searchBuilder: $requete->searchBuilder,
                export: $requete->export,
            );
        }

        $page = $this->depot->pourTableau($requete);

        $lignes = [];
        foreach ($page->lignes as $copie) {
            $lignes[] = $this->ligne($copie);
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
    private function ligne(ExamAttempt $copie): array
    {
        $corrigee = $copie->status === StatutCopieExamen::Corrigee;

        return [
            'uuid' => (string) $copie->uuid,
            'reference' => (string) $copie->reference,
            'statut' => $copie->status->value,
            'statutLibelle' => $copie->status->libelle(),
            'candidat' => $copie->user?->name,
            'epreuve' => $copie->exam?->title,
            'soumiseLe' => $copie->submitted_at?->toDateString(),
            'soumiseLeTexte' => $copie->submitted_at?->translatedFormat('j M Y · H:i'),
            'attenteHeures' => $copie->heuresDAttente(),
            'urgente' => $copie->estUrgente(),
            // La note n'a de sens qu'une fois la copie rendue.
            'note' => $corrigee ? $copie->score_percent : null,
            'admis' => $corrigee ? $copie->passed : null,
            'correcteur' => $copie->correcteur?->name,
            'actions' => [
                'prendre' => $copie->assigned_to === null,
                'ouvrir' => true,
            ],
        ];
    }
}
