<?php

declare(strict_types=1);

use App\Enums\StatutParrainage;
use App\Enums\UserRole;
use App\Models\Referral;
use App\Models\UnitPack;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API v1 — le contrat lui-même
|--------------------------------------------------------------------------
| Ce fichier ne teste pas une fonctionnalité : il teste les PROMESSES du
| contrat. Ce sont elles que l'application Flutter code en dur ; les casser
| casse une application déjà installée sur des téléphones.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 100, 'xp' => 250])->save();
    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;
});

describe('enveloppe', function (): void {
    it('met TOUJOURS la donnée sous « data »', function (): void {
        $corps = $this->withToken($this->jeton)->getJson('/api/v1/me')->json();

        expect($corps)->toHaveKey('data')
            ->and($corps)->not->toHaveKey('error');
    });

    it('met TOUJOURS l\'échec sous « error », avec un code et un message', function (): void {
        $corps = $this->withToken($this->jeton)->getJson('/api/v1/verifications/'.Str::uuid())->json();

        expect($corps)->toHaveKey('error')
            ->and($corps)->not->toHaveKey('data')
            ->and($corps['error'])->toHaveKeys(['code', 'message'])
            // Le message est rédigé en français, affichable tel quel : le
            // client mobile ne traduit pas.
            ->and($corps['error']['message'])->toBeString()->not->toBeEmpty();
    });

    it('rend une méthode non prévue dans l\'enveloppe, pas en HTML', function (): void {
        // Sans ce rendu, l'application recevrait la page d'erreur du site et
        // afficherait un écran blanc en croyant à une panne réseau.
        $this->withToken($this->jeton)->putJson('/api/v1/me')
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'methode_refusee');
    });

    it('rend une route inconnue dans l\'enveloppe', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/ce-qui-nexiste-pas')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'introuvable');
    });
});

describe('versionnement', function (): void {
    it('annonce la version servie sur chaque réponse', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader('X-Api-Version', (string) config('api.version'));
    });

    it('sert un client qui n\'annonce pas sa version', function (): void {
        config(['api.minimum_client' => '2.0.0']);

        // L'en-tête est facultatif au contrat : punir un client tiers pour
        // l'avoir cru serait un piège.
        $this->withToken($this->jeton)->getJson('/api/v1/me')->assertOk();
    });

    it('refuse une application trop ancienne avec un code que le client sait lire', function (): void {
        config(['api.minimum_client' => '2.0.0']);

        // 426 et non 400 : l'application doit afficher « mettez à jour »
        // plutôt que « erreur réseau ». C'est la différence entre quelqu'un
        // qui agit et quelqu'un qui désinstalle.
        $this->withToken($this->jeton)
            ->withHeader('X-Client-Version', '1.4.0')
            ->getJson('/api/v1/me')
            ->assertStatus(426)
            ->assertJsonPath('error.code', 'client_obsolete')
            ->assertHeader('X-Minimum-Client-Version', '2.0.0');
    });

    it('sert une application à jour', function (): void {
        config(['api.minimum_client' => '2.0.0']);

        $this->withToken($this->jeton)
            ->withHeader('X-Client-Version', '2.1.0')
            ->getJson('/api/v1/me')
            ->assertOk();
    });
});

describe('idempotence', function (): void {
    it('rejoue la réponse mémorisée au lieu de refaire l\'écriture', function (): void {
        Queue::fake();
        $cle = (string) Str::uuid();

        $premiere = $this->withToken($this->jeton)
            ->withHeader('Idempotency-Key', $cle)
            ->postJson('/api/v1/verifications', [
                'type' => 'text',
                'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
            ]);

        $premiere->assertStatus(201);
        $solde = $this->citoyen->fresh()?->units;

        $seconde = $this->withToken($this->jeton)
            ->withHeader('Idempotency-Key', $cle)
            ->postJson('/api/v1/verifications', [
                'type' => 'text',
                'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
            ]);

        /*
         | UNE RÉPONSE PERDUE EST INDISCERNABLE D'UNE REQUÊTE PERDUE. Sans
         | idempotence, le téléphone qui ne reçoit rien rejoue — et la personne
         | est débitée deux fois pour une seule question.
         */
        $seconde->assertStatus(201)
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonPath('data.uuid', $premiere->json('data.uuid'));

        expect($this->citoyen->fresh()?->units)->toBe($solde);
    });

    it('exécute normalement sans clé', function (): void {
        Queue::fake();

        // L'en-tête est facultatif : l'imposer rendrait l'API pénible à
        // essayer, et le risque qu'il couvre ne concerne que le rejeu.
        foreach (range(1, 2) as $ignore) {
            $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
                'type' => 'text',
                'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
            ])->assertStatus(201);
        }

        expect($this->citoyen->fresh()?->units)->toBeLessThan(100);
    });

    it('refuse une clé démesurée', function (): void {
        $this->withToken($this->jeton)
            ->withHeader('Idempotency-Key', str_repeat('a', 200))
            ->postJson('/api/v1/me/export')
            ->assertStatus(422);
    });

    it('ne mémorise PAS un échec', function (): void {
        $this->citoyen->forceFill(['units' => 0])->save();
        $cle = (string) Str::uuid();

        $this->withToken($this->jeton)
            ->withHeader('Idempotency-Key', $cle)
            ->postJson('/api/v1/verifications', [
                'type' => 'text',
                'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
            ])->assertStatus(402);

        // Rejouer un échec figerait une erreur passagère — un solde
        // momentanément insuffisant — et le client réessaierait éternellement
        // contre un cache.
        $this->citoyen->forceFill(['units' => 100])->save();
        Queue::fake();

        $this->withToken($this->jeton)
            ->withHeader('Idempotency-Key', $cle)
            ->postJson('/api/v1/verifications', [
                'type' => 'text',
                'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
            ])->assertStatus(201);
    });
});

