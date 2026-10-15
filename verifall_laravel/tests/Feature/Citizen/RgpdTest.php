<?php

declare(strict_types=1);

use App\Core\Identity\Services\ConsentService;
use App\Core\Identity\Services\ExportDonneesService;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Enums\ConsentType;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Jobs\PreparerExportDonnees;
use App\Livewire\Citizen\Reglages;
use App\Mail\ExportPretMail;
use App\Mail\OtpCodeMail;
use App\Mail\SuppressionProgrammeeMail;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| RGPD — portabilité et effacement
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create([
        'email' => 'adderly@verifall.com',
        'password' => 'MotDePasseActuel2026!',
        'phone' => '+229 97 00 00 00',
        'city' => 'Cotonou',
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

/** Récupère le code envoyé pour un usage donné, en interceptant le courriel. */
function codeSuppressionEnvoye(): string
{
    $code = null;

    Mail::assertQueued(OtpCodeMail::class, function (OtpCodeMail $courriel) use (&$code): bool {
        if ($courriel->purpose === OtpPurpose::SuppressionCompte) {
            $code = $courriel->code;

            return true;
        }

        return false;
    });

    return (string) $code;
}

describe('export de mes données', function (): void {
    it('met la construction en file plutôt que de faire attendre la page', function (): void {
        Queue::fake();

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('exporterMesDonnees');

        Queue::assertPushed(PreparerExportDonnees::class);
        expect(DataExport::where('user_id', $this->citoyen->id)->count())->toBe(1);
    });

    it('journalise la demande', function (): void {
        Queue::fake();

        Livewire::actingAs($this->citoyen)->test(Reglages::class)->call('exporterMesDonnees');

        // Extraire l'intégralité de ses données produit un fichier qui peut
        // ensuite être dérobé : l'action est sensible même demandée par la
        // personne concernée.
        expect(AuditLog::where('action', 'data_export')->exists())->toBeTrue();
    });

    it('refuse d\'empiler les demandes', function (): void {
        Queue::fake();

        $composant = Livewire::actingAs($this->citoyen)->test(Reglages::class);
        $composant->call('exporterMesDonnees');
        $composant->call('exporterMesDonnees');

        // Chaque archive est une copie complète de données personnelles :
        // en produire à la chaîne multiplie les objets à protéger.
        expect(DataExport::where('user_id', $this->citoyen->id)->count())->toBe(1);
    });

    it('construit une archive contenant le profil et les consentements', function (): void {
        Storage::fake('local');

        app(ConsentService::class)
            ->record($this->citoyen, ConsentType::Cgu, true);

        $export = app(ExportDonneesService::class)->demander($this->citoyen);
        $export = app(ExportDonneesService::class)->construire($export);

        expect($export->status)->toBe(DataExport::PRET)
            ->and($export->expires_at)->not->toBeNull()
            ->and(Storage::disk('local')->exists((string) $export->path))->toBeTrue();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path((string) $export->path));
        $donnees = json_decode((string) $zip->getFromName('donnees.json'), true);

        expect($donnees['profil']['email'])->toBe('adderly@verifall.com')
            ->and($donnees['profil']['ville'])->toBe('Cotonou')
            // Le journal des consentements est la preuve que la personne peut
            // exiger de nous : il a toute sa place dans l'export.
            ->and($donnees['consentements'])->toHaveCount(1)
            ->and($zip->getFromName('LISEZ-MOI.txt'))->toContain('données personnelles');

        $zip->close();
    });

    it('ne met jamais le mot de passe dans l\'archive', function (): void {
        Storage::fake('local');

        $export = app(ExportDonneesService::class)->construire(
            app(ExportDonneesService::class)->demander($this->citoyen),
        );

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path((string) $export->path));
        $contenu = (string) $zip->getFromName('donnees.json');
        $zip->close();

        // Nous n'en conservons qu'une empreinte ; la faire figurer dans un
        // fichier qui circule serait la pire des idées.
        expect($contenu)->not->toContain('password')
            ->and($contenu)->not->toContain($this->citoyen->password);
    });

    it('range l\'archive hors de toute URL publique', function (): void {
        Storage::fake('local');

        $export = app(ExportDonneesService::class)->construire(
            app(ExportDonneesService::class)->demander($this->citoyen),
        );

        // Disque privé et nom aléatoire : un nom déduit de l'identité rendrait
        // le contenu du dossier lisible sans ouvrir un seul fichier.
        expect($export->path)->toStartWith('exports/')
            ->and($export->path)->not->toContain('adderly');
    });

    it('prévient par courriel quand l\'archive est prête', function (): void {
        Storage::fake('local');
        Mail::fake();

        $export = app(ExportDonneesService::class)->demander($this->citoyen);
        (new PreparerExportDonnees($export->id))->handle(app(ExportDonneesService::class));

        // ExportPretMail implemente ShouldQueue : send() le met en file plutot
        // que de l'envoyer sur-le-champ, ce qui est precisement le but.
        Mail::assertQueued(ExportPretMail::class,
            fn (ExportPretMail $m) => $m->hasTo('adderly@verifall.com'));
    });
});

