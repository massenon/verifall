<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Enums\AvisCommunaute;
use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Models\ContentReport;
use App\Models\ForumCategory;
use App\Models\HubArticle;
use App\Models\HubJob;
use App\Models\LiveClass;
use App\Models\LiveClassRegistration;
use App\Models\Thread;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API v1 — forum, Hub, outils, séances en direct
|--------------------------------------------------------------------------
| La faute à ne jamais commettre : laisser l'avis de la communauté passer
| pour une vérification. Le bandeau officiel ne se déduit JAMAIS des votes.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create(['first_name' => 'Rita', 'last_name' => 'Dossou']);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;

    $this->rubrique = ForumCategory::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sante',
        'name' => 'Santé',
        'position' => 1,
    ]);
});

describe('forum — lecture', function (): void {
    it('liste les sujets avec les rubriques, paginés par curseur', function (): void {
        app(AnimationDuForum::class)->publier(
            $this->citoyen,
            'Cette vidéo montre-t-elle un nouveau vaccin ?',
            'Elle circule sur WhatsApp depuis hier matin.',
            $this->rubrique,
        );

        $this->withToken($this->jeton)->getJson('/api/v1/forum/threads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['meta' => ['categories', 'page' => ['next_cursor', 'has_more']]]);
    });

    it('n\'annonce un verdict officiel que si le serveur en a posé un', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen,
            'Cette vidéo montre-t-elle un nouveau vaccin ?',
            'Elle circule sur WhatsApp depuis hier matin.',
        );

        app(AnimationDuForum::class)->voter($this->citoyen, $sujet, AvisCommunaute::Faux);

        // Une communauté convaincue à 100 % qu'une rumeur est fausse n'en fait
        // pas une vérification. C'est même le mécanisme central de la
        // désinformation.
        $this->withToken($this->jeton)->getJson('/api/v1/forum/threads/'.$sujet->slug)
            ->assertOk()
            ->assertJsonPath('data.verdict_officiel', null)
            ->assertJsonPath('data.votes.'.AvisCommunaute::Faux->value, 100);
    });

    it('n\'expose pas l\'adresse e-mail de l\'auteur', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen,
            'Cette vidéo montre-t-elle un nouveau vaccin ?',
            'Elle circule sur WhatsApp depuis hier matin.',
        );

        $auteur = $this->withToken($this->jeton)
            ->getJson('/api/v1/forum/threads/'.$sujet->slug)->json('data.auteur');

        // Une ressource « utilisateur » unique finirait par livrer une adresse
        // e-mail dans une liste de messages. D'où deux ressources distinctes.
        expect($auteur)->toHaveKeys(['uuid', 'nom', 'initiales'])
            ->and($auteur)->not->toHaveKeys(['email', 'telephone', 'unites']);
    });

    it('rend mon propre vote, pour que l\'écran sache quoi montrer', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen,
            'Cette vidéo montre-t-elle un nouveau vaccin ?',
            'Elle circule sur WhatsApp depuis hier matin.',
        );

        app(AnimationDuForum::class)->voter($this->citoyen, $sujet, AvisCommunaute::Doute);

        $this->withToken($this->jeton)->getJson('/api/v1/forum/threads/'.$sujet->slug)
            ->assertOk()
            ->assertJsonPath('data.mon_vote', AvisCommunaute::Doute->value);
    });

    it('refuse sans jeton', function (): void {
        $this->getJson('/api/v1/forum/threads')->assertStatus(401);
    });
});

