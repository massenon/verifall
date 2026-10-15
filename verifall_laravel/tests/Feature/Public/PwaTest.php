<?php

declare(strict_types=1);

describe('le manifeste', function (): void {
    it('est un JSON de manifeste valide', function (): void {
        $reponse = $this->get('/manifest.webmanifest');

        $reponse->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8');

        $manifeste = json_decode((string) $reponse->getContent(), true);

        expect($manifeste)->toBeArray()
            ->and($manifeste['name'] ?? null)->toBeString()
            ->and($manifeste['short_name'] ?? null)->toBe("Vérif'All")
            ->and($manifeste['display'] ?? null)->toBe('standalone')
            ->and($manifeste['start_url'] ?? null)->toStartWith('http');
    });

    it('déclare une icône maskable, sans quoi Android rogne le logo', function (): void {
        /*
         | Une icône « any » posée dans un masque circulaire se fait couper aux
         | quatre coins. La variante `maskable` porte 20 % de marge de sécurité
         | prévus pour ça. Ce test existe parce que le fichier du dépôt portait
         | le nom `maskable-icon-512x512.png` en faisant réellement 128 × 128.
         */
        $manifeste = json_decode((string) $this->get('/manifest.webmanifest')->getContent(), true);

        $usages = array_column($manifeste['icons'], 'purpose');
        expect($usages)->toContain('maskable')->toContain('any');

        foreach ($manifeste['icons'] as $icone) {
            if ($icone['purpose'] === 'maskable') {
                $fichier = public_path(parse_url($icone['src'], PHP_URL_PATH) ?? '');
                expect(is_file($fichier))->toBeTrue();

                [$largeur, $hauteur] = getimagesize($fichier);
                expect($largeur)->toBe(512)->and($hauteur)->toBe(512);
            }
        }
    });

    it('ne propose que des raccourcis qui mènent quelque part', function (): void {
        $manifeste = json_decode((string) $this->get('/manifest.webmanifest')->getContent(), true);

        expect($manifeste['shortcuts'])->not->toBeEmpty();

        foreach ($manifeste['shortcuts'] as $raccourci) {
            $this->get($raccourci['url'])->assertOk();
        }
    });
});

describe('le service worker', function (): void {
    it('est servi à la racine, sans quoi sa portée serait partielle', function (): void {
        $reponse = $this->get('/sw.js');

        $reponse->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertHeader('Service-Worker-Allowed', '/');
    });

    it("n'est JAMAIS mis en cache par le navigateur", function (): void {
        /*
         | Un service worker fautif est ce qu'on ne peut PAS corriger depuis le
         | serveur : il s'interpose. S'il était lui-même en cache, le correctif
         | resterait invisible aussi longtemps que ce cache.
         */
        $entete = $this->get('/sw.js')->headers->get('Cache-Control');

        expect($entete)->toContain('no-store');
    });

    it('change de version quand le socle change', function (): void {
        $premier = (string) $this->get('/sw.js')->getContent();
        preg_match("/const VERSION = '([a-f0-9]+)'/", $premier, $v1);
        expect($v1[1] ?? '')->not->toBeEmpty();

        // Deux appels sans modification donnent la même version : sinon chaque
        // visite réinstallerait le service worker et purgerait le cache.
        $second = (string) $this->get('/sw.js')->getContent();
        preg_match("/const VERSION = '([a-f0-9]+)'/", $second, $v2);

        expect($v2[1])->toBe($v1[1]);
    });

    it('refuse de conserver ce qui appartient à quelqu’un', function (): void {
        /*
         | LE test qui compte. Sur un téléphone partagé — la norme dans le
         | public visé — un cache mal réglé rend un rapport de vérification
         | lisible par la personne suivante, hors ligne et après déconnexion.
         |
         | On vérifie que le refus repose sur l'en-tête posé par le serveur, et
         | non sur une liste d'adresses tenue à la main : une liste s'oublie à
         | la route suivante.
         */
        $js = (string) $this->get('/sw.js')->getContent();

        expect($js)->toContain("controle.includes('no-store')")
            ->and($js)->toContain("reponse.headers.has('Set-Cookie')")
            // Aucune écriture n'est rejouable depuis un cache.
            ->and($js)->toContain("requete.method !== 'GET'")
            // Ni l'API, ni les URL signées, qui expirent.
            ->and($js)->toContain("url.pathname.startsWith('/api/')")
            ->and($js)->toContain("url.searchParams.has('signature')");
    });

    it('sert bien une page hors ligne, et elle existe', function (): void {
        $js = (string) $this->get('/sw.js')->getContent();

        preg_match("/const PAGE_HORS_LIGNE = '([^']+)'/", $js, $trouve);
        expect($trouve[1] ?? '')->not->toBeEmpty();

        $this->get($trouve[1])->assertOk()->assertSee('Pas de connexion');
    });
});

describe('la déclaration dans les gabarits', function (): void {
    it('est posée sur les écrans du public, jamais sur le back-office', function (): void {
        $public = (string) $this->get(route('public.home'))->getContent();

        expect($public)->toContain('rel="manifest"')
            ->and($public)->toContain('name="theme-color"')
            ->and($public)->toContain('apple-touch-icon');

        // Le back-office n'a pas à être installable : une surface de moins.
        $staff = (string) $this->get(route('staff.login'))->getContent();
        expect($staff)->not->toContain('rel="manifest"');
    });

    it('répare le favicon, qui faisait un pixel sur un pixel', function (): void {
        // Tous les gabarits le déclarent ; il mesurait 1 × 1, donc l'onglet du
        // navigateur n'affichait rien sur l'ensemble du site.
        [$largeur, $hauteur] = getimagesize(public_path('assets/images/favicon.png'));

        expect($largeur)->toBeGreaterThanOrEqual(32)
            ->and($hauteur)->toBeGreaterThanOrEqual(32);
    });
});
