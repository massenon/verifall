<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Essaie plusieurs clés d'API à tour de rôle, avec un disjoncteur par clé.
 *
 * Repris du moteur de juillet, qui avait identifié le problème réel : le quota
 * gratuit de Gemini s'épuise en quelques heures, et avec une seule clé la
 * plateforme s'arrête net au milieu de la journée. Le remède est de faire
 * tourner plusieurs clés et de mettre au repos celle qui vient de refuser.
 *
 * Le disjoncteur est posé PAR CLÉ et non par service : une clé épuisée ne doit
 * pas condamner les autres. Sa durée est volontairement courte — quelques
 * minutes — parce qu'un quota se recharge, et qu'un repos trop long punirait
 * tous les utilisateurs pour un seul dépassement.
 *
 * Aucune clé n'apparaît dans les journaux : seulement son RANG.
 */
final class RotationDeCles
{
    /**
     * Repos d'une clé REFUSÉE — six heures.
     *
     * Long au regard des cinq minutes d'un quota, et volontairement : une clé
     * révoquée ne redevient pas valide dans la journée. Assez long pour qu'elle
     * cesse de ralentir chaque analyse ; assez court pour qu'une clé rétablie
     * chez le fournisseur reparte sans déploiement.
     */
    private const REPOS_CLE_REFUSEE_MINUTES = 360;

    public function __construct(private readonly string $service) {}

    /**
     * Exécute l'appel avec la première clé disponible.
     *
     * Le callback reçoit la clé et rend :
     *   · un résultat non-null → succès, on s'arrête là ;
     *   · null → échec ordinaire, on passe à la clé suivante ;
     *   · en levant `QuotaEpuise` → la clé est mise au repos.
     *
     * @template T
     *
     * @param  list<string>  $cles
     * @param  Closure(string): ?T  $appel
     * @return T|null
     */
    public function essayer(array $cles, Closure $appel, int $reposMinutes = 3): mixed
    {
        if ($cles === []) {
            return null;
        }

        foreach ($cles as $rang => $cle) {
            if ($this->auRepos($rang)) {
                continue;
            }

            try {
                $resultat = $appel($cle);

                if ($resultat !== null) {
                    return $resultat;
                }
            } catch (QuotaEpuise) {
                $this->mettreAuRepos($rang, $reposMinutes);
            } catch (CleRefusee) {
                /*
                 | Une clé REFUSÉE ne se recharge pas. La reposer aussi
                 | brièvement qu'un quota revenait à la réessayer toutes les
                 | cinq minutes, indéfiniment, en la comptant comme saine dans
                 | la rotation — et rien ne le disait.
                 |
                 | Le repos long n'est pas une punition : c'est ce qui rend le
                 | manque VISIBLE. La page « Santé du système » compte les clés
                 | vivantes, et une clé morte doit y apparaître morte.
                 |
                 | Pas d'oubli définitif pour autant : une clé peut être
                 | rétablie côté fournisseur, et un cache qu'on ne relâche
                 | jamais demanderait un déploiement pour s'en apercevoir.
                 */
                $this->mettreAuRepos($rang, self::REPOS_CLE_REFUSEE_MINUTES, refusee: true);
            }
        }

        return null;
    }

    /**
     * Toutes les clés sont-elles au repos ?
     *
     * Sert à écarter le moteur d'emblée plutôt qu'à parcourir des clés dont on
     * sait déjà qu'elles refuseront — et à afficher un état honnête sur la page
     * « Santé du système ».
     *
     * @param  list<string>  $cles
     */
    public function toutesAuRepos(array $cles): bool
    {
        if ($cles === []) {
            return true;
        }

        foreach (array_keys($cles) as $rang) {
            if (! $this->auRepos($rang)) {
                return false;
            }
        }

        return true;
    }

    private function auRepos(int $rang): bool
    {
        return Cache::has($this->cle($rang));
    }

    private function mettreAuRepos(int $rang, int $minutes, bool $refusee = false): void
    {
        Cache::put($this->cle($rang), true, now()->addMinutes($minutes));

        // Le RANG, jamais la clé : un journal se relit, se copie et s'exporte.
        $contexte = [
            'service' => $this->service,
            'rang' => $rang,
            'minutes' => $minutes,
        ];

        /*
         | Deux niveaux, parce que les deux situations n'appellent pas le même
         | geste : un quota se SUBIT — il se rechargera —, une clé refusée se
         | REMPLACE. Les journaliser pareil laissait la seconde se noyer dans
         | les avertissements de la première.
         */
        if ($refusee) {
            Log::error('Clé d\'API REFUSÉE par le fournisseur', $contexte + [
                'indice' => 'Révoquée, mal recopiée, ou sans les droits demandés : elle ne se rechargera pas seule.',
            ]);

            return;
        }

        Log::warning('Clé d\'API mise au repos', $contexte);
    }

    private function cle(int $rang): string
    {
        return 'api_key_down:'.$this->service.':'.$rang;
    }
}
