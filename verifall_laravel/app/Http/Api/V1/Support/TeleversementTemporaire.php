<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Le fichier monte d'abord, la soumission suit.
 *
 * POURQUOI DEUX APPELS AU LIEU D'UN MULTIPART UNIQUE. Le prototype mobile le
 * pose ainsi (`POST /uploads` puis `POST /verifications`), et la raison tient
 * au réseau béninois autant qu'à l'idempotence : une vidéo de vingt mégaoctets
 * envoyée sur une connexion instable échoue une fois sur trois. Si le fichier
 * voyageait avec la soumission, chaque reprise le renverrait en entier — et
 * une soumission rejouée après une réponse perdue le renverrait encore.
 * Découpé, le téléversement se reprend seul, et la soumission qui suit ne pèse
 * que quelques centaines d'octets, ce qui la rend rejouable sans douleur.
 *
 * CE QUI N'EST PAS FAIT ICI. Aucune règle métier : ni tarif, ni empreinte de
 * contenu, ni débit. Le fichier est déposé sur le disque PRIVÉ et rendu
 * ensuite à `SoumissionVerificationService`, qui le range et le compte comme
 * il le fait déjà pour l'écran web. C'est de la plomberie de transport.
 *
 * LE JETON RENDU EST LIÉ AU COMPTE : un identifiant intercepté ne permet pas
 * de faire analyser le fichier d'un autre, ni d'en découvrir l'existence.
 */
final class TeleversementTemporaire
{
    /** Dossier du disque privé où patientent les fichiers non encore soumis. */
    private const DOSSIER = 'televersements';

    /**
     * Durée de vie d'un téléversement en attente, en minutes.
     *
     * Deux heures : de quoi remplir un formulaire, changer d'avis, revenir.
     * Au-delà, le fichier est un orphelin — et la purge des orphelins existe
     * déjà côté `SoumissionVerificationService`.
     */
    private const MINUTES = 120;

    /**
     * Dépose un fichier et rend son identifiant.
     */
    public static function deposer(User $utilisateur, UploadedFile $fichier): string
    {
        $identifiant = (string) Str::uuid();

        // Nom aléatoire, jamais le nom d'origine : celui-ci vient du client,
        // peut porter un chemin, une extension double, ou le prénom de
        // quelqu'un. Il n'est conservé que comme libellé.
        $chemin = $fichier->storeAs(
            self::DOSSIER,
            $identifiant.'.'.$fichier->extension(),
            'local',
        );

        Cache::put(self::cle($utilisateur, $identifiant), [
            'chemin' => $chemin,
            'nom' => $fichier->getClientOriginalName(),
            'mime' => $fichier->getMimeType(),
        ], now()->addMinutes(self::MINUTES));

        return $identifiant;
    }

    /**
     * Reprend un fichier déposé, sous la forme d'un `UploadedFile`.
     *
     * Rend `null` si l'identifiant est inconnu, expiré, ou appartient à
     * quelqu'un d'autre — les trois cas se répondent pareil : « ce
     * téléversement a expiré ». Distinguer « inconnu » de « pas à vous »
     * transformerait ce point d'entrée en oracle.
     */
    public static function reprendre(User $utilisateur, string $identifiant): ?UploadedFile
    {
        $entree = Cache::get(self::cle($utilisateur, $identifiant));

        if (! is_array($entree) || ! Storage::disk('local')->exists($entree['chemin'])) {
            return null;
        }

        /*
         | `test: true` en dernier argument : le fichier n'est PAS le produit
         | d'un envoi HTTP en cours, il est déjà sur notre disque. Sans ce
         | drapeau, Symfony refuserait de le déplacer en jugeant qu'il n'a pas
         | été téléversé — et la soumission échouerait sans raison lisible.
         */
        return new UploadedFile(
            Storage::disk('local')->path($entree['chemin']),
            (string) $entree['nom'],
            $entree['mime'] !== null ? (string) $entree['mime'] : null,
            null,
            true,
        );
    }

    /** Oublie un téléversement une fois consommé. */
    public static function oublier(User $utilisateur, string $identifiant): void
    {
        Cache::forget(self::cle($utilisateur, $identifiant));
    }

    private static function cle(User $utilisateur, string $identifiant): string
    {
        return 'api:upload:'.$utilisateur->getAuthIdentifier().':'.$identifiant;
    }
}
