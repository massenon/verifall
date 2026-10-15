<?php

declare(strict_types=1);

use App\Core\Marketing\Seo\NoteDuVerdict;
use App\Core\Marketing\Seo\Referencement;
use App\Enums\Langue;
use App\Enums\VerdictFactCheck;
use App\Http\Middleware\ChoisirLaLangue;
use App\Models\BlogPost;

/*
 | Le référencement se TESTE, parce qu'il ne se voit pas.
 |
 | Une balise `og:image` relative, un `canonical` qui pointe ailleurs, un
 | JSON-LD cassé par une apostrophe : rien de tout cela ne produit d'erreur, ne
 | fait tomber un écran, ni n'apparaît à la relecture. Le premier symptôme est
 | un partage WhatsApp qui sort blanc — après publication.
 */

describe('chaque page publique se présente complètement', function (): void {
    /*
     | On balaie les routes plutôt que d'en choisir une : l'exigence est
     | « toutes les pages, sans exception », et c'est exactement ce genre de
     | promesse qu'un test nominatif laisse filer sur la page qu'on ajoutera
     | demain.
     */
    $pages = [
        'public.home', 'public.why', 'public.features', 'public.how',
        'public.team', 'public.contact', 'public.faq', 'public.media',
        'public.legal', 'public.privacy', 'public.cookies',
    ];

    foreach ($pages as $route) {
        it("pose ses métadonnées sur {$route}", function () use ($route): void {
            $reponse = $this->get(route($route));

            $reponse->assertOk();

            $html = $reponse->getContent();
            expect($html)->toBeString();

            foreach ([
                '<link rel="canonical"',
                '<meta property="og:title"',
                '<meta property="og:description"',
                '<meta property="og:url"',
                '<meta property="og:image"',
                '<meta property="og:site_name" content="Vérif\'All">',
                '<meta property="og:locale" content="fr_FR">',
                '<meta name="twitter:card" content="summary_large_image">',
                '<meta name="twitter:image"',
            ] as $balise) {
                expect($html)->toContain($balise);
            }
        });
    }

    it("n'expose jamais une image de partage relative", function (): void {
        // Facebook et WhatsApp ne résolvent pas les chemins relatifs : une URL
        // relative ne dégrade pas l'aperçu, elle le supprime.
        foreach (['public.home', 'public.faq', 'public.blog'] as $route) {
            $html = (string) $this->get(route($route))->getContent();

            preg_match_all('/<meta property="og:image" content="([^"]*)"/', $html, $trouves);

            expect($trouves[1])->not->toBeEmpty();
            foreach ($trouves[1] as $url) {
                expect($url)->toStartWith('http');
            }
        }
    });

    it('produit un fil d’Ariane exploitable, sauf sur l’accueil', function (): void {
        $accueil = (string) $this->get(route('public.home'))->getContent();
        expect($accueil)->not->toContain('BreadcrumbList');

        $interieure = (string) $this->get(route('public.why'))->getContent();
        expect($interieure)->toContain('BreadcrumbList');

        // Le fil doit être du JSON valide, pas seulement du texte qui y ressemble.
        preg_match('#<script type="application/ld\+json">(.*?BreadcrumbList.*?)</script>#s', $interieure, $bloc);
        expect($bloc[1] ?? '')->not->toBeEmpty();
        expect(json_decode($bloc[1], true))->toBeArray();
    });
});

describe('le titre et la description', function (): void {
    it('ne double jamais la marque', function (): void {
        // L'accueil porte déjà « Vérif'All » dans son titre : le suffixer
        // donnerait « Vérif'All … — Vérif'All ».
        $html = (string) $this->get(route('public.home'))->getContent();

        preg_match('#<title>(.*?)</title>#s', $html, $titre);

        // Blade échappe l'apostrophe en `&#039;` — et il a raison de le faire.
        // C'est le titre RENDU qu'on compte, pas la chaîne d'origine.
        $rendu = html_entity_decode($titre[1] ?? '', ENT_QUOTES, 'UTF-8');

        expect(substr_count($rendu, "Vérif'All"))->toBe(1);
    });

    it('coupe une description trop longue sur une frontière de mot', function (): void {
        // Pas de fabrique sur ce modèle, et pas besoin d'écrire en base : on
        // mesure une troncature de chaîne, pas une lecture.
        $article = new BlogPost([
            'title' => 'Un titre',
            'slug' => 'un-titre',
            'meta_description' => null,
            'excerpt' => str_repeat('désinformation ', 40),
        ]);

        $seo = app(Referencement::class)->pourArticleDeBlog($article);

        expect(mb_strlen($seo->descriptionCourte()))->toBeLessThanOrEqual(160)
            ->and($seo->descriptionCourte())->toEndWith('…')
            // Coupé proprement : pas au milieu d'un mot.
            ->and($seo->descriptionCourte())->not->toContain('désinformati…');
    });
});

describe('ClaimReview — la note dit ce que le verdict dit', function (): void {
    it('place le verdict aux deux bouts de l’échelle', function (): void {
        expect(NoteDuVerdict::depuis(VerdictFactCheck::Vrai)->valeur)->toBe(5)
            ->and(NoteDuVerdict::depuis(VerdictFactCheck::Faux)->valeur)->toBe(1)
            ->and(NoteDuVerdict::depuis(VerdictFactCheck::Faux)->libelle)->toBe('FAUX');
    });

    it("n'attribue AUCUNE note à ce qui n'est pas vérifiable", function (): void {
        /*
         | Le point qui compte. Une note médiane — 3 sur 5 — se lit « à moitié
         | vrai », ce qui est une affirmation sur le fond. Or nous n'affirmons
         | rien : nous disons que la vérification n'a pas abouti. Schema.org
         | prévoit ce cas ; on l'emploie.
         */
        $note = NoteDuVerdict::depuis(VerdictFactCheck::NonVerifiable);

        expect($note->valeur)->toBeNull()
            ->and($note->libelle)->toBe('NON VÉRIFIABLE');
    });

    it('traite un verdict inconnu comme non vérifiable, jamais comme vrai', function (): void {
        expect(NoteDuVerdict::depuis('bidon')->valeur)->toBeNull()
            ->and(NoteDuVerdict::depuis(null)->valeur)->toBeNull();
    });
});

describe('la langue déclarée aux robots et aux réseaux', function (): void {
    it("annonce l'anglais quand la page est servie en anglais", function (): void {
        /*
         | `og:locale` affirmait `fr_FR` en toutes circonstances. Une page rendue
         | en anglais mentait donc à Facebook et à WhatsApp au moment précis où
         | quelqu'un la partage — et l'aperçu ainsi produit est, pour la plupart
         | des gens, la seule chose qu'ils verront du lien.
         |
         | Rien ne le signalait : la balise était présente, bien formée, et
         | fausse.
         */
        $html = (string) test()
            ->withSession([ChoisirLaLangue::CLEF_SESSION => Langue::En->value])
            ->get(route('public.home'))->getContent();

        expect($html)->toContain('property="og:locale" content="en_GB"')
            ->and($html)->not->toContain('property="og:locale" content="fr_FR"');
    });

    it('annonce le français par défaut', function (): void {
        $html = (string) test()->get(route('public.home'))->getContent();

        expect($html)->toContain('property="og:locale" content="fr_FR"');
    });
});
