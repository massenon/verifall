<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Mail\LienSuppressionCompteMail;
use App\Mail\SuppressionProgrammeeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Voie de secours : suppression de compte par e-mail.
 *
 * Deux actions publiques, aucune session requise :
 *
 *   envoyerLien() — reçoit le formulaire, envoie un lien signé 24 h si
 *     l'adresse correspond à un compte actif. La réponse est TOUJOURS
 *     la même, que le compte existe ou non : ne pas le faire permettrait
 *     de découvrir quelle adresse est enregistrée.
 *
 *   confirmer() — honore le lien signé, ferme le compte et déclenche le
 *     délai de rétractation de 30 jours (même flux qu'in-app, Option A).
 */
final class SuppressionCompteController
{
    public function envoyerLien(Request $request, AuditLogService $audit): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
        ]);

        $confirmation = __('supprimer_mon_compte.message_confirmation');

        $utilisateur = User::query()
            ->where('email', $request->input('email'))
            ->first();

        if ($utilisateur !== null) {
            Mail::to($utilisateur)->queue(new LienSuppressionCompteMail($utilisateur));

            $audit->record(
                AuditLogService::MODIFICATION, 'account', null,
                cible: $utilisateur,
                cibleLibelle: 'Lien de suppression par e-mail envoyé',
            );
        }

        return redirect()
            ->route('public.account-deletion')
            ->with('suppression_lien', $confirmation);
    }

    public function confirmer(Request $request, string $uuid, SuppressionCompteService $suppressions): RedirectResponse
    {
        $utilisateur = User::query()->where('uuid', $uuid)->first();

        if ($utilisateur === null) {
            return redirect()
                ->route('public.account-deletion')
                ->with('suppression_confirmer', 'introuvable');
        }

        if ($utilisateur->deletion_requested_at !== null) {
            // Le compte est déjà en cours de suppression (double clic ou second
            // envoi) : ne rien faire, informer discrètement.
            return redirect()
                ->route('public.account-deletion')
                ->with('suppression_confirmer', 'deja_programme');
        }

        $suppressions->programmer($utilisateur);

        // Le même e-mail qu'après une suppression in-app : lien d'annulation
        // valable 30 jours, avec l'échéance d'effacement définitif.
        Mail::to($utilisateur)->queue(new SuppressionProgrammeeMail($utilisateur));

        return redirect()
            ->route('public.account-deletion')
            ->with('suppression_confirmer', 'reussie');
    }
}
