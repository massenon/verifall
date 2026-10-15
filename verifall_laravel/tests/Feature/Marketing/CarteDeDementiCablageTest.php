<?php

declare(strict_types=1);

use App\Core\Marketing\Cartes\CarteDeDementi;
use App\Core\Marketing\Seo\Referencement;
use App\Enums\VerdictFactCheck;
use App\Jobs\EngendrerLaCarteDeDementi;
use App\Models\HubArticle;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| LE CÂBLAGE de la carte de démenti
|--------------------------------------------------------------------------
| Le lot qui a produit la carte ne pouvait pas la brancher : l'observateur, la
| route et la balise `og:image` vivent dans des fichiers qui lui étaient
| interdits pendant le chantier parallèle. Sa fabrique était donc éprouvée, et
| pourtant AUCUNE carte n'était jamais fabriquée.
|
| C'est la panne la plus coûteuse de ce genre de découpage, parce qu'elle ne
| produit aucune erreur : chaque test de la fabrique reste vert, et le partage
| WhatsApp continue de montrer l'image générique.
|
| Ces tests-là éprouvent les trois soudures, et rien d'autre.
*/

beforeEach(function (): void {
    Storage::fake(CarteDeDementi::DISQUE);
});

function articleCable(array $extra = []): HubArticle
{
    return HubArticle::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cable-'.Str::random(8),
        'title' => "Non, aucune coupure d'électricité générale n'est programmée",
        'excerpt' => 'Aucun communiqué de la SBEE ne mentionne cette mesure.',
        'body' => '<p>Analyse.</p>',
        'category' => 'Actualités',
        'verdict' => VerdictFactCheck::Faux->value,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ], $extra));
}

it('met une carte en file dès la publication, sans que personne y pense', function (): void {
    /*
     | LA soudure principale. Sans observateur enregistré, rien n'appelle
     | jamais `planifierSiNecessaire()` — et le lot entier est du code mort.
     */
    Queue::fake();

    $article = articleCable();

    Queue::assertPushed(
        EngendrerLaCarteDeDementi::class,
        static fn (EngendrerLaCarteDeDementi $travail): bool => true,
    );

    expect($article->exists)->toBeTrue();
});

it('ne met rien en file pour un brouillon', function (): void {
    // L'observateur passe par `estEligible()` : un brouillon n'a pas de carte,
    // sinon on fabriquerait un verdict avant de l'avoir publié.
    Queue::fake();

    articleCable(['is_published' => false, 'published_at' => null]);

    Queue::assertNotPushed(EngendrerLaCarteDeDementi::class);
});

it('refabrique la carte quand le verdict est corrigé', function (): void {
    $article = articleCable();
    app(CarteDeDementi::class)->engendrer($article->refresh());

    $avant = $article->refresh()->share_card_path;
    expect($avant)->not->toBeNull();

    Queue::fake();
    $article->update(['verdict' => VerdictFactCheck::Vrai->value]);

    // Le verdict a changé, donc la carte n'est plus à jour : un travail part.
    Queue::assertPushed(EngendrerLaCarteDeDementi::class);
});

it("pose la carte en og:image du fact-check, pas l'image générique", function (): void {
    /*
     | La deuxième soudure. C'est CETTE balise que WhatsApp lit ; une carte
     | fabriquée mais jamais déclarée ne circule pas davantage qu'une carte
     | absente.
     */
    $article = articleCable();
    app(CarteDeDementi::class)->engendrer($article->refresh());

    $seo = app(Referencement::class)->pourFaitVerifie($article->refresh());

    expect($seo->image)->toContain('carte')
        ->and($seo->image)->not->toContain('verifall-prix-wsis-2026-annonce');
});

it("retombe sur l'image de couverture tant que la carte n'est pas fabriquée", function (): void {
    /*
     | LA FENÊTRE ENTRE LA PUBLICATION ET LE PASSAGE DU WORKER.
     |
     | Elle dure quelques secondes en service, et c'est exactement le moment où
     | un fact-check est partagé le plus. Le repli doit donc rester intact :
     | sans lui, l'article publié à la seconde près part sans aucun aperçu.
     |
     | `Queue::fake()` reproduit précisément cet état — le travail est en file,
     | il n'a pas encore tourné.
     */
    Queue::fake();

    $article = articleCable(['cover_path' => 'assets/images/blog/une.jpg']);

    $seo = app(Referencement::class)->pourFaitVerifie($article->refresh());

    expect($seo->image)->toContain('une.jpg');
});

it('efface la carte quand le fact-check est supprimé', function (): void {
    /*
     | Sans cela l'image resterait servie par son URL après le retrait de
     | l'article : un démenti que le site ne montre plus, mais que WhatsApp
     | continue d'afficher.
     */
    $article = articleCable();
    app(CarteDeDementi::class)->engendrer($article->refresh());

    $chemin = $article->refresh()->share_card_path;
    Storage::disk(CarteDeDementi::DISQUE)->assertExists($chemin);

    $article->delete();

    Storage::disk(CarteDeDementi::DISQUE)->assertMissing($chemin);
});

it('sert la carte sur la route publique posée à l’intégration', function (): void {
    // La troisième soudure : le lot avait dû déclarer cette route dans son
    // propre test. Ici on éprouve celle de `routes/web.php`.
    $article = articleCable();
    app(CarteDeDementi::class)->engendrer($article->refresh());

    $this->get(route('public.fact.carte', $article->refresh()))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});
