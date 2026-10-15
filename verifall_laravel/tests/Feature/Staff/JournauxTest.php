<?php

declare(strict_types=1);

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\SecurityLogService;
use App\Enums\UserRole;
use App\Livewire\Staff\AlertesIntrusion;
use App\Livewire\Staff\JournalAudit;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Journaux du back-office — audit et alertes
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);
});

/** Session staff prête à l'emploi. */
function enTantQueStaff(User $user)
{
    return test()->actingAs($user, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp]);
}

describe('accès', function (): void {
    it('refuse les deux journaux sans session staff', function (string $route): void {
        $this->get(route($route))->assertRedirect();
    })->with(['staff.audit', 'staff.alerts']);

    it('refuse un Mentor, qui n\'a pas les permissions de sécurité', function (string $route): void {
        $mentor = User::factory()->create(['otp_verified_at' => now()]);
        $mentor->assignRole(UserRole::Mentor->value);

        // La matrice ne lui accorde ni security.logs ni security.alerts.
        enTantQueStaff($mentor)->get(route($route))->assertForbidden();
    })->with(['staff.audit', 'staff.alerts']);

    it('autorise un administrateur', function (string $route): void {
        enTantQueStaff($this->admin)->get(route($route))->assertOk();
    })->with(['staff.audit', 'staff.alerts']);
});

describe('journal d\'audit', function (): void {
    it('affiche les entrées les plus récentes en premier', function (): void {
        $audit = app(AuditLogService::class);
        $audit->record('create', 'course', $this->admin, cibleLibelle: 'Ancien cours');
        $audit->record('delete', 'course', $this->admin, cibleLibelle: 'Cours supprimé');

        enTantQueStaff($this->admin);

        Livewire::test(JournalAudit::class)
            ->assertSeeInOrder(['Cours supprimé', 'Ancien cours']);
    });

    it('filtre par module', function (): void {
        $audit = app(AuditLogService::class);
        $audit->record('create', 'course', $this->admin, cibleLibelle: 'Un cours');
        $audit->record('update', 'user', $this->admin, cibleLibelle: 'Un citoyen');

        enTantQueStaff($this->admin);

        Livewire::test(JournalAudit::class)
            ->set('filtreModule', 'course')
            ->assertSee('Un cours')
            ->assertDontSee('Un citoyen');
    });

    it('recherche par acteur ou par cible', function (): void {
        app(AuditLogService::class)->record('update', 'course', $this->admin, cibleLibelle: 'OSINT Fondamentaux');

        enTantQueStaff($this->admin);

        Livewire::test(JournalAudit::class)
            ->set('recherche', 'OSINT')
            ->assertSee('OSINT Fondamentaux');
    });

    it('compte les actions destructives séparément', function (): void {
        $audit = app(AuditLogService::class);
        $audit->record('create', 'course', $this->admin);
        $audit->record('delete', 'course', $this->admin);
        $audit->record('role_change', 'user', $this->admin);

        enTantQueStaff($this->admin);

        // Ce sont elles qu'on cherche en priorité lors d'un incident.
        $composant = Livewire::test(JournalAudit::class);

        expect($composant->instance()->indicateurs['destructives'])->toBe(2)
            ->and($composant->instance()->indicateurs['evenements'])->toBe(3);
    });

    it('n\'expose aucune action de modification ou de suppression', function (): void {
        app(AuditLogService::class)->record('create', 'course', $this->admin);

        $html = enTantQueStaff($this->admin)->get(route('staff.audit'))->content();

        // Un journal d'audit modifiable ne prouverait rien.
        expect($html)->not->toContain('wire:click="supprimer')
            ->and($html)->not->toContain('x-delete-modal');
    });

    it('journalise l\'export lui-même', function (): void {
        app(AuditLogService::class)->record('create', 'course', $this->admin);

        enTantQueStaff($this->admin);

        Livewire::test(JournalAudit::class)->call('exporterCsv');

        // Extraire des données d'audit est une action sensible : savoir qui
        // l'a fait fait partie de l'audit.
        expect(AuditLog::where('action', 'data_export')->exists())->toBeTrue();
    });

    it('masque les champs sensibles dans le diff', function (): void {
        app(AuditLogService::class)->record(
            'update', 'user', $this->admin,
            avant: ['email' => 'a@b.c', 'password' => 'hachage-secret'],
            apres: ['email' => 'd@e.f', 'password' => 'autre-hachage'],
        );

        $trace = AuditLog::where('action', 'update')->first();

        // Un journal exporté ne doit pas devenir une cible plus intéressante
        // que la table users elle-même.
        expect($trace->before['password'])->toBe('[masqué]')
            ->and($trace->after['password'])->toBe('[masqué]')
            ->and($trace->before['email'])->toBe('a@b.c');
    });

    it('ne conserve dans le diff que ce qui a changé', function (): void {
        $audit = app(AuditLogService::class);
        $cible = User::factory()->create();

        $audit->recordDiff('user', $this->admin, $cible,
            ['name' => 'Adderly', 'profession' => 'Journaliste'],
            ['name' => 'Adderly H.', 'profession' => 'Journaliste'],
        );

        $trace = AuditLog::where('action', 'update')->latest('id')->first();

        // Journaliser l'objet entier noierait le changement dans le bruit.
        expect(array_keys($trace->after))->toBe(['name']);
    });
});

