<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\MessagesContact;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La table DataTables « Messages reçus », côté serveur (14 sept. 2026)
|--------------------------------------------------------------------------
|
| Clone du pilote `TableDesUtilisateursTest`. La boîte de contact contient des
| adresses et des messages libres écrits par des inconnus : fermée au
| modérateur et au mentor, sans export, et ce qui en sort est tronqué, en
| texte, sans téléphone, organisation, empreinte d'IP ni note interne.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

function tableMessagesContact(User $membre, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')
        ->getJson(route('staff.contact-messages.data', $parametres));
}

function messagePourTable(array $attributs = []): ContactMessage
{
    return ContactMessage::query()->forceCreate(array_merge([
        'uuid' => (string) Str::uuid(),
        'kind' => 'contact',
        'name' => 'Awa Sossou',
        'email' => 'awa'.Str::random(6).'@example.test',
        'phone' => '+22990000000',
        'organisation' => 'ORGANISATION-PRIVEE',
        'message' => "Une vidéo circule sur WhatsApp au sujet d'une coupure d'électricité.",
        'status' => 'new',
        'ip_hash' => 'EMPREINTE-IP-'.Str::random(20),
        'handling_note' => 'NOTE-INTERNE',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ], $attributs));
}

// ═══════════════════════════════════════════════════════════════ la porte

it('refuse la source JSON au mentor et au modérateur', function (): void {
    foreach ([UserRole::Mentor, UserRole::Moderateur] as $role) {
        $membre = User::factory()->actif()->create();
        $membre->assignRole($role->value);

        tableMessagesContact($membre)->assertForbidden();
    }
});

it('refuse la source JSON sans session du personnel', function (): void {
    $this->getJson(route('staff.contact-messages.data'))->assertUnauthorized();
});

it('porte exactement la même garde de route que l’écran', function (): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde('staff.contact-messages.data'))->toBe($garde('staff.contact-messages'))
        ->and($garde('staff.contact-messages.data'))->not->toBe([]);
});

it('refuse l’export, faute de permission qui le prévoie', function (): void {
    tableMessagesContact($this->admin, ['export' => 1])->assertForbidden();
});

// ═══════════════════════════════════════════════════════════ ce qui sort

it('ne renvoie ni id, ni téléphone, ni organisation, ni empreinte d’IP, ni note interne', function (): void {
    messagePourTable(['email' => 'awa@example.test']);

    $reponse = tableMessagesContact($this->admin, ['draw' => 7])->assertOk()->assertJsonPath('draw', 7);

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($reponse->json('data.0')))->toEqualCanonicalizing([
            'uuid', 'nature', 'natureLibelle', 'natureBadge', 'expediteur', 'email', 'extrait',
            'statut', 'statutLibelle', 'statutBadge', 'recuLe', 'recuLeTexte', 'actions',
        ])
        ->and($reponse->json('data.0.email'))->toBe('awa@example.test');

    expect($reponse->getContent())
        ->not->toContain('22990000000')
        ->not->toContain('ORGANISATION-PRIVEE')
        ->not->toContain('EMPREINTE-IP')
        ->not->toContain('NOTE-INTERNE')
        ->not->toContain('"id"');
});

it('tronque le message côté serveur et le rend en texte brut', function (): void {
    messagePourTable(['message' => '<script>alert("xss")</script> '.str_repeat('Voici mon signalement. ', 30)]);

    $extrait = tableMessagesContact($this->admin)->json('data.0.extrait');

    // Le texte part tel quel — c'est le navigateur qui l'échappe —, mais court.
    expect(mb_strlen($extrait))->toBeLessThanOrEqual(113)
        ->and($extrait)->toStartWith('<script>');
});

// ══════════════════════════════════════════════════ le jeu entier, borné

it('recherche dans TOUT le jeu, sans tenir compte de la casse — nom, adresse, organisation, message', function (): void {
    for ($i = 0; $i < 30; $i++) {
        messagePourTable(['created_at' => now()->subMinutes($i)]);
    }

    $cible = messagePourTable(['name' => 'Kossi Adjovi', 'organisation' => 'Radio Tokpa', 'created_at' => now()->subYears(2)]);

    $parNom = tableMessagesContact($this->admin, ['search' => ['value' => 'KOSSI'], 'length' => 10]);
    $parNom->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('recordsTotal', 31);
    expect($parNom->json('data.0.uuid'))->toBe($cible->uuid);

    // L'organisation est interrogeable sans être renvoyée.
    expect(tableMessagesContact($this->admin, ['search' => ['value' => 'radio tokpa']])->json('data.*.uuid'))->toBe([$cible->uuid]);
});

