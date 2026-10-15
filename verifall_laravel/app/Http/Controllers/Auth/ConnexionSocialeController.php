<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Identity\Exceptions\ConnexionSocialeRefusee;
use App\Core\Identity\Services\ConnexionSociale;
use App\Core\Identity\Services\SecurityLogService;
use App\Enums\FournisseurSocial;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as RedirectionSortante;
use Throwable;

/**
 * Aller chez le fournisseur d'identité, et en revenir.
 *
 * Deux points d'entrée seulement, et tout ce qui décide vit dans
 * `ConnexionSociale` : le contrôleur ne fait que traduire une redirection en
 * appel de service, et une exception en message affichable.
 */
final class ConnexionSocialeController
{
    /*
     | Le type de sortie est celui de SYMFONY, et c'est voulu.
     |
     | `Socialite\Contracts\Provider::redirect()` ne declare aucun type de
     | retour : rien dans le contrat ne promet une redirection Laravel. Le
     | pilote OAuth2 en rend une aujourd'hui, mais un pilote tiers n'y est pas
     | tenu. Annoncer `Illuminate\Http\RedirectResponse` serait affirmer plus
     | que la dependance ne garantit.
     |
     | Une route accepte n'importe quelle reponse Symfony : rien n'est perdu.
     | `retour()` construit sa propre redirection et garde donc le type Laravel.
     */
    public function rediriger(string $fournisseur): RedirectionSortante
    {
        $choisi = $this->fournisseur($fournisseur);

        return Socialite::driver($choisi->value)->redirect();
    }

    public function retour(
        string $fournisseur,
        Request $requete,
        ConnexionSociale $connexions,
        SecurityLogService $journal,
    ): RedirectResponse {
        $choisi = $this->fournisseur($fournisseur);

        /*
         | La personne a refusé l'autorisation chez le fournisseur. Ce n'est pas
         | une erreur : on la ramène simplement d'où elle vient, sans alarme.
         */
        if ($requete->has('error')) {
            return redirect()->route('login')
                ->with('info', 'Connexion annulée.');
        }

        try {
            $profil = Socialite::driver($choisi->value)->user();
        } catch (Throwable $e) {
            /*
             | Jeton invalide, état perdu, fournisseur injoignable. Le détail
             | est journalisé ; ce qu'on montre reste actionnable, parce qu'un
             | « une erreur est survenue » laisse la personne sans savoir si
             | elle doit réessayer ou changer de méthode.
             */
            Log::warning('Retour de connexion sociale illisible', [
                'fournisseur' => $choisi->value,
                'erreur' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'La connexion via '.$choisi->libelle()
                    .' n\'a pas abouti. Réessayez, ou utilisez votre mot de passe.',
            ]);
        }

        try {
            $utilisateur = $connexions->connecter(
                $choisi,
                $profil,
                (string) ($requete->ip() ?? '0.0.0.0'),
                (string) $requete->userAgent(),
            );
        } catch (ConnexionSocialeRefusee $refus) {
            // Le message de l'exception est écrit pour être lu.
            return redirect()->route('login')->withErrors(['email' => $refus->getMessage()]);
        }

        Auth::login($utilisateur, remember: true);

        // Régénération à l'élévation de privilège : neutralise la fixation de
        // session, où un attaquant impose son identifiant avant la connexion.
        Session::regenerate();

        $utilisateur->forceFill([
            'last_login_at' => now(),
            'last_login_ip_hash' => app(IdentityHasher::class)->hash((string) ($requete->ip() ?? '0.0.0.0')),
        ])->save();

        $journal->loginReussi($utilisateur);

        return redirect()->route($this->destination($utilisateur));
    }

    /**
     * Le fournisseur demandé, s'il est connu ET configuré.
     *
     * 404 dans tous les autres cas. Sans cette énumération, un `{provider}`
     * forgé irait instancier n'importe quel pilote de Socialite ; sans le
     * contrôle de configuration, un bouton mal branché produirait une erreur
     * du fournisseur au lieu d'un refus net.
     */
    private function fournisseur(string $nom): FournisseurSocial
    {
        $fournisseur = FournisseurSocial::tryFrom($nom);

        abort_if($fournisseur === null || ! $fournisseur->estConfigure(), 404);

        return $fournisseur;
    }

    /** Route d'arrivée selon le rôle — même règle qu'à la connexion classique. */
    private function destination(User $utilisateur): string
    {
        foreach (UserRole::cases() as $role) {
            if ($utilisateur->hasRole($role->value)) {
                return $role->routeApresConnexion();
            }
        }

        return UserRole::Citoyen->routeApresConnexion();
    }
}