describe('alertes intrusion', function (): void {
    it('affiche les événements de sécurité', function (): void {
        app(SecurityLogService::class)->loginEchoue('adderly@verifall.com', 3);

        enTantQueStaff($this->admin);

        Livewire::test(AlertesIntrusion::class)->assertSee('Connexion refusée');
    });

    it('n\'affiche jamais une identité en clair', function (): void {
        app(SecurityLogService::class)->loginEchoue('adderly@verifall.com', 3);

        $html = enTantQueStaff($this->admin)->get(route('staff.alerts'))->content();

        // Une page de surveillance ne doit pas devenir un annuaire.
        expect($html)->not->toContain('adderly@verifall.com');
    });

    it('filtre par criticité', function (): void {
        $journal = app(SecurityLogService::class);
        $journal->loginEchoue('a@verifall.com', 1);              // info
        $journal->record('otp_bruteforce', 'critical', 'b@verifall.com');

        enTantQueStaff($this->admin);

        $html = Livewire::test(AlertesIntrusion::class)
            ->set('filtreSeverite', 'critical')
            ->html();

        // On compte les LIGNES, pas les occurrences du libellé : celui-ci
        // figure aussi dans la liste déroulante des filtres, où sa présence
        // est normale. Un assertDontSee naïf échouait donc à tort.
        expect(substr_count($html, 'data-row-el'))->toBe(1)
            ->and($html)->toContain('Forçage de code');
    });

    it('compte les sources distinctes', function (): void {
        app(SecurityLogService::class)->loginEchoue('a@verifall.com', 1);

        enTantQueStaff($this->admin);

        // Distinguer une salve d'une seule source d'une campagne répartie :
        // ce ne sont pas les mêmes contre-mesures.
        expect(Livewire::test(AlertesIntrusion::class)->instance()->indicateurs['empreintes'])
            ->toBeGreaterThanOrEqual(1);
    });

    it('traduit les codes techniques en libellés lisibles', function (): void {
        enTantQueStaff($this->admin);

        $composant = Livewire::test(AlertesIntrusion::class)->instance();

        expect($composant->libelle('login_failed'))->toBe('Connexion refusée')
            ->and($composant->libelle('otp_bruteforce'))->toBe('Forçage de code')
            // Un code inconnu reste affichable plutôt que de casser la page.
            ->and($composant->libelle('evenement_inconnu'))->toBe('Evenement inconnu');
    });
});

describe('déconnexion staff', function (): void {
    it('refuse la méthode GET', function (): void {
        enTantQueStaff($this->admin)->get('/admin/deconnexion')->assertStatus(405);
    });

    it('déconnecte et inscrit la sortie au journal d\'audit', function (): void {
        enTantQueStaff($this->admin)
            ->post(route('staff.logout'))
            ->assertRedirect(route('staff.login'));

        expect(auth()->guard('staff')->check())->toBeFalse()
            // Sans cette trace, l'historique d'une session d'administration
            // s'arrête sans qu'on sache quand elle s'est close.
            ->and(AuditLog::where('action', 'logout')->exists())->toBeTrue();
    });
});

describe('visite guidée', function (): void {
    it('marque la visite comme vue', function (): void {
        expect($this->admin->tour_done_at)->toBeNull();

        $this->actingAs($this->admin)->post(route('tour.done'))->assertOk();

        expect($this->admin->fresh()->tour_done_at)->not->toBeNull();
    });

    it('est idempotente', function (): void {
        $this->actingAs($this->admin)->post(route('tour.done'));
        $premier = $this->admin->fresh()->tour_done_at;

        $this->travel(1)->hour();
        $this->actingAs($this->admin)->post(route('tour.done'));

        // Rejouée, elle ne fait rien de plus : c'est un confort, pas une
        // transaction.
        expect($this->admin->fresh()->tour_done_at->timestamp)->toBe($premier->timestamp);
    });

    it('peut être relancée', function (): void {
        $this->actingAs($this->admin)->post(route('tour.done'));
        $this->actingAs($this->admin)->post(route('tour.restart'))->assertOk();

        expect($this->admin->fresh()->tour_done_at)->toBeNull();
    });

    it('exige une session', function (): void {
        $this->post(route('tour.done'))->assertRedirect();
    });
});
