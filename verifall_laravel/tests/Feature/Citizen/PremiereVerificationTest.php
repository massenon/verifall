<?php

declare(strict_types=1);

use App\Livewire\Citizen\TableauDeBord;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| L'invitation à vérifier, juste après l'inscription
|--------------------------------------------------------------------------
| Quelqu'un qui vient de créer un compte a fait la démarche pour une raison
| précise : une rumeur reçue le matin même. Le tableau de bord d'un compte neuf
| est vide — aucune vérification, aucun badge, aucune progression — et rien n'y
| indique par où commencer.
|
| Cette modale existe pour ça, et elle est bornée par trois règles que ces
| tests tiennent : une seule fois, jamais superposée au quiz d'accueil, et
| refusable pour de bon.
*/

beforeEach(function (): void {
    /*
     | `onboarding_quiz_done_at` est renseigné : le QUIZ D'ACCUEIL passe devant
     | l'invitation à vérifier, et c'est voulu — il enseigne un réflexe en
     | trente secondes, là où l'invitation suppose qu'on ait déjà une rumeur
     | sous la main. Ces tests portent sur ce qui se passe APRÈS le quiz.
     */
    $this->citoyen = User::factory()->create([
        'otp_verified_at' => now(),
        'onboarding_quiz_done_at' => now(),
    ]);
});

it("laisse le QUIZ D'ACCUEIL passer devant, au sortir de l'inscription", function (): void {
    /*
     | L'ordre a été inversé deux fois avant d'être arrêté. Ce test le fige :
     | sur un compte tout neuf, c'est le quiz qui s'ouvre, jamais l'invitation.
     */
    $neuf = User::factory()->create(['otp_verified_at' => now()]);

    session()->put('verifall.premiere_verification', true);

    Livewire::actingAs($neuf)
        ->test(TableauDeBord::class)
        ->assertSee('Votre premier réflexe fact-checking')
        ->assertDontSee('Vérifiez votre première info');
});

it("ne s'affiche pas sans le drapeau d'inscription", function (): void {
    // Un compte ancien ne doit pas revoir l'invitation à chaque connexion.
    Livewire::actingAs($this->citoyen)
        ->test(TableauDeBord::class)
        ->assertDontSee('Vérifiez votre première info');
});

it("s'affiche quand l'inscription vient d'aboutir", function (): void {
    session()->put('verifall.premiere_verification', true);

    Livewire::actingAs($this->citoyen)
        ->test(TableauDeBord::class)
        ->assertSee('Vérifiez votre première info');
});

it('DISPARAÎT pour de bon quand on la refuse', function (): void {
    /*
     | Le point qui compte. Une invitation qui revient à chaque affichage du
     | tableau de bord cesse d'en être une : c'est une interruption.
     */
    session()->put('verifall.premiere_verification', true);

    Livewire::actingAs($this->citoyen)
        ->test(TableauDeBord::class)
        ->call('reporterPremiereVerification');

    expect(session()->has('verifall.premiere_verification'))->toBeFalse();

    Livewire::actingAs($this->citoyen)
        ->test(TableauDeBord::class)
        ->assertDontSee('Vérifiez votre première info');
});

it("mène à l'écran de vérification, et se referme en chemin", function (): void {
    /*
     | Elle n'embarque pas le formulaire : le dupliquer signifierait maintenir
     | deux fois la même validation, le même contrôle de solde et le même suivi
     | d'analyse — pour finir par les voir diverger.
     */
    session()->put('verifall.premiere_verification', true);

    Livewire::actingAs($this->citoyen)
        ->test(TableauDeBord::class)
        ->call('allerVerifier')
        ->assertRedirect(route('citizen.verify'));

    expect(session()->has('verifall.premiere_verification'))->toBeFalse();
});
