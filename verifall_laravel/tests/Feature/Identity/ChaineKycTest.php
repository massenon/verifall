<?php

declare(strict_types=1);

use App\Core\Identity\Notifications\DossierKycDepose;
use App\Core\Identity\Notifications\DossierKycTranche;
use App\Core\Identity\Services\DossiersKyc;
use App\Enums\StatutDossierKyc;
use App\Enums\TypePieceIdentite;
use App\Enums\UserRole;
use App\Livewire\Citizen\VerifierMonIdentite;
use App\Livewire\Staff\VerificationKyc;
use App\Models\KycSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La chaîne documentaire KYC
|--------------------------------------------------------------------------
|
| Avant le 1er septembre 2026, il n'existait qu'une colonne
| `users.kyc_verified_at` et un écran d'administration qui la basculait à la
| main. Le profil citoyen affichait « Identité non vérifiée » SANS AUCUNE
| ACTION POSSIBLE : le drapeau n'était levable que depuis la console.
|
| Le porteur du projet a tranché — construire la chaîne. Ces tests gardent les
| quatre propriétés qui la rendent défendable :
|
|   1. les pièces ne sont JAMAIS accessibles par une URL de stockage ;
|   2. le numéro n'est jamais conservé en clair ;
|   3. un refus porte toujours un motif, et il parvient à la personne ;
|   4. les images s'effacent, le dossier reste.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    $this->examinateur = User::factory()->create(['otp_verified_at' => now()]);
    $this->examinateur->assignRole(UserRole::Admin->value);
});

/** Une image de test, du poids et du type qu'un téléphone produit. */
function unePiece(string $nom = 'recto.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($nom, 1200, 800);
}

it('dépose un dossier et prévient les examinateurs', function () {
    Notification::fake();

    Livewire::actingAs($this->citoyen)
        ->test(VerifierMonIdentite::class)
        ->set('type', TypePieceIdentite::CarteNationale->value)
        ->set('numero', 'CIP-0099887')
        ->set('recto', unePiece())
        ->set('verso', unePiece('verso.jpg'))
        ->set('consentement', true)
        ->call('deposer')
        ->assertHasNoErrors();

    $dossier = KycSubmission::query()->first();

    expect($dossier)->not->toBeNull()
        ->and($dossier->status)->toBe(StatutDossierKyc::EnAttente)
        ->and($dossier->user_id)->toBe($this->citoyen->id);

    Notification::assertSentTo($this->examinateur, DossierKycDepose::class);
});

it('ne conserve JAMAIS le numéro de la pièce en clair', function () {
    Notification::fake();

    app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1234567', unePiece(),
    );

    $brut = DB::table('kyc_submissions')->first();

    expect($brut->document_number_hash)->not->toBeNull()
        ->and($brut->document_number_hash)->not->toContain('1234567')
        // …et la valeur n'apparaît nulle part ailleurs dans la ligne.
        ->and(json_encode((array) $brut))->not->toContain('1234567');
});

it('range les pièces sur le disque PRIVÉ, sous un nom aléatoire', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1234567', unePiece('ma-carte-kossi.jpg'),
    );

    // Le nom d'origine ne survit pas : « cni-kossi-adjovi.jpg » sur un disque
    // partagé est déjà une fuite.
    expect($dossier->front_path)->not->toContain('ma-carte-kossi')
        ->and($dossier->front_path)->toStartWith('kyc/');

    Storage::disk('local')->assertExists($dossier->front_path);
});

it('EXIGE le verso quand la pièce en a un, et pas pour un passeport', function () {
    Notification::fake();

    // Une CNI sans verso est refusée…
    expect(fn () => app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::CarteNationale, 'CIP-1', unePiece(),
    ))->toThrow(RuntimeException::class);

    // …un passeport passe, parce qu'il porte tout sur sa page d'identité.
    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    expect($dossier->back_path)->toBeNull();
});

it('n’ouvre pas DEUX dossiers en même temps', function () {
    Notification::fake();

    $service = app(DossiersKyc::class);
    $service->deposer($this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece());

    expect(fn () => $service->deposer($this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece()))
        ->toThrow(RuntimeException::class);

    expect(KycSubmission::query()->count())->toBe(1);
});

it('lève le drapeau à l’approbation, et prévient la personne', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    app(DossiersKyc::class)->approuver($dossier, $this->examinateur);

    expect($this->citoyen->fresh()->identiteVerifiee())->toBeTrue()
        ->and($dossier->fresh()->status)->toBe(StatutDossierKyc::Approuve)
        ->and($dossier->fresh()->reviewed_by)->toBe($this->examinateur->id);

    Notification::assertSentTo($this->citoyen, DossierKycTranche::class);
});

it('REFUSE un refus sans motif exploitable', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    // « non » n'apprend rien et fait redéposer la même photo.
    expect(fn () => app(DossiersKyc::class)->refuser($dossier, $this->examinateur, 'non'))
        ->toThrow(RuntimeException::class);

    expect($dossier->fresh()->status)->toBe(StatutDossierKyc::EnAttente);
    Notification::assertNotSentTo($this->citoyen, DossierKycTranche::class);
});

it('porte le motif du refus jusqu’à la personne', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    app(DossiersKyc::class)->refuser(
        $dossier, $this->examinateur, 'Le verso est illisible : reprenez la photo sans reflet.',
    );

    expect($dossier->fresh()->rejection_reason)->toContain('sans reflet')
        ->and($this->citoyen->fresh()->identiteVerifiee())->toBeFalse();

    Notification::assertSentTo(
        $this->citoyen,
        DossierKycTranche::class,
        fn (DossierKycTranche $n): bool => str_contains($n->texte(), 'sans reflet'),
    );
});

