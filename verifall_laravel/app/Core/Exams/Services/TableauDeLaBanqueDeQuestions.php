<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Enums\StatutQuestion;
use App\Enums\TypeQuestion;
use App\Models\Course;
use App\Models\QuestionBankItem;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate as PorteContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * La banque de questions, telle que DataTables la reçoit (vague 1 Académie,
 * 14 septembre 2026 — contrat `docs/DATATABLES_CONTRAT.md`).
 *
 * ## Ce que ce service décide
 *
 * 1. **Qui voit** : `viewAny` rejoué, comme la garde de route. Le mentor voit
 *    le fonds ENTIER, et c'est la règle écrite de `QuestionBankItemPolicy` —
 *    « un fonds commun est commun » —, pas un oubli de périmètre.
 * 2. **Pas d'export** : `MatricePermissions` ne déclare aucune permission
 *    d'export pour la banque. `export=1` répond 403 plutôt que d'adosser
 *    l'extraction du fonds à la simple consultation (§8 du contrat).
 * 3. **Ce qui sort** : des métadonnées. Jamais une proposition, jamais la
 *    bonne réponse, jamais l'explication — seulement sa présence. Une table
 *    de gestion qui publierait les corrigés d'un examen certifiant serait une
 *    fuite, même vers le personnel.
 * 4. **Les actions** : la réponse de la Policy pour chaque ligne ; le
 *    composant Livewire ré-autorise au clic.
 */
final readonly class TableauDeLaBanqueDeQuestions
{
    public function __construct(private DepotBanqueQuestions $depot) {}

    /**
     * Les filtres rapides et leurs seules valeurs acceptées.
     *
     * « Difficulté », que la matrice du contrat citait, n'est PAS proposée :
     * `question_bank_items` n'a aucune colonne qui la porte. Un filtre sans
     * colonne ne filtrerait rien en laissant croire le contraire.
     *
     * @return array<string, list<string>|'date'>
     */
    public function filtresAutorises(): array
    {
        return [
            'cours' => Course::query()->pluck('uuid')->map(static fn (mixed $u): string => (string) $u)->values()->all(),
            'type' => array_column(TypeQuestion::cases(), 'value'),
            'statut' => array_column(StatutQuestion::cases(), 'value'),
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
        $porte->authorize('viewAny', QuestionBankItem::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour la banque de questions.");
        }

        $page = $this->depot->pourTableau($requete);

        $lignes = [];
        foreach ($page->lignes as $question) {
            $lignes[] = $this->ligne($question, $porte);
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
    private function ligne(QuestionBankItem $question, PorteContract $porte): array
    {
        $publiee = $question->status === StatutQuestion::Publiee;
        $peutPublier = $porte->allows('publish', $question);

        return [
            'uuid' => (string) $question->uuid,
            'enonce' => Str::limit((string) $question->prompt, 180),
            // L'explication n'est pas lue ; seule sa présence l'est.
            'sansExplication' => (int) $question->getAttribute('a_une_explication') === 0,
            'type' => $question->type->value,
            'typeLibelle' => $question->type->libelle(),
            'cours' => $question->course?->title,
            'points' => (int) $question->points,
            'reponses' => (int) $question->answers_count,
            'tauxReussite' => $question->tauxDeReussite(),
            'statut' => $question->status->value,
            'statutLibelle' => $question->status->libelle(),
            'creeLe' => $question->created_at?->toDateString(),
            'creeLeTexte' => $question->created_at?->translatedFormat('j M Y'),
            'actions' => [
                'publier' => $peutPublier && ! $publiee,
                'retirer' => $peutPublier && $publiee,
                'modifier' => $porte->allows('update', $question),
                'supprimer' => $porte->allows('delete', $question),
            ],
        ];
    }
}