describe('forum — écriture', function (): void {
    it('publie un sujet en purifiant le corps AVANT de le stocker', function (): void {
        $reponse = $this->withToken($this->jeton)->postJson('/api/v1/forum/threads', [
            'titre' => 'Cette vidéo montre-t-elle un nouveau vaccin ?',
            'corps' => '<p>Elle circule sur WhatsApp.</p><script>alert(1)</script>',
            'categorie' => 'sante',
        ]);

        $reponse->assertStatus(201)->assertJsonStructure(['data' => ['slug', 'titre', 'corps']]);

        // Une seule vue qui oublierait de purifier à l'affichage suffirait à
        // exécuter ce qui a été stocké.
        $sujet = Thread::firstOrFail();
        expect($sujet->body)->not->toContain('<script')
            ->and($sujet->body)->toContain('WhatsApp');
    });

    it('vote une seule fois, et rend la répartition recalculée', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen, 'Cette vidéo montre-t-elle un nouveau vaccin ?', 'Elle circule.',
        );

        $this->withToken($this->jeton)
            ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/vote', ['avis' => AvisCommunaute::Faux->value])
            ->assertOk()
            ->assertJsonPath('data.votes_total', 1);

        // Le vote optimiste du prototype est sans danger : la réponse du
        // serveur fait foi, et l'unicité est en base.
        $this->withToken($this->jeton)
            ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/vote', ['avis' => AvisCommunaute::Vrai->value])
            ->assertOk()
            ->assertJsonPath('data.votes_total', 1);
    });

    it('refuse un avis inconnu', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen, 'Cette vidéo montre-t-elle un nouveau vaccin ?', 'Elle circule.',
        );

        $this->withToken($this->jeton)
            ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/vote', ['avis' => 'peut-etre'])
            ->assertStatus(422);
    });

    it('répond à un sujet', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen, 'Cette vidéo montre-t-elle un nouveau vaccin ?', 'Elle circule.',
        );

        $this->withToken($this->jeton)
            ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/replies', ['corps' => 'Je l\'ai reçue aussi.'])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['uuid', 'corps', 'auteur']]);
    });

    it('refuse de répondre dans un fil verrouillé', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen, 'Cette vidéo montre-t-elle un nouveau vaccin ?', 'Elle circule.',
        );
        $sujet->forceFill(['is_locked' => true])->save();

        // La modération a décidé ; contourner sa décision par l'API serait une
        // faille.
        $this->withToken($this->jeton)
            ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/replies', ['corps' => 'Je l\'ai reçue aussi.'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'action_impossible');
    });

    it('signale un sujet, sans empiler dix lignes', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen, 'Cette vidéo montre-t-elle un nouveau vaccin ?', 'Elle circule.',
        );

        foreach (range(1, 3) as $ignore) {
            $this->withToken($this->jeton)
                ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/report', ['motif' => 'desinformation'])
                ->assertStatus(201);
        }

        // Cinq personnes qui signalent le même message ne le rendent pas cinq
        // fois plus répréhensible : un humain tranche, toujours.
        expect(ContentReport::count())->toBe(1);
    });

    it('refuse un motif de signalement hors de la liste fermée', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen, 'Cette vidéo montre-t-elle un nouveau vaccin ?', 'Elle circule.',
        );

        $this->withToken($this->jeton)
            ->postJson('/api/v1/forum/threads/'.$sujet->slug.'/report', ['motif' => 'je-n-aime-pas'])
            ->assertStatus(422);
    });
});

describe('Hub', function (): void {
    beforeEach(function (): void {
        $this->breve = HubArticle::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'pas-de-tablettes-gratuites',
            'title' => 'Non, l\'État ne distribue pas de tablettes gratuites',
            'excerpt' => 'La rumeur circule depuis lundi.',
            'body' => '<p>Le communiqué officiel dit le contraire.</p>',
            'category' => 'Politique',
            'verdict' => VerdictFactCheck::Faux->value,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->offre = HubJob::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'charge-de-communication',
            'title' => 'Chargé de communication',
            'organisation' => 'ONG Sourire',
            'scope' => PorteeOpportunite::Benin->value,
            'type' => TypeOpportunite::Cdd->value,
            'location' => 'Cotonou',
            'description' => 'Une mission de six mois.',
            'apply_url' => 'https://exemple.org/postuler',
            'deadline' => now()->addDays(20),
            'is_verified' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    });

    it('liste les brèves sans leur corps entier', function (): void {
        $premier = $this->withToken($this->jeton)->getJson('/api/v1/hub/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        // Envoyer vingt articles complets pour n'en afficher que le chapô
        // coûte au citoyen un forfait qu'il paie au mégaoctet.
        expect($premier)->not->toHaveKey('corps');
    });

    it('rend le corps sur la fiche', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/hub/articles/'.$this->breve->slug)
            ->assertOk()
            ->assertJsonPath('data.corps', '<p>Le communiqué officiel dit le contraire.</p>')
            ->assertJsonPath('data.verdict', VerdictFactCheck::Faux->value);
    });

    it('répond INTROUVABLE pour une brève dépubliée', function (): void {
        $this->breve->forceFill(['is_published' => false])->save();

        // Le lien profond doit aboutir sur un écran de repli, pas sur un
        // contenu retiré de la circulation pour une bonne raison.
        $this->withToken($this->jeton)->getJson('/api/v1/hub/articles/'.$this->breve->slug)
            ->assertStatus(404);
    });

    it('filtre les opportunités par portée et par type', function (): void {
        $this->withToken($this->jeton)
            ->getJson('/api/v1/hub/opportunities?scope='.PorteeOpportunite::International->value)
            ->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($this->jeton)
            ->getJson('/api/v1/hub/opportunities?type='.TypeOpportunite::Cdd->value)
            ->assertOk()->assertJsonCount(1, 'data');
    });

    it('accompagne toute fiche d\'opportunité d\'un avertissement anti-frais', function (): void {
        // SYSTÉMATIQUE et non conditionnel : une offre frauduleuse ne se
        // signale pas d'elle-même, et c'est la plus dangereuse.
        $this->withToken($this->jeton)->getJson('/api/v1/hub/opportunities/'.$this->offre->slug)
            ->assertOk()
            ->assertJsonPath('data.verifiee', true)
            ->assertJsonStructure(['meta' => ['avertissement']]);
    });

    it('signale une opportunité', function (): void {
        $this->withToken($this->jeton)
            ->postJson('/api/v1/hub/opportunities/'.$this->offre->slug.'/report', ['motif' => 'arnaque'])
            ->assertStatus(201);

        expect(ContentReport::where('reportable_type', $this->offre->getMorphClass())->count())->toBe(1);
    });
});

