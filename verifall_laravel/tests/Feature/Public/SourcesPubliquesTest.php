<?php

declare(strict_types=1);

use App\Livewire\Public\SourcesPubliques;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| « Nos sources », la page de transparence
|--------------------------------------------------------------------------
|
| Le référentiel décidait déjà, en silence, du crédit accordé à un domaine dans
| chaque analyse. Le publier rend vérifiable ce qui était opaque — c'est le
| pendant de la page Méthodologie : celle-là dit COMMENT une affirmation devient
| un verdict, celle-ci dit SUR QUOI.
|
| Ce qui est éprouvé ici n'est pas la mise en page. C'est la PROMESSE : la liste
| est complète, elle vient du même référentiel que le moteur, et elle ne laisse
| pas fuir ce qui n'a pas à être publié.
|
*/

beforeEach(function (): void {
    Cache::flush();

    $famille = SourceCategory::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Réseaux de fact-checking',
        'slug' => 'reseaux-de-fact-checking',
        'description' => 'Rédactions membres de réseaux reconnus.',
        'default_credibility' => 88,
        'sort_order' => 50,
    ]);

    $presse = SourceCategory::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Presse béninoise',
        'slug' => 'presse-beninoise',
        'description' => 'Titres nationaux.',
        'default_credibility' => 75,
        'sort_order' => 40,
    ]);

    TrustedSource::query()->create([
        'uuid' => (string) Str::uuid(),
        'source_category_id' => $famille->id,
        'name' => 'Africa Check',
        'domain' => 'africacheck.org',
        'url' => 'https://africacheck.org',
        'credibility' => 90,
        'is_active' => true,
        'notes' => 'Note INTERNE de la rédaction, à ne jamais publier.',
    ]);

    TrustedSource::query()->create([
        'uuid' => (string) Str::uuid(),
        'source_category_id' => $presse->id,
        'name' => 'La Nation',
        'domain' => 'lanationbenin.info',
        'url' => 'https://lanationbenin.info',
        'credibility' => 78,
        'is_active' => true,
        'notes' => null,
    ]);

    // Une source DÉSACTIVÉE : elle ne doit apparaître nulle part.
    TrustedSource::query()->create([
        'uuid' => (string) Str::uuid(),
        'source_category_id' => $famille->id,
        'name' => 'Rédaction retirée',
        'domain' => 'retiree.example',
        'url' => 'https://retiree.example',
        'credibility' => 50,
        'is_active' => false,
        'notes' => null,
    ]);
});

it('sert la page à un visiteur qui n’a pas de compte', function (): void {
    // Une page de transparence derrière une connexion ne transparaît rien.
    $this->get(route('public.sources'))->assertOk();
});

it('publie la liste, groupée par famille', function (): void {
    Livewire::test(SourcesPubliques::class)
        ->assertSee('Africa Check')
        ->assertSee('africacheck.org')
        ->assertSee('La Nation')
        ->assertSee('Réseaux de fact-checking')
        ->assertSee('Presse béninoise');
});

it('ne publie JAMAIS une note interne ni une crédibilité chiffrée', function (): void {
    /*
     | Le test qui compte.
     |
     | `notes` est un carnet de travail de la rédaction. Et la crédibilité, sur
     | les 386 sources reprises d'un répertoire tiers, vaut la valeur par DÉFAUT
     | de leur famille — aucune n'a été notée une par une. Afficher « 90 » en
     | face d'une source laisserait croire à une évaluation individuelle qui n'a
     | pas eu lieu, et inviterait à discuter un chiffre que personne n'a arbitré.
     */
    $rendu = Livewire::test(SourcesPubliques::class)->html();

    /*
     | Sur le TEXTE visible, pas sur le HTML brut.
     |
     | « 90 » se trouve dans un identifiant Livewire ou une couleur
     | hexadécimale sans que rien ne soit publié. Une assertion sur la source
     | brute échouerait donc sur une page parfaitement correcte — et un test qui
     | crie au loup sur du code sain est un test qu'on finit par désactiver.
     */
    $texte = (string) preg_replace('/\s+/', ' ', (string) strip_tags($rendu));

    expect($texte)->not->toContain('Note INTERNE')
        ->and($texte)->not->toContain('90')
        ->and($texte)->not->toContain('78');
});

it('n’affiche pas une source désactivée', function (): void {
    // Retirer une source du référentiel doit la retirer de la page : sans quoi
    // la plateforme continue de se réclamer publiquement d'une source qu'elle
    // ne crédite plus.
    Livewire::test(SourcesPubliques::class)
        ->assertDontSee('Rédaction retirée')
        ->assertDontSee('retiree.example');
});

it('cherche sur le nom comme sur le domaine', function (): void {
    Livewire::test(SourcesPubliques::class)
        ->set('recherche', 'africacheck.org')
        ->assertSee('Africa Check')
        ->assertDontSee('La Nation')
        ->set('recherche', 'Nation')
        ->assertSee('La Nation')
        ->assertDontSee('Africa Check');
});

it('filtre par famille, et le second clic relâche le filtre', function (): void {
    Livewire::test(SourcesPubliques::class)
        ->call('choisirLaFamille', 'Presse béninoise')
        ->assertSee('La Nation')
        ->assertDontSee('Africa Check')
        // Recliquer sur la famille active la relâche : le geste attendu quand
        // on veut « tout » revoir, et celui qu'emploie déjà l'écran des outils.
        ->call('choisirLaFamille', 'Presse béninoise')
        ->assertSee('Africa Check');
});

it('le dit quand une recherche ne donne rien, au lieu d’afficher un vide', function (): void {
    // Un écran vide et un écran cassé se ressemblent exactement.
    Livewire::test(SourcesPubliques::class)
        ->set('recherche', 'zzzzz-introuvable')
        ->assertSee(__('nos_sources.aucune_source_ne_correspond'));
});

it('porte l’état dans l’adresse, pour qu’un filtre se partage', function (): void {
    /*
     | `#[Url]` n'a d'intérêt que vu du dehors : un lien collé dans un message
     | doit rouvrir la page déjà filtrée. On l'éprouve donc par une VRAIE
     | requête — vérifier qu'une annotation est posée ne dit rien de ce qu'elle
     | produit.
     */
    $this->get(route('public.sources', ['q' => 'africacheck.org']))
        ->assertOk()
        ->assertSee('Africa Check')
        ->assertDontSee('La Nation');
});

it('figure au plan du site : une page de transparence doit se trouver', function (): void {
    $corps = (string) $this->get('/sitemap.xml')->getContent();

    expect($corps)->toContain(route('public.sources'));
});

it('est atteignable depuis le pied de page de toute page publique', function (): void {
    /*
     | Le manque structurel que ce projet a payé QUATRE fois : une chaîne
     | serveur complète qui n'atteint aucun gabarit. Elle répond juste, et elle
     | ne s'affiche nulle part.
     |
     | Une page de transparence qu'aucun lien n'atteint ne transparaît rien.
     */
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee(route('public.sources'));
});
