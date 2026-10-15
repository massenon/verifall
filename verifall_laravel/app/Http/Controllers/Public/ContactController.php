<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Marketing\Services\BoiteDeContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reçoit le formulaire de contact public.
 *
 * ## Ce qu'il remplace
 *
 * Rien. Le prototype n'avait aucune route de contact : le formulaire portait
 * `data-vf-form`, dont le script appelle `preventDefault()`, attend 900 ms et
 * affiche « Message envoyé ! Notre équipe vous répondra très vite. »
 * **Quelqu'un écrivait, était remercié, et son message n'arrivait nulle part.**
 *
 * ## La réponse est la MÊME dans les trois cas
 *
 * Message accepté, piège à robots déclenché, rafale détectée : la personne voit
 * toujours la même confirmation. Ce n'est pas de la négligence — c'est ce qui
 * empêche de se servir du formulaire pour découvrir où sont les filtres. Un
 * robot à qui l'on répond « rejeté » apprend quoi changer ; un robot remercié
 * n'apprend rien.
 *
 * Les cas écartés sont journalisés côté serveur, là où ils servent à quelqu'un.
 */
final class ContactController
{
    public function __invoke(Request $request, BoiteDeContact $boite): RedirectResponse
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            /*
             | `rfc` seul, sans `dns` : la vérification DNS interroge un
             | résolveur PENDANT la requête, ce qui ajoute une latence variable
             | et fait échouer l'envoi quand le résolveur est indisponible. Sur
             | un formulaire où quelqu'un signale peut-être une intox, refuser
             | pour cette raison-là serait le pire des compromis.
             */
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            // Facultatifs au prototype : ils le restent. Exiger un téléphone
            // écarterait des gens qui n'en donnent pas volontiers.
            'phone' => ['nullable', 'string', 'max:40'],
            'organisation' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],

            // Le piège et l'horodatage. Déclarés pour traverser la validation ;
            // jamais enregistrés.
            'website' => ['nullable', 'string', 'max:200'],
            'rendu_a' => ['nullable', 'string', 'max:40'],
        ], messages: [
            'message.min' => 'Décrivez votre demande en quelques mots de plus.',
        ], attributes: [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'phone' => 'téléphone',
            'organisation' => 'organisation',
            'message' => 'message',
        ]);

        $confirmation = 'Message bien reçu. Notre équipe vous répondra à l\'adresse indiquée.';

        if ($boite->ressembleAUnRobot($donnees) || $boite->tropDeMessagesRecents($request->ip())) {
            // Rien n'est écrit, rien n'est envoyé, et la réponse est identique.
            return redirect()->route('public.contact')->with('contact', $confirmation);
        }

        $boite->recevoir([
            'name' => $donnees['name'],
            'email' => $donnees['email'],
            'phone' => $donnees['phone'] ?? null,
            'organisation' => $donnees['organisation'] ?? null,
            'message' => $donnees['message'],
        ], $request->ip());

        return redirect()->route('public.contact')->with('contact', $confirmation);
    }
}
