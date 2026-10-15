<?php

declare(strict_types=1);

use App\Core\Marketing\Services\BoiteDeContact;
use App\Mail\MessageDeContactRecu;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Le formulaire de contact
|--------------------------------------------------------------------------
| Il était FACTICE. Le prototype le portait avec `data-vf-form`, dont le
| gestionnaire appelle `ev.preventDefault()`, attend 900 ms et affiche
| « Message envoyé ! Notre équipe vous répondra très vite. » Aucune `action`,
| aucun `name` sur les champs, et aucune route POST côté serveur.
|
| Quelqu'un écrivait, était remercié, et son message n'arrivait nulle part —
| sur un site où l'on signale des intox. C'est pire que l'absence de
| formulaire : rien ne signale la panne, ni à la personne, ni à l'équipe.
*/

beforeEach(function (): void {
    Mail::fake();
});

function envoiValide(array $extra = []): array
{
    return array_merge([
        'name' => 'Awa Sossou',
        'email' => 'awa@example.com',
        'message' => "Une vidéo circule sur WhatsApp au sujet d'une coupure d'électricité.",
        // Le formulaire pose cet horodatage au rendu. On le recule pour
        // franchir le délai minimal — c'est ce que fait un humain qui écrit.
        'rendu_a' => (string) (time() - 30),
    ], $extra);
}

describe('la page publique', function (): void {
    it('poste vers une route qui EXISTE', function (): void {
        /*
         | Les trois conditions ensemble. La troisième compte autant que les
         | deux autres : remettre `data-vf-form` ferait ravaler la soumission
         | par le script, le message de succès s'afficherait encore, et plus
         | rien ne signalerait la panne.
         */
        $html = (string) $this->get(route('public.contact'))->assertOk()->getContent();

        expect($html)->toContain('action="'.route('public.contact.send').'"')
            ->and($html)->toContain('name="message"')
            ->and($html)->not->toContain('data-vf-form-success="Message envoyé');
    });

    it('porte un piège à robots invisible mais RÉEL', function (): void {
        /*
         | Masqué par le style et non par `type="hidden"` : les robots ignorent
         | les champs cachés, mais remplissent volontiers ce qui ressemble à un
         | champ ordinaire. Il s'appelle `website` — un robot qui reconnaît
         | « honeypot » l'évite.
         */
        $html = (string) $this->get(route('public.contact'))->getContent();

        expect($html)->toContain('name="website"')
            ->and($html)->not->toContain('name="honeypot"')
            ->and($html)->toContain('name="rendu_a"');
    });
});

describe("l'envoi", function (): void {
    it('enregistre le message ET prévient l’équipe', function (): void {
        $this->post(route('public.contact.send'), envoiValide())
            ->assertRedirect(route('public.contact'))
            ->assertSessionHas('contact');

        $message = ContactMessage::query()->first();

        expect($message)->not->toBeNull()
            ->and($message->name)->toBe('Awa Sossou')
            ->and($message->email)->toBe('awa@example.com')
            ->and($message->status->value)->toBe('new');
    });

    it('ne perd RIEN quand le serveur de courriel refuse', function (): void {
        /*
         | L'ordre des opérations n'est pas indifférent : on écrit d'abord, on
         | prévient ensuite. L'ordre inverse perdrait le message à la première
         | panne SMTP, en silence.
         */
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP indisponible'));

        $this->post(route('public.contact.send'), envoiValide())
            ->assertRedirect(route('public.contact'));

        expect(ContactMessage::query()->count())->toBe(1);
    });

    it("n'enregistre JAMAIS l'adresse IP en clair", function (): void {
        // Elle ne sert qu'à reconnaître une rafale, ce qu'une empreinte permet
        // aussi bien. En clair, ce serait une donnée personnelle conservée
        // sans nécessité.
        $this->post(route('public.contact.send'), envoiValide());

        $message = ContactMessage::query()->first();

        expect($message->ip_hash)->not->toBeNull()
            ->and($message->ip_hash)->not->toContain('127.0.0.1');
    });

    it('refuse un message trop court, et le dit', function (): void {
        $this->post(route('public.contact.send'), envoiValide(['message' => 'Salut']))
            ->assertSessionHasErrors('message');

        expect(ContactMessage::query()->count())->toBe(0);
    });
});

