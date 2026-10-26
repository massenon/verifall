<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Identity\Services\JournalDesConnexions;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Chaque connexion réussie, quel que soit le chemin (mot de passe citoyen,
 * console avec 2FA, connexion sociale), passe par `Auth::login()` et donc par
 * cet événement : un seul point d'écoute, aucun parcours oublié.
 */
final class JournaliserLaConnexion
{
    public function __construct(private readonly JournalDesConnexions $journal) {}

    public function handle(Login $evenement): void
    {
        if (! $evenement->user instanceof User) {
            return;
        }

        $requete = request();
        $sociale = $requete->is('auth/*');

        $methode = $requete->attributes->get('connexion.methode');

        $this->journal->reussie(
            $evenement->user,
            $evenement->guard === 'staff' ? 'console' : JournalDesConnexions::espaceDe($requete),
            is_string($methode) ? $methode : ($sociale ? 'sociale' : 'mot_de_passe'),
        );
    }
}
