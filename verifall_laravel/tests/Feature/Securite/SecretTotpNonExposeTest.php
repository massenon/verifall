<?php

declare(strict_types=1);

use App\Core\Identity\Services\TwoFactorService;
use App\Enums\UserRole;
use App\Livewire\Auth\StaffLogin;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
 | LA FAILLE QUE CE FICHIER EMPÊCHE DE REVENIR.
 |
 | `$enrolement` était une propriété publique Livewire sans `#[Locked]`. Le
 | client pouvait donc l'écrire — c'est le fonctionnement normal de Livewire,
 | pas un contournement.
 |
 | Or l'écran s'en servait pour décider d'afficher `two_factor_secret` et son
 | QR code, et ne vérifiait nulle part si le compte était RÉELLEMENT en cours
 | d'enrôlement.
 |
 | Enchaînement : qui détient le mot de passe d'un administrateur franchit
 | l'étape 1, force le drapeau, et l'écran lui rend le secret TOTP déjà
 | confirmé de ce compte. Le second facteur se déduit alors du premier — il ne
 | protège plus rien.
 |
 | Trouvé par une revue de code, pas par un test. D'où celui-ci.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    /*
     | Le modèle hache lui-même le mot de passe : le passer déjà haché le
     | hacherait deux fois, et aucune connexion ne serait possible. Et c'est
     | `otp_verified_at` qui vaut vérification ici, pas `email_verified_at`.
     */
    $this->admin = User::factory()->create([
        'email' => 'admin-protege@verifall.com',
        'password' => 'MotDePasseSolide2026!',
        'otp_verified_at' => now(),
    ]);
    $this->admin->assignRole(UserRole::Admin->value);

    /*
     | Le second facteur s'exige par role, et la configuration livree en
     | dispense l'administrateur. La faille decrite plus haut ne se joue que
     | lorsqu'il EST exige : on l'exige donc explicitement, sans quoi ce
     | fichier ne testerait plus rien tout en restant vert.
     */
    config()->set('verifall.securite.deux_facteurs.roles', [UserRole::Admin->value]);
});

describe('un compte DÉJÀ protégé', function (): void {
    beforeEach(function (): void {
        // 2FA activée ET confirmée : le cas normal d'un administrateur en place.
        // On CONFIRME par le service, comme le fait un vrai enrôlement :
        // poser la date à la main laisserait le secret dans un état que le
        // service n'a jamais validé.
        $service = app(TwoFactorService::class);
        $prepare = $service->preparer($this->admin);
        $service->confirmer($this->admin, app(Google2FA::class)->getCurrentOtp($prepare['secret']));
        $this->admin = $this->admin->fresh();
        // Le secret EN CLAIR, tel que l'écran l'afficherait : c'est celui-là
        // qui ne doit jamais ressortir, pas sa forme stockée.
        $this->secret = $prepare['secret'];

        expect($this->secret)->not->toBeNull();
    });

    it("n'expose JAMAIS son secret, même si le client force le drapeau", function (): void {
        $composant = Livewire::test(StaffLogin::class)
            ->set('email', 'admin-protege@verifall.com')
            ->set('motDePasse', 'MotDePasseSolide2026!')
            ->call('verifierIdentifiants');

        // L'étape 1 est franchie : l'écran demande le code.
        $composant->assertSet('attenteCode', true);

        /*
         | L'attaque. `#[Locked]` fait lever Livewire ; sans lui, la propriété
         | serait écrite. Dans les DEUX cas le secret doit rester invisible —
         | c'est le rendu, adossé au serveur, qui en décide.
         */
        try {
            $composant->set('enrolement', true);
        } catch (Throwable) {
            // Propriété verrouillée : c'est la défense en profondeur qui joue.
        }

        $html = $composant->html();

        expect($html)->not->toContain($this->secret)
            ->and($html)->not->toContain('Vos codes de récupération');
    });

    it('ne bascule pas en confirmation quand le drapeau est forcé à false', function (): void {
        /*
         | Le second volet. En branchant sur le drapeau, il suffisait de le
         | remettre à `false` et de saisir un code de récupération affiché pour
         | entrer SANS jamais poser `two_factor_confirmed_at`. Le compte restait
         | à mot de passe seul, en réémettant un secret à chaque connexion.
         |
         | Ici le compte est déjà confirmé : la date ne doit pas bouger.
         */
        $avant = $this->admin->fresh()->two_factor_confirmed_at;

        Livewire::test(StaffLogin::class)
            ->set('email', 'admin-protege@verifall.com')
            ->set('motDePasse', 'MotDePasseSolide2026!')
            ->call('verifierIdentifiants')
            ->set('code', '000000')
            ->call('verifierCode')
            ->assertHasErrors('code');

        expect($this->admin->fresh()->two_factor_confirmed_at?->timestamp)
            ->toBe($avant?->timestamp);
    });
});

describe('un compte SANS second facteur', function (): void {
    it("voit bien son secret : c'est le cas que l'enrôlement doit servir", function (): void {
        /*
         | Le pendant du test précédent. Une correction qui fermerait aussi
         | l'enrôlement légitime rendrait le back-office inaccessible à tout
         | compte neuf — ce qui était le défaut d'origine, dans l'autre sens.
         */
        expect($this->admin->two_factor_confirmed_at)->toBeNull();

        $composant = Livewire::test(StaffLogin::class)
            ->set('email', 'admin-protege@verifall.com')
            ->set('motDePasse', 'MotDePasseSolide2026!')
            ->call('verifierIdentifiants');

        // L'écran doit montrer un QR et des codes : c'est tout l'objet de
        // l'enrôlement. On vérifie la PRÉSENCE de l'étape, pas une chaîne
        // dont la forme dépend du chiffrement.
        $html = $composant->html();

        expect($html)->toContain('Vos codes de récupération');
    });

    it('exige un code valide pour confirmer, et confirme alors vraiment', function (): void {
        $composant = Livewire::test(StaffLogin::class)
            ->set('email', 'admin-protege@verifall.com')
            ->set('motDePasse', 'MotDePasseSolide2026!')
            ->call('verifierIdentifiants');

        $code = app(Google2FA::class)->getCurrentOtp($this->admin->fresh()->two_factor_secret);

        $composant->set('code', $code)->call('verifierCode');

        // La 2FA n'est effective qu'après ce premier code : sans confirmation,
        // le compte resterait à moitié protégé.
        expect($this->admin->fresh()->two_factor_confirmed_at)->not->toBeNull();
    });
});

describe('la surface exposée au client', function (): void {
    it('verrouille les deux propriétés que le client ne doit pas écrire', function (): void {
        $classe = new ReflectionClass(StaffLogin::class);

        foreach (['enrolement', 'codesRecuperation'] as $propriete) {
            $attributs = $classe->getProperty($propriete)->getAttributes();
            $noms = array_map(static fn ($a): string => $a->getName(), $attributs);

            expect($noms)->toContain(Locked::class);
        }
    });
});
