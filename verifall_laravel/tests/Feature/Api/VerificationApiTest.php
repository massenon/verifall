<?php

declare(strict_types=1);

use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Http\Api\V1\Support\TeleversementTemporaire;
use App\Models\AnalysisReport;
use App\Models\ContentReport;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API v1 — soumettre, suivre, lire un rapport
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 100])->save();

    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;
});

/** Une vérification appartenant à quelqu'un, avec ou sans rapport. */
function verificationDeTest(User $proprietaire, bool $avecRapport = false): Verification
{
    $verification = Verification::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $proprietaire->id,
        'type' => TypeContenu::Texte->value,
        'status' => $avecRapport ? StatutVerification::Terminee->value : StatutVerification::EnFile->value,
        'claim' => 'Le gouvernement distribue-t-il des tablettes gratuites ?',
        'cost_units' => 2,
    ]);

    if ($avecRapport) {
        AnalysisReport::create([
            'uuid' => (string) Str::uuid(),
            'verification_id' => $verification->id,
            'verdict' => VerdictFactCheck::Faux->value,
            'confidence' => 92,
            'summary' => 'Aucune source officielle ne mentionne cette distribution.',
        ]);
    }

    return $verification;
}

describe('soumission', function (): void {
    it('crée une vérification, débite et met l\'analyse en file', function (): void {
        $reponse = $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'text',
            'affirmation' => 'Le gouvernement distribue-t-il des tablettes gratuites ?',
            'contexte' => 'Reçu sur WhatsApp',
        ]);

        $reponse->assertStatus(201)
            ->assertJsonPath('data.statut', StatutVerification::EnFile->value)
            ->assertJsonStructure(['data' => ['uuid', 'reference', 'avancement'], 'meta' => ['solde']]);

        // Le COÛT vient du serveur : le client n'en envoie jamais.
        expect($this->citoyen->fresh()?->units)->toBe(100 - TypeContenu::Texte->cout());
    });

    it('rend un rapport NUL tant qu\'il n\'y en a pas', function (): void {
        /*
         | `rapport: {}` ferait croire à un verdict vide — le pire malentendu
         | possible sur un site de fact-checking. `rapport: null` dit
         | exactement ce qu'il faut : l'analyse n'a pas encore rendu. La clé
         | est TOUJOURS présente, ce qui évite au client d'avoir à distinguer
         | « absente » de « nulle ».
         */
        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'text',
            'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
        ])->assertStatus(201)->assertJsonPath('data.rapport', null);
    });

    it('refuse quand le solde ne suffit pas, avec un code que le client sait traiter', function (): void {
        $this->citoyen->forceFill(['units' => 0])->save();

        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'text',
            'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
        ])->assertStatus(402)
            ->assertJsonPath('error.code', 'solde_insuffisant');
    });

    it('exige un téléversement pour un type fichier', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'image',
            'affirmation' => 'Cette photo montre-t-elle bien le stade de Cotonou ?',
        ])->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['televersement']]]);
    });

    it('refuse un type inconnu', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'hologramme',
            'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
        ])->assertStatus(422);
    });

    it('refuse sans jeton', function (): void {
        $this->postJson('/api/v1/verifications', [
            'type' => 'text',
            'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
        ])->assertStatus(401);
    });

    it('borne le nombre d\'analyses par heure et annonce le délai', function (): void {
        config(['factcheck.runtime.analyses_par_heure' => 2]);
        $this->citoyen->forceFill(['units' => 1000])->save();

        foreach (range(1, 2) as $ignore) {
            $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
                'type' => 'text',
                'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
            ])->assertStatus(201);
        }

        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'text',
            'affirmation' => 'Une affirmation parfaitement ordinaire à vérifier.',
        ])->assertStatus(429)
            ->assertJsonPath('error.code', 'debit_depasse')
            ->assertJsonStructure(['meta' => ['retry_after']])
            ->assertHeader('Retry-After');
    });
});

