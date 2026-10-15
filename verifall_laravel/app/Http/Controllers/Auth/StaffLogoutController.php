<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\SecurityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Déconnexion du back-office.
 *
 * POST exclusivement, comme côté citoyen. La différence tient à la
 * journalisation : une déconnexion d'administrateur alimente le JOURNAL
 * D'AUDIT en plus du journal de sécurité (LOGIN_DECONNEXION : « connexion /
 * déconnexion → audit log »). Sans cette trace, l'historique d'une session
 * d'administration s'arrête sans qu'on sache quand elle s'est close.
 */
final class StaffLogoutController
{
    public function __invoke(
        Request $request,
        AuditLogService $audit,
        SecurityLogService $journal,
    ): RedirectResponse {
        $utilisateur = Auth::guard('staff')->user();

        if ($utilisateur !== null) {
            $audit->deconnexionStaff($utilisateur);
            $journal->record(SecurityLogService::DECONNEXION, 'info', $utilisateur->email, $utilisateur);
        }

        Auth::guard('staff')->logout();

        /*
         | La session entière est invalidée, et non le seul guard staff.
         |
         | Conserver la session en vie laisserait l'horodatage d'inactivité et
         | d'éventuels résidus d'état d'un poste d'administration sur une
         | machine peut-être partagée. Le citoyen éventuellement connecté sur
         | le même navigateur est déconnecté aussi : c'est le comportement
         | prudent, et l'on ne mélange pas les deux espaces de toute façon.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login')->with('toast', 'Session fermée.');
    }
}
