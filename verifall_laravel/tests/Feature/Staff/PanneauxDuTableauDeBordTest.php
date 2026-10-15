<?php

declare(strict_types=1);

use App\Core\Application\Services\PanneauxDuTableauDeBord;
use App\Core\FactChecking\Services\ClassificationThematique;
use App\Enums\ThematiqueVerification;
use App\Enums\UserRole;
use App\Models\HubArticle;
use App\Models\User;
use App\Models\Verification;
use App\Models\VisitorHit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Six panneaux qui ne mesuraient rien
|--------------------------------------------------------------------------
|
| `compte-admin/dashboard.html` affichait « Général 427 », « 216 en attente »,
| « Chrome 68 % » — des nombres ÉCRITS EN DUR dans le HTML. Transposés tels
| quels, ils auraient produit un écran qui a l'air de mesurer et n'observe rien,
| en première page du back-office.
|
| Ces tests gardent la propriété inverse : chaque nombre vient de la base, et
| quand la base est vide, le panneau le DIT.
|
*/

beforeEach(function (): void {
    Cache::flush();
});

/** Une vérification minimale, pour ne pas répéter les colonnes obligatoires. */
function uneVerification(string $affirmation): Verification
{
    return Verification::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'text',
        'status' => 'queued',
        'claim' => $affirmation,
        'content_fingerprint' => hash('sha256', $affirmation),
        'cost_units' => 0,
    ]);
}

it('range chaque affirmation sous une thématique, sans appeler aucun modèle', function (string $affirmation, ThematiqueVerification $attendu) {
    expect(app(ClassificationThematique::class)->pour($affirmation))->toBe($attendu);
})->with([
    'fraude au mobile money' => ['Gagnez 500 000 F avec Mobile Money, cliquez ici', ThematiqueVerification::Arnaque],
    'politique accentuée' => ['Le président a signé un décret hier soir', ThematiqueVerification::Politique],
    'santé' => ['Le vaccin contre le paludisme rend stérile', ThematiqueVerification::Sante],
    'économie accentuée' => ['L’économie subit une inflation du prix du carburant', ThematiqueVerification::Economie],
    'sécurité accentuée' => ['Attaque de djihadistes à la frontière nord', ThematiqueVerification::Securite],
    'rien de particulier' => ['Une vidéo montre un chat qui parle', ThematiqueVerification::General],
    'texte vide' => ['', ThematiqueVerification::General],
]);

it('reconnaît les mots ACCENTUÉS', function () {
    /*
     | Le premier jet normalisait avec `iconv('ASCII//TRANSLIT')`, qui rend
     | « pr'esident » selon la locale : le mot se cassait en « pr sident » et
     | AUCUN mot-clé accentué n'était plus reconnu. Le classement fonctionnait
     | sur « vaccin » et « carburant », et échouait en silence sur la moitié de
     | la taxonomie. Ce test est là pour que cela ne revienne pas.
     */
    $classement = app(ClassificationThematique::class);

    expect($classement->pour('président'))->toBe(ThematiqueVerification::Politique)
        ->and($classement->pour('économie'))->toBe(ThematiqueVerification::Economie)
        ->and($classement->pour('sécurité'))->toBe(ThematiqueVerification::Securite);
});

it('fait passer la fraude AVANT le domaine dont elle se réclame', function () {
    // « Investissement garanti pour financer les hôpitaux » est d'abord une
    // tentative d'escroquerie : c'est cela qu'il faut voir monter.
    expect(app(ClassificationThematique::class)->pour(
        'Investissement garanti en crypto pour financer les hôpitaux du pays'
    ))->toBe(ThematiqueVerification::Arnaque);
});

it('classe automatiquement à la création, quel que soit le chemin', function () {
    /*
     | L'observateur, et non un appel dans le service de soumission : les
     | vérifications naissent par trois chemins, plus les fabriques et les
     | seeders. Un appel posé dans un seul produirait un classement partiel —
     | qui ressemble à un classement.
     */
    $verification = uneVerification('Fausse offre d’emploi au port : envoyez 10 000 F pour le dossier');

    expect($verification->theme)->toBe(ThematiqueVerification::Arnaque);
});

it('n’écrase JAMAIS un thème posé à la main', function () {
    $verification = Verification::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'text',
        'status' => 'queued',
        'claim' => 'Le vaccin rend stérile',
        'theme' => ThematiqueVerification::Politique->value,
        'content_fingerprint' => hash('sha256', 'correction-manuelle'),
        'cost_units' => 0,
    ]);

    expect($verification->theme)->toBe(ThematiqueVerification::Politique);
});

