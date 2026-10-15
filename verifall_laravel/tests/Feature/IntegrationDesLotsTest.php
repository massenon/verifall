<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Application\Contracts\DepotCorrections;
use App\Core\Application\Contracts\DepotNotes;
use App\Core\Marketing\Services\Parrainage;
use App\Enums\UserRole;
use App\Models\MarketingCampaign;
use App\Models\User;
use Database\Seeders\CampagnesMarketingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LES SOUDURES entre les quatre lots et le socle
|--------------------------------------------------------------------------
| Quatre lots ont été écrits en parallèle, chacun interdit d'écrire dans les
| fichiers de couture — routes, gabarits, provider, planificateur. Ils ont donc
| livré du code juste que RIEN N'APPELAIT : une fabrique de cartes que personne
| ne déclenchait, un calcul de pontage que personne ne planifiait, un lien de
| pied de page vers une route inexistante.
|
| Ces défauts-là ne produisent aucune erreur. Les tests de chaque lot restent
| verts, `pint` est propre, et le produit ne fait simplement pas ce qu'il
| annonce. Le seul moyen de les attraper est de tester les soudures elles-mêmes.
|
| C'est ce que fait ce fichier, et rien d'autre : il ne re-teste aucun
| comportement métier, il vérifie que chaque morceau est BRANCHÉ.
*/

describe('les routes que les lots ne pouvaient pas poser', function (): void {
    it('déclare les trois pages de transparence', function (): void {
        /*
         | La dépendance la plus dure du chantier : `article.blade.php` appelle
         | `route('public.corrections')` dès qu'une correction existe. Sans la
         | route, une page de fact-check corrigé rend 500 — et c'est exactement
         | la page qu'on partage après avoir corrigé un verdict.
         */
        foreach (['public.corrections', 'public.methodology', 'public.funding'] as $nom) {
            expect(Route::has($nom))->toBeTrue("Route absente : {$nom}");
        }
    });

    it('sert la carte de démenti sous un nom stable', function (): void {
        // Le lot avait dû déclarer cette route dans son propre test, faute de
        // pouvoir écrire dans `routes/web.php`. Si les deux divergent, c'est
        // ici que ça se voit — avant le premier partage.
        expect(Route::has('public.fact.carte'))->toBeTrue();
    });

    it("ouvre les trois portes de l'infolettre, dont la sortie", function (): void {
        /*
         | Le désabonnement compte autant que l'abonnement : quelqu'un qui ne
         | trouve pas comment sortir d'une liste marque l'expéditeur comme
         | indésirable, et c'est la réputation du domaine entier qui tombe.
         */
        foreach ([
            'public.newsletter.subscribe',
            'public.newsletter.confirm',
            'public.newsletter.unsubscribe',
            'staff.newsletter',
        ] as $nom) {
            expect(Route::has($nom))->toBeTrue("Route absente : {$nom}");
        }
    });
});

describe("l'en-tete public", function (): void {
    it('propose la CONNEXION a un visiteur', function (): void {
        $html = (string) test()->get(route('public.home'))->assertOk()->getContent();

        expect($html)->toContain(route('login'))
            ->and($html)->not->toContain(route('citizen.dashboard'));
    });

    it('propose MON COMPTE et la deconnexion a qui est connecte', function (): void {
        /*
         | Proposer « Connexion » a quelqu'un qui l'est deja est le signe le
         | plus sur qu'un site ne sait pas qui le consulte. Le bouton garde sa
         | place, son style et son icone : seuls l'intitule et la destination
         | changent.
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);

        $html = (string) test()->actingAs($citoyen)
            ->get(route('public.home'))->assertOk()->getContent();

        expect($html)->toContain(route('citizen.dashboard'))
            ->toContain(route('logout'))
            ->and($html)->not->toContain('>Connexion<');
    });

    it('deconnecte par POST, jamais par un lien', function (): void {
        /*
         | Un GET qui deconnecte se declenche par un prechargement de navigateur
         | ou une image distante posee sur un autre site : c'est une faille CSRF
         | de manuel, et elle ne se remarque que par des sessions qui « sautent
         | toutes seules ».
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);

        $html = (string) test()->actingAs($citoyen)
            ->get(route('public.home'))->getContent();

        expect($html)->toContain('action="'.route('logout').'"')
            ->and($html)->toContain('name="_token"')
            ->and($html)->not->toContain('href="'.route('logout').'"');
    });
});

describe('le parrainage public', function (): void {
    it('affiche le bareme REEL, jamais un chiffre ecrit en dur', function (): void {
        /*
         | Le jour ou le bareme change, cette page doit suivre. Une page
         | promotionnelle qui annonce un bareme perime est une promesse rompue,
         | et c'est le pire endroit pour en faire une.
         */
        $html = (string) test()->get(route('public.referral'))->assertOk()->getContent();

        $bareme = app(Parrainage::class);

        expect($html)->toContain((string) $bareme->unitesParrain())
            ->and($html)->toContain((string) $bareme->unitesFilleul());
    });

    it('mene vers l inscription pour un visiteur, vers son code pour un membre', function (): void {
        $visiteur = (string) test()->get(route('public.referral'))->getContent();

        expect($visiteur)->toContain(route('register'))
            ->and($visiteur)->not->toContain(route('citizen.referral'));

        $citoyen = User::factory()->create(['otp_verified_at' => now()]);

        $membre = (string) test()->actingAs($citoyen)
            ->get(route('public.referral'))->getContent();

        expect($membre)->toContain(route('citizen.referral'));
    });

    it('rappelle la regle qui evite les malentendus', function (): void {
        /*
         | La recompense part a la PREMIERE VERIFICATION, jamais a
         | l'inscription. Ecrit en clair et non en petits caracteres : un
         | programme de parrainage attire les tentatives de fraude, et les
         | conditions doivent etre lisibles avant qu'on perde son temps.
         */
        test()->get(route('public.referral'))
            ->assertOk()
            ->assertSee('jamais', escape: false);
    });

    it('est annonce sur l accueil, sobrement', function (): void {
        $html = (string) test()->get(route('public.home'))->assertOk()->getContent();

        expect($html)->toContain('id="parrainage"')
            ->and($html)->toContain(route('public.referral'));
    });
});