describe('téléchargement de l\'archive', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');

        $this->export = app(ExportDonneesService::class)->construire(
            app(ExportDonneesService::class)->demander($this->citoyen),
        );

        $this->lien = URL::temporarySignedRoute('citizen.export.download',
            $this->export->expires_at, ['export' => $this->export->uuid]);
    });

    it('livre l\'archive à son propriétaire', function (): void {
        $this->actingAs($this->citoyen)->get($this->lien)->assertOk();
    });

    it('refuse un lien non signé', function (): void {
        // La signature prouve que le lien vient de nous et n'a pas été fabriqué.
        $this->actingAs($this->citoyen)
            ->get(route('citizen.export.download', ['export' => $this->export->uuid]))
            ->assertForbidden();
    });

    it('refuse un lien signé mais transféré à quelqu\'un d\'autre', function (): void {
        $autre = User::factory()->actif()->create();

        // Un lien finit dans une boîte mail, et une boîte mail se transfère :
        // la signature seule ne suffit pas à décider qui peut lire.
        $this->actingAs($autre)->get($this->lien)->assertForbidden();
    });

    it('refuse un visiteur anonyme, même avec le bon lien', function (): void {
        // Arrete des le garde de l'espace citoyen, avant meme le controleur :
        // un lien signe ne remplace pas une session.
        $this->get($this->lien)->assertRedirect(route('login'));
    });

    it('refuse une archive expirée', function (): void {
        $this->export->forceFill(['expires_at' => now()->subHour()])->save();

        $lienPerime = URL::temporarySignedRoute('citizen.export.download',
            now()->addHour(), ['export' => $this->export->uuid]);

        // 404 plutôt qu'un fichier vide, qui laisserait croire à un export
        // réussi mais lacunaire.
        $this->actingAs($this->citoyen)->get($lienPerime)->assertNotFound();
    });
});

