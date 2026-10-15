<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Citizen\Profil;
use App\Mail\OtpCodeMail;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Mon profil
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create([
        'first_name' => 'Adderly',
        'last_name' => 'Dossou',
        'email' => 'adderly@verifall.com',
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

/**
 * Récupère le code envoyé, en interceptant le courriel.
 *
 * Le code n'est jamais lisible en base — il y est haché. L'intercepter à
 * l'envoi est donc la seule façon honnête de le connaître dans un test, et
 * cela vérifie au passage qu'il part bien au bon destinataire.
 */
function codeEnvoyeA(string $adresse): string
{
    $code = null;

    Mail::assertQueued(OtpCodeMail::class, function (OtpCodeMail $courriel) use ($adresse, &$code): bool {
        if ($courriel->hasTo($adresse)) {
            $code = $courriel->code;

            return true;
        }

        return false;
    });

    return (string) $code;
}

describe('accès', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('citizen.profile'))->assertRedirect(route('login'));
    });

    it('refuse un compte dont le code n\'a jamais été validé', function (): void {
        $inactif = User::factory()->create(['otp_verified_at' => null]);

        // Un compte non activé n'a fait la preuve d'aucune maîtrise de son
        // adresse : le laisser entrer annulerait tout l'intérêt de l'OTP.
        $this->actingAs($inactif)->get(route('citizen.profile'))
            ->assertRedirect(route('login'));

        expect(auth()->check())->toBeFalse();
    });

    it('protège tout l\'espace citoyen, pas seulement le profil', function (string $route): void {
        $this->get(route($route))->assertRedirect(route('login'));
    })->with(['citizen.dashboard', 'citizen.settings', 'citizen.units', 'citizen.verify']);

    it('laisse entrer un citoyen actif', function (): void {
        $this->actingAs($this->citoyen)->get(route('citizen.profile'))->assertOk();
    });
});

describe('informations personnelles', function (): void {
    it('enregistre les champs du prototype', function (): void {
        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->set('prenom', 'Adderly')
            ->set('nom', 'Dossou-Kponou')
            ->set('telephone', '+229 97 12 34 56')
            ->set('ville', 'Cotonou')
            ->set('bio', 'Je vérifie avant de partager.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $frais = $this->citoyen->fresh();

        expect($frais->last_name)->toBe('Dossou-Kponou')
            ->and($frais->city)->toBe('Cotonou')
            ->and($frais->phone)->toBe('+229 97 12 34 56')
            // `name` est dérivé : deux sources pour la même information
            // finiraient par diverger.
            ->and($frais->name)->toBe('Adderly Dossou-Kponou');
    });

    it('refuse une ville hors de la liste', function (): void {
        // Un champ libre serait réaffiché tel quel sur le forum.
        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->set('ville', 'Paris')
            ->call('enregistrer')
            ->assertHasErrors('ville');
    });

    it('borne la bio à 240 caractères, comme le compteur du prototype', function (): void {
        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->set('bio', str_repeat('a', 241))
            ->call('enregistrer')
            ->assertHasErrors('bio');
    });

    it('journalise la modification, et seulement ce qui a changé', function (): void {
        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->set('ville', 'Parakou')
            ->call('enregistrer');

        $trace = AuditLog::where('module', 'profile')->latest('id')->first();

        expect(array_keys($trace->after))->toBe(['city']);
    });

    it('n\'expose pas l\'adresse e-mail au formulaire commun', function (): void {
        // Elle sert d'identifiant de connexion ET de canal de récupération :
        // la modifier au même titre qu'une ville serait une prise de compte
        // en deux clics depuis une session laissée ouverte.
        // Le composant n'expose aucune propriete `email` liee au formulaire :
        // seule `nouvelEmail`, qui passe par la verification, existe.
        $proprietes = array_keys(get_object_vars(
            Livewire::actingAs($this->citoyen)->test(Profil::class)->instance()
        ));

        expect($proprietes)->not->toContain('email')
            ->and($proprietes)->toContain('nouvelEmail');
    });
});

describe('changement d\'adresse e-mail', function (): void {
    beforeEach(function (): void {
        Mail::fake();
    });

    it('ne change rien avant vérification du nouveau destinataire', function (): void {
        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->call('ouvrirChangementEmail')
            ->set('nouvelEmail', 'nouvelle@verifall.com')
            ->call('envoyerCodeEmail')
            ->assertHasNoErrors();

        // Tant que le code n'est pas confirmé, l'adresse actuelle reste la
        // seule valable.
        expect($this->citoyen->fresh()->email)->toBe('adderly@verifall.com');

        // Le code part à la NOUVELLE adresse : l'envoyer à l'ancienne ne
        // prouverait rien sur la maîtrise de la nouvelle.
        Mail::assertQueued(OtpCodeMail::class,
            fn (OtpCodeMail $m) => $m->hasTo('nouvelle@verifall.com'));
    });

    it('applique le changement une fois le code validé', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->call('ouvrirChangementEmail')
            ->set('nouvelEmail', 'nouvelle@verifall.com')
            ->call('envoyerCodeEmail');

        $composant->set('codeEmail', codeEnvoyeA('nouvelle@verifall.com'))
            ->call('confirmerEmail')
            ->assertHasNoErrors();

        expect($this->citoyen->fresh()->email)->toBe('nouvelle@verifall.com');
    });

    it('refuse un code erroné', function (): void {
        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->call('ouvrirChangementEmail')
            ->set('nouvelEmail', 'nouvelle@verifall.com')
            ->call('envoyerCodeEmail')
            ->set('codeEmail', '000000')
            ->call('confirmerEmail')
            ->assertHasErrors('codeEmail');

        expect($this->citoyen->fresh()->email)->toBe('adderly@verifall.com');
    });

    it('refuse une adresse déjà prise', function (): void {
        User::factory()->actif()->create(['email' => 'occupee@verifall.com']);

        Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->call('ouvrirChangementEmail')
            ->set('nouvelEmail', 'occupee@verifall.com')
            ->call('envoyerCodeEmail')
            ->assertHasErrors('nouvelEmail');
    });

    it('ignore une adresse changée côté client après l\'envoi du code', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->call('ouvrirChangementEmail')
            ->set('nouvelEmail', 'nouvelle@verifall.com')
            ->call('envoyerCodeEmail');

        $code = codeEnvoyeA('nouvelle@verifall.com');

        // Les propriétés Livewire font l'aller-retour par le navigateur : si
        // l'adresse appliquée venait de là, il suffirait de la remplacer entre
        // l'envoi et la validation pour détourner le code vers un autre compte.
        // Elle est donc lue en session.
        $composant->set('nouvelEmail', 'attaquant@verifall.com')
            ->set('codeEmail', $code)
            ->call('confirmerEmail');

        expect($this->citoyen->fresh()->email)->toBe('nouvelle@verifall.com');
    });

    it('journalise l\'ancienne ET la nouvelle adresse', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Profil::class)
            ->call('ouvrirChangementEmail')
            ->set('nouvelEmail', 'nouvelle@verifall.com')
            ->call('envoyerCodeEmail');

        $composant->set('codeEmail', codeEnvoyeA('nouvelle@verifall.com'))
            ->call('confirmerEmail');

        $trace = AuditLog::where('module', 'profile')->latest('id')->first();

        // Un changement d'adresse est le premier geste d'une prise de compte :
        // savoir vers quoi elle a basculé est ce qui permet de la défaire.
        expect($trace->before['email'])->toBe('adderly@verifall.com')
            ->and($trace->after['email'])->toBe('nouvelle@verifall.com');
    });
});
