<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\HubArticle;
use App\Models\VerifiedFact;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

describe('le plan du site', function (): void {
    it('sert un XML valide', function (): void {
        $reponse = $this->get('/sitemap.xml');

        $reponse->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $reponse->getContent());

        expect($xml)->not->toBeFalse()
            ->and($xml->getName())->toBe('urlset');
    });

    it('énumère les pages éditoriales', function (): void {
        $contenu = (string) $this->get('/sitemap.xml')->getContent();

        expect($contenu)->toContain(route('public.home'))
            ->and($contenu)->toContain(route('public.facts'))
            ->and($contenu)->toContain(route('public.why'));
    });

    it("n'énumère JAMAIS un contenu non publié", function (): void {
        /*
         | Le point qui compte. Un plan qui déclare un brouillon apprend deux
         | choses au moteur : que cette URL existe, et — au moment où elle
         | répond 404 — que notre plan ment. La seconde coûte bien plus cher
         | que la première.
         */
        $brouillon = VerifiedFact::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Un fait encore en cours de vérification',
            'summary' => 'Rien ne doit sortir tant que ce n’est pas publié.',
            'verdict' => 'false',
            'keywords' => 'brouillon',
            'is_published' => false,
        ]);

        $contenu = (string) $this->get('/sitemap.xml')->getContent();

        expect($contenu)->not->toContain($brouillon->uuid);
    });

    it("n'énumère pas un article programmé pour plus tard", function (): void {
        $programme = BlogPost::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'article-programme',
            'title' => 'Article programmé',
            'excerpt' => 'Un chapeau.',
            'body' => 'Corps.',
            'is_published' => true,
            'published_at' => now()->addWeek(),
        ]);

        // `is_published` seul ne suffit pas : une date de publication future
        // veut dire « pas encore », et le plan doit le respecter.
        $contenu = (string) $this->get('/sitemap.xml')->getContent();

        expect($contenu)->not->toContain($programme->slug);
    });

    it('ne déclare que des URL qui répondent vraiment', function (): void {
        /*
         | Le test qui manquait, et le défaut qu'il ferme.
         |
         | Le plan énumérait les `VerifiedFact` — l'enregistrement INTERNE,
         | clé `uuid` — alors que la route `public.fact` sert `HubArticle`,
         | résolu par `slug`. Chaque URL de fact-check renvoyait donc 404 à
         | Googlebot, pendant que les pages réellement publiées n'y figuraient
         | pas du tout.
         |
         | Un plan qui déclare des 404 apprend au moteur que notre plan ment,
         | ce qui coûte bien plus cher que l'URL manquante elle-même.
         */
        HubArticle::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'un-fact-check-publie',
            'title' => 'Un fact-check publié',
            'excerpt' => 'Le chapeau.',
            'body' => '<p>Le corps.</p>',
            'category' => 'sante',
            'verdict' => 'false',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $xml = simplexml_load_string((string) $this->get('/sitemap.xml')->getContent());

        $trouve = false;

        foreach ($xml->url as $entree) {
            $chemin = parse_url((string) $entree->loc, PHP_URL_PATH) ?? '/';

            // Chaque URL déclarée doit répondre 200. Pas 301, pas 404.
            $this->get($chemin)->assertOk();

            if (str_contains($chemin, 'un-fact-check-publie')) {
                $trouve = true;
            }
        }

        // Et le fact-check publié doit bien y être.
        expect($trouve)->toBeTrue();
    });

    it('ne déclare aucune page réservée aux comptes connectés', function (): void {
        $contenu = (string) $this->get('/sitemap.xml')->getContent();

        foreach (['/admin', '/mon-espace', '/connexion', '/inscription'] as $prive) {
            expect($contenu)->not->toContain($prive);
        }
    });
});

describe('robots.txt', function (): void {
    it('interdit tout hors production', function (): void {
        // La suite tourne en `testing` : c'est le cas hors production.
        $reponse = $this->get('/robots.txt');

        $reponse->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        expect((string) $reponse->getContent())->toContain('Disallow: /');
    });

    it('renvoie au plan du site en production', function (): void {
        /*
         | Deux copies d'un même site dans un index se font concurrence, et
         | c'est le moteur qui tranche. D'où la bascule sur l'environnement —
         | qu'un fichier statique dans `public/` ne pourrait pas faire.
         */
        app()->detectEnvironment(static fn (): string => 'production');

        $contenu = (string) $this->get('/robots.txt')->getContent();

        expect($contenu)->toContain('Sitemap: ')
            ->and($contenu)->toContain('Disallow: /admin/')
            ->and($contenu)->toContain('Allow: /');
    });
});

