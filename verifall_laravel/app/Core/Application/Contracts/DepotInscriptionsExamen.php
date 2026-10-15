<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutInscriptionExamen;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * L'enrôlement aux examens — `enrolement-examens.html`.
 */
interface DepotInscriptionsExamen
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['session', 'statut', 'inscritLe'];

    /**
     * Toutes les inscriptions — le mentor en voit autant que l'administrateur ;
     * « session » et « examen » n'y sont que des filtres.
     *
     * @return PageDeResultats<ExamRegistration>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(ExamSession $session, User $candidat, array $attributs = []): ExamRegistration;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(ExamRegistration $inscription, array $attributs): ExamRegistration;

    public function parUuid(string $uuid): ?ExamRegistration;

    /** L'inscription de cette personne à cette session, s'il y en a une. */
    public function pour(User $candidat, ExamSession $session): ?ExamRegistration;

    public function codeConvocationLibre(string $code): bool;

    /**
     * @return LengthAwarePaginator<int, ExamRegistration>
     */
    public function lister(
        ?ExamSession $session = null,
        string $recherche = '',
        ?StatutInscriptionExamen $statut = null,
        int $parPage = 15,
    ): LengthAwarePaginator;

    /**
     * Indicateurs de tête, restreints à une session si elle est donnée.
     *
     * `presence` vaut `null` tant que personne n'est inscrit : « 0 % » sur une
     * session vide est un chiffre inventé.
     *
     * @return array{inscrits: int, presence: ?int, convocations_a_envoyer: int, reports: int}
     */
    public function indicateurs(?ExamSession $session = null): array;
}
