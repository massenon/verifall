<?php

declare(strict_types=1);

namespace App\Observers;

use App\Core\FactChecking\Services\ClassificationThematique;
use App\Models\Verification;

/**
 * Range chaque vérification sous une thématique, dès sa création.
 *
 * ## Pourquoi ici, et pas dans le service de soumission
 *
 * `SoumissionVerificationService` crée des vérifications par TROIS chemins —
 * soumission d'un membre, d'un visiteur, et décision de la plateforme
 * (« transmission au labo » depuis le forum). S'y ajoutent les fabriques de
 * test et les seeders.
 *
 * Un appel posé dans un seul de ces chemins produirait un panneau « Top
 * Thématiques » qui ne compte qu'une partie des analyses — et l'écart ne se
 * verrait jamais, puisqu'un classement partiel ressemble à un classement.
 *
 * ## `creating` et non `created`
 *
 * Le thème est écrit dans la MÊME insertion. Avec `created`, il faudrait un
 * second `UPDATE`, et toute vérification créée dans une transaction annulée
 * ensuite laisserait passer une écriture inutile.
 *
 * ## Le thème déjà posé n'est jamais écrasé
 *
 * Un correctif manuel depuis le back-office, ou un seeder qui range
 * délibérément une analyse, doit tenir. L'heuristique ne se substitue à personne.
 */
final readonly class ObservateurVerification
{
    public function __construct(private ClassificationThematique $classement) {}

    public function creating(Verification $verification): void
    {
        if ($verification->theme !== null) {
            return;
        }

        $verification->theme = $this->classement->pour((string) $verification->claim);
    }
}
