<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Enums\NiveauCours;
use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Enums\UserRole;
use App\Http\Api\V1\Support\Filtre;
use App\Models\Course;
use App\Models\ForumCategory;
use App\Models\HubArticle;
use App\Models\HubJob;
use App\Models\LiveClass;
use App\Models\Thread;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API v1 — les douze filtres de liste
|--------------------------------------------------------------------------
| Deux choses sont vérifiées ici, et la seconde est la plus importante.
|
| 1. Chaque filtre filtre réellement, côté SERVEUR.
| 2. **Le filtre survit au passage de page.** Le curseur ne porte pas les
|    filtres : un client qui les oublie reçoit la page suivante de la liste
|    NON filtrée — sans erreur, sans signe extérieur. C'est le bogue qu'aucun
|    écran ne trahit avant qu'un utilisateur le signale.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;
});

/** Un cours publié, d'un niveau donné. */
function coursApiDeTest(string $slug, NiveauCours $niveau, string $titre = 'Un cours'): Course
{
    return Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => $slug,
        'title' => $titre,
        'description' => 'Une description quelconque.',
        'category' => 'Fondamentaux',
        'level' => $niveau->value,
        'duration_label' => '2 h',
        'cost_units' => 0,
        'is_published' => true,
        'published_at' => now(),
    ]);
}

/** Une opportunité publiée, de portée et de type donnés. */
function offreApiDeTest(string $slug, PorteeOpportunite $portee, TypeOpportunite $type): HubJob
{
    return HubJob::create([
        'uuid' => (string) Str::uuid(),
        'slug' => $slug,
        'title' => 'Une offre',
        'organisation' => 'ONG Sourire',
        'scope' => $portee->value,
        'type' => $type->value,
        'location' => 'Cotonou',
        'description' => 'Une mission.',
        'apply_url' => 'https://exemple.org/postuler',
        'deadline' => now()->addDays(20),
        'is_published' => true,
        'published_at' => now(),
    ]);
}

describe('catalogue — level et q', function (): void {
    beforeEach(function (): void {
        /*
         | Ordre de création VOLONTAIRE : le catalogue est trié par `id`
         | décroissant, donc le cours avancé — créé en premier — se retrouve en
         | DERNIER. C'est ce qui permet, plus bas, de démontrer qu'un curseur
         | envoyé sans son filtre laisse remonter un cours d'un autre niveau.
         */
        coursApiDeTest('avance-un', NiveauCours::Avance, 'Enquête approfondie');
        coursApiDeTest('debutant-un', NiveauCours::Debutant, 'Reconnaître une intox');
        coursApiDeTest('debutant-deux', NiveauCours::Debutant, 'Vérifier une image');
    });

    it('filtre par niveau', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/courses?level='.NiveauCours::Debutant->value)
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withToken($this->jeton)->getJson('/api/v1/courses?level='.NiveauCours::Avance->value)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'avance-un');
    });

    it('refuse un niveau inconnu plutôt que de rendre une liste vide muette', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/courses?level=expert')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation')
            ->assertJsonStructure(['error' => ['details' => ['level']]]);
    });

    it('traite un niveau VIDE comme aucun filtre', function (): void {
        // `?level=` est ce qu'envoie une pastille « Tous » qui poste toujours
        // une valeur. Un 422 ici casserait l'écran par défaut.
        $this->withToken($this->jeton)->getJson('/api/v1/courses?level=')
            ->assertOk()->assertJsonCount(3, 'data');
    });

    it('cherche dans le titre ET la description', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/courses?q=intox')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'debutant-un');

        $this->withToken($this->jeton)->getJson('/api/v1/courses?q=description')
            ->assertOk()->assertJsonCount(3, 'data');
    });

    it('cumule level et q en ET', function (): void {
        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses?level='.NiveauCours::Debutant->value.'&q=Enquête')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('GARDE le filtre au passage de page', function (): void {
        coursApiDeTest('debutant-trois', NiveauCours::Debutant, 'Trois');

        $premiere = $this->withToken($this->jeton)
            ->getJson('/api/v1/courses?level='.NiveauCours::Debutant->value.'&limit=2');

        $premiere->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page.has_more', true);

        $curseur = $premiere->json('meta.page.next_cursor');

        // Le client RENVOIE le filtre avec le curseur : la page 2 ne contient
        // que des débutants, et surtout pas le cours avancé.
        $seconde = $this->withToken($this->jeton)->getJson(
            '/api/v1/courses?level='.NiveauCours::Debutant->value.'&limit=2&cursor='.$curseur,
        );

        $seconde->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'debutant-un');

        foreach ($seconde->json('data') as $cours) {
            expect($cours['niveau'])->toBe(NiveauCours::Debutant->value);
        }
    });

    it('démontre pourquoi le curseur seul ne suffit PAS', function (): void {
        coursApiDeTest('debutant-trois', NiveauCours::Debutant, 'Trois');

        $curseur = $this->withToken($this->jeton)
            ->getJson('/api/v1/courses?level='.NiveauCours::Debutant->value.'&limit=2')
            ->json('meta.page.next_cursor');

        /*
         | Curseur SANS le filtre. Le serveur ne peut pas deviner : il rend la
         | page suivante du catalogue entier. Aucune erreur, aucun signe — la
         | liste est simplement fausse. C'est ce comportement que le contrat
         | documente au §3.4, et la raison pour laquelle le client ne doit
         | jamais filtrer localement une liste paginée.
         */
        $sansFiltre = $this->withToken($this->jeton)->getJson('/api/v1/courses?limit=2&cursor='.$curseur);

        $sansFiltre->assertOk();

        $niveaux = array_column($sansFiltre->json('data'), 'niveau');
        expect($niveaux)->toContain(NiveauCours::Avance->value);
    });
});

