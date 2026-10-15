<?php

declare(strict_types=1);

use App\Core\Marketing\Services\SuiviDesVisites;
use App\Models\BlogPost;
use App\Models\ContentVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

function articleDeTest(): BlogPost
{
    return BlogPost::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'article-'.Str::random(6),
        'title' => 'Un article',
        'excerpt' => 'Un chapeau.',
        'body' => '<p>Un corps.</p>',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);
}

function visiteDepuis(array $entetes = [], string $ip = '203.0.113.7'): Request
{
    $requete = Request::create('/blog/x', 'GET', [], [], [], array_merge(
        ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 13) Chrome/120'],
        $entetes,
    ));

    return $requete;
}

describe('ce qui est compté', function (): void {
    it('compte une visite humaine', function (): void {
        $article = articleDeTest();

        $compte = app(SuiviDesVisites::class)->enregistrer($article, visiteDepuis());

        expect($compte)->toBeTrue()
            ->and(ContentVisit::count())->toBe(1)
            ->and($article->fresh()->views)->toBe(1);
    });

    it('ne compte pas deux fois la même personne le même jour', function (): void {
        /*
         | Un rafraîchissement n'est pas un lecteur de plus. C'est le premier
         | biais de l'ancien compteur, et le plus banal.
         */
        $article = articleDeTest();
        $suivi = app(SuiviDesVisites::class);

        $suivi->enregistrer($article, visiteDepuis());
        $second = $suivi->enregistrer($article, visiteDepuis());

        expect($second)->toBeFalse()
            ->and(ContentVisit::count())->toBe(1)
            ->and($article->fresh()->views)->toBe(1);
    });

    it('compte de nouveau un retour le lendemain', function (): void {
        $article = articleDeTest();
        $suivi = app(SuiviDesVisites::class);

        $suivi->enregistrer($article, visiteDepuis());

        $this->travel(1)->days();
        $retour = $suivi->enregistrer($article, visiteDepuis());

        // Revenir le lendemain EST une information : c'est un lecteur fidèle.
        expect($retour)->toBeTrue()
            ->and($article->fresh()->views)->toBe(2);
    });

    it('sépare deux personnes distinctes', function (): void {
        $article = articleDeTest();
        $suivi = app(SuiviDesVisites::class);

        $suivi->enregistrer($article, visiteDepuis(ip: '203.0.113.7'));
        $suivi->enregistrer($article, visiteDepuis(ip: '198.51.100.4'));

        expect($article->fresh()->views)->toBe(2);
    });
});

describe('ce qui n’est PAS compté', function (): void {
    it('écarte les robots d’indexation', function (): void {
        $article = articleDeTest();

        $compte = app(SuiviDesVisites::class)->enregistrer(
            $article,
            visiteDepuis(['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']),
        );

        // Enregistré pour mesurer l'indexation, mais JAMAIS mêlé à l'audience.
        expect($compte)->toBeFalse()
            ->and($article->fresh()->views)->toBe(0)
            ->and(ContentVisit::where('is_bot', true)->count())->toBe(1);
    });

    it('écarte les fabricants d’aperçu de lien', function (): void {
        /*
         | Celui-ci comptait double sur l'ancien dispositif : coller un lien
         | dans WhatsApp déclenche une visite pour fabriquer la vignette. Cent
         | partages produisaient cent « vues » sans un seul lecteur.
         */
        $article = articleDeTest();
        $suivi = app(SuiviDesVisites::class);

        foreach ([
            'WhatsApp/2.23.20',
            'facebookexternalhit/1.1',
            'Twitterbot/1.0',
            'TelegramBot (like TwitterBot)',
            'LinkedInBot/1.0',
        ] as $agent) {
            $suivi->enregistrer($article, visiteDepuis(['HTTP_USER_AGENT' => $agent], Str::random(8)));
        }

        expect($article->fresh()->views)->toBe(0);
    });

    it('écarte un préchargement du navigateur', function (): void {
        $article = articleDeTest();

        $compte = app(SuiviDesVisites::class)->enregistrer(
            $article,
            visiteDepuis(['HTTP_SEC_PURPOSE' => 'prefetch;prerender']),
        );

        // Survoler un lien n'est pas le lire. Et rien n'est enregistré du tout :
        // un préchargement n'est même pas un passage.
        expect($compte)->toBeFalse()
            ->and(ContentVisit::count())->toBe(0);
    });

    it('écarte une requête sans agent utilisateur', function (): void {
        $article = articleDeTest();

        app(SuiviDesVisites::class)->enregistrer(
            $article,
            visiteDepuis(['HTTP_USER_AGENT' => '']),
        );

        expect($article->fresh()->views)->toBe(0);
    });
});

