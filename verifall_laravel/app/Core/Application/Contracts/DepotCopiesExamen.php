<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutCopieExamen;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Les copies et leurs réponses — `correction-examens.html`.
 */
interface DepotCopiesExamen
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['soumiseLe', 'statut'];

    /**
     * La file de correction, pour la table DataTables — tous les correcteurs
     * la voient en entier ; « correcteur » n'y est qu'un filtre.
     *
     * @return PageDeResultats<ExamAttempt>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): ExamAttempt;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(ExamAttempt $copie, array $attributs): ExamAttempt;

    public function parUuid(string $uuid): ?ExamAttempt;

    public function parReference(string $reference): ?ExamAttempt;

    public function referenceLibre(string $reference): bool;

    /** La copie encore en composition, s'il y en a une. */
    public function copieEnCours(User $candidat, Exam $examen): ?ExamAttempt;

    /** Combien de fois cette personne a déjà composé — rattrapage compris. */
    public function nombreDeTentatives(User $candidat, Exam $examen): int;

    /**
     * Écrit d'un bloc les réponses d'une copie.
     *
     * @param  list<array<string, mixed>>  $reponses
     */
    public function enregistrerLesReponses(ExamAttempt $copie, array $reponses): void;

    /**
     * @return Collection<int, ExamAnswer>
     */
    public function reponsesDe(ExamAttempt $copie): Collection;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJourUneReponse(ExamAnswer $reponse, array $attributs): ExamAnswer;

    /**
     * La file de correction, la plus ancienne copie d'abord.
     *
     * @return LengthAwarePaginator<int, ExamAttempt>
     */
    public function fileDeCorrection(
        string $recherche = '',
        ?StatutCopieExamen $statut = null,
        ?int $correcteurId = null,
        int $parPage = 15,
    ): LengthAwarePaginator;

    /**
     * Indicateurs de tête de la file.
     *
     * `delai_moyen_jours` vaut `null` tant qu'aucune copie n'a été corrigée :
     * un délai moyen calculé sur zéro copie serait un chiffre inventé.
     *
     * @return array{en_attente: int, urgentes: int, corrigees_30j: int, delai_moyen_jours: ?float, correcteurs_actifs: int}
     */
    public function indicateurs(): array;
}