describe('les liaisons du conteneur', function (): void {
    it('résout les dépôts des quatre lots', function (): void {
        // Une interface non liée ne lève qu'au moment où quelqu'un s'en sert :
        // le formulaire d'abonnement, la page des corrections, l'écran d'un
        // sujet. Jamais au démarrage.
        foreach ([
            DepotAbonnes::class,
            DepotCorrections::class,
            DepotNotes::class,
        ] as $contrat) {
            expect(app($contrat))->toBeObject();
        }
    });
});

describe('les tâches planifiées', function (): void {
    it('planifie le pontage et l’infolettre', function (): void {
        /*
         | SANS `verifall:pontage`, AUCUNE NOTE NE SE PUBLIE JAMAIS. Les votes
         | s'accumulent, les notes restent « en attente d'évaluation », et rien
         | à l'écran ne signale la panne.
         |
         | Sans l'infolettre, le rendez-vous du lundi n'a simplement pas lieu —
         | et le seul dispositif qui le rende visible est un bandeau dans
         | l'écran d'administration, que personne n'ouvre le lundi matin.
         */
        $planifiees = collect(app(Schedule::class)->events())
            ->map(static fn ($evenement): string => $evenement->command ?? '')
            ->implode(' ');

        expect($planifiees)->toContain('verifall:pontage')
            ->and($planifiees)->toContain('verifall:infolettre-hebdomadaire');
    });

    it("envoie l'infolettre à l'heure de Cotonou, pas en UTC", function (): void {
        /*
         | `config/app.php` est en UTC. Sans fuseau explicite, « la lettre du
         | lundi matin » partirait à 08h10 locale — le nom du rendez-vous
         | deviendrait faux, et personne ne s'en apercevrait avant des semaines.
         */
        $infolettre = collect(app(Schedule::class)->events())
            ->first(static fn ($e): bool => str_contains((string) $e->command, 'infolettre-hebdomadaire'));

        expect($infolettre)->not->toBeNull()
            ->and($infolettre->timezone)->toBe('Africa/Porto-Novo')
            // Lundi, 07h10 locale.
            ->and($infolettre->expression)->toBe('10 7 * * 1');
    });
});