describe('outils', function (): void {
    it('liste les outils publiés et leurs catégories', function (): void {
        Tool::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'recherche-inversee',
            'title' => 'Recherche inversée d\'image',
            'description' => 'Retrouver l\'origine d\'une photo.',
            'category' => 'Images',
            'url' => 'https://images.google.com',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->withToken($this->jeton)->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.categories.0', 'Images');
    });

    it('ne montre pas un outil dépublié', function (): void {
        Tool::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'outil-retire',
            'title' => 'Outil retiré',
            'description' => 'Il ne fonctionne plus.',
            'category' => 'Images',
            'url' => 'https://exemple.org',
            'is_published' => false,
            'sort_order' => 1,
        ]);

        $this->withToken($this->jeton)->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('séances en direct', function (): void {
    beforeEach(function (): void {
        $this->seance = LiveClass::create([
            'uuid' => (string) Str::uuid(),
            'host_id' => $this->citoyen->id,
            'title' => 'Atelier vérification d\'images',
            'description' => 'Une heure de pratique.',
            'starts_at' => now()->addDays(10),
            'minutes' => 60,
            'join_url' => 'https://visio.example/atelier',
            'seats' => 50,
            'is_published' => true,
        ]);
    });

    it('ne livre PAS le lien visio d\'une séance encore lointaine', function (): void {
        // Un lien publié trois semaines à l'avance est un lien qui circule :
        // l'animateur découvrirait une salle déjà pleine d'inconnus.
        $this->withToken($this->jeton)->getJson('/api/v1/live-classes')
            ->assertOk()
            ->assertJsonPath('data.0.etat', 'upcoming')
            ->assertJsonPath('data.0.lien_visio', null);
    });

    it('filtre par état', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/live-classes?state=past')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($this->jeton)->getJson('/api/v1/live-classes?state=upcoming')
            ->assertOk()->assertJsonCount(1, 'data');
    });

    it('enregistre une demande de rappel, une seule fois', function (): void {
        foreach (range(1, 3) as $ignore) {
            $this->withToken($this->jeton)
                ->postJson('/api/v1/live-classes/'.$this->seance->uuid.'/remind')
                ->assertStatus(201)
                ->assertJsonPath('data.rappel_demande', true);
        }

        // Une inscription par personne et par séance : la contrainte est en
        // base, et le service ne la heurte pas.
        expect(LiveClassRegistration::count())->toBe(1);
    });

    it('refuse de programmer un rappel pour une séance passée', function (): void {
        $this->seance->forceFill(['starts_at' => now()->subDays(3)])->save();

        $this->withToken($this->jeton)
            ->postJson('/api/v1/live-classes/'.$this->seance->uuid.'/remind')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'action_impossible');
    });
});