it('pagine côté SQL et plafonne la longueur à 100', function (): void {
    for ($i = 0; $i < 110; $i++) {
        messagePourTable();
    }

    expect(tableMessagesContact($this->admin, ['start' => 100, 'length' => 10])->json('data'))->toHaveCount(10)
        ->and(tableMessagesContact($this->admin, ['length' => 1000000])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_MAXIMUM)
        ->and(tableMessagesContact($this->admin, ['length' => -1])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_PAR_DEFAUT);
});

it('trie sur une colonne de la liste blanche, et ignore le reste', function (): void {
    messagePourTable(['name' => 'Zoé']);
    messagePourTable(['name' => 'Albert']);

    expect(tableMessagesContact($this->admin, ['order' => [['name' => 'expediteur', 'dir' => 'asc']]])->json('data.0.expediteur'))->toBe('Albert');

    foreach (['phone', 'ip_hash', 'handling_note', 'email', 'id; DROP TABLE contact_messages'] as $colonne) {
        tableMessagesContact($this->admin, [
            'columns' => [['data' => $colonne]],
            'order' => [['column' => 0, 'dir' => 'asc'], ['name' => $colonne, 'dir' => 'desc']],
        ])->assertOk()->assertJsonPath('recordsTotal', 2);
    }
});

it('applique les filtres rapides — statut, nature, période — et ignore les valeurs inconnues', function (): void {
    $traite = messagePourTable(['status' => 'handled', 'created_at' => '2026-03-10 12:00:00']);
    $don = messagePourTable(['kind' => 'don', 'created_at' => '2026-05-01 12:00:00']);

    $uuids = static fn (array $filtres): array => tableMessagesContact(test()->admin, ['filtres' => $filtres])->json('data.*.uuid');

    expect($uuids(['statut' => 'handled']))->toBe([$traite->uuid])
        ->and($uuids(['nature' => 'don']))->toBe([$don->uuid])
        ->and($uuids(['recu_du' => '2026-03-01', 'recu_au' => '2026-03-31']))->toBe([$traite->uuid])
        ->and($uuids(['statut' => 'supprime', 'nature' => 'autre', 'recu_au' => 'hier']))->toHaveCount(2);
});

it('ne multiplie pas les requêtes avec la taille de la page', function (): void {
    $compter = function (int $longueur): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        tableMessagesContact($this->admin, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    for ($i = 0; $i < 40; $i++) {
        messagePourTable();
    }

    $compter(5);

    expect($compter(40))->toBe($compter(5));
});

// ══════════════════════════════════════════════════════════ le pont Livewire

it('demande à la table de se recharger après un changement de statut, toujours journalisé', function (): void {
    $message = messagePourTable();

    Livewire::actingAs($this->admin, 'staff')
        ->test(MessagesContact::class)
        ->call('ouvrir', $message->uuid)
        ->call('marquer', 'in_progress')
        ->assertDispatched(MessagesContact::EVENEMENT_TABLE_A_RECHARGER);

    expect($message->refresh()->status->value)->toBe('in_progress');
    $this->assertDatabaseHas('audit_logs', ['action' => 'contact_message.status', 'actor_id' => $this->admin->id]);
});

it('sert l’écran avec la zone DataTables DANS la racine, et aucune adresse dans le HTML', function (): void {
    $message = messagePourTable(['email' => 'pas-dans-la-page@example.test']);

    $this->actingAs($this->admin, 'staff')
        ->get(route('staff.contact-messages', ['q' => 'awa', 'statut' => 'new', 'nature' => 'don']))
        ->assertOk()
        ->assertSee('data-vdt="messages-contact"', escape: false)
        ->assertSee(route('staff.contact-messages.data'), escape: false)
        ->assertSee('id="contact-q"', escape: false)
        ->assertSee('value="awa"', escape: false)
        ->assertSee('<option value="new" selected', escape: false)
        ->assertSee('<option value="don" selected', escape: false)
        ->assertDontSee('pas-dans-la-page@example.test')
        ->assertDontSee('data-vdt-export', escape: false);

    // Le panneau de lecture reste rendu par Livewire, dans la racine.
    $html = Livewire::actingAs($this->admin, 'staff')
        ->test(MessagesContact::class)
        ->call('ouvrir', $message->uuid)
        ->html();

    expect($html)->toContain('id="message-ouvert"')
        ->and($html)->toContain('pas-dans-la-page@example.test')
        ->and(strpos($html, 'id="message-ouvert"'))->toBeLessThan(strpos($html, 'data-vdt="messages-contact"'));
});
