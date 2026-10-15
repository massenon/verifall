<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Marketing\Services\Infolettre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * L'abonnement vu du dehors : s'inscrire, confirmer, partir.
 *
 * Les trois actions sont PUBLIQUES et sans session, et c'est délibéré pour
 * chacune :
 *
 *   · s'inscrire — l'infolettre s'adresse aussi à qui n'a pas de compte ;
 *   · confirmer — le clic vient d'une boîte mail, pas d'un navigateur connecté ;
 *   · se désabonner — exiger une connexion pour sortir d'une liste est le
 *     meilleur moyen d'être signalé comme indésirable, et le RGPD attend
 *     exactement l'inverse : le retrait doit être aussi simple que l'adhésion.
 *
 * Aucune règle métier ici. Le contrôleur traduit une requête HTTP en appel de
 * service et un résultat en redirection — rien d'autre.
 */
final class AbonnementController
{
    /**
     * Inscription — une seule réponse, quoi qu'il advienne.
     *
     * Adresse nouvelle, adresse en attente, adresse déjà abonnée, adresse
     * revenue après un départ : la redirection et le message sont RIGOUREUSEMENT
     * identiques. Le contraire ferait de ce formulaire un annuaire — on y
     * saisirait l'adresse d'un journaliste pour savoir s'il lit Vérif'All, et le
     * défaut serait le même que sur un écran de connexion qui répond « ce compte
     * n'existe pas ».
     */
    public function souscrire(Request $request, Infolettre $infolettre): RedirectResponse
    {
        $donnees = $request->validate([
            /*
             | `rfc` seul, sans `dns` — contrairement à l'inscription.
             |
             | La vérification DNS interroge un résolveur PENDANT la requête :
             | sur un formulaire public, cela ajoute une latence variable et
             | fait échouer l'abonnement quand le résolveur est indisponible.
             | Ici, le double opt-in rend ce contrôle superflu : une adresse
             | inexistante ne confirmera jamais, et n'entrera donc jamais dans
             | la liste d'envoi.
             */
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'nom' => ['nullable', 'string', 'max:120'],
        ], attributes: ['email' => 'adresse e-mail', 'nom' => 'nom']);

        $infolettre->abonner(
            $donnees['email'],
            $donnees['nom'] ?? null,
            $request->ip(),
        );

        return back()->with('infolettre', 'verifiez_votre_boite');
    }

    /**
     * Confirmation du double opt-in.
     *
     * Un jeton inconnu ne produit pas d'erreur : il produit un message. Les
     * clients mail coupent les URL longues, et quelqu'un qui a recopié un lien
     * à la main mérite une explication, pas une page 404.
     */
    public function confirmer(string $jeton, Infolettre $infolettre): RedirectResponse
    {
        $abonne = $infolettre->confirmer($jeton);

        return redirect()->route('public.home')
            ->with('infolettre', $abonne !== null ? 'confirmee' : 'lien_invalide');
    }

    /**
     * Désabonnement en un clic, SANS connexion.
     *
     * L'authenticité vient de la signature de l'URL, vérifiée par le middleware
     * `signed` en amont : un identifiant modifié dans la barre d'adresse produit
     * un 403, et il est donc impossible de désabonner quelqu'un d'autre.
     *
     * Aucune page de confirmation intermédiaire : « êtes-vous sûr ? » sur un
     * départ est une friction qui n'existe que pour retenir, et elle se paie en
     * signalements pour indésirable.
     */
    public function desabonner(string $uuid, Infolettre $infolettre): RedirectResponse
    {
        $retire = $infolettre->desabonner($uuid);

        return redirect()->route('public.home')
            ->with('infolettre', $retire ? 'desabonnee' : 'lien_invalide');
    }
}
