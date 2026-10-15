<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\LiveClasses;
use App\Livewire\Staff\Outils;
use App\Models\AuditLog;
use App\Models\LiveClass;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Lot 2 — les deux écrans qui n'existaient pas
|--------------------------------------------------------------------------
| Leurs entrées de menu pointaient vers l'espace citoyen. Il n'y avait donc
| aucun moyen de programmer une séance ni de corriger un lien d'outil : ces
| décisions demandaient un développeur et un déploiement.
|
| Le test qui compte le plus est celui de la rediffusion. Le lot 1 a ajouté les
| trois colonnes et l'écran citoyen qui les lit ; sans le formulaire d'ici, la
| carte aurait annoncé « Rediffusion à venir » pour toujours — une porte posée
| sans poignée.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create([
        'otp_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->admin->assignRole(UserRole::Admin->value);
});

/** Une séance passée, sans rediffusion. */
function seancePassee(): LiveClass
{
    return LiveClass::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Atelier — remonter à la source',
        'description' => 'Trois cas réels.',
        'starts_at' => now()->subWeek(),
        'minutes' => 60,
        'join_url' => 'https://example.test/salle',
        'is_published' => true,
    ]);
}

describe('Live Classes — la poignée que le lot 1 avait laissée manquante', function (): void {
    it('dépose une rediffusion et la met en ligne aussitôt', function (): void {
        /*
         | Le champ de date est FACULTATIF et ne conditionne pas : qui colle un
         | lien et rien d'autre s'attend à ce qu'il serve. La date retarde, elle
         | n'autorise pas.
         */
        $seance = seancePassee();

        expect($seance->rediffusionDisponible())->toBeFalse();

        Livewire::actingAs($this->admin, 'staff')
            ->test(LiveClasses::class)
            ->call('ouvrirEdition', $seance->uuid)
            ->set('lienReplay', 'https://example.test/enregistrement')
            ->set('dureeEnregistrement', '47')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($seance->fresh()->rediffusionDisponible())->toBeTrue()
            ->and($seance->fresh()->recording_minutes)->toBe(47);
    });

    it('respecte une mise en ligne différée', function (): void {
        // Enregistrée le mardi, diffusée le jeudi une fois relue et coupée.
        $seance = seancePassee();

        Livewire::actingAs($this->admin, 'staff')
            ->test(LiveClasses::class)
            ->call('ouvrirEdition', $seance->uuid)
            ->set('lienReplay', 'https://example.test/enregistrement')
            ->set('replayPublieLe', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($seance->fresh()->rediffusionDisponible())->toBeFalse();
    });

    it('refuse un lien de rediffusion en clair', function (): void {
        $seance = seancePassee();

        Livewire::actingAs($this->admin, 'staff')
            ->test(LiveClasses::class)
            ->call('ouvrirEdition', $seance->uuid)
            ->set('lienReplay', 'http://example.test/enregistrement')
            ->call('enregistrer')
            ->assertHasErrors('lienReplay');
    });

    it('refuse un lien de visioconférence en clair', function (): void {
        /*
         | Même exigence que pour les candidatures du Hub : un lien de salle en
         | clair est un lien qu'un réseau partagé peut lire.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(LiveClasses::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Une séance de démonstration')
            ->set('debut', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('lienVisio', 'http://example.test/salle')
            ->call('enregistrer')
            ->assertHasErrors('lienVisio');
    });

    it('journalise la création', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(LiveClasses::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Vérifier une vidéo virale')
            ->set('debut', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('lienVisio', 'https://example.test/salle')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(AuditLog::query()->where('action', 'live_class.create')->exists())->toBeTrue();
    });

    it('refuse de supprimer une séance passée', function (): void {
        /*
         | Ses inscriptions sont la trace de qui comptait venir, et sa
         | rediffusion a pu être partagée. Effacer la ligne laisse tout cela
         | dans le vide. On dépublie, geste réversible.
         */
        $seance = seancePassee();

        expect($this->admin->can('delete', $seance))->toBeFalse();
    });

    it('laisse supprimer une séance à venir', function (): void {
        $seance = seancePassee();
        $seance->forceFill(['starts_at' => now()->addWeek()])->save();

        expect($this->admin->can('delete', $seance->fresh()))->toBeTrue();
    });
});

describe('Répertoire des outils', function (): void {
    it('crée une fiche et la journalise', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Outils::class)
            ->call('ouvrirCreation')
            ->set('titre', 'InVID')
            ->set('descriptif', 'Analyse image par image une vidéo et remonte à sa première publication.')
            ->set('lien', 'https://example.test/invid')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(Tool::query()->where('title', 'InVID')->exists())->toBeTrue()
            ->and(AuditLog::query()->where('action', 'tool.create')->exists())->toBeTrue();
    });

    it('refuse une catégorie hors de la liste fermée', function (): void {
        /*
         | Les catégories sont les CLÉS DE FILTRE de l'écran citoyen. Une valeur
         | inédite — « Images » à côté d'« images » — rendrait la fiche
         | introuvable, sans lever la moindre erreur.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Outils::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un outil')
            ->set('descriptif', 'Une description assez longue pour passer la validation.')
            ->set('lien', 'https://example.test/outil')
            ->set('categorie', 'Catégorie inventée')
            ->call('enregistrer')
            ->assertHasErrors('categorie');
    });

    it('refuse une adresse en clair', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Outils::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Un outil')
            ->set('descriptif', 'Une description assez longue pour passer la validation.')
            ->set('lien', 'http://example.test/outil')
            ->call('enregistrer')
            ->assertHasErrors('lien');
    });

    it('ne change pas le slug quand on corrige le titre', function (): void {
        /*
         | Le slug est la clé de route de la fiche. Le laisser suivre un titre
         | corrigé casserait tout lien déjà partagé, pour un gain nul : personne
         | ne lit le slug d'un outil.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Outils::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Outil mal orthografié')
            ->set('descriptif', 'Une description assez longue pour passer la validation.')
            ->set('lien', 'https://example.test/outil')
            ->call('enregistrer');

        $outil = Tool::query()->firstOrFail();
        $slugInitial = $outil->slug;

        Livewire::actingAs($this->admin, 'staff')
            ->test(Outils::class)
            ->call('ouvrirEdition', $outil->uuid)
            ->set('titre', 'Outil bien orthographié')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($outil->fresh()->slug)->toBe($slugInitial)
            ->and($outil->fresh()->title)->toBe('Outil bien orthographié');
    });
});

describe('les droits', function (): void {
    it('ferme les deux écrans à un modérateur', function (): void {
        /*
         | Le modérateur tient le forum et les signalements. Ni l'Académie ni
         | l'annuaire ne sont de son périmètre — et la matrice le disait déjà.
         */
        $moderateur = User::factory()->create([
            'otp_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $moderateur->assignRole(UserRole::Moderateur->value);

        $this->actingAs($moderateur, 'staff')->get(route('staff.live-classes'))->assertForbidden();
        $this->actingAs($moderateur, 'staff')->get(route('staff.tools'))->assertForbidden();
    });

    it('ouvre les Live Classes à un mentor — c\'est lui qui anime', function (): void {
        $mentor = User::factory()->create([
            'otp_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $mentor->assignRole(UserRole::Mentor->value);

        $this->actingAs($mentor, 'staff')->get(route('staff.live-classes'))->assertOk();
    });
});
