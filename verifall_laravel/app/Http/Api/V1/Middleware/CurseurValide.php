<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un curseur illisible ne doit pas faire tomber le serveur.
 *
 * LE DÉFAUT QUE ÇA CORRIGE. `?cursor=eyJpZCI6ImFiYyJ9` — un curseur bien formé
 * en base64, mais auquel manque la clé interne de Laravel — produisait un
 * **500**. Or c'est un cas parfaitement ordinaire côté mobile : un curseur
 * gardé d'une session précédente, recopié depuis une autre liste, ou tronqué
 * par un lien profond. Rendre une erreur serveur sur une valeur fournie par le
 * client est doublement fautif : ça ressemble à une panne de la plateforme, et
 * en développement ça expose une trace d'exécution.
 *
 * Réponse : **422**, avec le champ en cause. Le client sait alors quoi faire —
 * oublier son curseur et recharger la liste depuis le début — là où un 500
 * l'aurait envoyé réessayer indéfiniment la même requête fautive.
 *
 * Placé sur tout le groupe plutôt qu'appelé dans chaque contrôleur : six
 * endpoints paginent aujourd'hui, il y en aura d'autres, et un garde qu'on
 * doit penser à appeler est un garde qu'on oubliera.
 */
final class CurseurValide
{
    public function handle(Request $request, Closure $next): Response
    {
        $curseur = $request->query('cursor');

        if (! is_string($curseur) || $curseur === '') {
            return $next($request);
        }

        if (! $this->lisible($curseur)) {
            return Enveloppe::erreur(CodeErreur::Validation, null, [
                'cursor' => ['Ce curseur n\'est plus valide. Rechargez la liste depuis le début.'],
            ]);
        }

        return $next($request);
    }

    /**
     * Reproduit le décodage de `Illuminate\Pagination\Cursor::fromEncoded`,
     * mais SANS supposer que la clé interne est présente.
     *
     * Un base64 illisible est déjà traité par Laravel — il rend `null`, ce qui
     * revient à « pas de curseur », et la première page s'affiche. C'est
     * acceptable. Seule l'absence de `_pointsToNextItems` explosait.
     */
    private function lisible(string $curseur): bool
    {
        $decode = base64_decode(str_replace(['-', '_'], ['+', '/'], $curseur), true);

        if ($decode === false) {
            // Laravel retombera de lui-même sur « aucun curseur ».
            return true;
        }

        $parametres = json_decode($decode, true);

        if (! is_array($parametres)) {
            return true;
        }

        return array_key_exists('_pointsToNextItems', $parametres);
    }
}
