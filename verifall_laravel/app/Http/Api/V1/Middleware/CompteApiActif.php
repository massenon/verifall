<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un jeton valide ne suffit pas : le compte doit être ACTIF et VIVANT.
 *
 * Deux refus distincts, et c'est le point de ce middleware :
 *
 *  1. COMPTE NON ACTIVÉ. `otp_verified_at` nul : le compte existe mais
 *     personne n'a prouvé la maîtrise de l'adresse. Le web l'écarte déjà
 *     (`CompteActif`) ; sans équivalent ici, l'API rouvrirait exactement la
 *     porte que la vérification par code sert à fermer.
 *
 *  2. COMPTE EN COURS DE SUPPRESSION. Le RGPD ouvre trente jours de
 *     rétractation pendant lesquels la ligne survit. La laisser AGIR pendant
 *     ce délai serait absurde : on ne publie pas sur un forum depuis un compte
 *     qu'on a demandé à effacer, et on n'y dépense pas d'unités qui ne seront
 *     jamais remboursées.
 *
 * DEUX EXCEPTIONS, et seulement deux. `GET /me` reste ouvert : c'est ce qui
 * permet à l'application d'afficher « votre compte sera effacé le … » plutôt
 * qu'un mur d'erreur incompréhensible. `POST /auth/logout` reste ouvert :
 * refuser à quelqu'un de se déconnecter reviendrait à lui imposer de garder un
 * jeton vivant sur son téléphone.
 */
final class CompteApiActif
{
    /** Routes servies même à un compte en cours de suppression. */
    private const TOLEREES = ['api.v1.me.show', 'api.v1.auth.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User) {
            return Enveloppe::erreur(CodeErreur::NonAuthentifie);
        }

        if (! $utilisateur->estActive()) {
            return Enveloppe::erreur(CodeErreur::CompteInactif);
        }

        if ($utilisateur->deletion_requested_at !== null
            && ! in_array($request->route()?->getName(), self::TOLEREES, true)) {
            return Enveloppe::erreur(
                CodeErreur::CompteEnSuppression,
                'Votre compte est en cours de suppression. Annulez la demande depuis le lien reçu par courriel pour le réactiver.',
            );
        }

        return $next($request);
    }
}