describe('ce que le visiteur voit réellement', function (): void {
    it('mène aux trois pages de transparence depuis le pied de page', function (): void {
        // Une page publiée qu'aucun lien n'atteint n'est pas publiée. C'est le
        // pied de page qui fait la différence entre « existe » et « accessible ».
        $html = (string) $this->get(route('public.home'))->assertOk()->getContent();

        foreach (['public.corrections', 'public.methodology', 'public.funding'] as $nom) {
            expect($html)->toContain(route($nom));
        }
    });

    it("fait POSTER pour de vrai le formulaire d'infolettre de l'accueil", function (): void {
        /*
         | LE DÉFAUT LE PLUS COÛTEUX TROUVÉ SUR CE CHANTIER.
         |
         | Le prototype porte depuis toujours un formulaire d'abonnement sur
         | l'accueil — « Rejoignez la communauté anti-fake news ». Il était
         | FACTICE : `data-vf-form`, dont le gestionnaire appelle
         | `ev.preventDefault()`, attend 900 ms et affiche « Bienvenue dans la
         | communauté ! Vérifiez votre boîte mail. » Aucune `action`, aucun
         | `name` sur le champ. La promesse était faite, rien n'était envoyé.
         |
         | Pire que l'absence de formulaire : quelqu'un s'inscrivait, était
         | remercié, et n'existait nulle part.
         |
         | Ce test garde les trois conditions ensemble. La troisième compte
         | autant que les deux autres : remettre `data-vf-form` ferait ravaler
         | la soumission par le script, le message de bienvenue s'afficherait
         | encore, et plus rien ne signalerait la panne.
         */
        $html = (string) $this->get(route('public.home'))->assertOk()->getContent();

        expect($html)->toContain('action="'.route('public.newsletter.subscribe').'"')
            ->and($html)->toContain('name="email"')
            ->and($html)->not->toContain('data-vf-form-success="Bienvenue dans la communauté');
    });

    it("n'affiche jamais DEUX formulaires d'abonnement sur la même page", function (): void {
        /*
         | L'accueil a le sien depuis le prototype ; les autres pages
         | éditoriales reçoivent le composant. Poser les deux au même endroit
         | dupliquerait l'identifiant DOM `nl-email` — ce qui casse
         | silencieusement l'association du `<label for>` et envoie les lecteurs
         | d'écran sur le mauvais champ.
         */
        foreach (['public.home', 'public.facts', 'public.blog', 'public.corrections'] as $nom) {
            $html = (string) $this->get(route($nom))->assertOk()->getContent();

            expect(substr_count($html, 'id="nl-email"'))->toBe(1, "Formulaire en double sur {$nom}");
        }
    });

    it("offre l'ancre d'abonnement sur les pages ÉDITORIALES", function (): void {
        /*
         | Là où quelqu'un vient de lire un démenti — donc la personne la plus
         | susceptible d'en vouloir la suite chaque lundi. Sur l'accueil, c'est
         | la section que le prototype y consacre déjà qui la porte.
         */
        foreach (['public.home', 'public.facts', 'public.blog', 'public.corrections', 'public.methodology'] as $nom) {
            $this->get(route($nom))
                ->assertOk()
                ->assertSee('id="newsletter-section"', escape: false);
        }
    });

    it('le tient à l’écart des pages légales et utilitaires', function (): void {
        /*
         | LE GARDE-FOU DE L'ARBITRAGE 166.
         |
         | Le bloc a d'abord été posé dans le gabarit, donc sur les vingt-deux
         | pages publiques — mentions légales comprises. C'était un écart au
         | prototype qui ne se justifiait pas : les trois parcours d'abonnement
         | redirigent tous vers l'accueil, et l'ancre de la campagne se répare
         | en la rendant absolue.
         |
         | Ce test empêche la reprise du raccourci « je le remets dans le
         | gabarit, c'est plus simple ».
         */
        foreach (['public.legal', 'public.privacy', 'public.cookies', 'public.contact', 'public.faq'] as $nom) {
            $this->get(route($nom))
                ->assertOk()
                ->assertDontSee('id="newsletter-section"', escape: false);
        }
    });

    it('vise cette ancre par un chemin ABSOLU, depuis n’importe quelle page', function (): void {
        /*
         | `#newsletter-section` ne mène quelque part que si la page courante
         | porte déjà le bloc. La fenêtre marketing, elle, s'affiche partout :
         | depuis les mentions légales, le bouton défilerait jusqu'à rien.
         |
         | C'est cette barre oblique qui rend inutile de poser le bloc sur les
         | vingt-deux pages.
         */
        $seme = new CampagnesMarketingSeeder;
        $seme->run();

        $url = MarketingCampaign::where('slug', 'newsletter-boom')->value('url');

        expect($url)->toBe('/#newsletter-section');
    });

    it('range les nouveaux écrans dans la barre latérale du personnel', function (): void {
        /*
         | Un écran sans entrée de menu est un écran que personne n'ouvrira —
         | ce qui revient exactement à ne pas l'avoir écrit.
         |
         | Les trois entrées sont vérifiées ensemble parce qu'elles partagent le
         | même mode de défaillance : la route existe, l'écran fonctionne, les
         | tests de l'écran passent, et le lien manque au gabarit.
         */
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        $reponse = $this->actingAs($admin, 'staff')->get(route('staff.dashboard'))->assertOk();

        foreach ([
            'staff.newsletter',
            'staff.content-reports',
            'staff.contact-messages',
        ] as $nom) {
            $reponse->assertSee(route($nom), escape: false);
        }
    });

    it('ne montre PAS la boîte de contact au modérateur', function (): void {
        /*
         | Le pendant du test précédent. Un lien vers un écran interdit produit
         | une page 403 au clic : le menu promet ce que la Policy refuse, et
         | c'est le genre d'incohérence qui fait douter de tout le reste.
         */
        $this->seed(RolesAndPermissionsSeeder::class);

        $moderateur = User::factory()->create();
        $moderateur->assignRole(UserRole::Moderateur->value);

        $html = (string) $this->actingAs($moderateur, 'staff')
            ->get(route('staff.dashboard'))->assertOk()->getContent();

        expect($html)->toContain(route('staff.content-reports'))
            ->and($html)->not->toContain(route('staff.contact-messages'));
    });
});
