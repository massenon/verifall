<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;

/**
 * L'enveloppe de réponse — UNE seule forme, pour toute l'API.
 *
 * POURQUOI UNE ENVELOPPE PLUTÔT QUE L'OBJET NU. Renvoyer directement la
 * ressource oblige à inventer un canal parallèle pour tout ce qui n'est pas la
 * donnée : la page suivante, le solde après opération, un avertissement. Ce
 * canal finit dans des en-têtes propriétaires que personne ne documente.
 *
 * POURQUOI `error` PLUTÔT QU'UN CODE HTTP SEUL. Un client qui doit deviner la
 * forme de l'erreur affiche « une erreur est survenue », et c'est le pire
 * message possible. Ici l'échec porte toujours trois choses : un `code` stable
 * sur lequel le client branche son comportement, un `message` déjà rédigé en
 * français qu'il peut afficher tel quel, et des `details` par champ quand il
 * s'agit d'une validation.
 *
 * RÈGLE DE LECTURE CÔTÉ CLIENT, et il n'y en a qu'une :
 *
 *     si la réponse contient `error`, c'est un échec ; sinon `data` fait foi.
 *
 * `data` et `error` sont mutuellement exclusifs. `meta` peut accompagner les
 * deux — une erreur de débit y porte son délai d'attente.
 */
final class Enveloppe
{
    /**
     * Succès.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function donnee(
        JsonResource|ResourceCollection|Arrayable|array|string|int|bool|null $donnee,
        array $meta = [],
        int $statut = 200,
    ): JsonResponse {
        $corps = ['data' => $donnee];

        if ($meta !== []) {
            $corps['meta'] = $meta;
        }

        return response()->json($corps, $statut);
    }

    /**
     * Succès sans contenu utile — une action dont le résultat est « c'est fait ».
     *
     * `data: null` et non un corps vide : un client qui décode du JSON ne doit
     * jamais avoir à gérer le cas « rien à décoder ». C'est la même règle
     * partout, y compris ici.
     */
    public static function fait(int $statut = 200): JsonResponse
    {
        return response()->json(['data' => null], $statut);
    }

    /**
     * Page de liste, paginée PAR CURSEUR.
     *
     * @param  CursorPaginator<int, Model>  $page
     * @param  array<string, mixed>  $meta
     */
    public static function page(CursorPaginator $page, mixed $collection, array $meta = []): JsonResponse
    {
        return self::donnee($collection, array_merge($meta, [
            'page' => PageCurseur::meta($page),
        ]));
    }

    /**
     * Échec.
     *
     * @param  array<string, array<int, string>>  $details  erreurs par champ (validation)
     * @param  array<string, mixed>  $meta
     */
    public static function erreur(
        CodeErreur $code,
        ?string $message = null,
        array $details = [],
        array $meta = [],
    ): JsonResponse {
        $erreur = [
            'code' => $code->value,
            'message' => $message ?? $code->message(),
        ];

        if ($details !== []) {
            $erreur['details'] = $details;
        }

        $corps = ['error' => $erreur];

        if ($meta !== []) {
            $corps['meta'] = $meta;
        }

        return response()->json($corps, $code->statutHttp());
    }
}