describe('téléversement', function (): void {
    it('dépose le fichier sur le disque PRIVÉ et rend un identifiant', function (): void {
        Storage::fake('local');

        $reponse = $this->withToken($this->jeton)->postJson('/api/v1/uploads', [
            'type' => 'image',
            'fichier' => UploadedFile::fake()->image('rumeur.jpg'),
        ]);

        $reponse->assertStatus(201)->assertJsonStructure(['data' => ['televersement', 'nom', 'octets']]);

        // Rien dans la réponse ne permet de deviner un chemin sur le disque.
        expect($reponse->json('data'))->not->toHaveKey('chemin');
    });

    it('refuse un format que l\'écran web refuse aussi', function (): void {
        Storage::fake('local');

        // Assouplir ici ouvrirait par le mobile ce que le navigateur refuse —
        // et c'est toujours la porte la moins surveillée qu'on emprunte.
        $this->withToken($this->jeton)->postJson('/api/v1/uploads', [
            'type' => 'image',
            'fichier' => UploadedFile::fake()->create('charge.exe', 12, 'application/octet-stream'),
        ])->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fichier']]]);
    });

    it('enchaîne téléversement puis soumission', function (): void {
        Storage::fake('local');

        $televersement = $this->withToken($this->jeton)->postJson('/api/v1/uploads', [
            'type' => 'image',
            'fichier' => UploadedFile::fake()->image('rumeur.jpg'),
        ])->json('data.televersement');

        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'image',
            'affirmation' => 'Cette photo montre-t-elle bien le stade de Cotonou ?',
            'televersement' => $televersement,
        ])->assertStatus(201);
    });

    it('refuse un identifiant de téléversement appartenant à quelqu\'un d\'autre', function (): void {
        Storage::fake('local');

        $autre = User::factory()->actif()->create();

        /*
         | Le dépôt est fait EN DIRECT et non par un appel HTTP : dans un test
         | fonctionnel, le conteneur survit d'une requête à l'autre et le garde
         | d'authentification retient l'utilisateur résolu précédemment. Deux
         | requêtes portant deux jetons différents dans un même test seraient
         | toutes deux attribuées au premier, et le test mesurerait cet
         | artefact au lieu de la règle.
         */
        $televersement = TeleversementTemporaire::deposer(
            $autre,
            UploadedFile::fake()->image('rumeur.jpg'),
        );

        // Un identifiant intercepté ne permet pas de faire analyser le fichier
        // d'un autre, ni d'en découvrir l'existence : même réponse que pour un
        // identifiant expiré.
        $this->withToken($this->jeton)->postJson('/api/v1/verifications', [
            'type' => 'image',
            'affirmation' => 'Cette photo montre-t-elle bien le stade de Cotonou ?',
            'televersement' => $televersement,
        ])->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['televersement']]]);
    });
});

describe('consultation', function (): void {
    it('rend la vérification et son rapport', function (): void {
        $verification = verificationDeTest($this->citoyen, avecRapport: true);

        $this->withToken($this->jeton)->getJson('/api/v1/verifications/'.$verification->uuid)
            ->assertOk()
            ->assertJsonPath('data.rapport.verdict', VerdictFactCheck::Faux->value)
            ->assertJsonPath('data.rapport.confiance', 92);
    });

    it('n\'expose ni empreinte de contenu ni chemin de fichier', function (): void {
        $verification = verificationDeTest($this->citoyen, avecRapport: true);

        $donnees = $this->withToken($this->jeton)
            ->getJson('/api/v1/verifications/'.$verification->uuid)->json('data');

        expect($donnees)->not->toHaveKeys(['content_fingerprint', 'file_path', 'guest_hash', 'id']);
    });

    it('phase 11 : rend les preuves et les moteurs, comme l\'écran web — jamais coûts ni clés', function (): void {
        $verification = verificationDeTest($this->citoyen, avecRapport: true);

        $verification->evidences()->create([
            'source_url' => 'https://gouv.bj/communique',
            'source_name' => 'gouv.bj',
            'snippet' => 'Aucune distribution de tablettes annoncée.',
            'stance' => PositionPreuve::Contredit->value,
            'reliability' => 95,
        ]);

        $verification->engineRuns()->create([
            'engine' => MoteurAnalyse::Gemini->value,
            'verdict' => VerdictFactCheck::Faux->value,
            'confidence' => 90,
            'failed' => false,
        ]);

        $rapport = $this->withToken($this->jeton)
            ->getJson('/api/v1/verifications/'.$verification->uuid)->json('data.rapport');

        expect($rapport['preuves'][0]['url'])->toBe('https://gouv.bj/communique')
            ->and($rapport['preuves'][0]['fiabilite'])->toBe(95)
            ->and($rapport['moteurs'][0]['nom'])->toBe(MoteurAnalyse::Gemini->value)
            ->and($rapport['moteurs'][0]['verdict'])->toBe(VerdictFactCheck::Faux->value);

        $brut = json_encode($rapport);
        expect($brut)->not->toContain('cost_units')->not->toContain('api_key')->not->toContain('reasoning');
    });

    it('répond INTROUVABLE pour la vérification de quelqu\'un d\'autre', function (): void {
        $autre = User::factory()->actif()->create();
        $sienne = verificationDeTest($autre);

        // 404 et non 403 : un uuid deviné ne doit renseigner sur rien, pas
        // même sur l'existence de la vérification.
        $this->withToken($this->jeton)->getJson('/api/v1/verifications/'.$sienne->uuid)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'introuvable');
    });

    it('répond INTROUVABLE pour un uuid qui n\'existe pas', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/verifications/'.Str::uuid())
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'introuvable');
    });
});

