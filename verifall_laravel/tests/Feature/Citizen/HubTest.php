<?php

declare(strict_types=1);

use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\ArticleHub;
use App\Livewire\Citizen\HubInfos;
use App\Models\HubArticle;
use App\Models\HubJob;
use App\Models\HubPortal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Hub d'Infos Bénin
|--------------------------------------------------------------------------
| Le Hub n'est pas un agrégateur : chaque brève porte le verdict rendu par
| l'équipe. Une brève mal étiquetée dirait l'inverse de ce qu'elle doit dire.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

function breveDeTest(?VerdictFactCheck $verdict, string $categorie = 'Santé', bool $publiee = true): HubArticle
{
    return HubArticle::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'breve-'.Str::random(8),
        'title' => 'Une annonce qui circule sur WhatsApp',
        'excerpt' => 'Elle est reprise par plusieurs pages sans source identifiable.',
        'body' => '<p>Le détail de la vérification.</p>',
        'category' => $categorie,
        'verdict' => $verdict?->value,
        'source_name' => 'ARCEP Bénin',
        'is_published' => $publiee,
        'published_at' => now(),
    ]);
}

/** Une opportunité vérifiée, publiée, encore ouverte. */
function offreDeTest(
    string $titre,
    PorteeOpportunite $portee = PorteeOpportunite::Benin,
    TypeOpportunite $type = TypeOpportunite::Cdd,
): HubJob {
    return HubJob::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'offre-'.Str::random(8),
        'title' => $titre,
        'organisation' => 'Ministère du Numérique',
        'scope' => $portee->value,
        'type' => $type->value,
        'location' => 'Cotonou',
        'description' => 'Candidature 100 % en ligne, aucun paiement demandé.',
        'apply_url' => 'https://www.gouv.bj/offre',
        'deadline' => now()->addMonth()->toDateString(),
        'is_verified' => true,
        'is_published' => true,
        'published_at' => now(),
    ]);
}

describe('bandeau de verdict', function (): void {
    it('traduit le verdict dans le vocabulaire du Hub', function (VerdictFactCheck $verdict, string $attendu): void {
        // Le Hub dit « DÉMENTI » là où le rapport dit « FAUX » : deux registres,
        // un seul verdict stocké.
        expect(breveDeTest($verdict)->libelleDuBandeau())->toBe($attendu);
    })->with([
        [VerdictFactCheck::Vrai, 'VÉRIFIÉ'],
        [VerdictFactCheck::Faux, 'DÉMENTI'],
        [VerdictFactCheck::Trompeur, 'TROMPEUR'],
    ]);

    it('annonce « en cours » plutôt qu\'un verdict absent', function (): void {
        // Une brève sans verdict ne doit pas sortir avec un bandeau vide : le
        // lecteur croirait à un défaut d'affichage.
        expect(breveDeTest(null)->libelleDuBandeau())->toBe('EN COURS')
            ->and(breveDeTest(null)->classeDuBandeau())->toContain('hub-badge');
    });
});

describe('la liste', function (): void {
    it('ne montre jamais une brève non publiée', function (): void {
        $publiee = breveDeTest(VerdictFactCheck::Vrai);
        $brouillon = breveDeTest(VerdictFactCheck::Faux, publiee: false);
        $brouillon->forceFill(['title' => 'Brouillon interne à ne pas montrer'])->save();

        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertOk()
            ->assertSee($publiee->title)
            ->assertDontSee('Brouillon interne');
    });

    it('filtre par catégorie', function (): void {
        breveDeTest(VerdictFactCheck::Vrai, 'Santé');
        $economie = breveDeTest(VerdictFactCheck::Faux, 'Économie');
        $economie->forceFill(['title' => 'Fausse offre au Port de Cotonou'])->save();

        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->call('choisirCategorie', 'Économie')
            ->assertSee('Fausse offre au Port de Cotonou');
    });

    it('retire la mise en avant dès qu\'un filtre est actif', function (): void {
        breveDeTest(VerdictFactCheck::Vrai);

        // La garder en tête d'une liste filtrée montrerait un résultat qui ne
        // correspond pas à la recherche.
        $composant = Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->set('recherche', 'introuvable');

        expect($composant->get('recherche'))->toBe('introuvable');
        // Le libellé est celui du prototype : « Aucune actualité trouvée ».
        $composant->assertSee('Aucune actualité trouvée');
    });
});

describe('la brève', function (): void {
    it('compte une lecture, une seule fois par ouverture', function (): void {
        $breve = breveDeTest(VerdictFactCheck::Faux);

        $composant = Livewire::actingAs($this->citoyen)
            ->test(ArticleHub::class, ['article' => $breve->slug]);

        // Incrémenté au montage, jamais dans `render()` : un clic sur un filtre
        // suffirait sinon à gonfler les statistiques.
        $composant->call('$refresh');

        expect($breve->fresh()->views)->toBe(1);
    });

    it('refuse d\'ouvrir une brève non publiée', function (): void {
        $brouillon = breveDeTest(VerdictFactCheck::Faux, publiee: false);

        Livewire::actingAs($this->citoyen)
            ->test(ArticleHub::class, ['article' => $brouillon->slug])
            ->assertStatus(404);
    })->throws(ModelNotFoundException::class);
});

