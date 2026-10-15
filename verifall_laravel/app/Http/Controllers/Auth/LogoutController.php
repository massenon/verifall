<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Identity\Services\SecurityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Déconnexion.
 *
 * En POST exclusivement (SECURITE_LOGIN : « aucun GET de déconnexion »). Un
 * lien GET se déclencherait depuis une simple balise <img> sur un site tiers,
 * ou par un préchargeur de navigateur : l'utilisateur se retrouverait
 * déconnecté sans l'avoir demandé.
 */
final class LogoutController
{
    public function __invoke(Request $request, SecurityLogService $journal): RedirectResponse
    {
        $utilisateur = $request->user();

        if ($utilisateur !== null) {
            $journal->record(SecurityLogService::DECONNEXION, 'info', $utilisateur->email, $utilisateur);
        }

        Auth::logout();

        // Invalider PUIS regénérer le jeton : sans invalidation, l'ancien
        // identifiant de session resterait exploitable ; sans regénération,
        // le jeton CSRF suivant serait celui de la session close.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.home')->with('toast', 'À bientôt 👋');
    }
}
