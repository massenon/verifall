<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Marketing\Seo\Referencement;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as VueRendue;
use Throwable;

/**
 * Donne à CHAQUE page publique ses métadonnées, sans qu'elle ait à y penser.
 *
 * ## Pourquoi un compositeur de vue plutôt qu'une variable par page
 *
 * Parce que l'exigence est « toutes les pages, sans exception ». Une variable
 * qu'on passe page par page se perd exactement là où personne ne regarde : sur
 * les mentions légales, sur la politique de cookies, sur la page qu'on ajoutera
 * dans six mois. Le jour où elle manque, la page sort avec le titre par défaut
 * et sans aperçu de partage — et rien ne le signale.
 *
 * Ici, c'est l'inverse : les métadonnées sont posées par défaut à partir du nom
 * de la route, et une page n'a à intervenir QUE si elle a mieux à dire —
 * un article, un fact-check, une fiche de cours. Le défaut est correct ;
 * l'exception est explicite.
 */
final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Referencement::class);
    }

    public function boot(): void
    {
        View::composer('components.layouts.public', function (VueRendue $vue): void {
            // Une page qui a déjà construit ses propres métadonnées garde les
            // siennes : elle en sait plus que le nom de sa route.
            if ($vue->offsetExists('seo')) {
                return;
            }

            $route = request()->route()?->getName();

            $vue->with('seo', $this->app->make(Referencement::class)->pourPage(
                is_string($route) ? $route : 'public.home',
            ));
        });

        /*
         | Les campagnes d'affichage, sur le même gabarit.
         |
         | Mises en cache cinq minutes : elles sont lues sur CHAQUE page
         | publique, et changent quelques fois par mois. Sans cache, on paierait
         | une requête par visite pour un résultat identique.
         |
         | `Cache::flexible` n'est pas employé à dessein : une campagne éteinte
         | doit disparaître vite, pas « quand le cache voudra bien ».
         */
        View::composer('components.layouts.public', function (VueRendue $vue): void {
            if ($vue->offsetExists('campagnes')) {
                return;
            }

            /*
             | Le repli n'est PAS une précaution de style.
             |
             | Ce compositeur ajoute une lecture de base à CHAQUE page publique,
             | y compris l'accueil, qui n'en faisait aucune. Sans ce garde-fou,
             | une table absente — migration non jouée, base momentanément
             | injoignable — rend 500 sur tout le site public.
             |
             | Constaté immédiatement : le test d'exemple, qui appelle « / »
             | sans base, est passé de vert à 500 dès l'ajout du compositeur.
             |
             | Un bandeau publicitaire ne vaut pas une page d'erreur. En cas de
             | souci, on n'affiche aucune campagne et le site fonctionne.
             */
            try {
                $campagnes = Cache::remember(
                    'campagnes-diffusables',
                    now()->addMinutes(5),
                    static fn () => MarketingCampaign::diffusable()->get(),
                );
            } catch (Throwable $souci) {
                // Journalisé, pas silencieux : une campagne qui ne s'affiche
                // jamais doit laisser une trace quelque part.
                Log::warning('Campagnes indisponibles : '.$souci->getMessage());
                $campagnes = collect();
            }

            $vue->with('campagnes', $campagnes);
        });
    }
}