describe('portails officiels', function (): void {
    it('ne les affiche plus côté citoyen, sans les effacer', function (): void {
        HubPortal::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Portail officiel du Gouvernement',
            'url' => 'https://www.gouv.bj', 'category' => 'Institutions',
            'is_official' => true, 'position' => 1, 'is_published' => true,
        ]);

        // Mise en retrait décidée, pas abandon : les données restent en base et
        // le modèle avec, pour que le retour ne coûte rien.
        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertOk()
            ->assertDontSee('Portail officiel du Gouvernement');

        expect(HubPortal::count())->toBe(1);
    });
});

describe('opportunités', function (): void {
    it('sépare le Bénin de l\'international', function (): void {
        offreDeTest('Chargé de communication', PorteeOpportunite::Benin, TypeOpportunite::Cdd);
        offreDeTest('Bourse de master à Genève', PorteeOpportunite::International, TypeOpportunite::Bourse);

        // Quelqu'un qui cherche un emploi à Cotonou et quelqu'un qui cherche une
        // bourse à l'étranger ne lisent pas la même liste.
        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertSee('Opportunités Bénin')
            ->assertSee('Opportunités Internationales')
            ->assertSee('Chargé de communication')
            ->assertSee('Bourse de master à Genève');
    });

    it('n\'affiche jamais une opportunité non vérifiée', function (): void {
        $suspecte = offreDeTest('Recrutement urgent avec frais de dossier', PorteeOpportunite::Benin);
        $suspecte->forceFill(['is_verified' => false])->save();

        // Publier une opportunité non contrôlée reviendrait à la cautionner —
        // sur la page même qui met en garde contre les fausses offres.
        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertDontSee('Recrutement urgent');
    });

    it('garde une offre visible pendant la semaine qui suit son échéance', function (): void {
        $hier = offreDeTest('Stage clôturé hier', PorteeOpportunite::Benin);
        $hier->forceFill(['deadline' => now()->subDay()->toDateString()])->save();

        // Les candidatures de la veille se traitent encore, et une annonce
        // retirée trop tôt laisse croire qu'elle n'a jamais existé.
        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertSee('Stage clôturé hier')
            ->assertSee('Date limite dépassée');
    });

    it('la retire une fois le délai de grâce écoulé', function (): void {
        $vieille = offreDeTest('Offre expirée depuis longtemps', PorteeOpportunite::Benin);
        $vieille->forceFill([
            'deadline' => now()->subDays(HubJob::JOURS_DE_GRACE + 1)->toDateString(),
        ])->save();

        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertDontSee('Offre expirée depuis longtemps');
    });

    it('dépublie automatiquement au bout du délai, sans effacer', function (): void {
        $vieille = offreDeTest('À fermer', PorteeOpportunite::Benin);
        $vieille->forceFill([
            'deadline' => now()->subDays(HubJob::JOURS_DE_GRACE + 2)->toDateString(),
        ])->save();

        $recente = offreDeTest('À garder', PorteeOpportunite::Benin);
        $recente->forceFill(['deadline' => now()->addMonth()->toDateString()])->save();

        $this->artisan('verifall:fermer-opportunites')->assertSuccessful();

        // Dépubliée, jamais supprimée : l'équipe doit pouvoir vérifier ce qui a
        // été diffusé, et une offre retirée par erreur se remet en ligne.
        expect($vieille->fresh()->is_published)->toBeFalse()
            ->and($vieille->fresh()->closed_at)->not->toBeNull()
            ->and($vieille->fresh()->trashed())->toBeFalse()
            ->and($recente->fresh()->is_published)->toBeTrue();
    });

    it('ne touche à rien en simulation', function (): void {
        $vieille = offreDeTest('Simulation', PorteeOpportunite::Benin);
        $vieille->forceFill([
            'deadline' => now()->subDays(HubJob::JOURS_DE_GRACE + 2)->toDateString(),
        ])->save();

        $this->artisan('verifall:fermer-opportunites --dry-run')->assertSuccessful();

        expect($vieille->fresh()->is_published)->toBeTrue();
    });

    it('filtre par nature', function (): void {
        offreDeTest('Un contrat à durée indéterminée', PorteeOpportunite::Benin, TypeOpportunite::Cdi);
        offreDeTest('Une bourse doctorale', PorteeOpportunite::International, TypeOpportunite::Bourse);

        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->call('choisirType', TypeOpportunite::Bourse->value)
            ->assertSee('Une bourse doctorale')
            ->assertDontSee('Un contrat à durée indéterminée');
    });

    it('ne met en garde contre les frais que sur les recrutements', function (): void {
        offreDeTest('Poste de chargé de projet', PorteeOpportunite::Benin, TypeOpportunite::Cdd);

        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertSee('Aucun frais de dossier');

        HubJob::query()->forceDelete();
        offreDeTest('Colloque sur la désinformation', PorteeOpportunite::International, TypeOpportunite::Conference);

        // L'écrire sous une conférence payante serait faux.
        Livewire::actingAs($this->citoyen)
            ->test(HubInfos::class)
            ->assertSee('Colloque sur la désinformation')
            ->assertDontSee('Aucun frais de dossier');
    });
});
