<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Marketing\Services\BoiteDeContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reçoit une intention de soutien depuis la page « Financement ».
 *
 * ## CE FORMULAIRE N'ENCAISSE RIEN, et le dit
 *
 * Aucun prestataire de paiement n'est en service : le pilote Mobile Money est
 * en mode simulé, et aucune clé Stripe ni PayPal n'est configurée. Un formulaire
 * qui prendrait un montant en donnant l'impression d'un paiement mentirait deux
 * fois : sur ce qui se passe, et sur ce qui est reçu.
 *
 * Il enregistre donc une INTENTION, et l'équipe rappelle pour convenir des
 * modalités. Le montant est du texte libre et déclaratif, jamais une somme
 * débitée.
 *
 * Le reste de la chaîne est celui du formulaire de contact : même limite de
 * cadence, même piège à robots, même boîte de réception, même réponse
 * indifférenciée quand un envoi est écarté.
 */
final class SoutienController
{
    public function __invoke(Request $request, BoiteDeContact $boite): RedirectResponse
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'organisation' => ['nullable', 'string', 'max:150'],
            /*
             | Texte libre, et non un décimal. Quelqu'un écrit « 50 € », « selon
             | mes moyens » ou « en nature, du temps de formation » : contraindre
             | le champ à un nombre écarterait les deux dernières réponses, qui
             | valent autant que la première.
             */
            'donation_amount' => ['nullable', 'string', 'max:80'],
            'donation_recurrence' => ['nullable', 'string', 'in:ponctuel,mensuel,annuel,autre'],
            'message' => ['nullable', 'string', 'max:5000'],

            'website' => ['nullable', 'string', 'max:200'],
            'rendu_a' => ['nullable', 'string', 'max:40'],
        ], attributes: [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'donation_amount' => 'montant',
            'message' => 'message',
        ]);

        $confirmation = 'Merci. Votre proposition de soutien est enregistrée, nous vous écrivons pour convenir des modalités.';

        if ($boite->ressembleAUnRobot($donnees) || $boite->tropDeMessagesRecents($request->ip())) {
            return redirect()->route('public.funding')->with('soutien', $confirmation);
        }

        $boite->recevoir([
            'kind' => 'don',
            'name' => $donnees['name'],
            'email' => $donnees['email'],
            'organisation' => $donnees['organisation'] ?? null,
            // Un soutien sans mot reste un soutien : le message est facultatif,
            // et son absence ne doit pas faire échouer l'envoi.
            'message' => $donnees['message'] ?? 'Proposition de soutien, sans message.',
            'donation_amount' => $donnees['donation_amount'] ?? null,
            'donation_recurrence' => $donnees['donation_recurrence'] ?? null,
        ], $request->ip());

        return redirect()->route('public.funding')->with('soutien', $confirmation);
    }
}
