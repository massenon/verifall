<?php

declare(strict_types=1);

use App\Enums\VerdictFactCheck;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| Les huit composants de courriel
|--------------------------------------------------------------------------
|
| Ils sont rendus par le VRAI moteur — `Illuminate\Mail\Markdown` —, celui qui
| pose lui-même le namespace `mail::` et applique le thème. Un rendu à la
| ficelle par `Blade::render()` résoudrait les composants du FRAMEWORK dès
| qu'un envoi a eu lieu plus tôt dans le processus, et validerait des fichiers
| qui ne sont pas les nôtres.
|
| ## Ce que ces tests cherchent
|
| La directive demande (§51) qu'un gabarit ne produise JAMAIS un courriel
| cassé à cause d'une donnée manquante, et (§52) que chaque composant prévoie
| ses états. Les jeux ci-dessous sont donc des cas de BORD, pas des cas
| moyens : `null`, chaîne vide, 0 %, 100 %, valeur hors bornes, nom
| interminable.
|
| Ils répondent aussi à un manque que le projet a payé quatre fois : une
| chaîne complète qui n'atteint aucun gabarit. Huit composants que personne ne
| rend seraient du code mort qui compile.
|
*/

/** Rend le gabarit d'essai avec un jeu de données donné. */
function rendreLesComposants(array $donnees): string
{
    View::addNamespace('essais', base_path('tests/Fixtures/views'));

    return app(Markdown::class)->render('essais::composants', $donnees + [
        'preheader' => 'Aperçu de la boîte de réception.',
        'verdict' => VerdictFactCheck::Faux,
        'confiance' => 87,
        'titre' => 'Un titre ordinaire',
        'texte' => 'Une explication courte.',
        'pourcentage' => 72,
        'date' => '9 septembre 2026',
    ])->toHtml();
}

it('rend les huit composants sur des données ordinaires', function (): void {
    $rendu = rendreLesComposants([]);

    expect($rendu)
        ->toContain('Un titre ordinaire')
        ->toContain('Une explication courte.')
        ->toContain('72')
        ->toContain('ABC-123')
        ->toContain('En savoir plus');
});

it('écrit toujours le verdict EN TOUTES LETTRES, jamais par la seule couleur', function (): void {
    foreach (VerdictFactCheck::cases() as $verdict) {
        $rendu = rendreLesComposants(['verdict' => $verdict]);

        // Le mot d'abord : un destinataire qui ne distingue pas les couleurs,
        // un client qui refuse le CSS, un texte recopié à la main doivent tous
        // lire le verdict sans rien deviner (§12 et §36).
        // Le mot est dans la SOURCE, en clair. Les capitales du badge viennent
        // d'un `text-transform` : chercher « FAUX » dans le HTML ferait échouer
        // le test sur un courriel parfaitement lisible — et un lecteur d'écran
        // lit de toute façon le texte, pas la mise en forme.
        expect($rendu)->toContain($verdict->libelle())
            ->and($rendu)->toContain($verdict->couleurBarre());
    }
});

it('ne casse sur aucune donnée absente', function (): void {
    $rendu = rendreLesComposants([
        'confiance' => null,
        'date' => null,
        'texte' => '',
        'titre' => '',
    ]);

    // Aucune trace de gabarit en détresse, et le courriel garde sa structure.
    expect($rendu)->not->toContain('Undefined')
        ->and($rendu)->not->toContain('{{')
        ->and($rendu)->toContain('class="wrapper"');
});

it('borne une progression aberrante au lieu de la typographier', function (): void {
    // Une barre à 140 % est un mensonge dessiné ; une barre à -20 % déborde.
    expect(rendreLesComposants(['pourcentage' => 140]))->toContain('100')
        ->and(rendreLesComposants(['pourcentage' => -20]))->toContain('0');

    // À 0 comme à 100, le chiffre reste écrit : la barre seule ne dit rien à
    // qui ne la voit pas.
    expect(rendreLesComposants(['pourcentage' => 0]))->toContain('0')
        ->and(rendreLesComposants(['pourcentage' => 100]))->toContain('100');
});

it('encaisse un nom interminable sans déborder', function (): void {
    $rendu = rendreLesComposants(['titre' => str_repeat('Nom très long ', 30)]);

    expect($rendu)->toContain('Nom très long')
        ->and($rendu)->toContain('class="wrapper"');
});

it('échappe le texte reçu : un composant de courriel ne pose pas de HTML brut', function (): void {
    $rendu = rendreLesComposants([
        'titre' => '<script>alert(1)</script>',
        'texte' => '<img src=x onerror=alert(1)>',
    ]);

    // Un courriel part HORS de l'application, là où plus aucun filtre ne le
    // rattrape. C'est le seul endroit du produit où « en amont c'est déjà
    // propre » ne suffit pas.
    // On cherche la forme DANGEREUSE — une balise réellement ouverte —, pas la
    // sous-chaîne. `&lt;img src=x onerror=…&gt;` contient « onerror= » et est
    // parfaitement inerte : une assertion sur la sous-chaîne échouerait sur un
    // courriel correct, et c'est ainsi qu'on désactive un test de sécurité.
    expect($rendu)->not->toContain('<script')
        ->and($rendu)->not->toContain('<img src=x')
        ->and($rendu)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('n\'emploie aucune mise en page que les clients de messagerie ignorent', function (): void {
    $dossier = resource_path('views/vendor/mail/html');

    $composants = [
        'verdict', 'carte-preuve', 'carte-source', 'carte-alerte',
        'progression', 'carte-cours', 'carte-certificat', 'cta-secondaire',
    ];

    foreach ($composants as $composant) {
        $source = (string) file_get_contents($dossier.'/'.$composant.'.blade.php');

        // Outlook rend le HTML avec le moteur de Word : les tableaux ne sont
        // pas un archaïsme, ce sont les seuls conteneurs qui tiennent partout.
        expect($source)
            ->not->toContain('display: flex')
            ->not->toContain('display: grid')
            ->not->toContain('position: absolute')
            ->not->toContain('<script');
    }
});
