<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Les trois plafonds de l'API, et la façon de les annoncer.
 *
 * TROIS ET NON UN, parce qu'il y a trois risques qui n'ont rien à voir :
 *
 *   lecture  — la charge d'un client qui boucle. Généreux : l'écran de suivi
 *              d'analyse interroge l'avancement toutes les trois secondes, et
 *              se faire éconduire à ce moment-là ferait croire à une panne.
 *   ecriture — l'abus applicatif : spam du forum, signalements en rafale,
 *              recharges répétées.
 *   identite — le forçage de mot de passe et l'envoi massif de courriels.
 *
 * LA CLÉ EST LE JETON QUAND IL Y EN A UN, l'adresse IP sinon. Compter par
 * adresse seule punirait tout un cybercafé — ou tout un opérateur derrière un
 * NAT, ce qui est la norme sur le mobile au Bénin. Compter par jeton seul
 * laisserait les points d'entrée publics sans plafond, c'est-à-dire ceux qui
 * en ont le plus besoin.
 *
 * LES EN-TÊTES SONT LE POINT : `X-RateLimit-Limit`, `X-RateLimit-Remaining` et
 * `Retry-After` permettent au client de s'AUTO-RÉGULER — d'espacer ses appels
 * de lui-même — plutôt que de se faire éconduire et d'afficher une erreur. Un
 * plafond sans en-têtes est un mur invisible.
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->plafond('api-lecture', (int) config('api.debit.lecture', 120));
        $this->plafond('api-ecriture', (int) config('api.debit.ecriture', 30));

        /*
         | Identité : PAR ADRESSE UNIQUEMENT, et sans repli sur un jeton — il
         | n'y en a pas encore. Le verrouillage par compte existe en plus, dans
         | `LoginThrottleService` : les deux ne protègent pas de la même chose.
         | Celui-ci borne l'attaquant qui essaie mille adresses différentes ;
         | l'autre borne l'attaquant qui s'acharne sur une seule.
         */
        RateLimiter::for('api-identite', fn (Request $requete): Limit => Limit::perMinute(
            (int) config('api.debit.identite', 10)
        )->by($requete->ip() ?? 'inconnu')->response($this->refus(...)));
    }

    private function plafond(string $nom, int $parMinute): void
    {
        RateLimiter::for($nom, fn (Request $requete): Limit => Limit::perMinute($parMinute)
            ->by($this->cle($requete))
            ->response($this->refus(...)));
    }

    /**
     * Le jeton s'il y en a un, l'adresse sinon.
     *
     * L'identifiant du jeton et non celui du compte : un même compte peut
     * avoir un téléphone et une tablette, et l'usage de l'un ne doit pas
     * fermer l'autre.
     */
    private function cle(Request $requete): string
    {
        $jeton = $requete->user()?->currentAccessToken();

        if ($jeton !== null && property_exists($jeton, 'id')) {
            return 'jeton:'.$jeton->id;
        }

        return 'ip:'.($requete->ip() ?? 'inconnu');
    }

    /**
     * Le refus, dans l'enveloppe de l'API.
     *
     * Sans cette réponse sur mesure, Laravel renvoie son propre JSON
     * (`{"message": "Too Many Attempts."}`), qui n'a ni `error`, ni `code`, ni
     * message en français — et le client, ne le reconnaissant pas, le traiterait
     * comme une panne au lieu d'attendre.
     */
    private function refus(Request $requete, array $entetes): JsonResponse
    {
        $delai = (int) ($entetes['Retry-After'] ?? 60);

        return Enveloppe::erreur(CodeErreur::DebitDepasse, meta: ['retry_after' => $delai])
            ->withHeaders($entetes);
    }
}
