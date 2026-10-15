<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutExamen;
use App\Enums\StatutSessionExamen;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\QuestionBankItem;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Les examens et leurs sessions.
 *
 * Un seul dépôt pour les deux : une session n'existe jamais sans son examen, et
 * les séparer aurait obligé chaque service à en injecter deux pour publier une
 * épreuve.
 */
interface DepotExamens
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['titre', 'duree', 'seuil', 'statut', 'creeLe'];

    /**
     * Toutes les épreuves — le mentor en voit autant que l'administrateur.
     *
     * @return PageDeResultats<Exam>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): Exam;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(Exam $examen, array $attributs): Exam;

    public function supprimer(Exam $examen): void;

    public function parUuid(string $uuid): ?Exam;

    public function parSlug(string $slug): ?Exam;

    public function slugLibre(string $slug): bool;

    /**
     * Liste paginée de `examens.html`.
     *
     * @return LengthAwarePaginator<int, Exam>
     */
    public function lister(
        string $recherche = '',
        ?StatutExamen $statut = null,
        int $parPage = 15,
    ): LengthAwarePaginator;

    /**
     * Indicateurs de tête de `examens.html`.
     *
     * Seulement ce qui se mesure : le nombre d'examens publiés, les sessions
     * ouvertes, les candidats corrigés sur trente jours, le taux de réussite
     * global et la taille de la file de correction. Rien qui s'invente.
     *
     * @return array{actifs: int, sessions_ouvertes: int, candidats_30j: int, taux_reussite: ?int, a_corriger: int, abandons: int, certificats: int}
     */
    public function indicateurs(): array;

    /**
     * Le tableau de `stats-globales.html` : un examen par ligne.
     *
     * Chaque `Exam` rendu porte deux agrégats calculés à la lecture,
     * `sessions_count` et `average_score` — non stockés, parce qu'ils ne
     * s'affichent que sur cet écran et qu'une colonne de plus sur `exams`
     * aurait dérivé au premier recalcul oublié.
     *
     * @return LengthAwarePaginator<int, Exam>
     */
    public function statistiques(string $recherche = '', int $parPage = 15): LengthAwarePaginator;

    // ------------------------------------------------------------ SESSIONS

    public function sessionParUuid(string $uuid): ?ExamSession;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creerSession(Exam $examen, array $attributs): ExamSession;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJourSession(ExamSession $session, array $attributs): ExamSession;

    /** Une session du même examen commence-t-elle déjà à cette minute ? */
    public function sessionExisteA(Exam $examen, DateTimeInterface $debut): bool;

    /**
     * @return LengthAwarePaginator<int, ExamSession>
     */
    public function listerSessions(
        ?Exam $examen = null,
        ?StatutSessionExamen $statut = null,
        int $parPage = 15,
    ): LengthAwarePaginator;

    /**
     * Sessions ouvertes aux inscriptions, la liste déroulante de l'enrôlement.
     *
     * @return Collection<int, ExamSession>
     */
    public function sessionsAVenir(?Exam $examen = null): Collection;

    // ----------------------------------------------------------- QUESTIONS

    /** Ajoute une question à l'épreuve, au rang suivant si aucun n'est donné. */
    public function attacherUneQuestion(Exam $examen, QuestionBankItem $question, ?int $position = null): ExamQuestion;

    public function detacherUneQuestion(Exam $examen, QuestionBankItem $question): void;

    /**
     * Les questions de l'épreuve, dans l'ordre, avec leurs propositions.
     *
     * Chargées en une fois : composer un examen lit toutes ses questions, et
     * une requête par question ferait vingt allers-retours par candidat.
     *
     * @return Collection<int, QuestionBankItem>
     */
    public function questionsDe(Exam $examen): Collection;

    // ---------------------------------------------------------- COMPTEURS

    /**
     * Recalcule `candidates_count` et `passed_count` à partir des copies.
     *
     * RECALCUL et non incrément : un compteur incrémenté dérive dès qu'une
     * correction est annulée ou qu'un événement est rejoué, et le taux de
     * réussite affiché devient faux sans que rien ne le signale.
     */
    public function recalculerLesCompteurs(Exam $examen): Exam;

    public function recalculerLesCompteursDeSession(ExamSession $session): ExamSession;
}
