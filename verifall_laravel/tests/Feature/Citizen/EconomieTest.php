<?php

declare(strict_types=1);

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Economy\Providers\FakeMomoProvider;
use App\Core\Economy\Services\EconomyService;
use App\Core\Economy\Services\PaymentService;
use App\Enums\MotifMouvementUnite;
use App\Enums\OperateurMobileMoney;
use App\Enums\StatutPaiement;
use App\Enums\UserRole;
use App\Livewire\Citizen\Unites;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SecurityLog;
use App\Models\UnitLog;
use App\Models\UnitPack;
use App\Models\User;
use Database\Seeders\PacksUnitesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Économie — unités, recharges Mobile Money, dotation mensuelle
|--------------------------------------------------------------------------
| Ces tests portent sur de l'argent : chacun décrit une façon de perdre ou de
| fabriquer des unités.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    // La grille tarifaire vit en base depuis qu'elle a son écran
    // d'administration : sans elle, aucun achat n'est possible.
    $this->seed(PacksUnitesSeeder::class);

    config()->set('economy.momo.webhook_secret', 'secret-de-test-pour-les-rappels');

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 34])->save();
});

/** Envoie un rappel opérateur correctement signé. */
function rappelSigne(Payment $paiement, string $statut = 'succeeded'): TestResponse
{
    $charge = ['reference' => $paiement->provider_ref, 'status' => $statut];

    return test()->withHeaders(['X-Signature' => app(FakeMomoProvider::class)->signer($charge)])
        ->postJson(route('webhooks.momo', ['provider' => $paiement->provider->value]), $charge);
}

describe('solde et journal', function (): void {
    it('crédite et journalise dans la même opération', function (): void {
        app(EconomyService::class)->crediter($this->citoyen, 10, MotifMouvementUnite::DotationMensuelle);

        $ligne = UnitLog::latest('id')->first();

        expect($this->citoyen->fresh()->units)->toBe(44)
            ->and($ligne->delta)->toBe(10)
            // Le solde APRÈS est stocké : le journal se vérifie ligne à ligne
            // sans rejouer tout l'historique.
            ->and($ligne->balance_after)->toBe(44);
    });

    it('refuse un débit qui dépasse le solde, au lieu de le rogner', function (): void {
        // Accepter en mettant à zéro ferait rendre un service sans le facturer.
        expect(fn () => app(EconomyService::class)
            ->debiter($this->citoyen, 40, MotifMouvementUnite::Verification))
            ->toThrow(SoldeInsuffisant::class);

        expect($this->citoyen->fresh()->units)->toBe(34)
            ->and(UnitLog::count())->toBe(0);
    });

    it('ne laisse aucune trace quand le débit échoue', function (): void {
        try {
            app(EconomyService::class)->debiter($this->citoyen, 100, MotifMouvementUnite::Verification);
        } catch (SoldeInsuffisant) {
            // attendu
        }

        // Une trace sans mouvement est une réclamation en puissance.
        expect(UnitLog::count())->toBe(0);
    });

    it('refuse un montant nul ou négatif', function (int $montant): void {
        expect(fn () => app(EconomyService::class)
            ->crediter($this->citoyen, $montant, MotifMouvementUnite::Recharge))
            ->toThrow(RuntimeException::class);
    })->with([0, -5]);

    it('garde le journal et le solde d\'accord', function (): void {
        $economie = app(EconomyService::class);
        $economie->crediter($this->citoyen, 25, MotifMouvementUnite::Recharge);
        $economie->debiter($this->citoyen, 3, MotifMouvementUnite::Verification);
        $economie->crediter($this->citoyen, 10, MotifMouvementUnite::DotationMensuelle);

        // 34 de départ + 32 de mouvements. L'écart entre les deux serait le
        // signal qu'une écriture a échappé au service.
        expect($this->citoyen->fresh()->units)->toBe(66)
            ->and((int) UnitLog::sum('delta'))->toBe(32);
    });

    it('met à jour l\'instance de l\'appelant', function (): void {
        app(EconomyService::class)->crediter($this->citoyen, 5, MotifMouvementUnite::Recharge);

        // La laisser périmée ferait afficher l'ancien solde juste après
        // l'opération.
        expect($this->citoyen->units)->toBe(39);
    });
});

