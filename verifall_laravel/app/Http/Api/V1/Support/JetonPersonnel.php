<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use App\Http\Api\V1\Resources\MoiResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Émission d'un jeton personnel Sanctum, et la réponse qui l'accompagne.
 *
 * TROIS POINTS D'ENTRÉE émettent un jeton : la connexion, la validation du
 * code d'activation et la réinitialisation du mot de passe. Ils doivent
 * renvoyer EXACTEMENT la même chose, sans quoi le client aurait trois façons
 * de lire un jeton et n'en testerait qu'une.
 *
 * LE JETON EN CLAIR N'EXISTE QU'ICI, une fois. Sanctum n'en garde que
 * l'empreinte : il n'y a aucun endpoint pour le relire, et c'est voulu — un
 * jeton relisible est un jeton qu'une faille de lecture suffit à voler. Le
 * client le range dans `flutter_secure_storage` dès réception.
 *
 * L'EXPIRATION EST GLISSANTE : chaque appel authentifié repousse l'échéance
 * (voir `RafraichirLeJeton`). Une application dont on se sert ne redemande
 * donc jamais le mot de passe ; un téléphone perdu et oublié se referme tout
 * seul au bout de la durée configurée.
 */
final class JetonPersonnel
{
    /**
     * Nom par défaut d'un appareil non nommé.
     *
     * Volontairement générique : le client peut envoyer « Pixel 7 de Rita »,
     * et c'est ce qui apparaîtra dans la future liste des appareils. Faute de
     * quoi, un libellé neutre vaut mieux qu'un identifiant technique que
     * personne ne saura relier à un objet réel.
     */
    private const APPAREIL_PAR_DEFAUT = 'Application mobile';

    public static function reponse(User $utilisateur, ?string $appareil = null): JsonResponse
    {
        $expiration = now()->addMinutes((int) config('api.token_ttl_minutes'));

        $jeton = $utilisateur->createToken(
            $appareil !== null && trim($appareil) !== '' ? trim($appareil) : self::APPAREIL_PAR_DEFAUT,
            ['*'],
            $expiration,
        );

        return Enveloppe::donnee([
            'token' => $jeton->plainTextToken,
            'type' => 'Bearer',
            'expire_le' => $expiration->toIso8601String(),
            'utilisateur' => MoiResource::make($utilisateur),
        ]);
    }
}
