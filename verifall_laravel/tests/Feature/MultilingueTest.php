<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\DetecteurDeLangue;
use App\Enums\Langue;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Http\Middleware\ChoisirLaLangue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Français et anglais
|--------------------------------------------------------------------------
| Le français est le DÉFAUT, en toutes circonstances, et il ne se déduit de
| rien. C'est la contrainte la plus importante de tout le dispositif : un
| téléphone vendu au Bénin sort d'usine en anglais dans une proportion
| notable, et beaucoup de gens n'en changent jamais le réglage. Servir
| l'anglais à quelqu'un qui lit le français parce que son appareil n'a jamais
| été configuré serait une dégradation présentée comme une attention.
*/

describe('la langue par défaut', function (): void {
    it('est le FRANÇAIS, sans rien avoir à demander', function (): void {
        test()->get(route('public.home'))
            ->assertOk()
            ->assertSee('<html lang="fr"', escape: false);
    });

    it("ne se déduit JAMAIS de l'en-tête du navigateur", function (): void {
        /*
         | LE test qui protège la règle. Un navigateur qui réclame l'anglais
         | avec insistance reçoit quand même le français : seul un choix
         | explicite change la langue.
         */
        test()->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get(route('public.home'))
            ->assertOk()
            ->assertSee('<html lang="fr"', escape: false);
    });

    it('retombe sur le français devant une langue inconnue', function (): void {
        expect(Langue::depuis('yo'))->toBe(Langue::Fr)
            ->and(Langue::depuis(null))->toBe(Langue::Fr)
            ->and(Langue::depuis(''))->toBe(Langue::Fr);
    });
});

describe('le sélecteur', function (): void {
    it('bascule en anglais et le retient', function (): void {
        test()->from(route('public.home'))
            ->post(route('langue', 'en'))
            ->assertRedirect(route('public.home'));

        test()->get(route('public.home'))
            ->assertSee('<html lang="en"', escape: false);
    });

    it("refuse d'être un GET", function (): void {
        /*
         | Changer la langue modifie l'état de la session. Un GET qui modifie
         | l'état se déclenche par un préchargement de navigateur ou une image
         | distante posée sur un autre site : quelqu'un verrait sa langue
         | changer sans avoir rien cliqué.
         */
        test()->get('/langue/en')->assertMethodNotAllowed();
    });

    it('ne renvoie JAMAIS vers un domaine étranger', function (): void {
        /*
         | `back()` suit l'en-tête `Referer`, que n'importe qui peut forger.
         | Sans contrôle, le sélecteur devient une redirection ouverte : un lien
         | habillé aux couleurs du site renverrait ailleurs.
         */
        test()->withHeaders(['Referer' => 'https://exemple-malveillant.test/piege'])
            ->post(route('langue', 'en'))
            ->assertRedirect(route('public.home'));
    });

    it('ignore une langue inconnue sans rien casser', function (): void {
        test()->from(route('public.home'))
            ->post(route('langue', 'yo'))
            ->assertRedirect(route('public.home'));

        test()->get(route('public.home'))->assertSee('<html lang="fr"', escape: false);
    });

    it('enregistre le choix sur le compte, pour qu’il suive', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);

        test()->actingAs($citoyen)
            ->from(route('public.home'))
            ->post(route('langue', 'en'));

        expect($citoyen->refresh()->locale)->toBe('en');
    });

    it('laisse la SESSION primer sur la préférence du compte', function (): void {
        /*
         | Quelqu'un qui bascule en anglais depuis un poste partagé veut
         | l'anglais MAINTENANT, pas la langue enregistrée sur son compte.
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now(), 'locale' => 'fr']);

        test()->actingAs($citoyen)
            ->withSession([ChoisirLaLangue::CLEF_SESSION => 'en'])
            ->get(route('public.home'))
            ->assertSee('<html lang="en"', escape: false);
    });

    it('reprend la préférence du compte à défaut de session', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now(), 'locale' => 'en']);

        test()->actingAs($citoyen)
            ->get(route('public.home'))
            ->assertSee('<html lang="en"', escape: false);
    });
});

describe('les verdicts', function (): void {
    it('se disent dans les deux langues', function (): void {
        app()->setLocale('fr');
        expect(VerdictFactCheck::Trompeur->libelle())->toBe('Trompeur')
            ->and(VerdictFactCheck::NonVerifiable->libelle())->toBe('Non vérifiable');

        app()->setLocale('en');
        expect(VerdictFactCheck::Trompeur->libelle())->toBe('Misleading')
            ->and(VerdictFactCheck::NonVerifiable->libelle())->toBe('Unverifiable');
    });

    it('gardent les QUATRE mêmes verdicts dans les deux langues', function (): void {
        /*
         | Quatre, et quatre seulement. Ils sont nommés dans les mentions
         | légales, dans le balisage ClaimReview et sur les cartes de démenti :
         | un cinquième mot apparu dans un fichier de langue serait une promesse
         | que la plateforme ne tient nulle part ailleurs.
         */
        foreach (['fr', 'en'] as $langue) {
            $court = (array) __('verdicts.court', [], $langue);
            $explications = (array) __('verdicts.explication', [], $langue);

            expect(array_keys($court))->toBe(['true', 'false', 'misleading', 'unverifiable'])
                ->and(array_keys($explications))->toBe(['true', 'false', 'misleading', 'unverifiable']);
        }
    });

    it("ne dit JAMAIS qu'un verdict non vérifiable est à moitié vrai", function (): void {
        // C'est le verdict le plus souvent mal rapporté : sa formulation doit
        // dire l'absence de conclusion, jamais une demi-mesure.
        app()->setLocale('en');

        expect(VerdictFactCheck::NonVerifiable->explication())
            ->toContain('does not allow a conclusion')
            ->and(VerdictFactCheck::NonVerifiable->explication())->not->toContain('half');
    });
});

