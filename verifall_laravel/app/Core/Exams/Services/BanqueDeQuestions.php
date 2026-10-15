<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Exams\Exceptions\QuestionInutilisable;
use App\Enums\StatutQuestion;
use App\Enums\TypeQuestion;
use App\Models\QuestionBankItem;
use App\Models\User;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * La banque de questions : écriture, propositions, cycle de vie.
 *
 * Le point dur de ce service est la PUBLICATION. Une question à choix sans
 * bonne réponse ne plante pas : elle note zéro tout le monde, silencieusement,
 * et fait chuter le taux de réussite d'un examen entier sans qu'on sache
 * pourquoi. Le refus se fait donc ici, au moment de publier, et pas à la
 * correction — où il serait trop tard pour les copies déjà rendues.
 */
final class BanqueDeQuestions
{
    public function __construct(private readonly DepotBanqueQuestions $depot) {}

    /**
     * @param  array{course_id?: int|null, type: TypeQuestion, prompt: string, explanation?: string|null, points?: int}  $donnees
     */
    public function enregistrer(array $donnees, ?User $auteur = null): QuestionBankItem
    {
        return $this->depot->creer([
            'uuid' => (string) Str::uuid(),
            'course_id' => $donnees['course_id'] ?? null,
            'type' => $donnees['type']->value,
            'prompt' => trim($donnees['prompt']),
            'explanation' => $this->purifier($donnees['explanation'] ?? null),
            'points' => max(1, $donnees['points'] ?? 10),
            'status' => StatutQuestion::Brouillon->value,
            'created_by' => $auteur?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function mettreAJour(QuestionBankItem $question, array $donnees): QuestionBankItem
    {
        $attributs = [];

        if (isset($donnees['prompt'])) {
            $attributs['prompt'] = trim((string) $donnees['prompt']);
        }

        if (array_key_exists('explanation', $donnees)) {
            $attributs['explanation'] = $this->purifier($donnees['explanation']);
        }

        if (isset($donnees['points'])) {
            $attributs['points'] = max(1, (int) $donnees['points']);
        }

        if (array_key_exists('course_id', $donnees)) {
            $attributs['course_id'] = $donnees['course_id'];
        }

        if (isset($donnees['type']) && $donnees['type'] instanceof TypeQuestion) {
            $attributs['type'] = $donnees['type']->value;
        }

        return $this->depot->mettreAJour($question, $attributs);
    }

    /**
     * Réécrit d'un bloc le jeu de propositions.
     *
     * @param  list<array{label: string, is_correct?: bool, match_label?: string|null}>  $propositions
     */
    public function definirLesPropositions(QuestionBankItem $question, array $propositions): QuestionBankItem
    {
        return $this->depot->remplacerLesPropositions($question, $propositions);
    }

    /**
     * Rend la question utilisable dans un examen — si elle tient debout.
     */
    public function publier(QuestionBankItem $question): QuestionBankItem
    {
        $this->verifierQuElleTientDebout($question);

        return $this->depot->mettreAJour($question, ['status' => StatutQuestion::Publiee->value]);
    }

    /** Retire la question des tirages sans l'effacer, ni fausser les copies rendues. */
    public function marquerAReviser(QuestionBankItem $question): QuestionBankItem
    {
        return $this->depot->mettreAJour($question, ['status' => StatutQuestion::AReviser->value]);
    }

    public function archiver(QuestionBankItem $question): QuestionBankItem
    {
        return $this->depot->mettreAJour($question, ['status' => StatutQuestion::Archivee->value]);
    }

    public function recalculerLesStatistiques(QuestionBankItem $question): QuestionBankItem
    {
        return $this->depot->recalculerLesStatistiques($question);
    }

    /**
     * Une question à choix doit avoir des propositions, dont au moins une juste.
     *
     * @throws QuestionInutilisable
     */
    private function verifierQuElleTientDebout(QuestionBankItem $question): void
    {
        if (! $question->type->exigeDesPropositions()) {
            // Une réponse courte n'a rien à proposer : elle attend un mentor.
            return;
        }

        $options = $question->loadMissing('options')->options;

        if ($options->isEmpty()) {
            throw new QuestionInutilisable(
                'Une question de type '.$question->type->libelle().' doit proposer des réponses.'
            );
        }

        if ($options->where('is_correct', true)->isEmpty()) {
            throw new QuestionInutilisable(
                'Aucune bonne réponse n\'est désignée : la question noterait zéro tout le monde.'
            );
        }
    }

    /**
     * L'explication vient d'un éditeur riche : purifiée AVANT persistance.
     *
     * Purifier ici plutôt qu'à l'affichage seulement, parce qu'une donnée
     * stockée sale finit toujours par ressortir par un chemin qui a oublié de
     * la nettoyer — un export, une API, un e-mail.
     */
    private function purifier(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return trim((string) Purifier::clean($html, 'default'));
    }
}