describe('forum — category et q', function (): void {
    beforeEach(function (): void {
        $this->sante = ForumCategory::create([
            'uuid' => (string) Str::uuid(), 'slug' => 'sante', 'name' => 'Santé', 'position' => 1,
        ]);
        $this->politique = ForumCategory::create([
            'uuid' => (string) Str::uuid(), 'slug' => 'politique', 'name' => 'Politique', 'position' => 2,
        ]);

        $forum = app(AnimationDuForum::class);
        $forum->publier($this->citoyen, 'Une rumeur sur un vaccin', 'Elle circule depuis hier matin.', $this->sante);
        $forum->publier($this->citoyen, 'Une autre rumeur de santé', 'Elle circule depuis hier matin.', $this->sante);
        $forum->publier($this->citoyen, 'Un communiqué contesté', 'Il circule depuis hier matin.', $this->politique);
    });

    it('filtre par slug de rubrique', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/forum/threads?category=sante')
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('rend une liste VIDE — pas une erreur — sur une rubrique inconnue', function (): void {
        // Les rubriques sont des données, pas un enum : elles changent quand
        // l'équipe édite, et refuser une rubrique supprimée la veille
        // casserait un écran pour une raison qui n'est pas une erreur client.
        $this->withToken($this->jeton)->getJson('/api/v1/forum/threads?category=inexistante')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('traite la sentinelle « toutes » comme aucun filtre', function (): void {
        $this->withToken($this->jeton)
            ->getJson('/api/v1/forum/threads?category='.Filtre::SANS_FILTRE)
            ->assertOk()->assertJsonCount(3, 'data');
    });

    it('expose les rubriques du serveur, jamais une liste écrite à la main', function (): void {
        $slugs = array_column(
            $this->withToken($this->jeton)->getJson('/api/v1/forum/threads')->json('meta.categories'),
            'slug',
        );

        expect($slugs)->toContain('sante')->toContain('politique');
    });

    it('cherche dans le titre', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/forum/threads?q=communiqué')
            ->assertOk()->assertJsonCount(1, 'data');
    });

    it('GARDE le filtre au passage de page', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/forum/threads', [
            'titre' => 'Un troisième sujet de santé',
            'corps' => 'Un corps assez long pour passer la validation du service.',
            'category' => 'sante',
        ])->assertStatus(201);

        $premiere = $this->withToken($this->jeton)->getJson('/api/v1/forum/threads?category=sante&limit=2');
        $premiere->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.page.has_more', true);

        $seconde = $this->withToken($this->jeton)->getJson(
            '/api/v1/forum/threads?category=sante&limit=2&cursor='.$premiere->json('meta.page.next_cursor'),
        );

        $seconde->assertOk()->assertJsonCount(1, 'data');

        foreach ($seconde->json('data') as $sujet) {
            expect($sujet['categorie']['slug'])->toBe('sante');
        }
    });
});

