<?php

declare(strict_types=1);

use App\Core\Gamification\Services\AttributionDesBadges;
use App\Core\Gamification\Services\NiveauCitoyen;
use App\Core\Gamification\Services\SerieDeVigilance;
use App\Core\Marketing\Services\Parrainage;
use App\Enums\StatutParrainage;
use App\Enums\UserRole;
use App\Livewire\Auth\Register;
use App\Livewire\Citizen\MonParrainage;
use App\Livewire\Citizen\Recompenses;
use App\Models\Badge;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserBadge;
use Database\Seeders\BadgesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Gamification et parrainage
|--------------------------------------------------------------------------
| Deux fautes coûteraient de l'argent ou de la crédibilité : accorder deux fois
| la même récompense, et afficher un chiffre qu'on ne mesure pas. Un indicateur
| inventé est pire qu'un indicateur absent — il donne confiance dans un chiffre
| faux, et personne ne va vérifier d'où il sort.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(BadgesSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 10, 'xp' => 0, 'streak_days' => 0])->save();
});

describe('niveaux', function (): void {
    it('monte d\'un palier tous les mille XP', function (int $xp, int $niveau, string $nom): void {
        $this->citoyen->forceFill(['xp' => $xp])->save();
        $service = app(NiveauCitoyen::class);

        expect($service->niveau($this->citoyen))->toBe($niveau)
            ->and($service->nom($this->citoyen))->toBe($nom);
    })->with([
        [0, 1, 'Novice'],
        [999, 1, 'Novice'],
        [1000, 2, 'Observateur'],
        [2500, 3, 'Éclaireur'],
        // Au-delà du dernier nom, le niveau continue mais le titre se fige :
        // inventer des noms à l'infini produirait des titres absurdes.
        [99000, 100, 'Gardien'],
    ]);

    it('ne rend jamais un pourcentage aberrant', function (): void {
        $this->citoyen->forceFill(['xp' => 1750])->save();

        expect(app(NiveauCitoyen::class)->pourcentage($this->citoyen))->toBe(75)
            ->and(app(NiveauCitoyen::class)->xpRestant($this->citoyen))->toBe(250);
    });
});

describe('série de vigilance', function (): void {
    it('ne compte qu\'une fois par jour', function (): void {
        $service = app(SerieDeVigilance::class);

        $service->enregistrerUnPassage($this->citoyen);
        $service->enregistrerUnPassage($this->citoyen->fresh());

        // Sinon la série se gonfle en rafraîchissant une page.
        expect($this->citoyen->fresh()->streak_days)->toBe(1);
    });

    it('enchaîne les jours consécutifs', function (): void {
        $service = app(SerieDeVigilance::class);

        $this->travelTo(now()->subDays(2));
        $service->enregistrerUnPassage($this->citoyen);

        $this->travelTo(now()->addDay());
        $service->enregistrerUnPassage($this->citoyen->fresh());

        $this->travelTo(now()->addDay());
        $service->enregistrerUnPassage($this->citoyen->fresh());

        expect($this->citoyen->fresh()->streak_days)->toBe(3);
    });

    it('repart à un après un jour manqué, jamais à zéro', function (): void {
        $service = app(SerieDeVigilance::class);

        $this->travelTo(now()->subDays(5));
        $service->enregistrerUnPassage($this->citoyen);

        $this->travelBack();
        $service->enregistrerUnPassage($this->citoyen->fresh());

        // La personne est là aujourd'hui : lui afficher zéro serait faux.
        expect($this->citoyen->fresh()->streak_days)->toBe(1);
    });

    it('verse la prime au septième jour, une seule fois', function (): void {
        $service = app(SerieDeVigilance::class);
        $solde = $this->citoyen->units;

        // Ancre FIXE : `now()` suit l'horloge déplacée, et un décalage relatif
        // calculé à chaque tour reculerait dans le passé au lieu d'avancer.
        $depart = now()->startOfDay()->subDays(6)->addHours(9);

        for ($jour = 0; $jour <= 6; $jour++) {
            $this->travelTo($depart->copy()->addDays($jour));
            $service->enregistrerUnPassage($this->citoyen->fresh());
        }

        expect($this->citoyen->fresh()->streak_days)->toBe(7)
            ->and($this->citoyen->fresh()->units)->toBe($solde + SerieDeVigilance::UNITES_DE_PALIER);

        // Repasser le même septième jour ne reverse rien.
        $service->enregistrerUnPassage($this->citoyen->fresh());

        expect($this->citoyen->fresh()->units)->toBe($solde + SerieDeVigilance::UNITES_DE_PALIER);
    });
});

describe('badges', function (): void {
    it('accorde un badge dès que sa condition est remplie', function (): void {
        $this->citoyen->forceFill(['xp' => 1200])->save();

        $nouveaux = app(AttributionDesBadges::class)->recalculer($this->citoyen);

        expect($nouveaux->pluck('slug'))->toContain('verificateur-confirme');
    });

    it('n\'accorde jamais deux fois le même badge', function (): void {
        $this->citoyen->forceFill(['streak_days' => 7])->save();
        $service = app(AttributionDesBadges::class);

        $service->recalculer($this->citoyen);
        $xpApresLaPremiere = $this->citoyen->fresh()->xp;
        $unitesApresLaPremiere = $this->citoyen->fresh()->units;

        $service->recalculer($this->citoyen->fresh());

        // Un recalcul rejoué recréditerait sa récompense à chaque passage.
        expect(UserBadge::where('user_id', $this->citoyen->id)->count())
            ->toBe(UserBadge::where('user_id', $this->citoyen->id)->distinct('badge_id')->count('badge_id'))
            ->and($this->citoyen->fresh()->xp)->toBe($xpApresLaPremiere)
            ->and($this->citoyen->fresh()->units)->toBe($unitesApresLaPremiere);
    });

    it('ignore un badge dont le slug n\'a plus de condition', function (): void {
        $orphelin = Badge::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'badge-supprime-du-code',
            'icon' => '❓', 'name' => 'Orphelin', 'description' => 'Sans condition.',
            'position' => 99, 'is_active' => true,
        ]);

        // Un badge mal nettoyé ne doit pas faire tomber la page des
        // récompenses : il reste simplement verrouillé.
        expect($orphelin->estAcquis(['xp' => 999999]))->toBeFalse();
    });

    it('montre les badges verrouillés avec leur condition', function (): void {
        Livewire::actingAs($this->citoyen)
            ->test(Recompenses::class)
            ->assertOk()
            ->assertSee('Chasseur d\'intox')
            // Un badge verrouillé sans explication est une frustration gratuite.
            ->assertSee('5 intox détectées requises');
    });
});