describe('recharge Mobile Money', function (): void {
    it('fige le prix et la quantité depuis le serveur', function (): void {
        $paiement = app(PaymentService::class)->initier($this->citoyen, 'p25', '97 00 00 00');

        // Le client n'envoie qu'un identifiant de pack : ni le prix ni la
        // quantité ne transitent par le navigateur.
        expect($paiement->units)->toBe(UnitPack::where('code', 'p25')->value('units'))
            ->and($paiement->amount_fcfa)->toBe(UnitPack::where('code', 'p25')->value('price_fcfa'))
            ->and($paiement->status)->toBe(StatutPaiement::EnAttente);
    });

    it('refuse un pack inconnu au lieu de deviner un prix', function (): void {
        expect(fn () => app(PaymentService::class)->initier($this->citoyen, 'p9999', '97 00 00 00'))
            ->toThrow(RuntimeException::class);
    });

    it('devine l\'opérateur d\'après le numéro', function (string $numero, OperateurMobileMoney $attendu): void {
        expect(OperateurMobileMoney::depuisNumero($numero))->toBe($attendu);
    })->with([
        ['97 00 00 00', OperateurMobileMoney::Mtn],
        ['+229 94 12 34 56', OperateurMobileMoney::Moov],
        ['41 22 33 44', OperateurMobileMoney::Celtiis],
    ]);

    it('ne crédite rien tant que l\'opérateur n\'a pas confirmé', function (): void {
        app(PaymentService::class)->initier($this->citoyen, 'p25', '97 00 00 00');

        expect($this->citoyen->fresh()->units)->toBe(34);
    });

    it('chiffre le numéro de téléphone au repos', function (): void {
        $paiement = app(PaymentService::class)->initier($this->citoyen, 'p10', '97 00 00 00');

        $brut = DB::table('payments')->where('id', $paiement->id)->value('phone_encrypted');

        // Une fuite de la base ne doit livrer aucun numéro.
        expect($brut)->not->toContain('97')
            ->and($paiement->fresh()->phone_encrypted)->toBe('97 00 00 00');
    });
});

describe('rappel de l\'opérateur', function (): void {
    beforeEach(function (): void {
        $this->paiement = app(PaymentService::class)->initier($this->citoyen, 'p25', '97 00 00 00');
    });

    it('crédite les unités du pack', function (): void {
        rappelSigne($this->paiement)->assertOk();

        expect($this->citoyen->fresh()->units)->toBe(34 + UnitPack::where('code', 'p25')->value('units'))
            ->and($this->paiement->fresh()->status)->toBe(StatutPaiement::Reussi);
    });

    it('ne crédite JAMAIS deux fois un rappel rejoué', function (): void {
        rappelSigne($this->paiement)->assertOk();
        $apresPremier = $this->citoyen->fresh()->units;

        // Rejoué par l'opérateur qui n'a pas reçu notre accusé, ou par un
        // attaquant qui l'a intercepté.
        rappelSigne($this->paiement)->assertOk()->assertJson(['duplicate' => true]);

        expect($this->citoyen->fresh()->units)->toBe($apresPremier)
            ->and(UnitLog::where('reason', MotifMouvementUnite::Recharge->value)->count())->toBe(1);
    });

    it('rejette une signature invalide', function (): void {
        $this->postJson(route('webhooks.momo', ['provider' => 'mtn']), [
            'reference' => $this->paiement->provider_ref,
            'status' => 'succeeded',
        ], ['X-Signature' => 'signature-inventee'])->assertStatus(401);

        expect($this->citoyen->fresh()->units)->toBe(34);
    });

    it('rejette tout rappel quand aucun secret n\'est configuré', function (): void {
        config()->set('economy.momo.webhook_secret', null);

        // Accepter par défaut ouvrirait le crédit d'unités à qui devine l'URL.
        $this->postJson(route('webhooks.momo', ['provider' => 'mtn']), [
            'reference' => $this->paiement->provider_ref, 'status' => 'succeeded',
        ], ['X-Signature' => 'peu importe'])->assertStatus(401);

        expect($this->citoyen->fresh()->units)->toBe(34);
    });

    it('journalise une signature invalide comme un incident critique', function (): void {
        $this->postJson(route('webhooks.momo', ['provider' => 'mtn']),
            ['reference' => 'x', 'status' => 'succeeded'],
            ['X-Signature' => 'faux'])->assertStatus(401);

        // Une rafale ici est une tentative en cours, pas un incident isolé.
        expect(SecurityLog::where('severity', 'critical')->exists())->toBeTrue();
    });

    it('ignore le montant annoncé dans la charge', function (): void {
        $charge = [
            'reference' => $this->paiement->provider_ref,
            'status' => 'succeeded',
            // Même signée, la charge ne décide pas de ce qu'on doit.
            'units' => 99999,
            'amount_fcfa' => 1,
        ];

        $this->withHeaders(['X-Signature' => app(FakeMomoProvider::class)->signer($charge)])
            ->postJson(route('webhooks.momo', ['provider' => 'mtn']), $charge)->assertOk();

        expect($this->citoyen->fresh()->units)->toBe(34 + UnitPack::where('code', 'p25')->value('units'));
    });

    it('répond 404 sur une référence inconnue', function (): void {
        $charge = ['reference' => 'inventee', 'status' => 'succeeded'];

        $this->withHeaders(['X-Signature' => app(FakeMomoProvider::class)->signer($charge)])
            ->postJson(route('webhooks.momo', ['provider' => 'mtn']), $charge)->assertNotFound();
    });

    it('ne crédite rien sur un échec', function (): void {
        rappelSigne($this->paiement, 'failed')->assertOk();

        expect($this->citoyen->fresh()->units)->toBe(34)
            ->and($this->paiement->fresh()->status)->toBe(StatutPaiement::Echoue);
    });

    it('ne rouvre pas un paiement déjà expiré', function (): void {
        app(PaymentService::class)->denouer($this->paiement, StatutPaiement::Expire, 'abandon');

        rappelSigne($this->paiement)->assertOk()->assertJson(['duplicate' => true]);

        // Un rappel tardif ne doit pas « ré-réussir » un paiement abandonné.
        expect($this->citoyen->fresh()->units)->toBe(34)
            ->and($this->paiement->fresh()->status)->toBe(StatutPaiement::Expire);
    });
});