describe('mes vérifications', function (): void {
    it('pagine par curseur et filtre par verdict', function (): void {
        verificationDeTest($this->citoyen, avecRapport: true);
        verificationDeTest($this->citoyen);
        verificationDeTest($this->citoyen);

        $reponse = $this->withToken($this->jeton)->getJson('/api/v1/me/verifications?limit=2');

        $reponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['meta' => ['page' => ['per_page', 'next_cursor', 'has_more']]])
            ->assertJsonPath('meta.page.has_more', true);

        // La page suivante se demande avec le curseur OPAQUE, tel quel.
        $this->withToken($this->jeton)
            ->getJson('/api/v1/me/verifications?limit=2&cursor='.$reponse->json('meta.page.next_cursor'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->jeton)
            ->getJson('/api/v1/me/verifications?verdict='.VerdictFactCheck::Faux->value)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('ne montre jamais les vérifications d\'un autre compte', function (): void {
        $autre = User::factory()->actif()->create();
        verificationDeTest($autre);
        verificationDeTest($autre);

        $this->withToken($this->jeton)->getJson('/api/v1/me/verifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('borne la taille de page demandée', function (): void {
        // `?limit=100000` est une requête légitime en apparence et une
        // dénégation de service en pratique.
        $this->withToken($this->jeton)->getJson('/api/v1/me/verifications?limit=100000')
            ->assertOk()
            ->assertJsonPath('meta.page.per_page', 50);
    });
});

describe('contestation', function (): void {
    it('enregistre une demande de contre-expertise dans la file de modération', function (): void {
        $verification = verificationDeTest($this->citoyen, avecRapport: true);

        $this->withToken($this->jeton)->postJson(
            '/api/v1/verifications/'.$verification->uuid.'/contest',
            ['detail' => 'Le rapport ignore le communiqué officiel du 3 août.'],
        )->assertStatus(201)->assertJsonStructure(['data' => ['uuid', 'message']]);

        expect(ContentReport::where('reportable_type', $verification->getMorphClass())
            ->where('reportable_id', $verification->id)->count())->toBe(1);
    });

    it('ne change PAS l\'état du rapport', function (): void {
        $verification = verificationDeTest($this->citoyen, avecRapport: true);

        $this->withToken($this->jeton)->postJson(
            '/api/v1/verifications/'.$verification->uuid.'/contest',
            ['detail' => 'Le rapport ignore le communiqué officiel du 3 août.'],
        )->assertStatus(201);

        /*
         | Laisser un citoyen basculer son propre rapport en « arbitrage »
         | offrirait à quiconque un bouton pour effacer un verdict qui le
         | dérange. Un humain tranche, toujours.
         */
        expect($verification->fresh()?->status)->toBe(StatutVerification::Terminee);
    });

    it('ne crée pas dix lignes pour dix appels', function (): void {
        $verification = verificationDeTest($this->citoyen, avecRapport: true);

        foreach (range(1, 3) as $ignore) {
            $this->withToken($this->jeton)->postJson(
                '/api/v1/verifications/'.$verification->uuid.'/contest',
                ['detail' => 'Le rapport ignore le communiqué officiel du 3 août.'],
            )->assertStatus(201);
        }

        expect(ContentReport::count())->toBe(1);
    });

    it('refuse la contestation d\'une vérification qui n\'est pas la mienne', function (): void {
        $autre = User::factory()->actif()->create();
        $sienne = verificationDeTest($autre, avecRapport: true);

        $this->withToken($this->jeton)->postJson(
            '/api/v1/verifications/'.$sienne->uuid.'/contest',
            ['detail' => 'Le rapport ignore le communiqué officiel du 3 août.'],
        )->assertStatus(404);

        expect(ContentReport::count())->toBe(0);
    });
});