describe('le courriel de notification', function (): void {
    it('se REND réellement, sans erreur de gabarit', function (): void {
        /*
         | `Mail::fake()` ne rend jamais le gabarit : une balise Blade fautive
         | ou une route inconnue n'y produirait aucune erreur en test, et
         | échouerait au premier envoi réel — c'est-à-dire le jour où quelqu'un
         | écrit vraiment.
         |
         | Ce test-là le rend pour de bon.
         */
        $message = ContactMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Awa Sossou',
            'email' => 'awa@example.com',
            'organisation' => 'RTB Cotonou',
            'phone' => '+229 00 00 00 00',
            'message' => "Une vidéo circule au sujet d'une coupure d'électricité.",
            'status' => 'new',
        ]);

        $rendu = (new MessageDeContactRecu($message))->render();

        expect($rendu)->toContain('Awa Sossou')
            ->and($rendu)->toContain('awa@example.com')
            ->and($rendu)->toContain('RTB Cotonou')
            // Le lien vers le back-office : c'est lui qui appelle une route, et
            // donc lui qui casserait si elle changeait de nom.
            ->and($rendu)->toContain(route('staff.contact-messages'));
    });

    it("répond à l'expéditeur, pas à la plateforme", function (): void {
        /*
         | `Reply-To` porte l'adresse de celui qui a écrit : répondre depuis son
         | client mail suffit, sans passer par le back-office. Sans cet en-tête,
         | la réponse partirait vers la boîte de contact — c'est-à-dire vers
         | soi-même.
         */
        $message = ContactMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Awa Sossou',
            'email' => 'awa@example.com',
            'message' => 'Un message assez long pour passer la validation.',
            'status' => 'new',
        ]);

        $enveloppe = (new MessageDeContactRecu($message))->envelope();

        expect($enveloppe->replyTo[0]->address)->toBe('awa@example.com')
            ->and($enveloppe->subject)->toContain('Awa Sossou');
    });
});

describe('les robots', function (): void {
    it('sont écartés par le piège — et REMERCIÉS quand même', function (): void {
        /*
         | La réponse est identique à celle d'un envoi accepté. Ce n'est pas de
         | la négligence : un robot à qui l'on répond « rejeté » apprend quoi
         | changer, un robot remercié n'apprend rien.
         */
        $this->post(route('public.contact.send'), envoiValide(['website' => 'http://spam.example']))
            ->assertRedirect(route('public.contact'))
            ->assertSessionHas('contact');

        expect(ContactMessage::query()->count())->toBe(0);
        Mail::assertNothingSent();
    });

    it('sont écartés quand ils postent plus vite qu’un humain n’écrit', function (): void {
        // Un robot poste en quelques centaines de millisecondes ; personne ne
        // rédige un message en moins de trois secondes.
        $this->post(route('public.contact.send'), envoiValide(['rendu_a' => (string) time()]))
            ->assertRedirect(route('public.contact'));

        expect(ContactMessage::query()->count())->toBe(0);
    });

    it("laisse passer quand l'horodatage manque", function (): void {
        /*
         | Un navigateur ancien, un cache agressif ou un onglet dormant
         | suffisent à le perdre. Bloquer ici écarterait des humains — et le
         | piège, lui, reste actif.
         */
        $donnees = envoiValide();
        unset($donnees['rendu_a']);

        $this->post(route('public.contact.send'), $donnees);

        expect(ContactMessage::query()->count())->toBe(1);
    });
});