describe('réconciliation', function (): void {
    it('clôt les demandes restées sans réponse', function (): void {
        $paiement = app(PaymentService::class)->initier($this->citoyen, 'p10', '97 00 00 00');
        $paiement->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $this->artisan('verifall:reconcilier-paiements')->assertSuccessful();

        expect($paiement->fresh()->status)->toBe(StatutPaiement::Expire)
            ->and($this->citoyen->fresh()->units)->toBe(34);
    });

    it('épargne une demande encore récente', function (): void {
        $paiement = app(PaymentService::class)->initier($this->citoyen, 'p10', '97 00 00 00');

        $this->artisan('verifall:reconcilier-paiements')->assertSuccessful();

        // Conclure trop tôt annulerait un paiement que la personne est en
        // train de valider sur son téléphone.
        expect($paiement->fresh()->status)->toBe(StatutPaiement::EnAttente);
    });
});

describe('dotation mensuelle', function (): void {
    it('ajoute les unités sans remettre le solde à zéro', function (): void {
        $this->citoyen->forceFill(['units' => 210])->save();

        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();

        // Remettre à zéro confisquerait ce qui a été payé.
        expect($this->citoyen->fresh()->units)->toBe(210 + config('economy.monthly_grant.units'));
    });

    it('ne dote pas deux fois le même mois', function (): void {
        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();
        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();

        // Reprise après incident, planificateur qui double : la relance doit
        // être sans effet.
        expect(UnitLog::where('reason', MotifMouvementUnite::DotationMensuelle->value)->count())->toBe(1);
    });

    it('dote de nouveau le mois suivant', function (): void {
        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();

        $this->travel(1)->month();
        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();

        expect(UnitLog::where('reason', MotifMouvementUnite::DotationMensuelle->value)->count())->toBe(2);
    });

    it('épargne un compte jamais activé', function (): void {
        $inactif = User::factory()->create(['otp_verified_at' => null]);
        $inactif->assignRole(UserRole::Citoyen->value);

        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();

        // Doter une inscription jamais confirmée offrirait des unités à une
        // adresse dont personne n'a prouvé la maîtrise.
        expect($inactif->fresh()->units)->toBe(0);
    });

    it('épargne le personnel', function (): void {
        $admin = User::factory()->actif()->create();
        $admin->assignRole(UserRole::Admin->value);

        $this->artisan('verifall:dotation-mensuelle')->assertSuccessful();

        expect($admin->fresh()->units)->toBe(0);
    });
});

