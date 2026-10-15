<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\MessagesContact;
use App\Livewire\Staff\MotsSurveilles;
use App\Livewire\Staff\SignalementsContenu;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\ContentReport;
use App\Models\ModerationKeyword;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Deux files qui se remplissaient sans lecteur
|--------------------------------------------------------------------------
| `content_reports` recevait les signalements du forum depuis des mois, et
| AUCUN écran d'administration ne permettait de les lire — pour aucun type de
| contenu. `contact_messages` n'existait même pas : le formulaire public
| remerciait sans rien envoyer.
|
| C'est la panne la plus discrète qui soit : rien ne casse, rien n'alerte, et
| ce sont les citoyens qui signalent une intox qui en paient le prix.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    $this->moderateur = User::factory()->create();
    $this->moderateur->assignRole(UserRole::Moderateur->value);

    $this->mentor = User::factory()->create();
    $this->mentor->assignRole(UserRole::Mentor->value);
});

function messageDeContact(array $extra = []): ContactMessage
{
    return ContactMessage::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Awa Sossou',
        'email' => 'awa@example.com',
        'message' => "Une vidéo circule sur WhatsApp au sujet d'une coupure d'électricité.",
        'status' => 'new',
    ], $extra));
}

describe('les signalements de contenu', function (): void {
    it('sont ENFIN lisibles quelque part', function (): void {
        // Le test qui justifie l'écran : la file existait, personne ne pouvait
        // l'ouvrir.
        $this->actingAs($this->admin, 'staff')
            ->get(route('staff.content-reports'))
            ->assertOk();
    });

    it('sont ouverts au MODÉRATEUR — c’est son métier', function (): void {
        /*
         | C'est par ces signalements qu'un contenu problématique lui parvient.
         | Les lui fermer ferait de cet écran une pièce d'administration
         | décorative.
         */
        $this->actingAs($this->moderateur, 'staff')
            ->get(route('staff.content-reports'))
            ->assertOk();
    });

    it('restent fermés au mentor', function (): void {
        $this->actingAs($this->mentor, 'staff')
            ->get(route('staff.content-reports'))
            ->assertForbidden();
    });

    it('se tranchent, et la décision est JOURNALISÉE', function (): void {
        /*
         | Un rejet est une décision autant qu'un retrait : c'est lui qui
         | permet, plus tard, de répondre à « pourquoi ce contenu est-il resté
         | en ligne ? » Les deux issues sont donc consignées de la même façon.
         */
        $sujet = Thread::query()->first();
        $signalement = ContentReport::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->moderateur->id,
            'reportable_type' => Thread::class,
            'reportable_id' => $sujet?->id ?? 1,
            'reason' => 'Propos haineux',
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->moderateur, 'staff')
            ->test(SignalementsContenu::class)
            ->call('ouvrir', $signalement->uuid)
            ->set('note', 'Vérifié : le message ne contient pas de propos haineux.')
            ->call('trancher', 'dismissed');

        expect($signalement->refresh()->status->value)->toBe('dismissed')
            ->and($signalement->handled_by)->toBe($this->moderateur->id)
            ->and($signalement->handling_note)->not->toBeNull()
            ->and(AuditLog::where('action', 'content_report.dismissed')->exists())->toBeTrue();
    });

    it("n'offre AUCUNE suppression", function (): void {
        /*
         | Un signalement se traite, il ne s'efface pas. Effacer la trace ferait
         | perdre le fait qu'une décision a été prise, et rendrait impossible de
         | reconnaître un signaleur qui abuse du bouton.
         */
        expect($this->admin->can('content_report.handle'))->toBeTrue()
            ->and($this->admin->can('content_report.delete'))->toBeFalse();
    });
});

