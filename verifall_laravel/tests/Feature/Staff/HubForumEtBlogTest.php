<?php

declare(strict_types=1);

use App\Core\Community\Services\ModerationDuForum;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Staff\ActualitesHub;
use App\Livewire\Staff\ArticlesDuBlog;
use App\Livewire\Staff\CategoriesDuForum;
use App\Livewire\Staff\ForumCitoyen;
use App\Models\BlogPost;
use App\Models\ForumCategory;
use App\Models\HubArticle;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Hub, forum, blog — lot 8
|--------------------------------------------------------------------------
| Cinq écrans, et trois distinctions que le code ne doit jamais perdre :
|
| · VERROUILLER n'est pas SUPPRIMER. Un sujet verrouillé reste lisible ; une
|   suppression emporte la discussion entière, réponses de bonne foi comprises.
| · ESCALADER n'est pas TRANCHER. `Thread` sépare `escalated_by` de
|   `verified_by` : escalader, c'est demander.
| · Le HUB n'est pas le BLOG. Un article du Hub porte une affirmation et un
|   verdict, et alimente le flux ClaimReview. Un texte d'opinion qui y entrerait
|   serait lu par les moteurs comme une vérification.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

function uneRubrique(string $nom = 'Débats'): ForumCategory
{
    return ForumCategory::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => Str::slug($nom).'-'.Str::lower(Str::random(4)),
        'name' => $nom,
        'color' => '#059669',
        'position' => 10,
        'is_active' => true,
    ]);
}

function unSujet(?ForumCategory $rubrique = null): Thread
{
    return Thread::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sujet-'.Str::lower(Str::random(6)),
        'user_id' => User::factory()->create()->id,
        'forum_category_id' => $rubrique?->id,
        'title' => 'Une rumeur circule sur WhatsApp',
        'body' => str_repeat('Le contenu du sujet. ', 5),
        'last_activity_at' => now(),
    ]);
}

describe('la modération du forum', function (): void {
    it('verrouille sans supprimer — le sujet reste lisible', function (): void {
        $sujet = unSujet();

        Livewire::actingAs($this->admin, 'staff')
            ->test(ForumCitoyen::class)
            ->call('basculerLeVerrou', $sujet->uuid);

        expect($sujet->refresh()->is_locked)->toBeTrue()
            // La ligne EXISTE toujours : c'est toute la différence.
            ->and(Thread::query()->whereKey($sujet->id)->exists())->toBeTrue();

        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.lock']);
    });

    it('emporte les réponses quand on supprime, et les compte d\'abord', function (): void {
        /*
         | L'écran doit pouvoir dire AVANT le geste combien de messages partent.
         | Une discussion de quarante réponses ne se supprime pas comme un
         | message isolé.
         */
        $sujet = unSujet();

        foreach (range(1, 3) as $rang) {
            Reply::query()->create([
                'uuid' => (string) Str::uuid(),
                'thread_id' => $sujet->id,
                'user_id' => User::factory()->create()->id,
                'body' => 'Réponse '.$rang,
            ]);
        }

        $emportees = app(ModerationDuForum::class)->supprimerLeSujet($sujet);

        expect($emportees)->toBe(3)
            ->and(Thread::query()->whereKey($sujet->id)->exists())->toBeFalse()
            ->and(Reply::query()->where('thread_id', $sujet->id)->count())->toBe(0);
    });

    it('refuse d\'escalader deux fois', function (): void {
        /*
         | Escalader deux fois écraserait la date d'origine — donc l'ancienneté
         | de la demande, seule chose qui ordonne la file du fact-checking.
         */
        $sujet = unSujet();
        $moderation = app(ModerationDuForum::class);

        $moderation->escalader($sujet, $this->admin);
        $premiereDate = $sujet->refresh()->escalated_at;

        expect(fn () => $moderation->escalader($sujet, $this->admin))
            ->toThrow(RuntimeException::class, 'déjà été transmis');

        expect($sujet->refresh()->escalated_at?->timestamp)->toBe($premiereDate?->timestamp);
    });

    it('refuse d\'escalader un sujet déjà tranché', function (): void {
        $sujet = unSujet();
        $sujet->forceFill(['official_verdict' => VerdictFactCheck::Faux->value])->save();

        expect(fn () => app(ModerationDuForum::class)->escalader($sujet, $this->admin))
            ->toThrow(RuntimeException::class, 'verdict officiel');
    });

    it('sépare modérer de supprimer', function (): void {
        // La matrice donne les deux au modérateur, mais ce sont deux décisions
        // — et un rôle futur pourrait n'avoir que la première.
        $moderateur = User::factory()->create();
        $moderateur->assignRole(UserRole::Moderateur->value);

        expect($moderateur->can('forum.moderate'))->toBeTrue()
            ->and($moderateur->can('forum.delete'))->toBeTrue()
            // Il ne touche PAS aux rubriques : `forum_category.view` seulement.
            ->and($moderateur->can('forum_category.view'))->toBeTrue()
            ->and($moderateur->can('forum_category.manage'))->toBeFalse();
    });
});

