<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Enums\StatutInscriptionExamen;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * Les inscriptions aux examens, telles que DataTables les reçoit (vague 1
 * Académie, 14 septembre 2026 — contrat `docs/DATATABLES_CONTRAT.md`).
 *
 * ## Qui voit quoi
 *
 * Garde par PERMISSION (`enrolment.view`), comme la route et le composant. Le
 * mentor la détient et voit les inscrits de toutes les sessions — c'était déjà
 * le cas : la liste Livewire acceptait n'importe quelle session dans `?session=`
 * sans autre borne. Ses boutons, eux, suivent ses permissions : il n'en a
 * aucune d'écriture (`enrolment.create/update/convene/delete` sont à l'admin).
 *
 * ## Ce qui sort
 *
 * Nom et adresse du candidat, la session, l'état de la convocation et son
 * code — que l'écran affichait déjà, et qu'un surveillant lit à l'entrée —,
 * la présence, le statut, le motif d'un report. Rien d'autre du compte.
 *
 * Pas d'export : aucune permission d'export n'existe pour l'enrôlement.
 */
final readonly class TableauDeLEnrolementAuxExamens
{
    public function __construct(private DepotInscriptionsExamen $depot) {}

    /**
     * @return array<string, list<string>|'date'>
     */
    public function filtresAutorises(): array
    {
        return [
            // Toutes les sessions, passées comprises : un lien `?session=` vers
            // une session close ouvrait sa liste avant la migration.
            'session' => ExamSession::query()->pluck('uuid')->map(static fn (mixed $u): string => (string) $u)->values()->all(),
            'examen' => array_map('strval', array_keys($this->examensProposes())),
            'statut' => array_column(StatutInscriptionExamen::cases(), 'value'),
            'session_du' => 'date',
            'session_au' => 'date',
        ];
    }

    /**
     * Les épreuves du filtre « Examen » : sélecteur et liste blanche d'une
     * seule source.
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
        if (! $acteur->can('enrolment.view')) {
            throw new AuthorizationException("L'enrôlement aux examens demande l'habilitation enrolment.view.");
        }

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour l'enrôlement.");
        }

        // Une décision par REQUÊTE, pas par ligne : ces permissions ne
        // dépendent pas de l'inscription regardée.
        $droits = [
            'convoquer' => $acteur->can('enrolment.convene'),
            'arbitrer' => $acteur->can('enrolment.update'),
            'annuler' => $acteur->can('enrolment.delete'),
        ];

        $page = $this->depot->pourTableau($requete);

        $lignes = [];
        foreach ($page->lignes as $inscription) {
            $lignes[] = $this->ligne($inscription, $droits);
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
     * @param  array{convoquer: bool, arbitrer: bool, annuler: bool}  $droits
     * @return array<string, mixed>
     */
    private function ligne(ExamRegistration $inscription, array $droits): array
    {
        $session = $inscription->session;
        $annulee = $inscription->status === StatutInscriptionExamen::Annule;

        return [
            'uuid' => (string) $inscription->uuid,
            'candidat' => $inscription->user?->name,
            'email' => $inscription->user?->email,
            'epreuve' => $session?->exam?->title,
            'session' => $session?->starts_at?->toDateString(),
            'sessionTexte' => $session?->starts_at?->translatedFormat('j M Y · H:i'),
            'lieu' => $session !== null ? ($session->location ?? 'En ligne') : null,
            'convocation' => $inscription->convocation_sent_at?->toDateString(),
            'convocationTexte' => $inscription->convocation_sent_at?->translatedFormat('j M Y'),
            /*
             | Le code sert à ÉMARGER. Il ne part qu'à qui peut émarger
             | (`enrolment.update`) : un Mentor, qui n'a que `enrolment.view`,
             | n'en a pas l'usage, et un code de convocation lu par qui n'en a pas
             | l'usage est un code qui circule (arbitrage du 14 septembre 2026).
             */
            'codeConvocation' => $droits['arbitrer'] && $inscription->convocation_sent_at !== null
                ? $inscription->convocation_code
                : null,
            'presence' => $inscription->attended_at?->toDateString(),
            'statut' => $inscription->status->value,
            'statutLibelle' => $inscription->status->libelle(),
            'motifReport' => $inscription->deferral_requested_at !== null && $inscription->deferral_reason !== null
                ? Str::limit($inscription->deferral_reason, 80)
                : null,
            'inscritLe' => $inscription->created_at?->toDateString(),
            'inscritLeTexte' => $inscription->created_at?->translatedFormat('j M Y'),
            'actions' => [
                'convoquer' => $droits['convoquer'] && ! $annulee && $inscription->convocation_sent_at === null,
                'emarger' => $droits['arbitrer'] && ! $annulee && $inscription->attended_at === null,
                'arbitrer' => $droits['arbitrer'] && $inscription->deferral_requested_at !== null,
                'annuler' => $droits['annuler'] && ! $annulee,
            ],
        ];
    }
}
