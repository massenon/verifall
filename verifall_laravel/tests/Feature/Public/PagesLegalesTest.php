<?php

declare(strict_types=1);

use App\Core\Application\Reglages\IdentiteLegale;
use App\Core\Application\Reglages\Reglages;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Les trois pages légales
|--------------------------------------------------------------------------
| Le prototype les publiait avec des espaces réservés en clair : « [Votre
| Adresse Complète] », « [Votre Nom Complet] », « [Nom de votre hébergeur] ».
|
| Sur la page même qu'ouvre celui qui veut savoir à qui il a affaire, c'est le
| pire endroit possible pour un crochet. Et sur une plateforme qui juge la
| véracité d'autrui, ne pas savoir dire qui l'édite est une contradiction dans
| les termes.
|
| Le prototype décrivait par ailleurs des verdicts que la plateforme n'émet pas.
*/

beforeEach(function (): void {
    app(Reglages::class)->oublier();
});

describe('ce qui ne doit JAMAIS être publié', function (): void {
    it('ne laisse aucun espace réservé entre crochets', function (): void {
        foreach (['public.legal', 'public.privacy', 'public.cookies'] as $nom) {
            $html = (string) test()->get(route($nom))->assertOk()->getContent();

            expect($html)->not->toMatch('/\[(Votre|Nom de|Adresse de|Contact de)[^\]]*\]/');
        }
    });

    it('ne décrit pas de verdict que la plateforme n’émet pas', function (): void {
        /*
         | Le prototype annonçait « VRAI », « FAUX », « DOUTEUX ». Aucun verdict
         | « DOUTEUX » n'a jamais existé : les quatre sont VRAI, FAUX, TROMPEUR
         | et NON VÉRIFIABLE. Une page légale qui décrit des verdicts inexistants
         | engage sur du vide.
         */
        $html = (string) test()->get(route('public.legal'))->assertOk()->getContent();

        expect($html)->not->toContain('DOUTEUX')
            ->and($html)->toContain('TROMPEUR')
            ->and($html)->toContain('NON VÉRIFIABLE');
    });
});

describe("l'identité de l'éditeur", function (): void {
    it('vient des réglages, pas du code', function (): void {
        // Le jour où l'hébergeur change, la page doit suivre sans déploiement.
        app(Reglages::class)->set('legal_host_name', 'Un Autre Hebergeur');
        app(Reglages::class)->oublier();

        test()->get(route('public.legal'))
            ->assertOk()
            ->assertSee('Un Autre Hebergeur');
    });

    it('dit « Non communiqué » plutôt que d’inventer', function (): void {
        /*
         | CE TEST PORTE SUR LE COMPOSANT, et non plus sur un réglage vidé.
         |
         | Depuis que les quatre champs obligatoires ont une valeur par défaut au
         | catalogue, vider un réglage ne produit plus d'absence : `Reglages::get()`
         | retombe sur le défaut. C'est le bon comportement — un champ effacé par
         | mégarde ne doit pas blanchir les mentions légales — mais il rend
         | l'absence inatteignable par ce chemin.
         |
         | L'absence reste possible pour un champ sans défaut. C'est donc le
         | contrat du composant qu'il faut éprouver, et il n'a pas changé : une
         | page incomplète est un fait, un faux remplissage est un mensonge.
         */
        $rendu = Blade::render(
            '<x-mention-legale :valeur="$valeur" reglage="legal_director" libelle="Directeur de la publication" />',
            ['valeur' => null],
        );

        expect($rendu)->toContain('Non communiqué')
            ->and($rendu)->not->toMatch('/\[[^\]]+\]/');
    });

    it("signale au PERSONNEL ce qu'il reste à renseigner, jamais au visiteur", function (): void {
        /*
         | L'avertissement s'adresse à qui peut agir. Le montrer au visiteur
         | reviendrait à afficher nos propres lacunes administratives sur la page
         | qui doit inspirer confiance.
         */
        test()->seed(RolesAndPermissionsSeeder::class);

        $gabarit = '<x-mention-legale :valeur="$valeur" reglage="legal_director" libelle="Directeur de la publication" />';

        expect(Blade::render($gabarit, ['valeur' => null]))->not->toContain('À renseigner');

        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        test()->actingAs($admin, 'staff');

        expect(Blade::render($gabarit, ['valeur' => null]))->toContain('À renseigner');
    });

    it("retombe sur l'adresse de contact quand aucune adresse dédiée n'existe", function (): void {
        /*
         | Mieux vaut une adresse générale qui reçoit qu'une adresse dédiée qui
         | rebondit : un droit d'accès qui n'arrive nulle part est un droit
         | refusé.
         */
        $reglages = app(Reglages::class);
        $reglages->set('legal_dpo_email', '');
        $reglages->set('contact_email', 'contact@verifall.com');
        $reglages->oublier();

        expect(app(IdentiteLegale::class)->contactDonnees())->toBe('contact@verifall.com');
    });
});

describe('la politique des cookies', function (): void {
    it("n'annonce PAS de traceur qui n'est pas déposé", function (): void {
        /*
         | Le prototype affirmait employer Google Analytics et renvoyait à un
         | lien « Gérer mes cookies » du pied de page. Ni l'un ni l'autre
         | n'existent. Une politique qui annonce des traceurs absents est aussi
         | fautive qu'une politique qui en tait : dans les deux cas, elle décrit
         | autre chose que ce que fait le site.
         */
        $html = (string) test()->get(route('public.cookies'))->assertOk()->getContent();

        expect($html)->not->toContain('Google Analytics')
            ->and($html)->not->toContain('Gérer mes cookies');
    });

    it('nomme chaque traceur nécessaire et sa raison', function (): void {
        $html = (string) test()->get(route('public.cookies'))->getContent();

        foreach (['Cookie de session', 'CSRF', 'Préférence de langue'] as $traceur) {
            expect($html)->toContain($traceur);
        }
    });
});

describe('la politique de confidentialité', function (): void {
    it('énonce ses bases légales et ses durées', function (): void {
        // Sans base légale ni durée, une politique de confidentialité décrit
        // une intention, pas un traitement.
        $html = (string) test()->get(route('public.privacy'))->assertOk()->getContent();

        foreach (['Bases légales', 'Durées de conservation', 'Consentement', 'APDP'] as $attendu) {
            expect($html)->toContain($attendu);
        }
    });

    it("dit que l'adresse IP n'est jamais conservée en clair", function (): void {
        // C'est vrai dans le code : `IdentityHasher` la transforme avant
        // écriture. La politique doit dire ce que le code fait.
        test()->get(route('public.privacy'))
            ->assertOk()
            ->assertSee('empreinte irréversible');
    });
});
