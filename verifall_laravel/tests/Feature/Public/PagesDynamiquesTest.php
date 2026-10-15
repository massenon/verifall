<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\HubArticle;
use Illuminate\Support\Str;

/*
 | Les pages À PARAMÈTRE — un article de blog, un fact-check, un tutoriel.
 |
 | Elles n'étaient couvertes par AUCUN test, et c'est ce trou qui a laissé
 | passer deux erreurs 500 en production locale :
 |
 |  · `/blog` levait `LazyLoadingViolationException` sur `$article->author`,
 |    qu'aucune requête ne chargeait d'avance ;
 |  · `/actualites/{slug}` levait `TypeError`, parce que la méthode SEO avait
 |    été écrite pour `VerifiedFact` alors que la route sert `HubArticle`.
 |
 | Les deux sont invisibles à la relecture et au typage : la première ne se
 | déclenche qu'avec des données réelles, la seconde qu'à l'exécution. Seul un
 | rendu complet les attrape.
 */

function articleDeBlogPublie(string $suffixe = ''): BlogPost
{
    return BlogPost::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'un-article-de-blog'.$suffixe,
        'title' => 'Un article de blog'.$suffixe,
        'excerpt' => 'Le chapeau de cet article.',
        'body' => '<h2>Une section</h2><p>Un corps.</p>',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);
}

function factCheckPublie(array $extra = []): HubArticle
{
    return HubArticle::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'une-rumeur-dementie',
        'title' => 'Une rumeur démentie',
        /*
         | L'AFFIRMATION EXAMINÉE, sans laquelle plus aucun `ClaimReview` n'est
         | émis — et c'est voulu.
         |
         | Le titre énonce la conclusion ; l'employer comme `claimReviewed` avec
         | un verdict FAUX ferait dire au balisage l'inverse du démenti. Depuis
         | la correction, l'absence d'affirmation supprime le balisage plutôt
         | que de l'inverser, et ces tests doivent donc en fournir une.
         */
        'claim' => 'Le vaccin contre la fièvre jaune rendrait stérile.',
        'excerpt' => 'Ce que dit la rumeur, et ce que disent les faits.',
        'body' => '<p>Le corps de la vérification.</p>',
        'category' => 'sante',
        'verdict' => 'false',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ], $extra));
}

describe('la liste du blog', function (): void {
    it('se rend avec des articles, sans charger de relation à la volée', function (): void {
        /*
         | Le test qui manquait. `preventLazyLoading` est actif : toute relation
         | lue dans une vue sans avoir été chargée d'avance lève une exception.
         | C'est une bonne chose — elle transforme dix requêtes silencieuses par
         | page en erreur visible — encore faut-il qu'un test rende la page.
         */
        articleDeBlogPublie('-a-la-une');
        articleDeBlogPublie('-en-liste');

        $this->get(route('public.blog'))
            ->assertOk()
            ->assertSee('Un article de blog-en-liste');
    });

    it('affiche une signature de repli quand l’article n’a pas d’auteur', function (): void {
        /*
         | DEUX articles, et ce n'est pas un détail. Le premier devient
         | l'article « à la une », qui a son propre gabarit et que la liste
         | exclut. Avec un seul article, la boucle des cartes est vide — et le
         | test passait sans jamais rendre la ligne qu'il prétend vérifier.
         */
        articleDeBlogPublie('-a-la-une');
        articleDeBlogPublie('-en-liste');

        // Blade échappe l'apostrophe : on cherche le texte RENDU.
        $html = (string) $this->get(route('public.blog'))->getContent();

        expect(html_entity_decode($html, ENT_QUOTES, 'UTF-8'))->toContain("Vérif'All Team");
    });
});

describe('un article de blog', function (): void {
    it('se rend, avec ses métadonnées complètes', function (): void {
        $article = articleDeBlogPublie();

        $reponse = $this->get(route('public.blog.show', $article));
        $reponse->assertOk()->assertSee('Un article de blog');

        $html = (string) $reponse->getContent();

        expect($html)->toContain('NewsArticle')
            ->and($html)->toContain('<link rel="canonical"')
            ->and($html)->toContain('<meta property="og:type" content="article">');
    });

    it('garde la structure du corps après purification', function (): void {
        // La configuration par défaut du purificateur supprimait `h2` : un
        // article écrit avec des sous-titres ressortait en un seul paragraphe.
        $article = articleDeBlogPublie();

        $this->get(route('public.blog.show', $article))
            ->assertOk()
            ->assertSee('<h2>Une section</h2>', false);
    });
});

describe('un fact-check publié', function (): void {
    it('se rend, et porte son ClaimReview', function (): void {
        $article = factCheckPublie();

        $reponse = $this->get(route('public.fact', $article));
        $reponse->assertOk();

        $html = (string) $reponse->getContent();

        expect($html)->toContain('ClaimReview')
            // Verdict « faux » = 1 sur 5, avec son libellé en toutes lettres.
            ->and($html)->toContain('"ratingValue":1')
            ->and($html)->toContain('FAUX');
    });

    it('relie le démenti à la publication examinée, quand la source est connue', function (): void {
        /*
         | `itemReviewed` est ce qui permet à un moteur d'afficher notre démenti
         | À CÔTÉ du contenu qu'il dément. Sans lui, `ClaimReview` dit qu'on a
         | jugé quelque chose, sans dire quoi.
         */
        $article = factCheckPublie([
            'slug' => 'avec-source',
            'source_url' => 'https://factuel.afp.com/doc.afp.com.88CU2CQ',
            'source_name' => 'AFP Factuel',
        ]);

        $html = (string) $this->get(route('public.fact', $article))->getContent();

        expect($html)->toContain('itemReviewed')
            ->and($html)->toContain('factuel.afp.com')
            ->and($html)->toContain('AFP Factuel');
    });

    it("n'invente PAS d'itemReviewed quand la source est inconnue", function (): void {
        // Un `appearance` vide vaut moins qu'aucun `appearance` : il déclare un
        // lien qu'on n'a pas.
        $article = factCheckPublie(['slug' => 'sans-source', 'source_url' => null]);

        $html = (string) $this->get(route('public.fact', $article))->getContent();

        expect($html)->toContain('ClaimReview')
            ->and($html)->not->toContain('itemReviewed');
    });

    it("n'attribue aucune note chiffrée à une analyse en cours", function (): void {
        $article = factCheckPublie(['slug' => 'en-cours', 'verdict' => 'unverifiable']);

        $html = (string) $this->get(route('public.fact', $article))->getContent();

        expect($html)->toContain('NON VÉRIFIABLE')
            ->and($html)->not->toContain('"ratingValue"');
    });

    it('compte la visite, une seule fois', function (): void {
        $article = factCheckPublie();

        $this->get(route('public.fact', $article))->assertOk();
        $this->get(route('public.fact', $article))->assertOk();

        // Deux chargements, un lecteur : voir SuiviDesVisites.
        expect($article->fresh()->views)->toBe(1);
    });
});
