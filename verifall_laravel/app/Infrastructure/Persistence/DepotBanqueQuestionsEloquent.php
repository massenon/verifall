<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Enums\StatutQuestion;
use App\Enums\TypeQuestion;
use App\Infrastructure\Persistence\DataTables\FiltreSearchBuilder;
use App\Models\ExamAnswer;
use App\Models\QuestionBankItem;
use App\Models\QuestionBankOption;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DepotBanqueQuestionsEloquent implements DepotBanqueQuestions
{
    private const COLONNES_SQL = [
        'type' => 'type',
        'points' => 'points',
        'statut' => 'status',
        'creeLe' => 'created_at',
    ];

    /** @return PageDeResultats<QuestionBankItem> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = QuestionBankItem::query()->count();

        $filtree = QuestionBankItem::query()
            // Présence de l'explication, calculée en base : le service ne lit
            // jamais son contenu, seulement cette colonne booléenne.
            ->selectRaw("question_bank_items.*, CASE WHEN explanation IS NOT NULL AND explanation != '' THEN 1 ELSE 0 END as a_une_explication");

        $this->filtrer($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
        }

        $lignes = $filtree
            ->with('course')
            ->orderByDesc('question_bank_items.id')
            ->skip($requete->debut)
            ->take($requete->longueur)
            ->get();

        return new PageDeResultats(
            total: $total,
            filtres: $nombreFiltre,
            lignes: $lignes,
            tronque: $requete->export && $nombreFiltre > $requete->longueur,
        );
    }

    /**
     * @param  Builder<QuestionBankItem>  $requete
     */
    private function filtrer(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $requete->where('prompt', 'like', '%'.$criteres->recherche.'%');
        }

        if (isset($filtres['type'])) {
            $requete->where('type', $filtres['type']);
        }

        if (isset($filtres['statut'])) {
            $requete->where('status', $filtres['statut']);
        }

        if (isset($filtres['cours'])) {
            $requete->whereHas('course', fn (Builder $c) => $c->where('uuid', $filtres['cours']));
        }

        (new FiltreSearchBuilder(['enonce' => ['colonne' => 'prompt', 'type' => 'string']]))
            ->appliquer($requete, $criteres->searchBuilder);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): QuestionBankItem
    {
        return QuestionBankItem::create($attributs);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(QuestionBankItem $question, array $attributs): QuestionBankItem
    {
        $question->fill($attributs)->save();

        return $question->refresh();
    }

    public function supprimer(QuestionBankItem $question): void
    {
        // Suppression douce : les copies déjà rendues gardent leur énoncé
        // recopié, mais le lien vers l'original reste utile tant qu'il existe.
        $question->delete();
    }

    public function parUuid(string $uuid): ?QuestionBankItem
    {
        return QuestionBankItem::with('options')->where('uuid', $uuid)->first();
    }

    /**
     * @return LengthAwarePaginator<int, QuestionBankItem>
     */
    public function lister(
        string $recherche = '',
        ?TypeQuestion $type = null,
        ?StatutQuestion $statut = null,
        ?int $coursId = null,
        int $parPage = 15,
    ): LengthAwarePaginator {
        return QuestionBankItem::query()
            ->with('course')
            ->when($recherche !== '', fn (Builder $q) => $q->where('prompt', 'like', '%'.$recherche.'%'))
            ->when($type instanceof TypeQuestion, fn (Builder $q) => $q->where('type', $type?->value))
            ->when($statut instanceof StatutQuestion, fn (Builder $q) => $q->where('status', $statut?->value))
            ->when($coursId !== null, fn (Builder $q) => $q->where('course_id', $coursId))
            ->orderByDesc('created_at')
            ->paginate($parPage);
    }

    /**
     * @param  list<array{label: string, is_correct?: bool, match_label?: string|null}>  $propositions
     */
    public function remplacerLesPropositions(QuestionBankItem $question, array $propositions): QuestionBankItem
    {
        DB::transaction(function () use ($question, $propositions): void {
            QuestionBankOption::where('question_bank_item_id', $question->id)->delete();

            foreach (array_values($propositions) as $rang => $proposition) {
                QuestionBankOption::create([
                    'question_bank_item_id' => $question->id,
                    'position' => $rang + 1,
                    'label' => $proposition['label'],
                    'match_label' => $proposition['match_label'] ?? null,
                    'is_correct' => (bool) ($proposition['is_correct'] ?? false),
                ]);
            }
        });

        return $question->fresh(['options']) ?? $question;
    }

    public function noterUnChoix(int $optionId): void
    {
        QuestionBankOption::where('id', $optionId)->increment('picked_count');
    }

    public function recalculerLesStatistiques(QuestionBankItem $question): QuestionBankItem
    {
        /*
         | RECALCUL depuis les copies, et non incrément : une correction reprise
         | par un mentor change le verdict d'une réponse. Un compteur incrémenté
         | garderait l'ancien, et le taux de réussite affiché dériverait sans
         | que rien ne le signale.
         |
         | Les réponses non encore corrigées sont exclues : compter un « pas
         | encore lu » comme un échec ferait plonger le taux d'une question
         | seulement parce que la file de correction a du retard.
         */
        $tranchees = ExamAnswer::where('question_bank_item_id', $question->id)
            ->whereNotNull('is_correct');

        $question->forceFill([
            'answers_count' => (int) $tranchees->clone()->count(),
            'correct_count' => (int) $tranchees->clone()->where('is_correct', true)->count(),
        ])->save();

        return $question->refresh();
    }

    /**
     * @param  list<int>  $questionIds
     */
    public function recalculerLesStatistiquesPour(array $questionIds): void
    {
        if ($questionIds === []) {
            return;
        }

        QuestionBankItem::whereIn('id', $questionIds)
            ->get()
            ->each(fn (QuestionBankItem $question) => $this->recalculerLesStatistiques($question));
    }

    /**
     * @return array{total: int, avec_explication: int, taux_reussite: ?int, a_reviser: int}
     */
    public function indicateurs(): array
    {
        $agregats = QuestionBankItem::query()
            ->selectRaw('SUM(answers_count) as repondues, SUM(correct_count) as justes')
            ->first();

        $repondues = (int) ($agregats?->getAttribute('repondues') ?? 0);
        $justes = (int) ($agregats?->getAttribute('justes') ?? 0);

        return [
            'total' => (int) QuestionBankItem::count(),
            'avec_explication' => (int) QuestionBankItem::whereNotNull('explanation')
                ->where('explanation', '!=', '')
                ->count(),
            'taux_reussite' => $repondues === 0 ? null : (int) round($justes / $repondues * 100),
            'a_reviser' => (int) QuestionBankItem::where('status', StatutQuestion::AReviser->value)->count(),
        ];
    }
}