describe('le formulaire de soutien', function (): void {
    it("n'encaisse RIEN, et le dit", function (): void {
        /*
         | Aucun prestataire de paiement n'est en service : le pilote Mobile
         | Money est en mode simulé, aucune clé Stripe ni PayPal n'est
         | configurée. Un formulaire qui prendrait un montant en donnant
         | l'apparence d'un paiement mentirait deux fois : sur ce qui se passe,
         | et sur ce qui est reçu.
         */
        $html = (string) test()->get(route('public.funding'))->assertOk()->getContent();

        expect($html)->toContain('action="'.route('public.funding.support').'"')
            ->and($html)->toContain('encaisse aucun paiement');
    });

    it('enregistre une INTENTION, distincte du courrier ordinaire', function (): void {
        test()->post(route('public.funding.support'), [
            'name' => 'Awa Sossou',
            'email' => 'awa@example.com',
            'donation_amount' => '25 000 FCFA',
            'donation_recurrence' => 'mensuel',
            'rendu_a' => (string) (time() - 30),
        ])->assertRedirect(route('public.funding'))->assertSessionHas('soutien');

        $message = ContactMessage::query()->first();

        expect($message)->not->toBeNull()
            // La nature est ce qui empêche la demande de se noyer dans la file.
            ->and($message->kind->value)->toBe('don')
            ->and($message->donation_amount)->toBe('25 000 FCFA');
    });

    it('accepte un soutien en nature, pas seulement une somme', function (): void {
        /*
         | Le montant est du TEXTE LIBRE. Quelqu'un écrit « selon mes moyens »
         | ou « du temps de formation » : contraindre le champ à un nombre
         | écarterait ces réponses, qui valent la première.
         */
        test()->post(route('public.funding.support'), [
            'name' => 'Kossi Adjovi',
            'email' => 'kossi@example.com',
            'donation_amount' => 'du temps de formation',
            'rendu_a' => (string) (time() - 30),
        ])->assertSessionHasNoErrors();

        expect(ContactMessage::query()->first()->donation_amount)->toBe('du temps de formation');
    });

    it('accepte un soutien SANS message', function (): void {
        // Un soutien sans mot reste un soutien : son absence ne doit pas faire
        // échouer l'envoi.
        test()->post(route('public.funding.support'), [
            'name' => 'Rachida Baba',
            'email' => 'rachida@example.com',
            'rendu_a' => (string) (time() - 30),
        ])->assertSessionHasNoErrors();

        expect(ContactMessage::query()->count())->toBe(1);
    });

    it('applique le MÊME piège à robots que le contact', function (): void {
        test()->post(route('public.funding.support'), [
            'name' => 'Robot',
            'email' => 'robot@example.com',
            'website' => 'http://spam.example',
            'rendu_a' => (string) (time() - 30),
        ])->assertRedirect(route('public.funding'))->assertSessionHas('soutien');

        expect(ContactMessage::query()->count())->toBe(0);
    });
});

describe('la rafale', function (): void {
    it('se coupe au-delà de cinq messages depuis la même IP', function (): void {
        /*
         | Second rideau derrière `throttle` : celui-ci compte les messages
         | RÉELLEMENT écrits, donc il survit à un vidage de cookies là où la
         | limite de cadence de Laravel s'appuie aussi sur la session.
         */
        $boite = app(BoiteDeContact::class);

        for ($i = 0; $i < 5; $i++) {
            $boite->recevoir([
                'name' => 'Robot '.$i,
                'email' => 'robot'.$i.'@example.com',
                'message' => 'Message automatique numéro '.$i.' envoyé en rafale.',
            ], '203.0.113.7');
        }

        expect($boite->tropDeMessagesRecents('203.0.113.7'))->toBeTrue()
            // Une autre adresse n'est pas pénalisée par la précédente.
            ->and($boite->tropDeMessagesRecents('203.0.113.8'))->toBeFalse();
    });
});