it('refuse d’examiner deux fois le même dossier', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    app(DossiersKyc::class)->approuver($dossier, $this->examinateur);

    expect(fn () => app(DossiersKyc::class)->refuser($dossier->fresh(), $this->examinateur, 'Motif suffisant ici.'))
        ->toThrow(RuntimeException::class);
});

it('détruit les images après trente jours, et GARDE la preuve du contrôle', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::CarteNationale, 'CIP-1', unePiece(), unePiece('verso.jpg'),
    );
    app(DossiersKyc::class)->approuver($dossier, $this->examinateur);

    $chemins = array_values($dossier->fresh()->pieces());

    // Vingt-neuf jours : rien ne bouge.
    $this->travel(29)->days();
    expect(app(DossiersKyc::class)->purgerLesPiecesExpirees())->toBe(0);
    Storage::disk('local')->assertExists($chemins[0]);

    // Trente et un : les images partent.
    $this->travel(2)->days();
    expect(app(DossiersKyc::class)->purgerLesPiecesExpirees())->toBe(1);

    foreach ($chemins as $chemin) {
        Storage::disk('local')->assertMissing($chemin);
    }

    $purge = $dossier->fresh();

    // Le DOSSIER survit : c'est lui qui prouve que le contrôle a eu lieu.
    expect($purge->purged_at)->not->toBeNull()
        ->and($purge->front_path)->toBeNull()
        ->and($purge->status)->toBe(StatutDossierKyc::Approuve)
        ->and($purge->document_type)->toBe(TypePieceIdentite::CarteNationale)
        ->and($purge->reviewed_by)->toBe($this->examinateur->id)
        // …et la personne reste vérifiée.
        ->and($this->citoyen->fresh()->identiteVerifiee())->toBeTrue();
});

it('ne sert AUCUNE pièce sans signature, ni à un membre non habilité', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    $adresseNue = route('staff.kyc.piece', ['dossier' => $dossier->uuid, 'piece' => 'recto']);

    $signee = url()->temporarySignedRoute(
        'staff.kyc.piece',
        now()->addMinutes(10),
        ['dossier' => $dossier->uuid, 'piece' => 'recto'],
    );

    /*
     | L'ORDRE COMPTE. `actingAs()` persiste pour tout le reste du test : le
     | contrôle « sans session » doit donc passer EN PREMIER, sinon il hérite
     | de la connexion posée par une assertion précédente et rend un faux vert.
     | C'est exactement ce qui s'est produit au premier jet.
     */
    $this->get($signee)->assertRedirect();

    // Signée, connecté, mais sans l'habilitation : refusé.
    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);
    $this->actingAs($mentor, 'staff')->get($signee)->assertForbidden();

    // Sans signature : refusé, même connecté et habilité.
    $this->actingAs($this->examinateur, 'staff')->get($adresseNue)->assertForbidden();

    // Les trois conditions réunies : servie.
    $this->actingAs($this->examinateur, 'staff')->get($signee)->assertOk();
});

it('n’expose pas les chemins des pièces dans une sérialisation', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    /*
     | Un `toArray()` traîne partout : ressource d'API, `dd()`, journal
     | d'exception. Il ne doit pas recracher l'emplacement d'un scan de carte
     | d'identité, ni le haché du numéro.
     */
    $serialise = json_encode($dossier->toArray());

    expect($serialise)->not->toContain('kyc/')
        ->and($serialise)->not->toContain('front_path')
        ->and($serialise)->not->toContain('document_number_hash');
});

it('refuse le dépôt d’un compte DÉJÀ vérifié', function () {
    Notification::fake();

    $this->citoyen->forceFill(['kyc_verified_at' => now()])->save();

    expect(fn () => app(DossiersKyc::class)->deposer(
        $this->citoyen->fresh(), TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    ))->toThrow(RuntimeException::class);
});

it('relie le badge « non vérifiée » du profil à l’écran de dépôt', function () {
    // Le badge annonçait un état sans offrir aucun moyen d'en changer.
    $this->actingAs($this->citoyen)
        ->get(route('citizen.profile'))
        ->assertOk()
        ->assertSee(route('citizen.kyc'), escape: false);
});

it('n’approuve pas depuis l’écran staff sans l’habilitation kyc.review', function () {
    Notification::fake();

    $dossier = app(DossiersKyc::class)->deposer(
        $this->citoyen, TypePieceIdentite::Passeport, 'BJ-1', unePiece(),
    );

    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);

    /*
     | La garde tombe DÈS L'OUVERTURE du tiroir, et pas seulement à la décision :
     | ouvrir met sous les yeux le nom, l'adresse et les liens vers les pièces.
     |
     | On n'enchaîne donc pas sur `statuer()` : un `abort()` en cours de chaîne
     | laisse Livewire sans instantané valide, et l'erreur qui remonte parle de
     | sérialisation au lieu de parler du refus d'accès.
     */
    Livewire::actingAs($mentor, 'staff')
        ->test(VerificationKyc::class)
        ->assertSet('peutExaminer', false)
        ->call('ouvrirLaDecision', $dossier->uuid, 'approuver')
        ->assertForbidden();

    expect($dossier->fresh()->status)->toBe(StatutDossierKyc::EnAttente)
        ->and($this->citoyen->fresh()->identiteVerifiee())->toBeFalse();
});
