<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    // Les rôles et permissions ne sont pas dans les migrations : sans ce
    // semis, `assignRole('Admin')` échoue avant même d'atteindre l'écran.
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Lot 2 — le menu latéral ne doit plus mentir
|--------------------------------------------------------------------------
| Cinq défauts vivaient dans un seul fichier, `layouts/staff.blade.php`. Aucun
| n'était visible en test : toutes les pages répondaient 200, et un lien qui
| mène au mauvais endroit répond 200 lui aussi.
|
| Le premier test de ce fichier est le plus important, et il ne concerne aucun
| des cinq : c'est une RÈGLE, gardée pour toujours. Le défaut « une entrée du
| back-office pointe vers l'espace citoyen » avait été signalé sur les Live
| Classes ; en auditant le gabarit, on en a trouvé un second, sur le répertoire
| d'outils, que personne n'avait remarqué. Un troisième arrivera au prochain
| écran ajouté à la va-vite. Autant l'empêcher.
*/

/**
 * Combien d'entrées du MENU LATÉRAL mènent à cette route.
 *
 * Compte les lignes portant `sidebar-link`, et elles seules : la barre du haut
 * — menu « Créer », menu utilisateur — renvoie vers plusieurs des mêmes écrans
 * légitimement, et les inclure ferait échouer le test pour de bonnes raisons.
 */
/**
 * Combien de fois cette route figure-t-elle au menu ?
 *
 * ## Lit la DÉCLARATION, plus le gabarit
 *
 * Ces tests comptaient des lignes `sidebar-link` dans
 * `layouts/staff.blade.php`. Depuis le 3 septembre 2026, le menu n'est plus
 * écrit dans le gabarit : il est déclaré dans `MenuDuBackOffice` et rendu par
 * une boucle, pour que sa visibilité soit LUE sur la garde de chaque route
 * plutôt que recopiée à côté.
 *
 * Compter des lignes de gabarit rendrait donc systématiquement zéro. **Ce que
 * ces tests protègent n'a pas changé** — pas deux chemins vers la même page —
 * seul l'endroit où la réponse se lit a bougé.
 */
function entreesDuMenu(string $route): int
{
    $total = 0;

    foreach (MenuDuBackOffice::GROUPES as $entrees) {
        $total += count(array_filter(
            array_keys($entrees),
            static fn (string $declaree): bool => $declaree === $route,
        ));
    }

    return $total;
}