describe('la détection à la soumission', function (): void {
    it('reconnaît un texte français', function (): void {
        expect(app(DetecteurDeLangue::class)->detecter(
            "Une vidéo circule sur les réseaux et prétend que le gouvernement a annoncé la gratuité de l'électricité.",
        ))->toBe(Langue::Fr);
    });

    it('reconnaît un texte anglais', function (): void {
        expect(app(DetecteurDeLangue::class)->detecter(
            'A video is circulating on social media claiming that the government has announced free electricity for everyone.',
        ))->toBe(Langue::En);
    });

    it('retourne le FRANÇAIS quand le texte est trop court pour conclure', function (): void {
        /*
         | Inventer une langue sur trois mots serait pire que ne rien conclure.
         | La détection oriente la rédaction du rapport ; elle ne décide de
         | rien.
         */
        expect(app(DetecteurDeLangue::class)->detecter('Coupure électricité'))->toBe(Langue::Fr)
            ->and(app(DetecteurDeLangue::class)->detecter(''))->toBe(Langue::Fr);
    });

    it('ne se laisse pas tromper par un lien anglais dans un texte français', function (): void {
        // Un lien anglais dans un texte français en fausserait le comptage à
        // lui seul.
        expect(app(DetecteurDeLangue::class)->detecter(
            'Cette information est fausse, mais la source citée est https://www.the-daily-news-and-report.com/what-they-said',
        ))->toBe(Langue::Fr);
    });
});

describe("l'administration reste en français", function (): void {
    /*
     | LE PERSONNEL DE CETTE PLATEFORME EN EST AUSSI LE PUBLIC.
     |
     | Le sélecteur de langue vit sur le site public ; l'administration n'en a
     | aucun. Mais le middleware s'applique au groupe `web`, routes du personnel
     | comprises. Quelqu'un qui basculait en anglais pour relire une page
     | publique arrivait ensuite en administration avec l'anglais en session,
     | sans l'avoir demandé et sans moyen de revenir.
     |
     | Ce qu'il y voyait n'était pas une administration en anglais, mais un
     | MÉLANGE : les libellés venus d'énumérations basculaient, la pagination et
     | les messages de validation aussi, tandis que tout ce qui est écrit en dur
     | dans les gabarits restait français — jusqu'à deux langues sur une même
     | ligne de tableau.
     |
     | Traduire l'administration serait un chantier légitime. Le faire à moitié,
     | par ricochet, n'en est pas un.
     */
    beforeEach(function (): void {
        test()->seed(RolesAndPermissionsSeeder::class);

        $this->membre = User::factory()->create(['otp_verified_at' => now()]);
        $this->membre->assignRole(UserRole::Admin->value);
    });

    it("sert le français même quand l'anglais est choisi en session", function (): void {
        $reponse = test()->actingAs($this->membre, 'staff')
            ->withSession([
                ChoisirLaLangue::CLEF_SESSION => Langue::En->value,
                'staff.last_activity' => now()->timestamp,
            ])
            ->get(route('staff.dashboard'));

        $reponse->assertOk();

        expect(app()->getLocale())->toBe(Langue::Fr->value);
    });

    it("n'EFFACE PAS le choix de langue au passage", function (): void {
        /*
         | Le point délicat, et la raison pour laquelle l'affichage et la
         | mémorisation sont deux gestes séparés.
         |
         | Forcer la langue affichée est légitime ; effacer un choix explicite
         | au passage ne l'est pas. Quelqu'un qui a choisi l'anglais pour lire
         | une page publique doit le retrouver en y revenant, même après un
         | détour par l'administration.
         */
        test()->actingAs($this->membre, 'staff')
            ->withSession([
                ChoisirLaLangue::CLEF_SESSION => Langue::En->value,
                'staff.last_activity' => now()->timestamp,
            ])
            ->get(route('staff.dashboard'))
            ->assertOk();

        expect(session(ChoisirLaLangue::CLEF_SESSION))->toBe(Langue::En->value);

        // Et le site public le respecte toujours.
        test()->get(route('public.home'))->assertOk();

        expect(app()->getLocale())->toBe(Langue::En->value);
    });

    it('laisse le site public et le compte citoyen basculer, eux', function (): void {
        // La règle vise l'administration, pas la langue en général : sans cette
        // vérification, épingler tout le monde au français passerait aussi.
        test()->withSession([ChoisirLaLangue::CLEF_SESSION => Langue::En->value])
            ->get(route('public.home'))
            ->assertOk();

        expect(app()->getLocale())->toBe(Langue::En->value);
    });
});
