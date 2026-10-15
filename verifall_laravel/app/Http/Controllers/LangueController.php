<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Langue;
use App\Http\Middleware\ChoisirLaLangue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bascule la langue, et revient d'où l'on vient.
 *
 * ## Pourquoi un POST et non un lien
 *
 * Changer la langue MODIFIE l'état de la session. Un GET qui modifie l'état
 * se déclenche par un préchargement de navigateur ou une image distante posée
 * sur un autre site : quelqu'un verrait sa langue changer sans avoir rien
 * cliqué, et ne comprendrait pas pourquoi.
 *
 * ## Le retour est VÉRIFIÉ
 *
 * `back()` suit l'en-tête `Referer`, que n'importe qui peut forger. Sans
 * contrôle, le sélecteur de langue devient une redirection ouverte : un lien
 * habillé aux couleurs du site renverrait ailleurs. On ne revient donc que
 * vers une adresse de ce domaine.
 */
final class LangueController
{
    public function __invoke(Request $request, string $langue): RedirectResponse
    {
        $choisie = Langue::tryFrom($langue);

        if ($choisie === null) {
            // Une langue inconnue ne casse rien : on repart sans rien changer.
            return $this->retour($request);
        }

        $request->session()->put(ChoisirLaLangue::CLEF_SESSION, $choisie->value);

        /*
         | Enregistrée sur le compte, pour que le choix suive d'un appareil à
         | l'autre. `forceFill` : `locale` n'est pas dans `$fillable`, afin
         | qu'une requête forgée ne puisse pas l'écrire par assignation de
         | masse.
         */
        $utilisateur = $request->user();

        if ($utilisateur !== null) {
            $utilisateur->forceFill(['locale' => $choisie->value])->save();
        }

        return $this->retour($request);
    }

    private function retour(Request $request): RedirectResponse
    {
        $precedent = (string) $request->headers->get('referer', '');

        if ($precedent !== '' && str_starts_with($precedent, $request->getSchemeAndHttpHost())) {
            return redirect()->to($precedent);
        }

        // À défaut d'origine sûre, l'accueil. Jamais l'adresse fournie.
        return redirect()->route('public.home');
    }
}
