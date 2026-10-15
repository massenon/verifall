<?php

declare(strict_types=1);

use App\Core\Identity\Services\OtpService;
use App\Enums\OtpPurpose;
use App\Livewire\Auth\Register;
use App\Models\OtpCode;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| B-04 — l'inscription qui « s'arrête à la deuxième étape »
|--------------------------------------------------------------------------
| Le formulaire n'a aucun défaut : ses quatre étapes s'enchaînent, chaque
| bouton est câblé, la validation est en place. Ce qui manquait était en aval.
|
| `InscriptionCitoyen::envoyerLeCode()` met le courriel à la FILE. Sans
| `queue:work`, le message reste en table `jobs` : l'écran affiche « saisissez
| le code reçu » et attend un courriel qui ne partira jamais. Rien ne casse —
| pas d'exception, pas de journal, page 200, suite verte.
|
| Le correctif tient en trois gestes, et aucun ne touche à la sécurité :
| l'écran DIT ce qu'il attend, le renvoi cesse d'être muet, et une commande
| locale débloque qui est déjà devant l'écran.
|
| `verifall:diagnostic` (lot 0) traite l'autre moitié : pourquoi rien ne part.
*/

describe('la commande verifall:code-otp', function (): void {
    it('émet un code utilisable', function (): void {
        $utilisateur = User::factory()->create(['email' => 'aya@example.test']);

        $this->artisan('verifall:code-otp', ['email' => 'aya@example.test'])
            ->assertSuccessful();

        /*
         | Le code n'est pas relisible en base — il y est haché, et c'est très
         | bien ainsi. On éprouve donc ce qui compte vraiment : qu'un code
         | valide EXISTE désormais pour ce compte et cet usage.
         */
        expect($utilisateur->fresh())->not->toBeNull();

        $codes = OtpCode::query()
            ->where('purpose', OtpPurpose::Registration->value)
            ->whereNull('consumed_at')
            ->count();

        expect($codes)->toBe(1);
    });

    it('refuse une adresse inconnue plutôt que d\'émettre dans le vide', function (): void {
        $this->artisan('verifall:code-otp', ['email' => 'personne@example.test'])
            ->expectsOutputToContain('Aucun compte pour cette adresse.')
            ->assertFailed();
    });

    it('refuse un usage qui n\'existe pas', function (): void {
        User::factory()->create(['email' => 'aya@example.test']);

        $this->artisan('verifall:code-otp', ['email' => 'aya@example.test', '--usage' => 'nimporte'])
            ->assertFailed();
    });

    it('sert les autres usages, pas seulement l\'inscription', function (): void {
        User::factory()->create(['email' => 'aya@example.test']);

        $this->artisan('verifall:code-otp', [
            'email' => 'aya@example.test',
            '--usage' => OtpPurpose::PasswordReset->value,
        ])->assertSuccessful();

        expect(OtpCode::query()
            ->where('purpose', OtpPurpose::PasswordReset->value)
            ->whereNull('consumed_at')
            ->count())->toBe(1);
    });

    it('invalide le code précédent, comme un renvoi depuis l\'écran', function (): void {
        /*
         | Un seul code valide à la fois : c'est la règle d'`OtpService::issue`,
         | et la commande ne la contourne pas — elle passe par le service
         | ordinaire, pas par la table.
         */
        $utilisateur = User::factory()->create(['email' => 'aya@example.test']);

        app(OtpService::class)->issue($utilisateur->email, OtpPurpose::Registration);

        $this->artisan('verifall:code-otp', ['email' => 'aya@example.test'])->assertSuccessful();

        expect(OtpCode::query()
            ->where('purpose', OtpPurpose::Registration->value)
            ->whereNull('consumed_at')
            ->count())->toBe(1);
    });
});

describe('l\'écran de vérification', function (): void {
    it('annonce le délai et la durée de validité', function (): void {
        /*
         | Devant six cases vides et aucune indication, on ne sait pas s'il faut
         | patienter, renvoyer, ou tout reprendre. L'écran ne disait ni l'un ni
         | l'autre.
         */
        Livewire::test(Register::class)
            ->set('etape', 3)
            ->set('email', 'aya@example.test')
            ->assertSee('reste valable')
            ->assertSee((string) OtpService::TTL_MINUTES);
    });

    it('ne reste plus muet quand le renvoi échoue faute de session', function (): void {
        /*
         | `renvoyer()` sortait sans un mot si le compte n'était pas retrouvé :
         | on cliquait, la page ne bougeait pas, et rien ne disait qu'il fallait
         | recommencer. Le même cas, dans `verifier()`, était déjà expliqué.
         */
        Livewire::test(Register::class)
            ->set('etape', 3)
            ->set('compteUuid', '00000000-0000-0000-0000-000000000000')
            ->call('renvoyer')
            ->assertDispatched('toast')
            ->assertSet('etape', 1);
    });
});
