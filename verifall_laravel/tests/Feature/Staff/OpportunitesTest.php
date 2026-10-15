<?php

declare(strict_types=1);

use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Enums\UserRole;
use App\Livewire\Staff\Opportunites;
use App\Models\AuditLog;
use App\Models\HubJob;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

function offre(array $extra = []): HubJob
{
    return HubJob::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'offre-'.Str::lower(Str::random(6)),
        'title' => 'Chargé de communication digitale',
        'organisation' => 'Une ONG',
        'scope' => PorteeOpportunite::Benin->value,
        'type' => TypeOpportunite::Cdd->value,
        'location' => 'Cotonou',
        'description' => str_repeat('Un descriptif suffisamment long. ', 3),
        'apply_url' => 'https://exemple.bj/candidater',
        'deadline' => today()->addWeeks(3),
        'is_verified' => false,
        'is_published' => false,
    ], $extra));
}

describe('accès', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('staff.hub-jobs'))->assertRedirect(route('staff.login'));
    });

    it('refuse un citoyen', function (): void {
        $citoyen = User::factory()->create();
        $citoyen->assignRole(UserRole::Citoyen->value);

        $this->actingAs($citoyen, 'staff')->get(route('staff.hub-jobs'))->assertForbidden();
    });

    it("laisse entrer l'administration", function (): void {
        $this->actingAs($this->admin, 'staff')->get(route('staff.hub-jobs'))->assertOk();
    });
});

