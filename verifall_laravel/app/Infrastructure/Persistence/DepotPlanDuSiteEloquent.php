<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotPlanDuSite;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\HubArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class DepotPlanDuSiteEloquent implements DepotPlanDuSite
{
    /**
     * Les pages éditoriales, avec la priorité que leur rôle justifie.
     *
     * La priorité n'est pas une note de qualité : c'est une indication de
     * l'importance RELATIVE des pages entre elles, pour un moteur qui doit
     * choisir par où commencer. Tout mettre à 1.0 revient à ne rien dire.
     *
     * @var array<string, array{0: string, 1: string}> route => [changefreq, priority]
     */
    private const PAGES = [
        'public.home' => ['daily', '1.0'],
        'public.facts' => ['daily', '0.9'],
        'public.blog' => ['weekly', '0.8'],
        'public.tutorials' => ['weekly', '0.8'],
        'public.verify' => ['monthly', '0.8'],
        'public.why' => ['monthly', '0.7'],
        'public.features' => ['monthly', '0.7'],
        'public.how' => ['monthly', '0.7'],
        'public.team' => ['monthly', '0.5'],
        // Transparence. Le journal des corrections change au rythme des
        // corrections, donc plus souvent que le reste des pages éditoriales ;
        // le financement, une fois l'an.
        'public.referral' => ['monthly', '0.6'],
        'public.corrections' => ['weekly', '0.6'],
        'public.methodology' => ['monthly', '0.6'],
        // La liste des sources bouge quand la rédaction l'amende — plus souvent
        // que la méthodologie, qui décrit un principe.
        'public.sources' => ['weekly', '0.6'],
        'public.funding' => ['yearly', '0.4'],
        'public.faq' => ['monthly', '0.5'],
        'public.media' => ['monthly', '0.5'],
        'public.contact' => ['yearly', '0.4'],
        'public.verify-certificate' => ['yearly', '0.4'],
        // Les pages légales entrent au plan : elles rassurent un moteur sur le
        // sérieux d'un site, et ce sont elles qu'on cherche quand on doute.
        'public.legal' => ['yearly', '0.2'],
        'public.terms' => ['yearly', '0.2'],
        'public.privacy' => ['yearly', '0.2'],
        'public.cookies' => ['yearly', '0.2'],
    ];

    public function entrees(): array
    {
        /*
         | Une heure de cache. Le plan est relu plusieurs fois par jour par
         | chaque moteur ; le recalculer à chaque passage ferait trois requêtes
         | de plus pour un résultat identique.
         */
        return Cache::remember('plan-du-site', now()->addHour(), function (): array {
            $entrees = [];

            foreach (self::PAGES as $route => [$frequence, $priorite]) {
                $entrees[] = [
                    'url' => route($route),
                    'lastmod' => null,
                    'changefreq' => $frequence,
                    'priority' => $priorite,
                ];
            }

            /*
             | Les fact-checks d'abord : c'est le contenu qui fait la valeur du
             | site aux yeux d'un moteur, et celui qu'on veut voir indexé vite.
             |
             | ATTENTION AU MODÈLE. La route `public.fact` sert `HubArticle` —
             | l'article PUBLIÉ, résolu par son `slug`. `VerifiedFact` est
             | l'enregistrement interne auquel il se rattache, clé `uuid`, et il
             | n'a AUCUNE page publique.
             |
             | La première version énumérait les `VerifiedFact` : chaque URL de
             | fact-check du plan renvoyait donc 404 à Googlebot, tandis que les
             | pages réellement publiées n'y figuraient pas du tout. Le pire des
             | deux mondes — un plan qui ment ET qui omet.
             |
             | La même confusion avait déjà produit un `TypeError` sur la page
             | de fact-check. Deux modèles proches, deux rôles distincts.
             */
            foreach (HubArticle::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->get() as $factCheck) {
                $entrees[] = [
                    'url' => route('public.fact', $factCheck),
                    'lastmod' => $factCheck->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.9',
                ];
            }

            foreach (BlogPost::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->get() as $article) {
                $entrees[] = [
                    'url' => route('public.blog.show', $article),
                    'lastmod' => $article->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            }

            foreach ($this->coursPublies() as $cours) {
                $entrees[] = [
                    'url' => route('public.tutorial', $cours),
                    'lastmod' => $cours->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            }

            return $entrees;
        });
    }

    /**
     * Les cours visibles publiquement.
     *
     * La colonne qui porte la publication a changé de nom au fil des sprints ;
     * on ne devine pas, on regarde ce que le modèle déclare réellement.
     *
     * @return Collection<int, Course>
     */
    private function coursPublies(): Collection
    {
        $requete = Course::query();

        foreach (['is_published', 'published'] as $colonne) {
            if (Schema::hasColumn((new Course)->getTable(), $colonne)) {
                return $requete->where($colonne, true)->get();
            }
        }

        return $requete->get();
    }
}
