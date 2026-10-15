<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Core\Marketing\Services\Parrainage;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Lot 3 — le barème du parrainage passe de 100/50 à 10/5, et devient réglable
|--------------------------------------------------------------------------
| Les deux montants vivaient dans des CONSTANTES DE CLASSE : modifiables par un
| développeur et par lui seul, pour une décision commerciale qui appartient au
| porteur du projet. Même arbitrage que la grille tarifaire des packs.
|
| Le piège de ce lot était de le « corriger » en écrivant 10 et 5 à la place de
| 100 et 50. Le barème serait resté aussi figé, et les tests auraient continué
| de passer en n'éprouvant rien.
|
| Les deux premiers tests sont donc les seuls qui comptent vraiment : ils
| CHANGENT le réglage et vérifient que le versement suit. Un test qui affirme
| « 10 » sans toucher au réglage passerait aussi bien si la lecture n'était pas
| branchée.
*/

/** Un parrain et son filleul, rattachés. */
function couplageParrainage(): array
{
    $service = app(Parrainage::class);

    $parrain = User::factory()->actif()->create();
    $filleul = User::factory()->actif()->create();
    $filleul->forceFill(['units' => 0])->save();
    $parrain->forceFill(['units' => 0])->save();

    $service->rattacher($filleul, $service->codeDe($parrain));

    return [$service, $parrain->fresh(), $filleul->fresh()];
}

describe('le barème est réglable', function (): void {
    it('verse ce que les réglages disent, pas une constante', function (): void {
        /*
         | LE test du lot. On règle 42 et 7 — des valeurs qu'aucun défaut ne
         | pourrait produire par hasard — et l'on vérifie les soldes.
         */
        app(Reglages::class)->set(Parrainage::CLE_UNITES_PARRAIN, '42');
        app(Reglages::class)->set(Parrainage::CLE_UNITES_FILLEUL, '7');

        [$service, $parrain, $filleul] = couplageParrainage();

        $service->honorer($filleul);

        expect($parrain->fresh()->units)->toBe(42)
            ->and($filleul->fresh()->units)->toBe(7);
    });

    it('annonce à l\'écran exactement ce qu\'il verse', function (): void {
        /*
         | Une page promettant 100 unités pendant que le service en crédite 10
         | n'est pas un bug d'affichage : c'est une promesse non tenue, sur un
         | programme de recommandation. Les vues lisent donc les mêmes méthodes
         | que le versement.
         */
        app(Reglages::class)->set(Parrainage::CLE_UNITES_PARRAIN, '42');
        app(Reglages::class)->set(Parrainage::CLE_UNITES_FILLEUL, '7');

        $html = (string) $this->get(route('public.referral'))->assertOk()->getContent();

        expect($html)->toContain('42')->and($html)->toContain('7');
    });

    it('retombe sur 10 et 5 quand rien n\'est réglé', function (): void {
        // Le défaut du catalogue, qui est le barème demandé.
        $service = app(Parrainage::class);

        expect($service->unitesParrain())->toBe(10)
            ->and($service->unitesFilleul())->toBe(5);
    });

    it('ne met pas la page d\'accueil à terre si les réglages sont illisibles', function (): void {
        /*
         | Régression introduite PAR ce lot, et rattrapée par la suite.
         |
         | Ces montants sont affichés par la page d'accueil. Avant, ils venaient
         | d'une constante : zéro requête. Les faire venir des réglages y a
         | introduit une dépendance à la base — et un test qui ne migre pas
         | rendait aussitôt l'accueil en 500, sur « no such table: settings ».
         |
         | En production, la même chose se produirait pendant une migration ou
         | sur un déploiement neuf. Un chiffre promotionnel ne doit pas pouvoir
         | faire tomber la page la plus visitée du site.
         */
        Schema::drop('settings');

        expect(app(Parrainage::class)->unitesParrain())->toBe(10)
            ->and(app(Parrainage::class)->unitesFilleul())->toBe(5);

        $this->get(route('public.home'))->assertOk();
    });

    it('tient debout si le réglage est vidé', function (): void {
        /*
         | Une ligne vide en base ne doit pas créditer zéro. Le catalogue rend
         | son défaut, et les constantes du service le doublent — c'est la
         | raison pour laquelle elles survivent.
         */
        app(Reglages::class)->set(Parrainage::CLE_UNITES_PARRAIN, '');

        expect(app(Parrainage::class)->unitesParrain())->toBe(10);
    });
});

describe('ce qui a déjà été versé ne bouge plus', function (): void {
    it('historise le montant sur la ligne de parrainage', function (): void {
        /*
         | C'est ce qui rend le réglage SÛR à modifier. `referrer_points` et
         | `referred_points` gardent ce qui a réellement été crédité : baisser
         | le barème demain ne réécrit pas le passé, et les totaux de l'écran
         | « Mon parrainage » — qui somment ces colonnes — continuent de dire la
         | vérité.
         */
        app(Reglages::class)->set(Parrainage::CLE_UNITES_PARRAIN, '42');
        app(Reglages::class)->set(Parrainage::CLE_UNITES_FILLEUL, '7');

        [$service, , $filleul] = couplageParrainage();
        $service->honorer($filleul);

        // Le barème change APRÈS le versement.
        app(Reglages::class)->set(Parrainage::CLE_UNITES_PARRAIN, '1');
        app(Reglages::class)->set(Parrainage::CLE_UNITES_FILLEUL, '1');

        $parrainage = Referral::query()->firstOrFail();

        expect((int) $parrainage->referrer_points)->toBe(42)
            ->and((int) $parrainage->referred_points)->toBe(7);
    });
});