describe('ce qui est conservé', function (): void {
    it('ne stocke NI adresse IP NI agent utilisateur', function (): void {
        /*
         | Le point RGPD. On vérifie sur la ligne réellement écrite, pas sur
         | l'intention : une promesse de minimisation qui n'est pas testée est
         | une promesse qu'un correctif ultérieur peut défaire en silence.
         */
        $article = articleDeTest();
        app(SuiviDesVisites::class)->enregistrer($article, visiteDepuis());

        $ligne = (array) ContentVisit::query()->first()->getAttributes();
        $serialise = json_encode($ligne);

        expect($serialise)->not->toContain('203.0.113.7')
            ->and($serialise)->not->toContain('Mozilla')
            ->and($serialise)->not->toContain('Android');
    });

    it('rend la même personne méconnaissable le lendemain', function (): void {
        /*
         | Le sel comprend la date. Deux visites du même appareil à deux jours
         | d'écart portent donc des empreintes SANS RAPPORT : le suivi ne peut
         | pas retracer un parcours dans le temps, même pour qui aurait la base.
         */
        $article = articleDeTest();
        $suivi = app(SuiviDesVisites::class);

        $suivi->enregistrer($article, visiteDepuis());
        $premiere = ContentVisit::query()->first()->visitor_hash;

        $this->travel(1)->days();
        $suivi->enregistrer($article, visiteDepuis());
        $seconde = ContentVisit::query()->latest('id')->first()->visitor_hash;

        expect($seconde)->not->toBe($premiere);
    });

    it('garde le domaine du référent, jamais l’URL complète', function (): void {
        $article = articleDeTest();

        app(SuiviDesVisites::class)->enregistrer($article, visiteDepuis([
            'HTTP_REFERER' => 'https://www.facebook.com/groups/12345/posts/67890/',
        ]));

        $visite = ContentVisit::query()->first();

        expect($visite->referrer_host)->toBe('www.facebook.com')
            // L'URL d'un fil peut désigner quelqu'un ; le domaine, non.
            ->and($visite->referrer_host)->not->toContain('groups');
    });

    it('ne retient pas une navigation interne comme une provenance', function (): void {
        /*
         | LE RÉFÉRENT EST BÂTI SUR L'HÔTE DE LA REQUÊTE, PAS SUR `APP_URL`.
         |
         | Le code compare l'hôte du référent à celui de la requête en cours —
         | et c'est la bonne comparaison : elle reste juste derrière plusieurs
         | domaines, là où `APP_URL` n'en désigne qu'un.
         |
         | Ce test employait `url('/blog')`, donc `APP_URL`. Il passait tant que
         | la machine de développement avait `APP_URL=http://localhost`, et
         | échouait dès que quelqu'un y mettait le domaine de préproduction :
         | le référent devenait « externe » aux yeux du code, sans que rien
         | n'ait changé dans le code. Un test qui dépend du `.env` d'un poste ne
         | teste pas le comportement, il teste ce poste.
         */
        $article = articleDeTest();

        $requete = visiteDepuis();
        $requete->headers->set('referer', 'https://'.$requete->getHost().'/blog');
        $requete->server->set('HTTP_REFERER', 'https://'.$requete->getHost().'/blog');

        app(SuiviDesVisites::class)->enregistrer($article, $requete);

        expect(ContentVisit::query()->first()->referrer_host)->toBeNull();
    });

    it("retient bien une provenance qui vient VRAIMENT d'ailleurs", function (): void {
        // Le pendant du test précédent : sans lui, une implémentation qui
        // n'enregistrerait JAMAIS de référent le ferait passer au vert.
        $article = articleDeTest();

        app(SuiviDesVisites::class)->enregistrer($article, visiteDepuis([
            'HTTP_REFERER' => 'https://un-autre-site.example/page',
        ]));

        expect(ContentVisit::query()->first()->referrer_host)->toBe('un-autre-site.example');
    });
});
