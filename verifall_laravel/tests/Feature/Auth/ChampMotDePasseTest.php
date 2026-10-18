<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Register;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| L'œil « voir / cacher » et le témoin de force
|--------------------------------------------------------------------------
|
| Trois comportements étaient morts, tous pour la MÊME raison : le prototype
| attache ses écouteurs au `DOMContentLoaded`, et les champs de mot de passe de
| l'inscription vivent dans `@if ($etape === 2)` — ils n'existent pas encore à
| cet instant.
|
| Ce qu'un test PHP peut tenir ici : que le balisage attendu par le script soit
| présent, sur les six champs, et à l'étape où il doit l'être. Le comportement
| lui-même (le clic, la barre qui s'allonge) se vérifie au navigateur — il est
| consigné comme tel dans le journal.
|
*/

it('donne un œil à CHAQUE champ de mot de passe de la connexion', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-pwd-eye="psw-input"', escape: false);
});

it('donne un œil aux DEUX champs de l’inscription, à l’étape où ils vivent', function () {
    /*
     | La confirmation n'en avait pas. C'est pourtant là qu'il sert le plus :
     | on ne relit pas un mot de passe qu'on connaît, on relit celui qu'on est
     | en train d'inventer.
     */
    Livewire::test(Register::class)
        ->set('etape', 2)
        ->assertSee('data-pwd-eye="reg-password"', escape: false)
        ->assertSee('data-pwd-eye="reg-password-confirm"', escape: false);
});

it('donne un œil aux DEUX champs de la réinitialisation', function () {
    Livewire::test(ForgotPassword::class)
        ->set('etape', 2)
        ->assertSee('data-pwd-eye="fp-password"', escape: false)
        ->assertSee('data-pwd-eye="fp-password-confirm"', escape: false);
});

it('donne enfin un œil à la connexion du PERSONNEL', function () {
    /*
     | Le sixième et dernier champ. J'avais renoncé en supposant que `.ffield`,
     | la classe du design system staff, ne supporterait pas un bouton en
     | position absolue.
     |
     | Vérification faite : `.ffield` EST `position: relative`, et la règle vient
     | de `citizen.css` — que la mise en page du personnel charge aussi. Le
     | renoncement était une supposition, pas une contrainte.
     */
    $this->get(route('staff.login'))
        ->assertOk()
        ->assertSee('data-pwd-eye="a-pass"', escape: false)
        // Depuis la console premium (n° 176), la variante « console » : seul le
        // conteneur change, le bouton et son comportement sont partagés.
        ->assertSee('class="champ !h-11 pr-11"', escape: false)
        /*
         | ET le script. Sans lui, le bouton serait du balisage mort : il
         | s'afficherait et un clic ne ferait rien. Cette mise en page ne le
         | chargeait pas — huitième chaîne muette du chantier, attrapée avant
         | livraison cette fois.
         */
        ->assertSee('assets/js/auth-forms.js', escape: false);
});

it('n’emploie PAS l’attribut du prototype, pour ne pas doubler l’écouteur', function () {
    /*
     | `verifall.js` attache encore son propre gestionnaire à
     | `[data-pwd-toggle]`. Deux écouteurs sur un même bouton, ce serait deux
     | bascules par clic — donc aucun effet visible, et le défaut le plus
     | pénible à diagnostiquer de la famille.
     */
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('data-pwd-toggle', escape: false);
});

it('porte les deux icônes, celle qui montre et celle qui masque', function () {
    // Le script bascule l'attribut `hidden` de l'une à l'autre : sans les deux,
    // l'icône contredirait l'état du champ.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-pwd-icon="voir"', escape: false)
        ->assertSee('data-pwd-icon="cacher"', escape: false);
});

it('donne au témoin de force le conteneur que le script cherche', function () {
    // Il visait `[data-psw-bar]` depuis un script qui ne s'exécutait jamais sur
    // cette étape, et le conteneur n'avait aucun identifiant.
    Livewire::test(Register::class)
        ->set('etape', 2)
        ->assertSee('id="psw-meter"', escape: false)
        ->assertSee('id="psw-meter-message"', escape: false);
});

it('sert les deux scripts sur les écrans d’authentification', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('assets/js/password-meter.js', escape: false)
        ->assertSee('assets/js/auth-forms.js', escape: false);
});

describe('le témoin de force, tel que le script le calcule', function () {
    /*
     | Le barème est en JavaScript ; ces assertions le lisent DANS le fichier
     | livré. C'est le même procédé que `FinalisationInscriptionTest` et
     | `VisiteGuideeTest`, et il tient la seule chose qu'un test PHP puisse
     | tenir ici : que le contrat demandé soit bien celui qui est servi.
     */
    it('écoute « input » et non « keyup »', function () {
        $script = file_get_contents(public_path('assets/js/password-meter.js'));

        // `keyup` n'est jamais émis par un collage ni par un gestionnaire de
        // mots de passe : la barre serait restée vide devant un mot de passe
        // excellent, soit exactement le cas qu'on veut encourager.
        expect($script)->toContain("addEventListener('input'")
            ->and($script)->not->toContain("addEventListener('keyup'");
    });

    it('compte les quatre familles du barème demandé', function () {
        $script = file_get_contents(public_path('assets/js/password-meter.js'));

        expect($script)->toContain('/[a-z]/')
            ->and($script)->toContain('/[A-Z]/')
            ->and($script)->toContain('/[0-9]/')
            ->and($script)->toContain('longueurMinimale');
    });

    it('émet les événements onScore du contrat d’origine', function () {
        $script = file_get_contents(public_path('assets/js/password-meter.js'));

        // Un écran qui voudrait bloquer son bouton d'envoi sous un palier donné
        // écoute ceux-là ; les retirer casserait un contrat public.
        expect($script)->toContain("'onScore' + points");
    });

    it('se garde d’empiler barres et écouteurs quand on le rappelle', function () {
        $script = file_get_contents(public_path('assets/js/password-meter.js'));

        // L'étape 2 apparaît et disparaît : l'initialisation est rejouée.
        expect($script)->toContain('pswMeterLie');
    });
});
