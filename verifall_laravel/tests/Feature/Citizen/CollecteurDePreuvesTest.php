<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\CollecteurDePreuves;
use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Core\FactChecking\Services\UrlSandbox;
use App\Core\FactChecking\ValueObjects\PaquetDePreuves;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Enums\PositionPreuve;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| CollecteurDePreuves — phase 6
|--------------------------------------------------------------------------
| Les deux premiers blocs testent UNIQUEMENT la fabrication du paquet depuis
| des candidates données, en isolation. Le dernier bloc (« branchement dans
| l'orchestrateur ») prouve le fil de bascule décrit dans le docblock de la
| classe : désactivé par défaut (`factcheck.collecteur_de_preuves_actif`),
| actif à la demande, sans toucher au format `sources:[{url}]` que les trois
| adaptateurs LLM lisent encore — ce dernier point reste un chantier séparé.
|
| Le résolveur DNS du sandbox sous-jacent est TOUJOURS fixé à une IP
| publique : sans ça, ces tests dépendraient de la résolution RÉELLE de
| domaines inventés (`exemple.com`, `site3.exemple`), qui n'existent pas
| dans le vrai DNS — exactement le genre de test flaky que
| `UrlSandboxTest` évite déjà en injectant son propre résolveur.
|
| ## Le piège trouvé en écrivant le dernier bloc
|
| Rebinder `UrlSandbox::class` suffit quand le test résout `CollecteurDePreuves`
| directement (`app(CollecteurDePreuves::class)`) — c'est le cas des deux
| premiers blocs. Il ne suffit PAS quand `CollecteurDePreuves` est résolu en
| profondeur, comme PARAMÈTRE OPTIONNEL d'un constructeur déjà volumineux
| (`OrchestrateurVerification`, neuf paramètres) : le conteneur en construit
| alors un exemplaire qui ignore le rebind et retombe sur `new UrlSandbox`
| (résolution DNS RÉELLE), repéré ici parce qu'un des cinq domaines simulés
| (`lanationbenin.info`) ne résout pas sur ce poste — échec silencieux,
| sélectif, et reproductible à 100 %, jamais aléatoire. La parade : rebinder
| AUSSI `CollecteurDePreuves::class` explicitement, pour que sa construction
| passe par le conteneur plutôt que par le défaut du paramètre.
*/

beforeEach(function (): void {
    app()->bind(UrlSandbox::class, fn () => new UrlSandbox(resoudreHote: fn (string $h): string => '203.0.113.10'));
    app()->bind(CollecteurDePreuves::class, fn () => new CollecteurDePreuves(app(UrlSandbox::class)));
});

function candidate(string $url, string $nom = 'Source', int $fiabilite = 60): Preuve
{
    return new Preuve(url: $url, nomSource: $nom, extrait: 'extrait de secours', position: PositionPreuve::Neutre, fiabilite: $fiabilite);
}

function pageHtml(string $titre, string $corps, ?string $canonique = null, ?string $datePubliee = null): string
{
    $lienCanonique = $canonique !== null ? "<link rel=\"canonical\" href=\"{$canonique}\">" : '';
    $meta = $datePubliee !== null ? "<meta property=\"article:published_time\" content=\"{$datePubliee}\">" : '';

    return "<!DOCTYPE html><html><head><title>{$titre}</title>{$lienCanonique}{$meta}</head><body>{$corps}</body></html>";
}

describe('collecte — du candidat au paquet identifié', function (): void {
    it('fetch une candidate pertinente et l\'identifie SRC_001', function (): void {
        Http::fake(['exemple.com/*' => Http::response(
            pageHtml('Le prix du carburant a doublé', 'Le prix du carburant a officiellement doublé ce mois-ci selon le ministère.'),
            200,
        )]);

        $paquet = app(CollecteurDePreuves::class)->collecter(
            'Le prix du carburant a doublé',
            [candidate('https://exemple.com/article', 'Exemple')],
        );

        expect($paquet->estVide())->toBeFalse()
            ->and($paquet->compte())->toBe(1)
            ->and($paquet->elements()[0]->id)->toBe('SRC_001');
    });

    it('écarte une candidate FETCHÉE mais hors sujet — jamais mise dans le paquet', function (): void {
        Http::fake(['exemple.com/*' => Http::response(
            pageHtml('Résultats du championnat régional', 'Une équipe locale a remporté le tournoi ce week-end.'),
            200,
        )]);

        $paquet = app(CollecteurDePreuves::class)->collecter(
            'Le prix du carburant a doublé au Bénin',
            [candidate('https://exemple.com/sport')],
        );

        expect($paquet->estVide())->toBeTrue();
    });

    it('écarte une candidate qui échoue à la récupération — source indisponible', function (): void {
        Http::fake(['exemple.com/*' => Http::response('', 500)]);

        $paquet = app(CollecteurDePreuves::class)->collecter(
            'Une affirmation quelconque',
            [candidate('https://exemple.com/panne')],
        );

        expect($paquet->estVide())->toBeTrue();
    });

    it('déduplique deux candidates du même éditeur AVANT de fetcher — une seule requête', function (): void {
        Http::fake(['lemonde.fr/*' => Http::response(
            pageHtml('Le prix du carburant', 'Le prix du carburant a doublé selon les autorités.'),
            200,
        )]);

        app(CollecteurDePreuves::class)->collecter('Le prix du carburant a doublé', [
            candidate('https://www.lemonde.fr/a', fiabilite: 60),
            candidate('https://m.lemonde.fr/b', fiabilite: 90),
        ]);

        Http::assertSentCount(1);
    });

    it('lit la date de publication et le lien canonique RÉELLEMENT déclarés par la page', function (): void {
        Http::fake(['exemple.com/*' => Http::response(
            pageHtml(
                'Le prix du carburant a doublé',
                'Le prix du carburant a doublé ce mois, confirme le ministère des Finances.',
                canonique: 'https://exemple.com/canonique-article',
                datePubliee: '2026-09-15T10:00:00Z',
            ),
            200,
        )]);

        $paquet = app(CollecteurDePreuves::class)->collecter(
            'Le prix du carburant a doublé',
            [candidate('https://exemple.com/article?utm=x')],
        );

        $element = $paquet->elements()[0];

        expect($element->urlCanonique)->toBe('https://exemple.com/canonique-article')
            ->and($element->publieeLe?->toDateString())->toBe('2026-09-15');
    });

    it('ne devine jamais une date absente de la page', function (): void {
        Http::fake(['exemple.com/*' => Http::response(
            pageHtml('Le prix du carburant a doublé', 'Le prix du carburant a doublé, sans date affichée.'),
            200,
        )]);

        $paquet = app(CollecteurDePreuves::class)->collecter(
            'Le prix du carburant a doublé',
            [candidate('https://exemple.com/sans-date')],
        );

        expect($paquet->elements()[0]->publieeLe)->toBeNull();
    });

    it('plafonne à douze éléments', function (): void {
        $candidates = [];
        $fakes = [];

        for ($i = 1; $i <= 15; $i++) {
            $fakes["site{$i}.exemple/*"] = Http::response(
                pageHtml('Le prix du carburant a doublé', 'Le prix du carburant a doublé partout, confirmé.'),
                200,
            );
            $candidates[] = candidate("https://site{$i}.exemple/a");
        }

        Http::fake($fakes);

        $paquet = app(CollecteurDePreuves::class)->collecter('Le prix du carburant a doublé', $candidates);

        expect($paquet->compte())->toBe(PaquetDePreuves::MAX_ELEMENTS);
    });

    it('ne fetch RIEN sur une liste de candidates vide', function (): void {
        Http::fake();

        expect(app(CollecteurDePreuves::class)->collecter('Une affirmation', [])->estVide())->toBeTrue();
        Http::assertNothingSent();
    });
});

describe('PaquetDePreuves — résolution d\'identifiant, contrat V-10', function (): void {
    it('résout un identifiant présent, et seulement lui', function (): void {
        Http::fake(['exemple.com/*' => Http::response(
            pageHtml('Le prix a doublé', 'Le prix du carburant a doublé, article complet.'),
            200,
        )]);

        $paquet = app(CollecteurDePreuves::class)->collecter('Le prix du carburant a doublé', [
            candidate('https://exemple.com/a'),
        ]);

        expect($paquet->resoudre('SRC_001'))->not->toBeNull()
            ->and($paquet->resoudre('SRC_999'))->toBeNull();
    });

    it('un paquet vide dit explicitement qu\'aucun identifiant n\'est citable', function (): void {
        expect(PaquetDePreuves::vide()->pourPrompt())->toContain('NON_VERIFIABLE');
    });
});

describe('branchement dans l\'orchestrateur — fil de bascule, phase 6/V-10', function (): void {
    /*
    | `AdaptateurSimule::preuvesSimulees()` cite toujours deux de ces cinq
    | domaines réels, choisis par une empreinte déterministe du claim — on les
    | fake TOUS, par domaine entier (n'importe quel article), pour ne pas
    | dépendre de savoir lesquels seront tirés.
    */
    function fakerLesCinqDomainesSimules(string $titre, string $corps): void
    {
        $page = pageHtml($titre, $corps);

        Http::fake([
            'gouv.bj/*' => Http::response($page, 200),
            'arcep.bj/*' => Http::response($page, 200),
            'lanationbenin.info/*' => Http::response($page, 200),
            'factuel.afp.com/*' => Http::response($page, 200),
            'banouto.info/*' => Http::response($page, 200),
        ]);
    }

    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->citoyen = User::factory()->actif()->create();
        $this->citoyen->assignRole(UserRole::Citoyen->value);
        $this->citoyen->forceFill(['units' => 20])->save();
        Queue::fake();
    });

    it('éteint par défaut : aucune requête sortante, les colonnes de canonicalisation restent NULL', function (): void {
        fakerLesCinqDomainesSimules('Le prix du carburant a doublé', 'Le prix du carburant a doublé ce mois, confirment les autorités.');

        $verification = app(SoumissionVerificationService::class)
            ->soumettre($this->citoyen, TypeContenu::Texte, 'Le prix du carburant a doublé ce mois.');

        app(OrchestrateurVerification::class)->executer($verification);

        Http::assertNothingSent();

        $preuve = $verification->refresh()->evidences->first();
        expect($preuve)->not->toBeNull()
            ->and($preuve->url_finale)->toBeNull()
            ->and($preuve->recupere_le)->toBeNull();
    });

    it('activé : les preuves citées par les moteurs simulés sont RÉELLEMENT récupérées et canonicalisées', function (): void {
        config(['factcheck.collecteur_de_preuves_actif' => true]);
        fakerLesCinqDomainesSimules('Le prix du carburant a doublé', 'Le prix du carburant a doublé ce mois, confirment les autorités.');

        $verification = app(SoumissionVerificationService::class)
            ->soumettre($this->citoyen, TypeContenu::Texte, 'Le prix du carburant a doublé ce mois.');

        app(OrchestrateurVerification::class)->executer($verification);

        $preuves = $verification->refresh()->evidences;

        expect($preuves)->not->toBeEmpty();

        // Chaque source simulée pointe vers un des cinq domaines fakés : la
        // page a donc été réellement ouverte, et le prouve par ses colonnes
        // de canonicalisation remplies — jamais devinées.
        foreach ($preuves as $preuve) {
            expect($preuve->url_finale)->not->toBeNull()
                ->and($preuve->recupere_le)->not->toBeNull()
                ->and($preuve->fetched)->toBeTrue();
        }
    });

    it('activé mais candidate non pertinente : la preuve reste enregistrée depuis le moteur, sans canonicalisation', function (): void {
        config(['factcheck.collecteur_de_preuves_actif' => true]);
        // Pages fakées HORS SUJET : aucune ne passe le filtre de pertinence,
        // le paquet reste vide, et l'enregistrement retombe sur le
        // comportement d'avant cette phase plutôt que d'échouer.
        fakerLesCinqDomainesSimules('Résultats sportifs du week-end', 'Une équipe locale a remporté le tournoi régional.');

        $verification = app(SoumissionVerificationService::class)
            ->soumettre($this->citoyen, TypeContenu::Texte, 'Le prix du carburant a doublé ce mois.');

        app(OrchestrateurVerification::class)->executer($verification);

        $preuve = $verification->refresh()->evidences->first();

        expect($preuve)->not->toBeNull()
            ->and($preuve->url_finale)->toBeNull()
            ->and($preuve->recupere_le)->toBeNull()
            // Toujours là, et toujours marquée consultée côté moteur : ne pas
            // confondre « non canonicalisée » et « absente du rapport ».
            ->and($preuve->fetched)->toBeTrue();
    });
});
