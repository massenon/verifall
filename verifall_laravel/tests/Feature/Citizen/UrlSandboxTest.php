<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\UrlSandbox;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| UrlSandbox — anti-SSRF, phase 6
|--------------------------------------------------------------------------
| Le point P0 de l'audit de sécurité : une validation qui ne porterait que
| sur l'URL INITIALE laisse passer un SSRF par redirection. Chaque test ici
| vérifie un saut, pas seulement un point de départ.
|
| Le résolveur DNS est TOUJOURS injecté : un test de sécurité qui dépendrait
| du réseau pour vérifier une règle de sécurité serait le pire genre de test
| flaky possible.
*/

function sandboxAvec(array $carte): UrlSandbox
{
    return new UrlSandbox(resoudreHote: fn (string $hote): string => $carte[$hote] ?? '203.0.113.10');
}

describe('schémas', function (): void {
    it('refuse un schéma autre que http(s)', function (): void {
        expect(sandboxAvec([])->recuperer('file:///etc/passwd'))->toBeNull();
        expect(sandboxAvec([])->recuperer('ftp://exemple.com/x'))->toBeNull();
        expect(sandboxAvec([])->recuperer('gopher://exemple.com/x'))->toBeNull();
    });
});

describe('hôtes littéraux', function (): void {
    it('refuse localhost sous toutes ses formes', function (): void {
        Http::fake();

        expect(sandboxAvec([])->recuperer('http://localhost/'))->toBeNull();
        expect(sandboxAvec([])->recuperer('http://sous.localhost/'))->toBeNull();
        expect(sandboxAvec([])->recuperer('http://127.0.0.1/'))->toBeNull();
        expect(sandboxAvec([])->recuperer('http://0.0.0.0/'))->toBeNull();
        expect(sandboxAvec([])->recuperer('http://[::1]/'))->toBeNull();

        Http::assertNothingSent();
    });

    it('refuse une IP privée (RFC 1918) donnée directement', function (): void {
        Http::fake();

        expect(sandboxAvec([])->recuperer('http://10.0.0.5/'))->toBeNull();
        expect(sandboxAvec([])->recuperer('http://172.16.0.1/'))->toBeNull();
        expect(sandboxAvec([])->recuperer('http://192.168.1.1/'))->toBeNull();

        Http::assertNothingSent();
    });

    it('refuse le point de métadonnées cloud (lien local)', function (): void {
        Http::fake();

        expect(sandboxAvec([])->recuperer('http://169.254.169.254/latest/meta-data/'))->toBeNull();

        Http::assertNothingSent();
    });
});

describe('résolution DNS vers une IP privée', function (): void {
    it('refuse un domaine public qui résout vers une IP privée', function (): void {
        Http::fake();

        $sandbox = sandboxAvec(['interne.exemple.com' => '10.0.0.5']);

        expect($sandbox->recuperer('http://interne.exemple.com/'))->toBeNull();

        Http::assertNothingSent();
    });

    it('accepte un domaine public qui résout vers une IP publique', function (): void {
        Http::fake(['exemple.com/*' => Http::response('contenu public', 200)]);

        $sandbox = sandboxAvec(['exemple.com' => '93.184.216.34']);

        $resultat = $sandbox->recuperer('http://exemple.com/article');

        expect($resultat)->not->toBeNull()
            ->and($resultat['corps'])->toBe('contenu public');
    });
});