describe('parrainage', function (): void {
    it('donne un code lisible, sans caractères confondables', function (): void {
        $code = app(Parrainage::class)->codeDe($this->citoyen);

        expect($code)->not->toBeEmpty()
            ->and(substr($code, -4))->not->toMatch('/[O0I1S5]/')
            // Stable : le rappeler ne doit pas en fabriquer un second.
            ->and(app(Parrainage::class)->codeDe($this->citoyen->fresh()))->toBe($code);
    });

    it('refuse qu\'on se parraine soi-même', function (): void {
        $service = app(Parrainage::class);
        $code = $service->codeDe($this->citoyen);

        // Un lien ouvert dans son propre navigateur puis une inscription.
        expect($service->rattacher($this->citoyen->fresh(), $code))->toBeNull()
            ->and(Referral::count())->toBe(0);
    });

    it('ne récompense rien à la seule inscription', function (): void {
        $service = app(Parrainage::class);
        $filleul = User::factory()->actif()->create();
        $solde = $this->citoyen->units;

        $service->rattacher($filleul, $service->codeDe($this->citoyen));

        // Payer l'inscription seule, c'est payer les faux comptes.
        expect(Referral::first()->status)->toBe(StatutParrainage::Inscrit)
            ->and($this->citoyen->fresh()->units)->toBe($solde);
    });

    it('récompense les deux à la première vérification, une seule fois', function (): void {
        $service = app(Parrainage::class);
        $filleul = User::factory()->actif()->create();
        $filleul->forceFill(['units' => 0])->save();
        $solde = $this->citoyen->units;

        $service->rattacher($filleul, $service->codeDe($this->citoyen));

        $service->honorer($filleul->fresh());
        $service->honorer($filleul->fresh());

        /*
         | Le barème est lu par le SERVICE, pas par la constante : depuis le
         | lot 3 il vient des réglages, et un test qui affirmerait un chiffre
         | en dur n'éprouverait plus le versement — il éprouverait sa propre
         | copie du montant.
         */
        expect($this->citoyen->fresh()->units)->toBe($solde + $service->unitesParrain())
            ->and($filleul->fresh()->units)->toBe($service->unitesFilleul())
            ->and(Referral::first()->status)->toBe(StatutParrainage::Recompense);
    });

    it('ne prive pas le filleul quand le parrain a fermé son compte', function (): void {
        $service = app(Parrainage::class);
        $parrain = User::factory()->actif()->create();
        $filleul = User::factory()->actif()->create();

        $service->rattacher($filleul, $service->codeDe($parrain));
        $parrain->forceDelete();

        $parrainage = $service->honorer($filleul->fresh());

        // Le filleul n'y est pour rien.
        expect($parrainage)->not->toBeNull()
            ->and($parrainage->qualified_at)->not->toBeNull();
    });

    it('ne montre du filleul que son prénom et son initiale', function (): void {
        $service = app(Parrainage::class);
        $filleul = User::factory()->actif()->create(['name' => 'Marie Ahouansou']);

        $service->rattacher($filleul, $service->codeDe($this->citoyen));

        // Afficher le nom complet livrerait au parrain des données que son
        // filleul ne lui a pas confiées.
        expect(Referral::first()->nomDuFilleul())->toBe('Marie A.');

        Livewire::actingAs($this->citoyen)
            ->test(MonParrainage::class)
            ->assertSee('Marie A.')
            ->assertDontSee('Ahouansou');
    });

    it('compte les invitations OUVERTES, pas les envois', function (): void {
        $service = app(Parrainage::class);
        $service->codeDe($this->citoyen);

        $service->compterUneVisite($this->citoyen);
        $service->compterUneVisite($this->citoyen->fresh());

        // Compter les envois supposerait de suivre chaque partage WhatsApp.
        expect($service->indicateurs($this->citoyen->fresh())['visites'])->toBe(2);

        Livewire::actingAs($this->citoyen)
            ->test(MonParrainage::class)
            ->assertSee('Invitations ouvertes');
    });
});

describe('la page d\'invitation', function (): void {
    it('dépose le code puis renvoie sur l\'inscription', function (): void {
        $code = app(Parrainage::class)->codeDe($this->citoyen);

        $this->get(route('public.invitation', ['code' => $code]))
            ->assertRedirect(route('register'))
            ->assertSessionHas(Register::CLE_PARRAINAGE, $code);

        expect($this->citoyen->fresh()->referral_visits)->toBe(1);
    });

    it('n\'envoie personne sur une 404 avec un code inconnu', function (): void {
        // Quelqu'un qui a mal recopié un lien reçu sur WhatsApp doit arriver
        // sur l'inscription : il perdra le parrainage, pas l'envie de créer
        // un compte.
        $this->get(route('public.invitation', ['code' => 'INCONNU42']))
            ->assertRedirect(route('register'));
    });
});
