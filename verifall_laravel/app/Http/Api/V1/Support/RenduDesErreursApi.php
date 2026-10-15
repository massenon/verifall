<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Toute erreur de l'API sort par la même porte, dans la même enveloppe.
 *
 * POURQUOI C'EST LE POINT LE PLUS IMPORTANT DU CONTRAT. Un client qui doit
 * deviner la forme de l'erreur n'essaie pas : il affiche « une erreur est
 * survenue » et laisse l'utilisateur sans recours. Ici, chaque échec porte un
 * `code` stable sur lequel brancher un comportement — proposer une recharge,
 * redemander un jeton, réessayer plus tard — et un `message` déjà rédigé en
 * français, affichable tel quel.
 *
 * SANS CE RENDU, un simple 404 de route model binding renverrait la page HTML
 * d'erreur du site à un client qui attend du JSON, et l'application afficherait
 * un écran blanc en croyant à une panne réseau.
 *
 * CE QUI N'EST JAMAIS RENVOYÉ : le message d'une exception non prévue. Ces
 * messages portent des noms de classes, des chemins de fichiers, parfois des
 * fragments de requête SQL. Ils partent au journal ; le client reçoit une
 * formulation neutre.
 */
final class RenduDesErreursApi
{
    public static function enregistrer(Exceptions $exceptions): void
    {
        $exceptions->render(static function (Throwable $exception, Request $requete) {
            if (! self::concerneLApi($requete)) {
                return null;
            }

            return match (true) {
                $exception instanceof ValidationException => Enveloppe::erreur(
                    CodeErreur::Validation,
                    details: $exception->errors(),
                ),

                // Jeton absent, révoqué, ou expiré : les trois se répondent
                // pareil. Distinguer « révoqué » de « absent » indiquerait à
                // qui essaie qu'un jeton a bel et bien existé.
                $exception instanceof AuthenticationException => Enveloppe::erreur(
                    CodeErreur::NonAuthentifie,
                ),

                $exception instanceof AuthorizationException => Enveloppe::erreur(
                    CodeErreur::DroitManquant,
                ),

                // Route model binding qui n'a rien trouvé. Le message par
                // défaut nomme la CLASSE du modèle — « App\Models\Course » —
                // ce qui renseigne sur la structure interne sans rien
                // apporter au client.
                $exception instanceof ModelNotFoundException,
                $exception instanceof NotFoundHttpException => Enveloppe::erreur(
                    CodeErreur::Introuvable,
                ),

                $exception instanceof MethodNotAllowedHttpException => Enveloppe::erreur(
                    CodeErreur::MethodeRefusee,
                ),

                $exception instanceof TooManyRequestsHttpException => Enveloppe::erreur(
                    CodeErreur::DebitDepasse,
                    meta: self::delai($exception),
                )->withHeaders($exception->getHeaders()),

                /*
                 | LE DÉPASSEMENT DE DÉBIT ARRIVE ICI, ET NON À LA BRANCHE
                 | CI-DESSUS.
                 |
                 | Les trois limiteurs d'`ApiServiceProvider` attachent tous
                 | `->response(...)`. `ThrottleRequests::buildException` emballe
                 | alors la réponse dans une `HttpResponseException` — et
                 | `renderViaCallbacks()` s'exécute AVANT la branche du
                 | framework qui la déballe.
                 |
                 | Sans cette ligne, chaque dépassement tombait dans `default`
                 | et sortait en **500**, `Retry-After` et `X-RateLimit-*`
                 | retirés au passage. Un client qui sonde l'écran d'analyse
                 | toutes les trois secondes voyait donc un plantage et, privé
                 | de tout signal de temporisation, continuait de frapper.
                 |
                 | La branche `TooManyRequestsHttpException` ci-dessus était,
                 | de fait, du code mort.
                 */
                $exception instanceof HttpResponseException => $exception->getResponse(),

                default => self::inattendue($exception),
            };
        });
    }

    /**
     * Ne s'applique qu'aux routes de l'API.
     *
     * Testé sur le CHEMIN et non sur l'en-tête `Accept` : une page du site
     * appelée en XHR envoie elle aussi `Accept: application/json`, et
     * détourner ses erreurs vers cette enveloppe casserait Livewire.
     */
    private static function concerneLApi(Request $requete): bool
    {
        return $requete->is('api/*');
    }

    /**
     * @return array<string, mixed>
     */
    private static function delai(HttpExceptionInterface $exception): array
    {
        $entetes = $exception->getHeaders();

        return isset($entetes['Retry-After'])
            ? ['retry_after' => (int) $entetes['Retry-After']]
            : [];
    }

    private static function inattendue(Throwable $exception): JsonResponse
    {
        /*
         | Une exception HTTP portée à la main — `abort(403, '…')` — garde son
         | statut, mais pas son message : ces messages sont écrits pour un
         | développeur, pas pour quelqu'un dans un taxi à Cotonou.
         */
        if ($exception instanceof HttpExceptionInterface) {
            $code = match ($exception->getStatusCode()) {
                401 => CodeErreur::NonAuthentifie,
                403 => CodeErreur::DroitManquant,
                404 => CodeErreur::Introuvable,
                405 => CodeErreur::MethodeRefusee,
                409 => CodeErreur::Conflit,
                422 => CodeErreur::Validation,
                429 => CodeErreur::DebitDepasse,
                default => CodeErreur::ErreurServeur,
            };

            return Enveloppe::erreur($code);
        }

        // En développement, le message reste lisible : l'écraser rendrait
        // l'API impossible à déboguer sans ouvrir les journaux à chaque appel.
        return Enveloppe::erreur(
            CodeErreur::ErreurServeur,
            config('app.debug') === true ? $exception->getMessage() : null,
        );
    }
}
