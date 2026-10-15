<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Core\FactChecking\Services\TiplineWhatsApp;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| La tipline WhatsApp
|--------------------------------------------------------------------------
| En Afrique de l'Ouest francophone, la désinformation ne circule pas sur le
| web : elle circule sur WhatsApp, en images et en messages vocaux transférés.
| Une tipline transforme la vérification d'une démarche en un réflexe.
|
| Le module reste ÉTEINT tant que les trois valeurs ne sont pas renseignées.
| Ce qui manque n'est pas du code : ce sont des identifiants délivrés par Meta,
| après vérification de l'organisation.
*/

beforeEach(function (): void {
    test()->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    $this->reglages = app(Reglages::class);
    $this->reglages->oublier();
});

function tiplineConfiguree(): void
{
    $reglages = app(Reglages::class);
    $reglages->set('whatsapp_phone_number_id', '123456789');
    $reglages->set('whatsapp_token', 'jeton-permanent-meta');
    $reglages->set('whatsapp_verify_token', 'mon-jeton-de-rappel');
    $reglages->oublier();
}

describe("l'activation", function (): void {
    it('est ÉTEINTE tant que rien n’est configuré', function (): void {
        expect(app(TiplineWhatsApp::class)->estActive())->toBeFalse()
            ->and(app(TiplineWhatsApp::class)->manquants())->toHaveCount(3);
    });

    it('exige les TROIS valeurs, pas deux', function (): void {
        /*
         | Deux sur trois ne produisent pas une tipline à moitié fonctionnelle :
         | elles produisent une tipline qui REÇOIT sans pouvoir RÉPONDRE.
         | Quelqu'un transfère une rumeur et n'obtient jamais de verdict, ce qui
         | est pire que de ne rien proposer du tout.
         */
        $this->reglages->set('whatsapp_phone_number_id', '123456789');
        $this->reglages->set('whatsapp_token', 'jeton-permanent-meta');
        $this->reglages->oublier();

        expect(app(TiplineWhatsApp::class)->estActive())->toBeFalse()
            ->and(array_keys(app(TiplineWhatsApp::class)->manquants()))
            ->toBe(['whatsapp_verify_token']);
    });

    it("s'allume quand les trois sont là", function (): void {
        tiplineConfiguree();

        expect(app(TiplineWhatsApp::class)->estActive())->toBeTrue()
            ->and(app(TiplineWhatsApp::class)->manquants())->toBe([]);
    });

    it('ignore une valeur faite uniquement d’espaces', function (): void {
        tiplineConfiguree();
        $this->reglages->set('whatsapp_token', '   ');
        $this->reglages->oublier();

        expect(app(TiplineWhatsApp::class)->estActive())->toBeFalse();
    });
});

describe('le jeton de rappel', function (): void {
    it('accepte le jeton attendu', function (): void {
        tiplineConfiguree();

        expect(app(TiplineWhatsApp::class)->jetonDeRappelValide('mon-jeton-de-rappel'))->toBeTrue();
    });

    it('refuse tout le reste, y compris le vide', function (): void {
        tiplineConfiguree();

        $tipline = app(TiplineWhatsApp::class);

        expect($tipline->jetonDeRappelValide('autre-chose'))->toBeFalse()
            ->and($tipline->jetonDeRappelValide(''))->toBeFalse()
            ->and($tipline->jetonDeRappelValide(null))->toBeFalse();
    });

    it('refuse TOUT quand aucun jeton n’est configuré', function (): void {
        // Sans configuration, un rappel ne peut venir de personne de légitime.
        expect(app(TiplineWhatsApp::class)->jetonDeRappelValide('n-importe-quoi'))->toBeFalse();
    });

    it('compare à TEMPS CONSTANT', function (): void {
        /*
         | Une comparaison ordinaire s'arrête au premier caractère différent, et
         | le temps de réponse trahit alors combien de caractères étaient
         | corrects : c'est ainsi qu'on reconstitue un secret, caractère par
         | caractère.
         */
        $source = (string) file_get_contents(
            app_path('Core/FactChecking/Services/TiplineWhatsApp.php')
        );

        expect($source)->toContain('hash_equals');
    });
});

describe("l'écran d'administration", function (): void {
    it("s'ouvre à l'administration", function (): void {
        test()->actingAs($this->admin, 'staff')
            ->get(route('staff.tipline'))
            ->assertOk();
    });

    it('reste fermé au mentor', function (): void {
        $mentor = User::factory()->create();
        $mentor->assignRole(UserRole::Mentor->value);

        test()->actingAs($mentor, 'staff')
            ->get(route('staff.tipline'))
            ->assertForbidden();
    });

    it('NOMME ce qui manque, plutôt que de dire « indisponible »', function (): void {
        /*
         | Un module absent et un module en attente de configuration se
         | ressemblent exactement. Sans cette liste, on cherche la tipline dans
         | le menu, on ne la trouve pas, et on conclut qu'elle n'a pas été
         | faite — alors que ce qui manque est un dossier chez Meta.
         */
        test()->actingAs($this->admin, 'staff')
            ->get(route('staff.tipline'))
            ->assertOk()
            ->assertSee('Identifiant du numéro WhatsApp')
            ->assertSee('Jeton de vérification du rappel')
            ->assertSee('Désactivée');
    });

    it("n'affiche JAMAIS le jeton, même configuré", function (): void {
        /*
         | Le jeton autorise à répondre au nom de la plateforme. Il se saisit
         | dans les paramètres, où il est chiffré et jamais renvoyé au
         | navigateur ; cet écran n'a aucune raison de le montrer.
         */
        tiplineConfiguree();

        $html = (string) test()->actingAs($this->admin, 'staff')
            ->get(route('staff.tipline'))->getContent();

        expect($html)->not->toContain('jeton-permanent-meta')
            ->and($html)->not->toContain('mon-jeton-de-rappel')
            // Le numéro, lui, n'est pas un secret : il est fait pour être connu.
            ->and($html)->toContain('123456789');
    });
});