describe('suppression du compte', function (): void {
    beforeEach(function (): void {
        Mail::fake();
    });

    it('exige le mot exact du prototype', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirSuppression')
            ->set('motSuppression', 'supprimer')
            ->set('motDePasseSuppression', 'MotDePasseActuel2026!')
            ->call('verifierSuppression')
            ->assertHasErrors('motSuppression');
    });

    it("accepte DELETE, le mot que montre l'écran anglais", function (): void {
        /*
         | L'écran anglais affiche « Type DELETE ». Tant que le serveur ne
         | comparait qu'au mot français, l'anglophone qui tapait EXACTEMENT ce
         | qu'on lui demandait était refusé — et le message d'erreur lui disait
         | de taper ce qu'il venait de taper.
         |
         | Le mot ne prouve que l'intention ; l'identité reste prouvée juste
         | après par le mot de passe, puis par le code envoyé par courriel.
         | En accepter un second n'affaiblit donc rien.
         */
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirSuppression')
            ->set('motSuppression', 'DELETE')
            ->set('motDePasseSuppression', 'MotDePasseActuel2026!')
            ->call('verifierSuppression')
            ->assertHasNoErrors()
            ->assertSet('etapeSuppression', 'code');
    });

    it('refuse les minuscules dans les DEUX langues', function (): void {
        /*
         | On a ajouté un mot, pas une tolérance. Taper des capitales demande un
         | geste de plus, et c'est ce geste que le prototype réclame avant
         | d'effacer un compte.
         */
        foreach (['supprimer', 'delete', 'Delete'] as $approximatif) {
            Livewire::actingAs($this->citoyen)->test(Reglages::class)
                ->call('ouvrirSuppression')
                ->set('motSuppression', $approximatif)
                ->set('motDePasseSuppression', 'MotDePasseActuel2026!')
                ->call('verifierSuppression')
                ->assertHasErrors('motSuppression');
        }

        expect($this->citoyen->fresh()->deleted_at)->toBeNull();
    });

    it('exige le mot de passe : taper un mot prouve l\'intention, pas l\'identité', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirSuppression')
            ->set('motSuppression', 'SUPPRIMER')
            ->set('motDePasseSuppression', 'PasLeBon2026!')
            ->call('verifierSuppression')
            ->assertHasErrors('motDePasseSuppression');

        expect($this->citoyen->fresh()->deleted_at)->toBeNull();
    });

    it('n\'efface rien avant le code envoyé par courriel', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirSuppression')
            ->set('motSuppression', 'SUPPRIMER')
            ->set('motDePasseSuppression', 'MotDePasseActuel2026!')
            ->call('verifierSuppression')
            ->assertHasNoErrors()
            ->assertSet('etapeSuppression', 'code');

        expect(User::withTrashed()->find($this->citoyen->id)->deleted_at)->toBeNull();

        Mail::assertQueued(OtpCodeMail::class,
            fn (OtpCodeMail $m) => $m->purpose === OtpPurpose::SuppressionCompte);
    });

    it('refuse un code seul, sans le premier temps', function (): void {
        // Le code ne prouve que l'accès à la boîte mail. Sans le mot de passe,
        // il ne doit rien pouvoir détruire.
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('etapeSuppression', 'code')
            ->set('codeSuppression', '123456')
            ->call('confirmerSuppression');

        expect(User::withTrashed()->find($this->citoyen->id)->deleted_at)->toBeNull();
    });

    it('ferme le compte et arme le délai de rétractation', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirSuppression')
            ->set('motSuppression', 'SUPPRIMER')
            ->set('motDePasseSuppression', 'MotDePasseActuel2026!')
            ->call('verifierSuppression');

        $composant->set('codeSuppression', codeSuppressionEnvoye())
            ->call('confirmerSuppression');

        $ferme = User::withTrashed()->find($this->citoyen->id);

        expect($ferme->deleted_at)->not->toBeNull()
            ->and($ferme->deletion_requested_at)->not->toBeNull()
            // Les données restent : c'est tout l'objet du délai.
            ->and($ferme->email)->toBe('adderly@verifall.com')
            ->and($ferme->anonymized_at)->toBeNull();

        // Le courriel est la SEULE voie de retour : le compte étant fermé, on
        // ne peut plus se connecter pour changer d'avis.
        Mail::assertQueued(SuppressionProgrammeeMail::class);
    });

    it('détruit les archives d\'export sans attendre les 30 jours', function (): void {
        Storage::fake('local');

        app(ExportDonneesService::class)->construire(
            app(ExportDonneesService::class)->demander($this->citoyen),
        );

        app(SuppressionCompteService::class)->programmer($this->citoyen);

        // Une copie complète des données de quelqu'un qui demande leur
        // effacement n'a aucune raison de survivre à sa demande.
        expect(DataExport::where('user_id', $this->citoyen->id)->count())->toBe(0);
    });
});

describe('rétractation', function (): void {
    it('rouvre le compte depuis le lien signé, sans session', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);

        $lien = URL::temporarySignedRoute('account.deletion.cancel',
            now()->addDays(30), ['uuid' => $this->citoyen->uuid]);

        // Sans session : c'est le cœur du dispositif, le compte étant fermé.
        $this->get($lien)->assertRedirect(route('login'));

        $repris = User::find($this->citoyen->id);

        expect($repris)->not->toBeNull()
            ->and($repris->deleted_at)->toBeNull()
            ->and($repris->deletion_requested_at)->toBeNull();
    });

    it('refuse un lien non signé', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);

        $this->get(route('account.deletion.cancel', ['uuid' => $this->citoyen->uuid]))
            ->assertForbidden();

        expect(User::find($this->citoyen->id))->toBeNull();
    });

    it('ne ressuscite pas un compte déjà anonymisé', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);
        app(SuppressionCompteService::class)->anonymiser($this->citoyen);

        $lien = URL::temporarySignedRoute('account.deletion.cancel',
            now()->addDays(30), ['uuid' => $this->citoyen->uuid]);

        // Rendre un compte vidé de tout ce qui le rendait utilisable serait pire
        // que de dire franchement qu'il est trop tard.
        $this->get($lien)->assertSessionHas('suppression_annulation', 'trop_tard');
    });
});