describe('forum — le nom du champ « category » à la publication', function (): void {
    beforeEach(function (): void {
        ForumCategory::create([
            'uuid' => (string) Str::uuid(), 'slug' => 'sante', 'name' => 'Santé', 'position' => 1,
        ]);
    });

    it('accepte le nom canonique « category »', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/forum/threads', [
            'titre' => 'Une rumeur sur un vaccin',
            'corps' => 'Un corps assez long pour passer la validation du service.',
            'category' => 'sante',
        ])->assertStatus(201)->assertJsonPath('data.categorie.slug', 'sante');
    });

    it('accepte encore l\'ancien nom « categorie »', function (): void {
        // Compatibilité d'une version : ne casser aucun client déjà écrit
        // contre la première rédaction du contrat coûte trois lignes.
        $this->withToken($this->jeton)->postJson('/api/v1/forum/threads', [
            'titre' => 'Une rumeur sur un vaccin',
            'corps' => 'Un corps assez long pour passer la validation du service.',
            'categorie' => 'sante',
        ])->assertStatus(201)->assertJsonPath('data.categorie.slug', 'sante');
    });

    it('publie sans rubrique quand aucune n\'est envoyée', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/forum/threads', [
            'titre' => 'Une rumeur sur un vaccin',
            'corps' => 'Un corps assez long pour passer la validation du service.',
        ])->assertStatus(201)->assertJsonPath('data.categorie', null);

        expect(Thread::firstOrFail()->forum_category_id)->toBeNull();
    });
});

describe('hub — category, scope et type', function (): void {
    beforeEach(function (): void {
        foreach (['Politique', 'Politique', 'Santé'] as $index => $rubrique) {
            HubArticle::create([
                'uuid' => (string) Str::uuid(),
                'slug' => 'breve-'.$index,
                'title' => 'Une brève '.$index,
                'excerpt' => 'Un chapô.',
                'body' => '<p>Un corps.</p>',
                'category' => $rubrique,
                'is_published' => true,
                'published_at' => now(),
            ]);
        }

        offreApiDeTest('bj-cdd', PorteeOpportunite::Benin, TypeOpportunite::Cdd);
        offreApiDeTest('bj-stage', PorteeOpportunite::Benin, TypeOpportunite::Stage);
        offreApiDeTest('intl-bourse', PorteeOpportunite::International, TypeOpportunite::Bourse);
    });

    it('filtre les brèves par catégorie', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/hub/articles?category=Politique')
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('traite « toutes » comme aucun filtre sur les brèves aussi', function (): void {
        // Les trois filtres `category` de l'API lisent la sentinelle de la
        // même façon : trois interprétations d'un même paramètre, ce sont
        // trois occasions de rendre une liste plausible et fausse.
        $this->withToken($this->jeton)
            ->getJson('/api/v1/hub/articles?category='.Filtre::SANS_FILTRE)
            ->assertOk()->assertJsonCount(3, 'data');
    });

    it('filtre les opportunités par portée', function (): void {
        $this->withToken($this->jeton)
            ->getJson('/api/v1/hub/opportunities?scope='.PorteeOpportunite::Benin->value)
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('refuse la notation abrégée du simulateur', function (): void {
        // Le panneau écrit `?scope=bj|intl` ; les valeurs réelles sont `benin`
        // et `international`. Le refus est franc, pas silencieux.
        $this->withToken($this->jeton)->getJson('/api/v1/hub/opportunities?scope=bj')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['scope']]]);
    });

    it('filtre les opportunités par type', function (): void {
        $this->withToken($this->jeton)
            ->getJson('/api/v1/hub/opportunities?type='.TypeOpportunite::Bourse->value)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'intl-bourse');
    });

    it('cumule portée et type en ET', function (): void {
        $this->withToken($this->jeton)->getJson(
            '/api/v1/hub/opportunities?scope='.PorteeOpportunite::Benin->value
            .'&type='.TypeOpportunite::Bourse->value,
        )->assertOk()->assertJsonCount(0, 'data');
    });

    it('traite un type VIDE comme aucun filtre', function (): void {
        // C'est exactement ce que déclare le simulateur : `?scope=…&type=`.
        $this->withToken($this->jeton)->getJson('/api/v1/hub/opportunities?type=')
            ->assertOk()->assertJsonCount(3, 'data');
    });

    it('GARDE les filtres au passage de page', function (): void {
        offreApiDeTest('bj-cdi', PorteeOpportunite::Benin, TypeOpportunite::Cdi);

        $premiere = $this->withToken($this->jeton)->getJson(
            '/api/v1/hub/opportunities?scope='.PorteeOpportunite::Benin->value.'&limit=2',
        );

        $premiere->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.page.has_more', true);

        $seconde = $this->withToken($this->jeton)->getJson(
            '/api/v1/hub/opportunities?scope='.PorteeOpportunite::Benin->value
            .'&limit=2&cursor='.$premiere->json('meta.page.next_cursor'),
        );

        $seconde->assertOk()->assertJsonCount(1, 'data');

        foreach ($seconde->json('data') as $offre) {
            expect($offre['portee'])->toBe(PorteeOpportunite::Benin->value);
        }
    });
});

