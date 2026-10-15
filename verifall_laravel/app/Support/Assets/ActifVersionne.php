<?php

declare(strict_types=1);

namespace App\Support\Assets;

use Illuminate\Support\Facades\Cache;

/**
 * L'adresse d'un actif statique, suffixée de sa date de modification.
 *
 * ## Le défaut que cela corrige
 *
 * Les feuilles et les scripts étaient servis sans aucun marqueur de version.
 * Un navigateur qui a mis `campagnes.css` en cache continue donc de servir
 * l'ancienne version après un correctif, parfois plusieurs jours.
 *
 * C'est la pire forme de défaut en apparence : le correctif est juste, il est
 * déployé, le serveur le sert bel et bien, et la personne qui a signalé le
 * problème continue de le voir. On corrige alors deux fois la même chose, en
 * cherchant la cause là où elle n'est pas.
 *
 * ## Pourquoi `filemtime` et non un condensat du contenu
 *
 * Un condensat exigerait de lire le fichier entier à chaque requête. La date de
 * modification est lue par le système de fichiers, ne coûte presque rien, et
 * change exactement quand le fichier change. Elle est en outre mise en cache
 * une heure : en service, ces fichiers ne bougent qu'au déploiement.
 *
 * ## Ce qui reste vrai si le fichier manque
 *
 * On renvoie l'adresse sans suffixe plutôt que de lever. Une feuille absente
 * est déjà un problème ; y ajouter une page 500 n'aide personne.
 */
final class ActifVersionne
{
    public static function url(string $chemin): string
    {
        $version = Cache::remember(
            'actif-version:'.$chemin,
            now()->addHour(),
            static function () use ($chemin): ?int {
                $absolu = public_path($chemin);

                if (! is_file($absolu)) {
                    return null;
                }

                $date = filemtime($absolu);

                return $date === false ? null : $date;
            },
        );

        $url = asset($chemin);

        return $version === null ? $url : $url.'?v='.$version;
    }
}
