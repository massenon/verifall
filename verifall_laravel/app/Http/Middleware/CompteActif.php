<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'espace citoyen exige un compte AUTHENTIFIÉ et ACTIVÉ.
 *
 * Deux conditions, pas une : un compte dont l'OTP n'a jamais été validé existe
 * en base mais n'a fait la preuve d'aucune maîtrise de l'adresse e-mail. Le
 * laisser entrer reviendrait à ouvrir l'espace personnel à quiconque connaît
 * une adresse — exactement ce que la vérification par code sert à empêcher.
 *
 * La session est fermée dans ces cas, plutôt que simplement redirigée : garder
 * ouverte une session qu'on refuse d'honorer entretient l'ambiguïté.
 *
 * Un TROISIÈME cas s'y ajoute depuis le 2 septembre 2026 : le compte suspendu.
 * Il porte son propre drapeau de session, parce que l'écran de connexion n'a pas
 * la même chose à dire à qui n'a jamais validé son code qu'à qui vient d'être
 * sanctionné.
 */
final class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = Auth::guard('web')->user();

        if ($utilisateur === null) {
            return redirect()->guest(route('login'));
        }

        if (! $utilisateur->estActive()) {
            return $this->refuser($request, 'compte_inactif');
        }

        /*
         | La suspension ferme déjà les sessions au moment où elle est
         | prononcée. Ce contrôle-ci est le filet : il rattrape une session
         | rouverte depuis un « se souvenir de moi », et une suspension posée
         | autrement qu'en passant par le service.
         |
         | Drapeau DISTINCT de `compte_inactif` : l'écran de connexion n'a pas
         | la même chose à dire dans les deux cas, et un seul drapeau
         | l'obligerait à choisir le message le plus vague des deux.
         */
        if ($utilisateur->estSuspendu()) {
            return $this->refuser($request, 'compte_suspendu');
        }

        return $next($request);
    }

    /**
     * Ferme la session et renvoie à la connexion, en disant pourquoi.
     *
     * La session est fermée plutôt que simplement redirigée : garder ouverte une
     * session qu'on refuse d'honorer entretient l'ambiguïté.
     */
    private function refuser(Request $request, string $drapeau): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with($drapeau, true);
    }
}