/** Un administrateur, connecté sur le guard `staff`. */
function administrateur(): User
{
    $membre = User::factory()->create([
        'otp_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);

    $membre->assignRole(UserRole::Admin->value);

    return $membre;
}

describe('la règle, gardée pour toujours', function (): void {
    it('ne laisse aucune route citoyenne dans le menu du back-office', function (): void {
        /*
         | Une entrée `citizen.*` dans ce gabarit fait basculer l'administrateur
         | du guard `staff` au guard `web`. Sans session citoyenne ouverte — le
         | cas ordinaire — il tombe sur l'écran de connexion CITOYEN, depuis le
         | back-office. Rien ne casse, rien n'alerte : la page répond 200.
         |
         | Le test lit le GABARIT et non une page rendue : il attrape aussi les
         | entrées cachées derrière un `@can` que le compte de test ne
         | déclencherait pas.
         */
        $gabarit = file_get_contents(resource_path('views/components/layouts/staff.blade.php'));

        expect($gabarit)->not->toContain("route('citizen.");
    });
});

describe('les doublons du menu', function (): void {
    it('ne propose plus deux chemins vers les paramètres', function (): void {
        /*
         | « SYSTÈME › Paramètres » et « CONFIG SYSTÈME › Réglages du Système »
         | menaient tous deux à `staff.settings`. On cherche celui qu'on a
         | utilisé la dernière fois, et on ne le retrouve pas.
         |
         | On compte les entrées `sidebar-link` et non toutes les occurrences de
         | la route : le MENU UTILISATEUR de la barre du haut y renvoie lui
         | aussi, par « Mon profil » et « Paramètres ». C'est un troisième
         | doublon, réel, et il n'est PAS de ce lot : « Mon profil » pointe sur
         | les réglages faute d'écran de profil du personnel — c'est B-13, avec
         | l'espace mentor. L'élargir ici mêlerait deux chantiers.
         */
        expect(entreesDuMenu('staff.settings'))->toBe(1);
    });

    it('ne propose plus deux chemins vers les opportunités du Hub', function (): void {
        // « HUB D'OPPORTUNITÉS › Opportunités » doublait « HUB OPPORTUNITÉS ›
        // Jobs Vérifiés », tous deux vers `staff.hub-jobs`.
        expect(entreesDuMenu('staff.hub-jobs'))->toBe(1);
    });

    it('propose « Portails Officiels », et le lien MÈNE quelque part', function (): void {
        /*
         | CE TEST A ÉTÉ RETOURNÉ le 1er septembre 2026.
         |
         | Il vérifiait l'inverse — que l'entrée avait été RETIRÉE — et il avait
         | raison de le faire : les portails n'étaient affichés nulle part, et
         | une entrée d'administration pour un module invisible est une promesse
         | qu'on ne tient pas.
         |
         | La mise en retrait était explicitement « décidée, pas un abandon »
         | (`Citizen\HubInfos` le disait). Le porteur du projet a demandé le
         | retour : CRUD d'administration, et rubrique citoyenne derrière un
         | interrupteur fermé par défaut.
         |
         | La prémisse a donc expiré, et ce qu'il faut garder est l'inverse :
         | l'entrée existe ET mène à un écran réel.
         */
        // Le libellé se lit dans la déclaration, pas dans le gabarit — voir
        // `entreesDuMenu()`.
        $libelles = collect(MenuDuBackOffice::GROUPES)->flatten(1)->pluck(1);

        expect($libelles)->toContain('Portails Officiels')
            ->and(entreesDuMenu('staff.hub-portals'))->toBe(1);

        $this->actingAs(administrateur(), 'staff')
            ->get(route('staff.hub-portals'))
            ->assertOk();
    });
});

describe('la cloche du back-office', function (): void {
    it('n\'affiche plus de notifications écrites en dur', function (): void {
        /*
         | Quatre notifications et une pastille figée sur « 3 » vivaient dans le
         | gabarit. Chaque membre de l'équipe, sur chaque page, voyait « Dossier
         | KYC-2214 soumis » et « il y a 8 min ».
         |
         | Plus grave qu'un décor : une pastille toujours allumée DÉSAPPREND à
         | la regarder. Le jour où douze signalements arrivent vraiment, plus
         | personne ne distingue ce chiffre de celui d'hier.
         */
        $membre = administrateur();

        $reponse = $this->actingAs($membre, 'staff')->get(route('staff.dashboard'));

        $reponse->assertOk()
            ->assertDontSee('Dossier KYC-2214 soumis')
            ->assertDontSee('12 nouveaux signalements')
            ->assertDontSee('Sauvegarde BD réussie');
    });

    it('n\'allume aucune pastille quand il n\'y a rien à lire', function (): void {
        $membre = administrateur();

        $this->actingAs($membre, 'staff')
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('data-notif-count', escape: false);
    });
});

describe('les deux écrans qui manquaient', function (): void {
    it('sert les Live Classes depuis le guard staff', function (): void {
        $this->actingAs(administrateur(), 'staff')
            ->get(route('staff.live-classes'))
            ->assertOk()
            ->assertSee('Live Classes');
    });

    it('sert le répertoire des outils depuis le guard staff', function (): void {
        $this->actingAs(administrateur(), 'staff')
            ->get(route('staff.tools'))
            ->assertOk()
            ->assertSee('Répertoire des outils');
    });

    it('refuse les deux écrans à une session citoyenne', function (): void {
        /*
         | Le pendant du premier test : le back-office n'est pas atteignable
         | depuis le guard `web`, même pour un administrateur. La séparation des
         | guards n'est pas conceptuelle, elle est effective.
         */
        $membre = administrateur();

        $this->actingAs($membre, 'web')->get(route('staff.live-classes'))->assertRedirect();
        $this->actingAs($membre, 'web')->get(route('staff.tools'))->assertRedirect();
    });
});