describe('le piège de short_open_tag', function (): void {
    /*
     | `/sitemap.xml` a rendu 500 en PRODUCTION, neuf fois au journal du
     | 2 septembre 2026 :
     |
     |     syntax error, unexpected identifier "version"
     |     (View: resources/views/public/plan-du-site.blade.php)
     |
     | Écrite en clair, « <?xml » est lue par PHP comme une balise ouvrante
     | COURTE dès que `short_open_tag` vaut On — ce qui est le cas sur
     | l'hébergement mutualisé, et PAS en local. Les huit tests ci-dessus
     | passaient tous pendant que la page était morte en ligne.
     |
     | ## Pourquoi celui-ci lance un second processus, ce qu'aucun autre ne fait
     |
     | Parce qu'aucune autre méthode ne prouve la propriété.
     |
     | Lire la SOURCE ne dit rien : le fichier CORRIGÉ contient toujours la
     | chaîne « <?xml », à l'intérieur d'un `{!! '…' !!}`. Un test qui la
     | cherche signale le fichier réparé — vérifié, c'est ce qu'une première
     | version faisait.
     |
     | `token_get_all()` ne dit rien non plus : il lit le `short_open_tag` du
     | processus courant, qui vaut Off ici. Il classerait « <?xml » en HTML
     | inerte et ne verrait rien — exactement comme le développement n'a rien
     | vu pendant des semaines.
     |
     | Reste à demander à PHP lui-même, avec le réglage de la production.
     */
    it('compile en un fichier que PHP accepte même avec short_open_tag=On', function (): void {
        $binaire = (new PhpExecutableFinder)->find();

        if ($binaire === false) {
            $this->markTestSkipped('Binaire PHP introuvable : ce test a besoin d’un second processus.');
        }

        $source = resource_path('views/public/plan-du-site.blade.php');

        Blade::compile($source);

        $compile = Blade::getCompiledPath($source);

        expect($compile)->toBeFile();

        $analyse = new Process([$binaire, '-d', 'short_open_tag=1', '-l', $compile]);
        $analyse->run();

        expect($analyse->isSuccessful())->toBeTrue(
            'Le gabarit compilé ne passe pas l’analyse syntaxique sous short_open_tag=On — '.
            'c’est exactement la 500 de production du 2 septembre 2026 : '.
            trim($analyse->getOutput().$analyse->getErrorOutput()),
        );
    });

    it('émet la déclaration XML par Blade, et en toute première ligne', function (): void {
        $source = (string) file_get_contents(resource_path('views/public/plan-du-site.blade.php'));

        $premiere = strtok($source, "\n");

        /*
         | Deux exigences, et la seconde se perd facilement.
         |
         | La déclaration passe par un echo Blade : le « ?> » qu'elle contient
         | devient alors le texte d'une chaîne, pas une fermeture de balise.
         |
         | Et elle se tient en PREMIÈRE ligne. Les commentaires Blade
         | disparaissent à la compilation, mais PAS les sauts de ligne qui les
         | entourent : placés avant, ils pousseraient du blanc en tête du flux,
         | et une déclaration XML qui n'ouvre pas le document rend le plan
         | illisible pour un moteur.
         */
        /*
         | ON NE CHERCHE PAS LA DÉCLARATION EN UN SEUL MORCEAU DANS LA SOURCE.
         |
         | C'est ce que faisait l'assertion d'origine, et elle était
         | IMPOSSIBLE à satisfaire par le fichier corrigé : la correction
         | consiste précisément à découper la chaîne — `'<' . '?xml …?' . '>'`
         | — pour qu'aucun « ?> » contigu ne traîne dans le gabarit compilé.
         | Le test exigeait donc le défaut qu'il servait à interdire, et il
         | était rouge depuis le correctif.
         |
         | On éprouve les deux propriétés réelles : la source PASSE par un echo
         | Blade en première ligne, et le flux SERVI ouvre bien sur la
         | déclaration, sans blanc devant.
         */
        expect($premiere)->toStartWith('{!!');
        expect($premiere)->toContain('?xml version="1.0" encoding="UTF-8"');

        expect($this->get(route('public.sitemap'))->getContent())
            ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
    });
});