describe('les rubriques du forum', function (): void {
    it('fige le slug, qui sert le filtre citoyen ET l\'API mobile', function (): void {
        /*
         | `ForumController` cherche la rubrique par slug. Le laisser suivre un
         | nom corrigé casserait le filtre web et l'API d'un coup, sans erreur.
         */
        $rubrique = uneRubrique('Santé');
        $slugOrigine = $rubrique->slug;

        Livewire::actingAs($this->admin, 'staff')
            ->test(CategoriesDuForum::class)
            ->call('ouvrirEdition', $rubrique->uuid)
            ->set('nom', 'Santé publique')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($rubrique->refresh()->name)->toBe('Santé publique')
            ->and($rubrique->slug)->toBe($slugOrigine);
    });

    it('refuse une couleur qui n\'est pas hexadécimale', function (): void {
        // La valeur alimente un attribut `style` côté citoyen : une chaîne
        // libre y serait injectée telle quelle.
        Livewire::actingAs($this->admin, 'staff')
            ->test(CategoriesDuForum::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Une rubrique')
            ->set('couleur', 'red; background:url(javascript:alert(1))')
            ->call('enregistrer')
            ->assertHasErrors('couleur');
    });

    it('ne supprime pas les sujets de la rubrique supprimée', function (): void {
        $rubrique = uneRubrique();
        $sujet = unSujet($rubrique);

        Livewire::actingAs($this->admin, 'staff')
            ->test(CategoriesDuForum::class)
            ->call('demanderLaSuppression', $rubrique->uuid)
            ->call('supprimer');

        expect(Thread::query()->whereKey($sujet->id)->exists())->toBeTrue()
            ->and($sujet->refresh()->forum_category_id)->toBeNull();
    });
});

describe('les actualités du Hub', function (): void {
    it('exige l\'affirmation vérifiée, cœur du démenti', function (): void {
        /*
         | Sans elle, l'article explique une rumeur que personne ne reconnaît —
         | et le flux ClaimReview, qui la publie comme `claimReviewed`, n'a rien
         | à donner aux moteurs.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(ActualitesHub::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un titre suffisamment long')
            ->set('affirmation', '')
            ->set('chapeau', str_repeat('Le chapeau de cet article. ', 2))
            ->set('corps', str_repeat('Le corps de l\'article. ', 5))
            ->set('categorie', 'Santé')
            ->call('enregistrer')
            ->assertHasErrors('affirmation');
    });

    it('purifie le corps AVANT persistance', function (): void {
        // L'article est rendu au public ET ingéré par des moteurs.
        Livewire::actingAs($this->admin, 'staff')
            ->test(ActualitesHub::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un démenti à publier')
            ->set('affirmation', 'Une rumeur qui circule largement.')
            ->set('chapeau', str_repeat('Le chapeau de cet article. ', 2))
            ->set('corps', '<p>Analyse</p><script>alert(1)</script><img src=x onerror="alert(2)">')
            ->set('categorie', 'Santé')
            ->set('verdict', VerdictFactCheck::Faux->value)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $article = HubArticle::query()->firstOrFail();

        expect($article->body)
            ->toContain('Analyse')
            ->not->toContain('<script')
            ->not->toContain('onerror');
    });

    it('naît en brouillon, et la date de mise en ligne ne se réécrit pas', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(ActualitesHub::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un démenti à publier')
            ->set('affirmation', 'Une rumeur qui circule largement.')
            ->set('chapeau', str_repeat('Le chapeau de cet article. ', 2))
            ->set('corps', str_repeat('Le corps de l\'article. ', 5))
            ->set('categorie', 'Santé')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $article = HubArticle::query()->firstOrFail();

        expect($article->is_published)->toBeFalse();

        $composant = Livewire::actingAs($this->admin, 'staff')->test(ActualitesHub::class);

        $composant->call('basculerLaPublication', $article->uuid);
        $premiere = $article->refresh()->published_at;

        // Dépublier puis republier ne rajeunit pas le démenti : c'est cette
        // date qui le situe dans le temps.
        $composant->call('basculerLaPublication', $article->uuid);
        $composant->call('basculerLaPublication', $article->uuid);

        expect($article->refresh()->published_at?->timestamp)->toBe($premiere?->timestamp);
    });
});

describe('le blog', function (): void {
    it('n\'offre PAS la publication dans le formulaire', function (): void {
        // `post.publish` est séparé de `post.update` : une case au formulaire
        // rendrait le droit contournable.
        $composant = Livewire::actingAs($this->admin, 'staff')
            ->test(ArticlesDuBlog::class)->instance();

        expect(property_exists($composant, 'publie'))->toBeFalse()
            ->and(method_exists($composant, 'basculerLaPublication'))->toBeTrue();
    });

    it('borne les métadonnées à ce que les moteurs affichent', function (): void {
        // Au-delà, la fin est tronquée — et une phrase coupée au milieu vaut
        // moins qu'une phrase courte écrite exprès.
        Livewire::actingAs($this->admin, 'staff')
            ->test(ArticlesDuBlog::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un billet de fond')
            ->set('chapeau', str_repeat('Le chapeau de ce billet. ', 2))
            ->set('corps', str_repeat('Le corps du billet. ', 5))
            ->set('metaTitre', str_repeat('a', 100))
            ->call('enregistrer')
            ->assertHasErrors('metaTitre');
    });

    it('purifie le corps, comme le Hub', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(ArticlesDuBlog::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un billet de fond')
            ->set('chapeau', str_repeat('Le chapeau de ce billet. ', 2))
            ->set('corps', '<p>'.str_repeat('Un texte de fond suffisamment long. ', 3).'</p><script>alert(1)</script>')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(BlogPost::query()->value('body'))
            ->toContain('Un texte de fond')
            ->not->toContain('<script');
    });

    it('reste distinct du Hub — deux tables, deux droits', function (): void {
        /*
         | La confusion serait grave dans un sens : un texte d'opinion entré
         | dans le flux ClaimReview y serait lu comme une vérification.
         */
        $moderateur = User::factory()->create();
        $moderateur->assignRole(UserRole::Moderateur->value);

        expect($moderateur->can('hub_article.view'))->toBeTrue()
            ->and($moderateur->can('hub_article.create'))->toBeFalse()
            ->and($moderateur->can('post.view'))->toBeFalse();
    });
});