describe('anonymisation', function (): void {
    it('efface les données personnelles et garde la ligne', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);
        app(SuppressionCompteService::class)->anonymiser($this->citoyen);

        $anonyme = User::withTrashed()->find($this->citoyen->id);

        expect($anonyme)->not->toBeNull()
            ->and($anonyme->name)->toBe('Citoyen supprimé')
            ->and($anonyme->phone)->toBeNull()
            ->and($anonyme->city)->toBeNull()
            ->and($anonyme->email)->not->toContain('adderly')
            ->and($anonyme->anonymized_at)->not->toBeNull()
            // Détruire la ligne romprait les journaux d'audit, les certificats
            // et les contributions au forum d'autres personnes.
            ->and($anonyme->id)->toBe($this->citoyen->id);
    });

    it('retire rôles, jetons et sessions', function (): void {
        DB::table('sessions')->insert([
            'id' => 'session-du-compte', 'user_id' => $this->citoyen->id,
            'ip_address' => '41.85.0.1', 'user_agent' => 'Android',
            'payload' => '', 'last_activity' => now()->timestamp,
        ]);
        $this->citoyen->createToken('mobile');

        app(SuppressionCompteService::class)->programmer($this->citoyen);
        app(SuppressionCompteService::class)->anonymiser($this->citoyen);

        // Un jeton survivant continuerait d'ouvrir l'application mobile.
        expect($this->citoyen->fresh()->tokens()->count())->toBe(0)
            ->and(DB::table('sessions')->where('id', 'session-du-compte')->exists())->toBeFalse()
            ->and(User::withTrashed()->find($this->citoyen->id)->getRoleNames())->toBeEmpty();
    });

    it('donne une adresse unique à chaque compte anonymisé', function (): void {
        $second = User::factory()->actif()->create();

        foreach ([$this->citoyen, $second] as $compte) {
            app(SuppressionCompteService::class)->programmer($compte);
            app(SuppressionCompteService::class)->anonymiser($compte);
        }

        // La colonne est unique : deux NULL passeraient, mais deux valeurs
        // identiques feraient échouer le second effacement.
        $adresses = User::withTrashed()->whereNotNull('anonymized_at')->pluck('email');

        expect($adresses)->toHaveCount(2)
            ->and($adresses->unique())->toHaveCount(2);
    });

    it('ne se rejoue pas sur un compte déjà vidé', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);
        app(SuppressionCompteService::class)->anonymiser($this->citoyen);

        $date = User::withTrashed()->find($this->citoyen->id)->anonymized_at;

        $this->travel(1)->hour();
        app(SuppressionCompteService::class)->anonymiser(User::withTrashed()->find($this->citoyen->id));

        // Rejouer écraserait le marqueur et ferait perdre la date réelle.
        expect(User::withTrashed()->find($this->citoyen->id)->anonymized_at->timestamp)
            ->toBe($date->timestamp);
    });
});

describe('purge planifiée', function (): void {
    it('détruit le fichier des archives expirées, pas seulement la ligne', function (): void {
        Storage::fake('local');

        $export = app(ExportDonneesService::class)->construire(
            app(ExportDonneesService::class)->demander($this->citoyen),
        );
        $chemin = (string) $export->path;

        $export->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('verifall:purger-donnees-expirees')->assertSuccessful();

        // Un delete() simple laisserait sur le disque un orphelin plein de
        // données personnelles que plus rien ne viendrait chercher.
        expect(DataExport::find($export->id))->toBeNull()
            ->and(Storage::disk('local')->exists($chemin))->toBeFalse();
    });

    it('anonymise les comptes dont le délai est écoulé', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);

        $this->travel(SuppressionCompteService::JOURS_RETRACTATION + 1)->days();

        $this->artisan('verifall:purger-donnees-expirees')->assertSuccessful();

        expect(User::withTrashed()->find($this->citoyen->id)->anonymized_at)->not->toBeNull();
    });

    it('épargne les comptes encore dans le délai', function (): void {
        app(SuppressionCompteService::class)->programmer($this->citoyen);

        $this->travel(SuppressionCompteService::JOURS_RETRACTATION - 1)->days();

        $this->artisan('verifall:purger-donnees-expirees')->assertSuccessful();

        // Un droit à la rétractation qui s'exerce trop tôt n'en est pas un.
        expect(User::withTrashed()->find($this->citoyen->id)->anonymized_at)->toBeNull()
            ->and(User::withTrashed()->find($this->citoyen->id)->email)->toBe('adderly@verifall.com');
    });
});
