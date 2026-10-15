<?php

declare(strict_types=1);

use App\Livewire\Citizen\MesTutoriels;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Les guides pratiques s'ouvrent DANS l'espace citoyen
|--------------------------------------------------------------------------
| « Lire le guide » pointait sur `route('public.tutorials')`. Depuis son
| espace, le citoyen était éjecté vers le site vitrine : il y perdait son
| en-tête, son solde d'unités, sa cloche, et devait revenir en arrière pour
| retrouver son parcours.
|
| Pire : le guide n'y était même pas. La page publique liste des COURS, pas ces
| quatre fiches. Le lien ne menait donc pas au mauvais endroit par erreur — il
| ne menait à rien du tout.
|
| Le premier test est celui qui compte : il garde la RÈGLE, pas la
| correction — aucun lien vers le site public depuis cet écran.
*/

function citoyenActif(): User
{
    return User::factory()->create(['otp_verified_at' => now()]);
}

describe('la règle', function (): void {
    it('ne renvoie plus vers le site public depuis les guides', function (): void {
        Livewire::actingAs(citoyenActif())
            ->test(MesTutoriels::class)
            ->assertDontSee(route('public.tutorials'), escape: false);
    });
});

describe('ouvrir un guide', function (): void {
    it('affiche les quatre étapes sans quitter l\'écran', function (): void {
        Livewire::actingAs(citoyenActif())
            ->test(MesTutoriels::class)
            ->call('ouvrirLeGuide', 'verifier-une-image')
            ->assertSet('guide', 'verifier-une-image')
            ->assertSee('Lancez une recherche inversée')
            ->assertSee('Comparez les dates et contextes')
            // Aucune redirection : le composant reste monté, le gabarit citoyen
            // avec lui. C'est tout l'intérêt.
            ->assertNoRedirect();
    });

    it('sert les quatre guides', function (): void {
        $cles = ['verifier-une-image', 'arnaque-recrutement', 'lire-un-rapport', 'audio-suspect'];

        foreach ($cles as $cle) {
            $composant = Livewire::actingAs(citoyenActif())
                ->test(MesTutoriels::class)
                ->call('ouvrirLeGuide', $cle);

            $composant->assertSet('guide', $cle);

            expect($composant->instance()->guideOuvert())->not->toBeNull();
        }
    });

    it('ignore une clé inventée plutôt que d\'ouvrir un panneau vide', function (): void {
        // C'est un paramètre d'URL : il se modifie à la main.
        Livewire::actingAs(citoyenActif())
            ->test(MesTutoriels::class)
            ->call('ouvrirLeGuide', 'guide-invente')
            ->assertSet('guide', '');
    });

    it('revient à la liste', function (): void {
        Livewire::actingAs(citoyenActif())
            ->test(MesTutoriels::class)
            ->call('ouvrirLeGuide', 'audio-suspect')
            ->call('fermerLeGuide')
            ->assertSet('guide', '')
            ->assertSee('Vérifier une image en 3 minutes');
    });
});

describe('les identifiants de guide', function (): void {
    it('ne changent PAS avec la langue', function (): void {
        /*
         | Ils vivaient d'abord dans les fichiers de langue, et le test de
         | couverture les a refusés — à juste titre, mais pour une raison plus
         | profonde que la parité : traduits, `?guide=verifier-une-image`
         | deviendrait `?guide=check-an-image`. Le même guide aurait deux
         | adresses, et un lien partagé cesserait de fonctionner pour qui n'a
         | pas la même langue.
         */
        $enFrancais = array_column(
            Livewire::actingAs(citoyenActif())->test(MesTutoriels::class)->instance()->guides(),
            'cle',
        );

        app()->setLocale('en');

        $enAnglais = array_column(
            Livewire::actingAs(citoyenActif())->test(MesTutoriels::class)->instance()->guides(),
            'cle',
        );

        expect($enAnglais)->toBe($enFrancais)
            ->and($enFrancais)->toBe([
                'verifier-une-image', 'arnaque-recrutement', 'lire-un-rapport', 'audio-suspect',
            ]);
    });

    it('trouve son guide dans les deux langues', function (): void {
        // Le rapprochement se fait par POSITION : les deux fichiers doivent
        // déclarer les guides dans le même ordre.
        app()->setLocale('en');

        Livewire::actingAs(citoyenActif())
            ->test(MesTutoriels::class)
            ->call('ouvrirLeGuide', 'verifier-une-image')
            ->assertSee('Run a reverse search');
    });
});

describe('les icônes', function (): void {
    it('sont des SVG en ligne, pas une police d\'icônes', function (): void {
        /*
         | Une police d'icônes se télécharge, et tant qu'elle n'est pas arrivée
         | le navigateur affiche le NOM du glyphe : on lit « arrow_forward » à
         | l'écran. Hors ligne — et l'application a un service worker — c'est
         | l'état permanent.
         */
        $rendu = Livewire::actingAs(citoyenActif())->test(MesTutoriels::class)->html();

        expect($rendu)->toContain('<svg')
            ->and($rendu)->not->toContain('material-symbols-rounded');
    });
});
