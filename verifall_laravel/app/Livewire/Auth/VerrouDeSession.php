<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Identity\Services\VerrouDeSession as Verrou;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * L'écran de verrouillage de la console (arbitrage n° 180).
 *
 * Il ne montre que l'heure, le nom de la personne et un champ : aucune donnée
 * de la console n'y transparaît, puisque la garde est déconnectée.
 */
#[Layout('components.layouts.console-verrou', ['title' => "Session verrouillée — Vérif'All"])]
final class VerrouDeSession extends Component
{
    public string $motDePasse = '';

    public ?string $erreur = null;

    public function mount(Verrou $verrou): void
    {
        if ($verrou->etat(request()) === null) {
            $this->redirectRoute('staff.login');

            return;
        }

        if ($verrou->estExpire(request())) {
            $verrou->fermer(request());
            session()->flash('inactivite', true);
            $this->redirectRoute('staff.login');
        }
    }

    public function deverrouiller(Verrou $verrou): void
    {
        $retour = $verrou->etat(request())['retour'] ?? route('staff.dashboard');
        $resultat = $verrou->deverrouiller(request(), $this->motDePasse);
        $this->motDePasse = '';

        match ($resultat) {
            'ok' => $this->redirect($retour),
            'expire' => $this->versLaConnexion('inactivite'),
            'ferme' => $this->versLaConnexion('verrou_ferme'),
            default => $this->refuser($verrou),
        };
    }

    /** Retour à la connexion complète, avec le bandeau qui dit pourquoi. */
    private function versLaConnexion(string $motif): void
    {
        session()->flash($motif, true);
        $this->redirectRoute('staff.login');
    }

    public function render(Verrou $verrou): View
    {
        $etat = $verrou->etat(request());
        $membre = $verrou->membre(request());
        $nom = $membre === null ? '' : (trim(($membre->first_name ?? '').' '.($membre->last_name ?? '')) ?: (string) $membre->name);

        return view('livewire.auth.verrou-de-session', [
            'nom' => $nom,
            'initiales' => collect(explode(' ', $nom))->filter()->take(2)->map(fn (string $m): string => mb_strtoupper(mb_substr($m, 0, 1)))->implode(''),
            'depuis' => $etat === null ? null : now()->setTimestamp($etat['depuis'])->format('H:i'),
            'inactivite' => ($etat['raison'] ?? '') === 'inactivite',
        ]);
    }

    private function refuser(Verrou $verrou): void
    {
        $restants = Verrou::ESSAIS_MAX - ($verrou->etat(request())['echecs'] ?? 0);
        $this->erreur = $restants > 1
            ? "Mot de passe incorrect. Il reste {$restants} essais."
            : 'Mot de passe incorrect. Dernier essai avant la fermeture de la session.';
    }
}