describe('page « Mes unités »', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('citizen.units'))->assertRedirect(route('login'));
    });

    it('affiche le solde et l\'historique du propriétaire seulement', function (): void {
        app(EconomyService::class)->crediter($this->citoyen, 10, MotifMouvementUnite::DotationMensuelle);

        $autre = User::factory()->actif()->create();
        app(EconomyService::class)->crediter($autre, 7, MotifMouvementUnite::Parrainage, 'Parrainage secret');

        $html = Livewire::actingAs($this->citoyen)->test(Unites::class)->html();

        expect($html)->toContain('Dotation mensuelle')
            ->and($html)->not->toContain('Parrainage secret');
    });

    it('filtre par sens', function (): void {
        $economie = app(EconomyService::class);
        $economie->crediter($this->citoyen, 10, MotifMouvementUnite::DotationMensuelle);
        $economie->debiter($this->citoyen, 3, MotifMouvementUnite::Verification);

        $html = Livewire::actingAs($this->citoyen)->test(Unites::class)
            ->set('sens', 'debit')->html();

        expect(substr_count($html, 'data-row-el'))->toBe(1);
    });

    it('ignore un pack fabriqué côté client', function (): void {
        Livewire::actingAs($this->citoyen)->test(Unites::class)
            ->call('choisirPack', 'pack-gratuit')
            ->assertSet('pack', 'p25');
    });

    it('refuse un numéro qui n\'est pas béninois', function (): void {
        Livewire::actingAs($this->citoyen)->test(Unites::class)
            ->call('ouvrirAchat')
            ->set('numero', '06 12 34 56 78 90')
            ->call('payer')
            ->assertHasErrors('numero');
    });

    it('ouvre une demande sans rien créditer', function (): void {
        Livewire::actingAs($this->citoyen)->test(Unites::class)
            ->call('ouvrirAchat')
            ->set('numero', '97 00 00 00')
            ->call('payer')
            ->assertHasNoErrors();

        expect(Payment::count())->toBe(1)
            ->and($this->citoyen->fresh()->units)->toBe(34);
    });

    it('crédite par le bouton de simulation, sans appel réseau vers soi-même', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Unites::class)
            ->call('ouvrirAchat')
            ->set('numero', '97 00 00 00')
            ->call('payer');

        $composant->call('simulerPaiement');

        /*
         | Le contrôleur du rappel est invoqué DIRECTEMENT, avec une charge
         | réellement signée. Un appel HTTP vers nous-mêmes bloquait sous
         | `artisan serve`, mono-processus : le crédit arrivait APRÈS le rendu,
         | et l'écran affichait encore « en attente » alors que le paiement
         | était passé.
         */
        expect($this->citoyen->fresh()->units)->toBe(34 + UnitPack::where('code', 'p25')->value('units'))
            ->and(Payment::latest('id')->first()->status)->toBe(StatutPaiement::Reussi)
            // L'écran cesse d'attendre dans la même requête.
            ->and($composant->get('paiementEnCours'))->toBeNull();
    });

    it('ne renseigne pas sur la demande d\'autrui', function (): void {
        $autre = User::factory()->actif()->create();
        $paiementAutrui = app(PaymentService::class)->initier($autre, 'p60', '97 11 22 33');

        $html = Livewire::actingAs($this->citoyen)->test(Unites::class)
            ->set('paiementEnCours', $paiementAutrui->uuid)->html();

        expect($html)->not->toContain('Validez la demande');
    });
});

describe('ajustement administrateur', function (): void {
    it('exige un motif', function (): void {
        $admin = User::factory()->actif()->create();
        $admin->assignRole(UserRole::Admin->value);

        // Créditer sans encaissement est le geste le plus sensible du
        // back-office : il ne peut pas être anonyme.
        expect(fn () => app(PaymentService::class)->ajusterParAdmin($this->citoyen, $admin, 50, '  '))
            ->toThrow(RuntimeException::class);
    });

    it('journalise l\'ajustement', function (): void {
        $admin = User::factory()->actif()->create();
        $admin->assignRole(UserRole::Admin->value);

        app(PaymentService::class)->ajusterParAdmin($this->citoyen, $admin, 50, 'Geste commercial');

        expect($this->citoyen->fresh()->units)->toBe(84)
            ->and(AuditLog::where('module', 'ai_unit')->exists())->toBeTrue();
    });
});

