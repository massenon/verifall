<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejouer une écriture sans la refaire deux fois.
 *
 * POURQUOI CE MIDDLEWARE EXISTE. L'application mobile doit fonctionner hors
 * réseau : les actions partent dans une file locale et sont rejouées à la
 * reconnexion. Or une réponse perdue est indiscernable d'une requête perdue.
 * Le téléphone qui ne reçoit rien ne sait pas si la vérification a été lancée
 * — et s'il rejoue, la personne est débitée deux fois pour une seule question.
 *
 * Le client génère un UUID par ACTE (pas par tentative) et le place dans
 * `Idempotency-Key`. La première tentative exécute et mémorise la réponse ;
 * toute reprise porteuse de la même clé reçoit la réponse mémorisée, à
 * l'identique, sans rien réexécuter.
 *
 * CE QUE CE MIDDLEWARE NE FAIT PAS. Il ne rend pas les services idempotents :
 * ceux qui le sont déjà par contrainte d'unicité — inscription à un cours,
 * complétion d'une leçon — n'en ont pas besoin. Il couvre ce qui ne peut PAS
 * l'être par la base : un débit, une soumission, une tentative de quiz.
 *
 * La clé est portée par le COMPTE : deux personnes qui tireraient le même UUID
 * ne doivent pas se voler leur réponse, et une clé recopiée depuis un autre
 * appareil ne doit rien révéler.
 */
final class Idempotence
{
    private const ENTETE = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $cle = $request->header(self::ENTETE);
        $utilisateur = $request->user();

        // En-tête facultatif : sans lui, on exécute normalement. L'imposer
        // rendrait l'API pénible à essayer, et le risque qu'il couvre ne
        // concerne que le rejeu, que seul le client sait déclencher.
        if ($cle === null || $cle === '' || $utilisateur === null) {
            return $next($request);
        }

        // Bornée : une clé de 4 Ko stockée telle quelle serait un vecteur de
        // saturation du cache.
        if (mb_strlen($cle) > 128) {
            return Enveloppe::erreur(
                CodeErreur::Validation,
                'La clé d\'idempotence ne doit pas dépasser 128 caractères.',
            );
        }

        $empreinte = 'api:idem:'.$utilisateur->getAuthIdentifier().':'.hash('sha256', $cle);

        $memorisee = Cache::get($empreinte);

        if (is_array($memorisee)) {
            return response($memorisee['corps'], $memorisee['statut'])
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replay', 'true');
        }

        $reponse = $next($request);

        /*
         | Seuls les SUCCÈS sont mémorisés. Rejouer un échec figerait une
         | erreur passagère — un solde momentanément insuffisant, une coupure
         | d'un moteur d'analyse — et le client réessaierait éternellement
         | contre un cache.
         */
        if ($reponse->getStatusCode() < 300) {
            Cache::put($empreinte, [
                'statut' => $reponse->getStatusCode(),
                'corps' => $reponse->getContent(),
            ], now()->addHours((int) config('api.idempotence_heures', 24)));
        }

        return $reponse;
    }
}