describe('les mots surveillés', function (): void {
    it("s'ouvrent au modérateur, c'est son métier", function (): void {
        /*
         | C'est lui qui voit passer les formes que prend l'injure ou la rumeur.
         | Lui interdire d'ajouter un mot le jour où une rumeur monte
         | reviendrait à ne pas la surveiller.
         */
        test()->actingAs(test()->moderateur, 'staff')
            ->get(route('staff.moderation-keywords'))
            ->assertOk();
    });

    it('restent fermés au mentor', function (): void {
        test()->actingAs(test()->mentor, 'staff')
            ->get(route('staff.moderation-keywords'))
            ->assertForbidden();
    });

    it('EXIGENT une raison', function (): void {
        /*
         | Sans elle, personne n'ose retirer une entrée six mois plus tard,
         | faute de savoir ce qu'elle visait. Une liste qu'on n'ose pas élaguer
         | finit par bloquer des choses que plus personne n'assume.
         */
        Livewire::actingAs(test()->admin, 'staff')
            ->test(MotsSurveilles::class)
            ->call('ouvrirFormulaire')
            ->set('terme', 'ordure')
            ->set('note', '')
            ->call('enregistrer')
            ->assertHasErrors('note');

        expect(ModerationKeyword::query()->count())->toBe(0);
    });

    it('refusent un doublon', function (): void {
        // Le même mot avec deux actions différentes produirait un comportement
        // dépendant de l'ordre de lecture, c'est-à-dire imprévisible.
        ModerationKeyword::query()->create([
            'uuid' => (string) Str::uuid(),
            'term' => 'ordure',
            'action' => 'block',
            'note' => 'Injure directe.',
        ]);

        Livewire::actingAs(test()->admin, 'staff')
            ->test(MotsSurveilles::class)
            ->call('ouvrirFormulaire')
            ->set('terme', 'ordure')
            ->set('note', 'Une autre raison tout aussi valable.')
            ->call('enregistrer')
            ->assertHasErrors('terme');
    });

    it('se désactivent au lieu de se supprimer, et le journalisent', function (): void {
        $mot = ModerationKeyword::query()->create([
            'uuid' => (string) Str::uuid(),
            'term' => 'ordure',
            'action' => 'block',
            'note' => 'Injure directe.',
            'is_active' => true,
        ]);

        Livewire::actingAs(test()->admin, 'staff')
            ->test(MotsSurveilles::class)
            ->call('basculer', $mot->uuid);

        expect($mot->refresh()->is_active)->toBeFalse()
            // La ligne demeure, avec sa raison et son compteur.
            ->and(ModerationKeyword::query()->count())->toBe(1)
            ->and(AuditLog::where('action', 'moderation_keyword.toggle')->exists())->toBeTrue();
    });
});

describe('la boîte de contact', function (): void {
    it("s'ouvre à l'administration", function (): void {
        $this->actingAs($this->admin, 'staff')
            ->get(route('staff.contact-messages'))
            ->assertOk();
    });

    it('reste fermée au modérateur', function (): void {
        /*
         | Elle contient des adresses e-mail et des messages libres qui peuvent
         | nommer des personnes. Le périmètre du modérateur est le forum ;
         | l'élargir « puisqu'il modère déjà » confondrait deux responsabilités.
         */
        $this->actingAs($this->moderateur, 'staff')
            ->get(route('staff.contact-messages'))
            ->assertForbidden();
    });

    /*
     | Adaptés au passage de la boîte sous DataTables (14 septembre 2026). La
     | liste n'est plus dans le rendu Livewire : elle arrive par
     | `staff.contact-messages.data`. Même vérification, à l'endroit où les
     | messages se lisent désormais. Détail dans `TableDesMessagesContactTest`.
     */
    it('affiche les messages reçus', function (): void {
        messageDeContact();

        Livewire::actingAs($this->admin, 'staff')
            ->test(MessagesContact::class)
            ->assertSee('data-vdt="messages-contact"', false);

        expect($this->actingAs($this->admin, 'staff')
            ->getJson(route('staff.contact-messages.data'))
            ->assertOk()
            ->json('data.*.expediteur'))->toBe(['Awa Sossou']);
    });

    it('se filtre par statut et par recherche', function (): void {
        messageDeContact();
        messageDeContact(['name' => 'Kossi Adjovi', 'email' => 'kossi@example.com', 'status' => 'handled']);

        $noms = fn (array $parametres): array => $this->actingAs($this->admin, 'staff')
            ->getJson(route('staff.contact-messages.data', $parametres))
            ->assertOk()
            ->json('data.*.expediteur');

        expect($noms(['search' => ['value' => 'Kossi']]))->toBe(['Kossi Adjovi'])
            ->and($noms(['filtres' => ['statut' => 'new']]))->toBe(['Awa Sossou']);
    });

    it('change de statut, et le journalise', function (): void {
        $message = messageDeContact();

        Livewire::actingAs($this->admin, 'staff')
            ->test(MessagesContact::class)
            ->call('ouvrir', $message->uuid)
            ->call('marquer', 'handled');

        expect($message->refresh()->status->value)->toBe('handled')
            ->and($message->handled_by)->toBe($this->admin->id)
            ->and(AuditLog::where('action', 'contact_message.status')->exists())->toBeTrue();
    });

    it('marque « indésirable » SANS effacer', function (): void {
        // Un faux positif se rattrape ; une suppression, non.
        $message = messageDeContact();

        Livewire::actingAs($this->admin, 'staff')
            ->test(MessagesContact::class)
            ->call('ouvrir', $message->uuid)
            ->call('marquer', 'spam');

        expect(ContactMessage::query()->count())->toBe(1)
            ->and($message->refresh()->status->value)->toBe('spam');
    });

    it("n'exécute JAMAIS le HTML d'un message", function (): void {
        /*
         | Le message est du texte libre écrit par un inconnu : c'est le cas
         | type d'injection. Il est rendu avec {{ }} et jamais {!! !!}.
         */
        $message = messageDeContact(['message' => '<script>alert("xss")</script> Voici mon signalement complet.']);

        $html = Livewire::actingAs($this->admin, 'staff')
            ->test(MessagesContact::class)
            ->call('ouvrir', $message->uuid)
            ->html();

        expect($html)->not->toContain('<script>alert')
            ->and($html)->toContain('&lt;script&gt;');
    });
});