describe('le journal comptable ne change pas de langue', function (): void {
    /*
     | CE QUI EST ÉCRIT EN BASE N'EST PAS CE QUI EST AFFICHÉ, et c'est voulu.
     |
     | `unit_logs.label` est une colonne persistée, cherchée en `LIKE` et
     | exportée en CSV. En y écrivant le libellé traduit, on obtenait un journal
     | panaché : les mêmes opérations libellées en deux langues selon celle de
     | l'écran au moment de l'écriture. Un relevé comptable qui se contredit
     | d'une ligne à l'autre ne se défend pas devant quelqu'un qui conteste son
     | solde — et c'est précisément à cela qu'il sert.
     |
     | Rien ne l'aurait signalé : ni erreur, ni test, ni page blanche. Juste un
     | historique de moins en moins lisible à mesure que les gens basculent de
     | langue.
     */
    it("écrit TOUJOURS dans la même langue, quelle que soit celle de l'écran", function (): void {
        $citoyen = User::factory()->create(['units' => 10]);

        app()->setLocale('fr');
        app(EconomyService::class)->crediter($citoyen, 5, MotifMouvementUnite::Recharge);

        app()->setLocale('en');
        app(EconomyService::class)->crediter($citoyen, 5, MotifMouvementUnite::Recharge);

        $libelles = UnitLog::where('user_id', $citoyen->id)
            ->where('reason', MotifMouvementUnite::Recharge->value)
            ->pluck('label')
            ->unique();

        expect($libelles)->toHaveCount(1, 'Deux libellés pour un même motif : le journal est panaché.')
            ->and($libelles->first())->toBe(MotifMouvementUnite::Recharge->libelleCanonique());
    });

    it("affiche le libellé TRADUIT, même si le stocké ne l'est pas", function (): void {
        // Le stockage est stable, la lecture est traduite. Les deux exigences
        // ne s'opposent que si l'on confond la colonne et l'affichage.
        $citoyen = User::factory()->create(['units' => 10]);

        app()->setLocale('fr');
        $mouvement = app(EconomyService::class)->crediter($citoyen, 5, MotifMouvementUnite::Recharge);

        app()->setLocale('en');

        expect($mouvement->fresh()->libelleAffiche())
            ->toBe(MotifMouvementUnite::Recharge->libelle())
            ->and($mouvement->fresh()->libelleAffiche())
            ->not->toBe($mouvement->fresh()->label);
    });

    it("ne traduit PAS un libellé que quelqu'un a saisi", function (): void {
        /*
         | Un motif d'ajustement administrateur est une DONNÉE, pas un gabarit.
         | Le traduire reviendrait à réécrire ce qu'un humain a écrit — et ce
         | texte-là est justement celui qu'on relira pour justifier l'opération.
         */
        $citoyen = User::factory()->create(['units' => 10]);

        $mouvement = app(EconomyService::class)->crediter(
            $citoyen, 5, MotifMouvementUnite::AjustementAdmin, 'Geste commercial, dossier 4412',
        );

        app()->setLocale('en');

        expect($mouvement->fresh()->libelleAffiche())->toBe('Geste commercial, dossier 4412');
    });

    it('retrouve en anglais une ligne écrite en français', function (): void {
        /*
         | Le lecteur anglophone voit le libellé traduit et tape ce qu'il voit.
         | Chercher la seule colonne stockée ne lui rendrait rien, et un journal
         | qui ne retrouve pas ce qu'il vient d'afficher passe pour incomplet.
         */
        $citoyen = User::factory()->create(['units' => 50]);

        app()->setLocale('fr');
        app(EconomyService::class)->crediter($citoyen, 5, MotifMouvementUnite::Recharge);
        app(EconomyService::class)->debiter($citoyen, 1, MotifMouvementUnite::Verification);

        app()->setLocale('en');

        $terme = MotifMouvementUnite::Recharge->libelle();

        /*
         | On assure sur la REFERENCE de la ligne, pas sur le terme cherche :
         | le terme est reaffiche dans le champ de recherche, donc `assertSee`
         | l'y trouverait meme sans aucun resultat. Le test serait vert et ne
         | prouverait rien.
         */
        $recharge = UnitLog::where('user_id', $citoyen->id)
            ->where('reason', MotifMouvementUnite::Recharge->value)->firstOrFail();

        $verification = UnitLog::where('user_id', $citoyen->id)
            ->where('reason', MotifMouvementUnite::Verification->value)->firstOrFail();

        Livewire::actingAs($citoyen)->test(Unites::class)
            ->set('recherche', $terme)
            ->assertSee($recharge->reference())
            ->assertDontSee($verification->reference());
    });
});
