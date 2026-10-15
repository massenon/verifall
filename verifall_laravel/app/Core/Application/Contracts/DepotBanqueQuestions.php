<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutQuestion;
use App\Enums\TypeQuestion;
use App\Models\QuestionBankItem;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * La banque de questions — `questions.html`.
 *
 * Elle est indépendante des examens qui la consomment : c'est tout l'intérêt
 * d'une banque. Une question retirée d'une épreuve reste disponible pour la
 * suivante.
 */
interface DepotBanqueQuestions
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['type', 'points', 'statut', 'creeLe'];

    /** Colonnes que SearchBuilder peut interroger. */
    public const COLONNES_SEARCHBUILDER = ['enonce'];

    /**
     * Le fonds entier, pour la table DataTables — commun à tous les mentors.
     *
     * @return PageDeResultats<QuestionBankItem>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): QuestionBankItem;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(QuestionBankItem $question, array $attributs): QuestionBankItem;

    public function supprimer(QuestionBankItem $question): void;

    public function parUuid(string $uuid): ?QuestionBankItem;

    /**
     * @return LengthAwarePaginator<int, QuestionBankItem>
     */
    public function lister(
        string $recherche = '',
        ?TypeQuestion $type = null,
        ?StatutQuestion $statut = null,
        ?int $coursId = null,
        int $parPage = 15,
    ): LengthAwarePaginator;

    /**
     * Remplace en bloc les propositions d'une question.
     *
     * En bloc et non une par une : modifier une question, c'est réécrire son
     * jeu de réponses. Une mise à jour partielle laisserait une ancienne bonne
     * réponse en place à côté de la nouvelle.
     *
     * @param  list<array{label: string, is_correct?: bool, match_label?: string|null}>  $propositions
     */
    public function remplacerLesPropositions(QuestionBankItem $question, array $propositions): QuestionBankItem;

    /** Incrémente le compteur de choix d'une proposition. */
    public function noterUnChoix(int $optionId): void;

    /**
     * Recalcule le taux de réussite de la question à partir des copies.
     *
     * @return QuestionBankItem le modèle rafraîchi
     */
    public function recalculerLesStatistiques(QuestionBankItem $question): QuestionBankItem;

    /**
     * Même chose, pour toutes les questions d'une copie qu'on vient de noter.
     *
     * En bloc : corriger une copie touche une vingtaine de questions, et les
     * recharger une par une ferait vingt allers-retours par correction.
     *
     * @param  list<int>  $questionIds
     */
    public function recalculerLesStatistiquesPour(array $questionIds): void;

    /**
     * Indicateurs de tête de `questions.html`.
     *
     * @return array{total: int, avec_explication: int, taux_reussite: ?int, a_reviser: int}
     */
    public function indicateurs(): array;
}
