<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Application\Contracts\DepotPlanDuSite;
use Illuminate\Http\Response;

/**
 * Le plan du site, engendré depuis la base.
 *
 * ## Pourquoi il ne peut pas être un fichier statique
 *
 * Un `sitemap.xml` écrit à la main est faux le lendemain de sa rédaction. Or
 * c'est par lui qu'un moteur découvre un fact-check publié ce matin : sans
 * plan, il attendra de tomber dessus par un lien. Sur un site dont la valeur
 * est la fraîcheur, ce délai est le produit lui-même.
 *
 * ## Ce qu'il contient, et ce qu'il ne contient pas
 *
 * Uniquement des pages publiques, publiées, et indexables. Pas les écrans
 * connectés, pas les brouillons, pas les pages d'authentification — un plan
 * qui déclare des URL renvoyant 302 vers la connexion abîme la confiance que
 * le moteur accorde au reste du fichier.
 *
 * `lastmod` vient de la donnée réelle. Le déclarer à « aujourd'hui » pour tout,
 * comme le font beaucoup de plans engendrés, apprend au moteur que notre date
 * ne veut rien dire — et il cesse de la lire.
 */
final class PlanDuSiteController
{
    public function __construct(
        private readonly DepotPlanDuSite $plan,
    ) {}

    public function __invoke(): Response
    {
        $entrees = $this->plan->entrees();

        $xml = view('public.plan-du-site', ['entrees' => $entrees])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // Un moteur repasse ici plusieurs fois par jour. Une heure de cache
            // suffit à absorber ce trafic sans retarder la découverte d'une
            // publication au-delà de ce qu'un moteur tolère.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