describe('outils et séances — category et state', function (): void {
    beforeEach(function (): void {
        foreach ([['Images', 'recherche-inversee'], ['Images', 'exif'], ['Archives', 'wayback']] as $index => $outil) {
            Tool::create([
                'uuid' => (string) Str::uuid(),
                'slug' => $outil[1],
                'title' => 'Outil '.$index,
                'description' => 'Un outil.',
                'category' => $outil[0],
                'url' => 'https://exemple.org',
                'is_published' => true,
                'sort_order' => $index,
            ]);
        }

        LiveClass::create([
            'uuid' => (string) Str::uuid(), 'host_id' => $this->citoyen->id,
            'title' => 'À venir', 'description' => 'Bientôt.',
            'starts_at' => now()->addDays(10), 'minutes' => 60,
            'join_url' => 'https://visio.example/a', 'seats' => 50, 'is_published' => true,
        ]);
        LiveClass::create([
            'uuid' => (string) Str::uuid(), 'host_id' => $this->citoyen->id,
            'title' => 'Terminée', 'description' => 'C\'était hier.',
            'starts_at' => now()->subDays(3), 'minutes' => 60,
            'join_url' => 'https://visio.example/b', 'seats' => 50, 'is_published' => true,
        ]);
        LiveClass::create([
            'uuid' => (string) Str::uuid(), 'host_id' => $this->citoyen->id,
            'title' => 'En cours', 'description' => 'Maintenant.',
            'starts_at' => now()->subMinutes(10), 'minutes' => 60,
            'join_url' => 'https://visio.example/c', 'seats' => 50, 'is_published' => true,
        ]);
    });

    it('filtre les outils par catégorie', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/tools?category=Images')
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('traite « toutes » comme aucun filtre sur les outils', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/tools?category='.Filtre::SANS_FILTRE)
            ->assertOk()->assertJsonCount(3, 'data');
    });

    it('rend une liste vide sur une catégorie d\'outil inconnue', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/tools?category=Inexistante')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('filtre les séances par état, déduit de l\'heure', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/live-classes?state=upcoming')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'À venir');

        $this->withToken($this->jeton)->getJson('/api/v1/live-classes?state=live')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'En cours');

        $this->withToken($this->jeton)->getJson('/api/v1/live-classes?state=past')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Terminée');
    });

    it('refuse un état inconnu', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/live-classes?state=annulee')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['state']]]);
    });

    it('rend les trois séances sans filtre', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/live-classes')
            ->assertOk()->assertJsonCount(3, 'data');
    });
});

describe('curseur illisible', function (): void {
    it('répond 422 plutôt que de tomber, sur un curseur périmé', function (): void {
        /*
         | Cas parfaitement ordinaire côté mobile : un curseur gardé d'une
         | session précédente, recopié depuis une autre liste, ou tronqué par
         | un lien profond. Il rendait un 500 — c'est-à-dire « la plateforme
         | est en panne » — là où il fallait dire « oubliez ce curseur et
         | rechargez la liste ».
         */
        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses?cursor='.base64_encode('{"id":"abc"}'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation')
            ->assertJsonStructure(['error' => ['details' => ['cursor']]]);
    });

    it('ignore un curseur qui n\'est même pas du base64', function (): void {
        // Laravel retombe de lui-même sur « aucun curseur » : la première page
        // s'affiche, ce qui est le comportement utile.
        $this->withToken($this->jeton)->getJson('/api/v1/courses?cursor=%%%')
            ->assertOk();
    });
});

describe('mes vérifications — verdict et q', function (): void {
    it('traite un verdict VIDE comme aucun filtre', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/verifications?verdict=')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('refuse un verdict inconnu', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/verifications?verdict=peut-etre')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['verdict']]]);
    });
});