describe('agrégats du tableau de bord', function (): void {
    it('rend l\'accueil en UN appel', function (): void {
        // Six allers-retours sur un réseau mobile béninois feraient de l'écran
        // d'accueil le plus lent de l'application — celui qu'on voit à chaque
        // ouverture.
        $this->withToken($this->jeton)->getJson('/api/v1/me/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'salutation',
                'compteurs' => ['verifications', 'cours_suivis', 'badges', 'unites'],
                'niveau' => ['rang', 'nom', 'pourcentage'],
                'serie' => ['jours', 'vivante'],
                'activite' => ['jours', 'valeurs'],
            ]]);
    });

    it('n\'annonce aucune offre quand aucun cours n\'est en promotion', function (): void {
        // Annoncer une offre qui n'existe pas serait une promesse en l'air.
        $this->withToken($this->jeton)->getJson('/api/v1/me/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cours_offert', null);
    });

    it('borne la période des statistiques à une liste fermée', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/stats?period=30d')
            ->assertOk()
            ->assertJsonPath('data.periode_jours', 30);

        // Une valeur libre laisserait demander cent mille jours d'historique.
        $this->withToken($this->jeton)->getJson('/api/v1/me/stats?period=100000d')
            ->assertStatus(422);
    });
});

describe('porte-monnaie', function (): void {
    it('rend le solde et la grille tarifaire ensemble', function (): void {
        UnitPack::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'p25',
            'label' => '25 unités',
            'units' => 25,
            'price_fcfa' => 1000,
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->withToken($this->jeton)->getJson('/api/v1/me/wallet')
            ->assertOk()
            ->assertJsonPath('data.solde', 100)
            ->assertJsonPath('data.packs.0.code', 'p25')
            ->assertJsonPath('data.packs.0.unites', 25);
    });

    it('refuse un pack inconnu plutôt que de deviner un prix', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/me/wallet/topup', [
            'pack' => 'p9999',
            'numero' => '97000000',
        ])->assertStatus(422)->assertJsonPath('error.code', 'action_impossible');
    });

    it('n\'expose ni numéro chiffré ni référence opérateur', function (): void {
        UnitPack::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'p25',
            'label' => '25 unités',
            'units' => 25,
            'price_fcfa' => 1000,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $donnees = $this->withToken($this->jeton)->postJson('/api/v1/me/wallet/topup', [
            'pack' => 'p25',
            'numero' => '97000000',
        ])->assertStatus(201)->json('data');

        expect($donnees)->not->toHaveKeys(['phone_encrypted', 'provider_ref', 'failure_reason']);

        // Le solde n'a PAS bougé : il ne bougera qu'au rappel signé de
        // l'opérateur. Le client ne crédite jamais.
        expect($this->citoyen->fresh()?->units)->toBe(100);
    });

    it('rend le relevé paginé par curseur', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/wallet/transactions')
            ->assertOk()
            ->assertJsonStructure(['meta' => ['solde', 'page' => ['per_page', 'next_cursor', 'has_more']]]);
    });
});

describe('récompenses et parrainage', function (): void {
    it('rend les badges verrouillés AVEC leur condition', function (): void {
        // Montrer la destination motive davantage que la masquer ; un cadenas
        // sans explication n'est qu'une frustration gratuite.
        $this->withToken($this->jeton)->getJson('/api/v1/me/gamification')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'niveau' => ['rang', 'nom', 'xp', 'pourcentage'],
                'serie' => ['jours', 'record', 'vivante'],
                'badges',
            ]]);
    });

    it('rend mon code et mon lien de parrainage', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/referrals')
            ->assertOk()
            ->assertJsonStructure(['data' => ['code', 'lien', 'indicateurs', 'filleuls']]);
    });

    it('n\'expose pas l\'adresse e-mail des filleuls', function (): void {
        $filleul = User::factory()->actif()->create(['first_name' => 'Kofi']);

        Referral::create([
            'uuid' => (string) Str::uuid(),
            'referrer_id' => $this->citoyen->id,
            'referred_id' => $filleul->id,
            'status' => StatutParrainage::Inscrit->value,
            'registered_at' => now(),
        ]);

        $premier = $this->withToken($this->jeton)->getJson('/api/v1/me/referrals')
            ->assertOk()
            ->json('data.filleuls.0');

        // La personne parrainée n'a pas consenti à ce que son adresse soit
        // rendue à qui l'a invitée.
        expect($premier['prenom'])->toBe('Kofi')
            ->and($premier)->not->toHaveKeys(['email', 'telephone']);
    });

    it('ne compte rien à l\'ouverture de l\'écran de parrainage', function (): void {
        $avant = (int) $this->citoyen->fresh()?->referral_visits;

        $this->withToken($this->jeton)->postJson('/api/v1/me/referrals/invite')
            ->assertOk()
            ->assertJsonStructure(['data' => ['code', 'lien', 'message']]);

        /*
         | Le seul compteur honnête du parrainage est le nombre de personnes
         | qui ont OUVERT une invitation, et il s'incrémente sur la page
         | publique. L'incrémenter ici fabriquerait un indicateur qui mesure
         | des consultations de son propre code en le faisant passer pour de
         | l'audience.
         */
        expect((int) $this->citoyen->fresh()?->referral_visits)->toBe($avant);
    });
});
