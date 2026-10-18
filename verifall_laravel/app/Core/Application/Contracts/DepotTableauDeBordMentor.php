<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\User;

/**
 * Les lectures du tableau de bord du mentor (IMPLEMENTATION_PLAN, P4-2).
 *
 * Un dépôt, parce qu'il compose plusieurs agrégats et nourrit un tableau de
 * bord (règle 2 du chantier). Toutes les lectures sont BORNÉES aux cours du
 * mentor (`EspaceDuMentor::idsDesCours`) : un mentor ne voit jamais les
 * apprenants ni les copies d'un autre.
 */
interface DepotTableauDeBordMentor
{
    /**
     * @return array{
     *   journee: list<array{heure: string, debut: float, duree: float, icone: string, titre: string, detail: string, ton: string, lien: string}>,
     *   chiffres: array{actifs: int, aCorriger: int, completion: int, note: ?float, avis: int},
     *   copies: list<array{apprenant: string, initiales: string, devoir: string, cours: string, soumis: string, urgent: bool}>,
     *   assignees: list<array{texte: string, reste: int, lien: string}>,
     *   cours: list<array{titre: string, inscrits: int, actifs: int, completion: int, note: ?float, tendance: list<int>}>,
     *   aRisque: list<array{nom: string, initiales: string, cours: string, progression: int, derniere: string}>,
     *   sessions: list<array{titre: string, quand: string, inscrits: int}>,
     *   questions: list<array{titre: string, auteur: string, quand: string, lien: string}>
     * }
     */
    public function pour(User $mentor): array;
}
