<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QuestionBankItem;
use App\Models\User;

/**
 * Qui alimente le fonds commun de questions d'examen.
 *
 * ## Le mentor écrit, il ne publie pas — et ce n'est pas la même règle que pour les cours
 *
 * La matrice donne au mentor `question_bank.view/create/update`, jamais
 * `publish`, et le commentaire l'explique : « il alimente le fonds commun de
 * questions, mais ne le publie pas : une question publiée est posée à tous les
 * candidats ».
 *
 * La borne « seulement SES ressources » qui vaut pour les cours ne vaut PAS
 * ici, et c'est délibéré : un fonds commun est commun. Une question mal
 * formulée doit pouvoir être corrigée par le premier mentor qui la repère,
 * sans attendre son auteur — c'est justement ce qui distingue un fonds
 * d'une collection privée.
 *
 * ## `delete` n'est pas donné au mentor
 *
 * Supprimer une question du fonds efface une trace que des copies rendues
 * citent encore. Le geste ordinaire est la mise en révision, qui la retire des
 * tirages sans fausser les notes déjà données.
 */
final class QuestionBankItemPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('question_bank.view');
    }

    public function view(User $utilisateur, QuestionBankItem $question): bool
    {
        return $utilisateur->can('question_bank.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('question_bank.create');
    }

    public function update(User $utilisateur, QuestionBankItem $question): bool
    {
        return $utilisateur->can('question_bank.update');
    }

    public function publish(User $utilisateur, QuestionBankItem $question): bool
    {
        return $utilisateur->can('question_bank.publish');
    }

    public function delete(User $utilisateur, QuestionBankItem $question): bool
    {
        return $utilisateur->can('question_bank.delete');
    }
}