describe('le point P0 de l\'audit : chaque SAUT de redirection est revalidé', function (): void {
    it('suit une redirection vers une autre adresse PUBLIQUE', function (): void {
        Http::fake([
            'public-a.exemple/*' => Http::response('', 302, ['Location' => 'http://public-b.exemple/page']),
            'public-b.exemple/*' => Http::response('contenu final', 200),
        ]);

        $sandbox = sandboxAvec(['public-a.exemple' => '203.0.113.1', 'public-b.exemple' => '203.0.113.2']);

        $resultat = $sandbox->recuperer('http://public-a.exemple/depart');

        expect($resultat)->not->toBeNull()
            ->and($resultat['url_finale'])->toBe('http://public-b.exemple/page')
            ->and($resultat['corps'])->toBe('contenu final');
    });

    it('REFUSE une redirection dont la cible résout vers une IP privée — le cas P0', function (): void {
        Http::fake([
            'public-a.exemple/*' => Http::response('', 302, ['Location' => 'http://interne.exemple/secret']),
        ]);

        $sandbox = sandboxAvec(['public-a.exemple' => '203.0.113.1', 'interne.exemple' => '10.0.0.9']);

        expect($sandbox->recuperer('http://public-a.exemple/depart'))->toBeNull();

        // Un seul appel : le second saut n'a jamais été tenté.
        Http::assertSentCount(1);
    });

    it('REFUSE une redirection directement vers une IP privée littérale', function (): void {
        Http::fake([
            'public-a.exemple/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        $sandbox = sandboxAvec(['public-a.exemple' => '203.0.113.1']);

        expect($sandbox->recuperer('http://public-a.exemple/depart'))->toBeNull();
        Http::assertSentCount(1);
    });

    it('résout une redirection RELATIVE contre l\'hôte courant', function (): void {
        Http::fake([
            'public-a.exemple/depart' => Http::response('', 302, ['Location' => '/arrivee']),
            'public-a.exemple/arrivee' => Http::response('contenu', 200),
        ]);

        $sandbox = sandboxAvec(['public-a.exemple' => '203.0.113.1']);

        $resultat = $sandbox->recuperer('http://public-a.exemple/depart');

        expect($resultat)->not->toBeNull()
            ->and($resultat['url_finale'])->toBe('http://public-a.exemple/arrivee');
    });

    it('abandonne après un nombre maximum de sauts, sans boucler indéfiniment', function (): void {
        Http::fake(fn () => Http::response('', 302, ['Location' => 'http://public-a.exemple/boucle']));

        $sandbox = sandboxAvec(['public-a.exemple' => '203.0.113.1']);

        expect($sandbox->recuperer('http://public-a.exemple/boucle'))->toBeNull();
    });
});

describe('robustesse', function (): void {
    it('ne lève JAMAIS, même sur une exception réseau', function (): void {
        Http::fake(fn () => throw new ConnectionException('panne simulée'));

        $sandbox = sandboxAvec(['exemple.com' => '203.0.113.1']);

        expect($sandbox->recuperer('http://exemple.com/'))->toBeNull();
    });

    it('rend null sur une réponse d\'erreur HTTP', function (): void {
        Http::fake(['exemple.com/*' => Http::response('', 404)]);

        $sandbox = sandboxAvec(['exemple.com' => '203.0.113.1']);

        expect($sandbox->recuperer('http://exemple.com/introuvable'))->toBeNull();
    });
});

describe('recupererPlusieurs — en parallèle, même garantie par adresse', function (): void {
    it('récupère plusieurs adresses publiques en un seul lot', function (): void {
        Http::fake([
            'a.exemple/*' => Http::response('contenu A', 200),
            'b.exemple/*' => Http::response('contenu B', 200),
        ]);

        $sandbox = sandboxAvec(['a.exemple' => '203.0.113.1', 'b.exemple' => '203.0.113.2']);

        $resultats = $sandbox->recupererPlusieurs(['http://a.exemple/x', 'http://b.exemple/y']);

        expect($resultats['http://a.exemple/x']['corps'])->toBe('contenu A')
            ->and($resultats['http://b.exemple/y']['corps'])->toBe('contenu B');
    });

    it('refuse individuellement une adresse privée SANS faire échouer les autres du lot', function (): void {
        Http::fake(['a.exemple/*' => Http::response('contenu A', 200)]);

        $sandbox = sandboxAvec(['a.exemple' => '203.0.113.1']);

        $resultats = $sandbox->recupererPlusieurs(['http://a.exemple/x', 'http://10.0.0.5/secret']);

        expect($resultats['http://a.exemple/x']['corps'])->toBe('contenu A')
            ->and($resultats['http://10.0.0.5/secret'])->toBeNull();
    });

    it('revalide CHAQUE saut de CHAQUE adresse, même en lot — le point P0', function (): void {
        Http::fake([
            'a.exemple/*' => Http::response('', 302, ['Location' => 'http://interne.exemple/secret']),
            'b.exemple/*' => Http::response('contenu B', 200),
        ]);

        $sandbox = sandboxAvec([
            'a.exemple' => '203.0.113.1', 'interne.exemple' => '10.0.0.9', 'b.exemple' => '203.0.113.2',
        ]);

        $resultats = $sandbox->recupererPlusieurs(['http://a.exemple/depart', 'http://b.exemple/y']);

        expect($resultats['http://a.exemple/depart'])->toBeNull()
            ->and($resultats['http://b.exemple/y']['corps'])->toBe('contenu B');
    });

    it('rend un tableau vide sur un lot vide, sans appel réseau', function (): void {
        Http::fake();

        expect(sandboxAvec([])->recupererPlusieurs([]))->toBe([]);
        Http::assertNothingSent();
    });
});