describe('la création', function (): void {
    it('crée une opportunité complète', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Chargé de plaidoyer numérique')
            ->set('organisation', 'Fondation X')
            ->set('portee', PorteeOpportunite::Benin->value)
            ->set('type', TypeOpportunite::Cdi->value)
            ->set('lieu', 'Porto-Novo')
            ->set('descriptif', str_repeat('Un descriptif assez long pour passer. ', 3))
            ->set('lienCandidature', 'https://exemple.bj/postuler')
            ->set('echeance', today()->addMonth()->toDateString())
            ->call('enregistrer')
            ->assertHasNoErrors();

        $creee = HubJob::where('title', 'Chargé de plaidoyer numérique')->first();

        expect($creee)->not->toBeNull()
            // Créée NON vérifiée : la vérification est un geste séparé.
            ->and($creee->is_verified)->toBeFalse();
    });

    it('refuse un lien de candidature non chiffré', function (): void {
        /*
         | Une candidature transmet un nom, une adresse, un téléphone et souvent
         | une pièce d'identité. En HTTP, tout cela circule en clair — et le
         | public du Hub est précisément celui que visent les arnaques au
         | recrutement.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un intitulé valable')
            ->set('organisation', 'Org')
            ->set('lieu', 'Cotonou')
            ->set('descriptif', str_repeat('Descriptif assez long. ', 3))
            ->set('lienCandidature', 'http://exemple.bj/postuler')
            ->set('echeance', today()->addMonth()->toDateString())
            ->call('enregistrer')
            ->assertHasErrors('lienCandidature');
    });

    it('refuse une échéance déjà passée', function (): void {
        // Publier une offre close fait perdre son temps à quelqu'un qui
        // cherche du travail.
        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un intitulé valable')
            ->set('organisation', 'Org')
            ->set('lieu', 'Cotonou')
            ->set('descriptif', str_repeat('Descriptif assez long. ', 3))
            ->set('lienCandidature', 'https://exemple.bj/postuler')
            ->set('echeance', today()->subDay()->toDateString())
            ->call('enregistrer')
            ->assertHasErrors('echeance');
    });
});

describe('la vérification', function (): void {
    it('est un geste distinct, journalisé et nominatif', function (): void {
        /*
         | Une offre frauduleuse publiée doit pouvoir être remontée à qui l'a
         | validée. Sans nom sur la vérification, il n'y a personne à qui
         | demander ce qui a été contrôlé.
         */
        $offre = offre(['is_published' => true]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->call('basculerLaVerification', $offre->uuid);

        $offre->refresh();

        expect($offre->is_verified)->toBeTrue()
            ->and($offre->verified_by)->toBe($this->admin->id)
            ->and(AuditLog::where('action', 'hub_job.verify')->count())->toBe(1);
    });

    it('SAUTE dès que le contenu de l’offre change', function (): void {
        /*
         | LE point de cet écran. Le contenu a changé ; l'examen qui l'avait
         | validé ne porte plus dessus. Garder la coche ferait endosser à
         | quelqu'un un texte qu'il n'a pas lu.
         */
        $offre = offre(['is_verified' => true, 'verified_by' => $this->admin->id]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->call('ouvrirEdition', $offre->uuid)
            ->set('titre', 'Un intitulé entièrement réécrit')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $offre->refresh();

        expect($offre->title)->toBe('Un intitulé entièrement réécrit')
            ->and($offre->is_verified)->toBeFalse()
            ->and($offre->verified_by)->toBeNull();
    });

    it('compte les offres en ligne et non vérifiées', function (): void {
        offre(['is_published' => true, 'is_verified' => false]);
        offre(['is_published' => true, 'is_verified' => true]);
        offre(['is_published' => false, 'is_verified' => false]);

        // Une seule est en ligne SANS contrôle — c'est le chiffre à surveiller.
        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->assertSee('En ligne, non vérifiées')
            ->assertSee('À contrôler en priorité');
    });
});

describe('la suppression', function (): void {
    it('refuse de supprimer une offre PUBLIÉE', function (): void {
        /*
         | Elle a pu être partagée, mise en favori, ou faire l'objet d'une
         | candidature en cours. On la dépublie — geste réversible — plutôt que
         | d'effacer une ligne à laquelle des liens renvoient.
         */
        $offre = offre(['is_published' => true]);

        expect($this->admin->can('delete', $offre))->toBeFalse();
    });

    it('accepte de supprimer un brouillon', function (): void {
        $offre = offre(['is_published' => false]);

        expect($this->admin->can('delete', $offre))->toBeTrue();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->call('demanderLaSuppression', $offre->uuid)
            ->call('supprimer');

        expect(HubJob::where('uuid', $offre->uuid)->exists())->toBeFalse()
            ->and(AuditLog::where('action', 'hub_job.delete')->count())->toBe(1);
    });
});

describe('les filtres', function (): void {
    it('isole les offres en ligne non vérifiées', function (): void {
        offre(['title' => 'Offre contrôlée', 'is_published' => true, 'is_verified' => true]);
        offre(['title' => 'Offre à contrôler', 'is_published' => true, 'is_verified' => false]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->set('filtreEtat', 'a-verifier')
            ->assertSee('Offre à contrôler')
            ->assertDontSee('Offre contrôlée');
    });

    it('filtre par portée et par nature', function (): void {
        offre(['title' => 'Stage international', 'scope' => PorteeOpportunite::International->value, 'type' => TypeOpportunite::Stage->value]);
        offre(['title' => 'CDI béninois', 'scope' => PorteeOpportunite::Benin->value, 'type' => TypeOpportunite::Cdi->value]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->set('filtrePortee', PorteeOpportunite::International->value)
            ->assertSee('Stage international')
            ->assertDontSee('CDI béninois');

        Livewire::actingAs($this->admin, 'staff')
            ->test(Opportunites::class)
            ->set('filtreType', TypeOpportunite::Cdi->value)
            ->assertSee('CDI béninois')
            ->assertDontSee('Stage international');
    });
});

describe('ce que l’écran n’expose PAS', function (): void {
    it("n'offre aucun bouton de fermeture manuelle", function (): void {
        /*
         | `FermerLesOpportunitesExpirees` s'en charge chaque nuit, une semaine
         | après l'échéance. Un second chemin vers le même effet ne serait pas
         | couvert par les mêmes tests.
         */
        foreach (['fermer', 'cloturer', 'archiver'] as $methode) {
            expect(method_exists(Opportunites::class, $methode))->toBeFalse();
        }
    });
});