it('classe les thématiques par volume, sans les lignes à zéro', function () {
    uneVerification('Gagnez de l’argent avec Mobile Money');
    uneVerification('Une arnaque au faux recrutement circule');
    uneVerification('Le ministre a démissionné');

    $top = app(PanneauxDuTableauDeBord::class)->topThematiques();

    expect($top)->toHaveCount(2)
        ->and($top[0]['theme'])->toBe(ThematiqueVerification::Arnaque)
        ->and($top[0]['nombre'])->toBe(2)
        ->and($top[1]['nombre'])->toBe(1);
});

it('rend les jours SANS signalement, pour que la courbe ne mente pas', function () {
    /*
     | Un graphe qui saute les jours vides transforme trois signalements espacés
     | d'une semaine en une courbe qui monte. La forme mentirait.
     */
    $serie = app(PanneauxDuTableauDeBord::class)->activiteDesSignalements();

    expect($serie)->toHaveCount(14)
        ->and(array_column($serie, 'jour'))->toBe(
            collect(range(13, 0))->map(fn (int $r): string => now()->subDays($r)->toDateString())->all()
        );
});

it('compte un départ vers la source, et refuse une URL piégée', function () {
    $article = HubArticle::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'essai-depart-vers-la-source',
        'title' => 'Aucune taxe WhatsApp n’est instaurée au Bénin',
        'claim' => 'Une taxe WhatsApp entre en vigueur',
        'excerpt' => 'Aucun texte officiel n’instaure une telle taxe.',
        'body' => '<p>Démenti.</p>',
        'category' => 'Société',
        'verdict' => 'false',
        'is_published' => true,
        'published_at' => now(),
        // `source_clicks` n'est PAS `fillable`, et c'est voulu : un compteur
        // ne se pose pas à la main. Il part de zéro et ne monte que par
        // `increment()`, depuis le contrôleur de départ.
        'source_url' => 'https://www.gouv.bj/actualite/essai',
    ]);

    $this->get(route('public.fact.source', $article))
        ->assertRedirect('https://www.gouv.bj/actualite/essai')
        // On ne transmet pas notre autorité de référencement à ce qu'on démentit,
        // et passer par une route interne ne doit pas contourner cette décision.
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($article->fresh()->source_clicks)->toBe(1);

    // Une valeur saisie de travers en base ne doit pas partir dans un `Location`.
    $article->forceFill(['source_url' => 'javascript:alert(1)'])->save();

    $this->get(route('public.fact.source', $article))
        ->assertRedirect(route('public.fact', $article));

    expect($article->fresh()->source_clicks)->toBe(1);
});

it('calcule la part de chaque navigateur sur la fenêtre', function () {
    VisitorHit::create(['browser' => 'Chrome', 'day' => now()->toDateString(), 'hits' => 30]);
    VisitorHit::create(['browser' => 'Firefox', 'day' => now()->toDateString(), 'hits' => 10]);
    // Hors fenêtre : ne doit pas compter.
    VisitorHit::create(['browser' => 'Safari', 'day' => now()->subDays(40)->toDateString(), 'hits' => 999]);

    $panneau = app(PanneauxDuTableauDeBord::class)->navigateurs();

    expect($panneau['total'])->toBe(40)
        ->and($panneau['familles'])->toHaveCount(2)
        ->and($panneau['familles'][0])->toMatchArray(['nom' => 'Chrome', 'part' => 75.0]);
});

it('ne montre pas un citoyen anonymisé dans la liste de bienvenue', function () {
    /*
     | Après une demande de suppression RGPD, il ne reste qu'un identifiant
     | technique. L'afficher dans une liste de bienvenue rappellerait à l'écran
     | quelqu'un qui a précisément demandé à disparaître.
     */
    $this->seed(RolesAndPermissionsSeeder::class);

    $vivant = User::factory()->create();
    $vivant->assignRole(UserRole::Citoyen->value);

    $efface = User::factory()->create(['anonymized_at' => now()]);
    $efface->assignRole(UserRole::Citoyen->value);

    $liste = app(PanneauxDuTableauDeBord::class)->nouveauxCitoyens();

    expect($liste->pluck('uuid')->all())->toContain($vivant->uuid)
        ->and($liste->pluck('uuid')->all())->not->toContain($efface->uuid);
});
