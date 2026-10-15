<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Marketing\Services\Parrainage;
use App\Livewire\Auth\Register;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Une invitation ouverte — le lien et le QR code du parrainage.
 *
 * Le code est déposé en SESSION puis la personne est envoyée sur l'inscription.
 * Le porter dans l'URL du formulaire le ferait disparaître au premier retour en
 * arrière, et le parrain perdrait sa recommandation sans que personne comprenne
 * pourquoi.
 *
 * Un code inconnu ne produit AUCUNE erreur : quelqu'un qui a mal recopié un
 * lien reçu sur WhatsApp doit arriver sur l'inscription, pas sur une page 404.
 * Il perdra le parrainage, pas l'envie de s'inscrire.
 */
final class InvitationController
{
    public function __invoke(string $code, Parrainage $parrainage): RedirectResponse
    {
        $parrain = $parrainage->parrainDuCode($code);

        if ($parrain !== null) {
            // Un compte déjà connecté n'est pas un filleul potentiel : compter
            // sa visite gonflerait l'indicateur du parrain sans rien produire.
            if (! Auth::guard('web')->check()) {
                $parrainage->compterUneVisite($parrain);
                Session::put(Register::CLE_PARRAINAGE, $parrain->referral_code);
            }
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('citizen.dashboard');
        }

        return redirect()->route('register');
    }
}
