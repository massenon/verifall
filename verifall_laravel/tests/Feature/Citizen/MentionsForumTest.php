<?php

declare(strict_types=1);

use App\Core\Community\Notifications\MentionDansLeForum;
use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\Mentions;
use App\Core\Identity\Services\PreferencesService;
use App\Enums\PreferenceNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Citer quelqu'un dans une discussion
|--------------------------------------------------------------------------
| Une mention doit garantir qu'on ne puisse pas s'en servir pour harceler.
| Trois garde-fous, et chacun répond à un usage précis du dispositif par
| quelqu'un de mal intentionné : un plafond par message, jamais soi-même, et
| une seule notification par personne même si elle est citée trois fois.
|
| Le canal courriel respecte la préférence `ActiviteForum`, qui existait en base
| sans que rien ne la consomme. La brancher à moitié aurait produit des
| courriels que personne ne peut arrêter, sur un produit qui s'engage par
| ailleurs sur le consentement.
*/

beforeEach(function (): void {
    Notification::fake();

    $this->auteur = User::factory()->create([
        'name' => 'Awa Sossou',
        'username' => 'awa-sossou',
        'otp_verified_at' => now(),
    ]);

    $this->cite = User::factory()->create([
        'name' => 'Kossi Adjovi',
        'username' => 'kossi-adjovi',
        'otp_verified_at' => now(),
    ]);
});

describe("l'extraction", function (): void {
    it('repère un identifiant cité', function (): void {
        expect(app(Mentions::class)->extraire('Salut @kossi-adjovi, ton avis ?'))
            ->toBe(['kossi-adjovi']);
    });

    it('ne prend PAS une adresse électronique pour une mention', function (): void {
        /*
         | Dans « écrivez à awa@verifall.com », le `@verifall` n'est pas une
         | mention. Sans ce filtre, toute adresse citée dans un message
         | déclencherait une recherche de compte, et parfois une notification à
         | quelqu'un qui n'a rien à voir.
         */
        expect(app(Mentions::class)->extraire('Écrivez à awa@verifall.com pour signaler.'))
            ->toBe([]);
    });

    it('ne compte quelqu’un qu’une fois, même cité trois fois', function (): void {
        expect(app(Mentions::class)->extraire('@kossi-adjovi @kossi-adjovi encore @kossi-adjovi'))
            ->toBe(['kossi-adjovi']);
    });
});

describe('les destinataires', function (): void {
    it("n'inclut JAMAIS l'auteur lui-même", function (): void {
        // Se mentionner produirait une notification à chaque publication, ce
        // qui n'apprend rien à personne.
        $destinataires = app(Mentions::class)
            ->destinataires('@awa-sossou @kossi-adjovi', $this->auteur);

        expect($destinataires->pluck('username')->all())->toBe(['kossi-adjovi']);
    });

    it('écarte un compte dont l’adresse n’est pas vérifiée', function (): void {
        /*
         | Lui écrire reviendrait à envoyer un courriel à une adresse que
         | personne n'a prouvé posséder.
         */
        User::factory()->create(['username' => 'jamais-verifie', 'otp_verified_at' => null]);

        expect(app(Mentions::class)->destinataires('@jamais-verifie', $this->auteur))
            ->toHaveCount(0);
    });

    it('plafonne le nombre de personnes prévenues', function (): void {
        /*
         | Sans ce plafond, un seul message citant deux cents personnes devient
         | un envoi de masse déguisé.
         */
        $texte = '';

        for ($i = 1; $i <= Mentions::MAXIMUM_PAR_MESSAGE + 3; $i++) {
            User::factory()->create(['username' => 'temoin-'.$i, 'otp_verified_at' => now()]);
            $texte .= ' @temoin-'.$i;
        }

        expect(app(Mentions::class)->destinataires($texte, $this->auteur))
            ->toHaveCount(Mentions::MAXIMUM_PAR_MESSAGE);
    });
});

describe("l'envoi", function (): void {
    it('prévient la personne citée dans un sujet', function (): void {
        app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Une question sur une vidéo qui circule',
            'Bonjour @kossi-adjovi, peux-tu regarder cette vidéo qui circule sur WhatsApp ?',
        );

        Notification::assertSentTo($this->cite, MentionDansLeForum::class);
    });

    it('ENVOIE un courriel quand la préférence est active', function (): void {
        app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Une question sur une vidéo qui circule',
            'Bonjour @kossi-adjovi, peux-tu regarder cette vidéo qui circule ?',
        );

        Notification::assertSentTo(
            $this->cite,
            MentionDansLeForum::class,
            function (MentionDansLeForum $notification, array $canaux): bool {
                return in_array('mail', $canaux, true) && in_array('database', $canaux, true);
            },
        );
    });

    it("N'ENVOIE PAS de courriel quand la préférence est éteinte", function (): void {
        /*
         | LE test qui justifie tout le dispositif. La préférence existait en
         | base et ne commandait rien : un interrupteur qui n'éteint rien est
         | pire que pas d'interrupteur, parce qu'il donne le sentiment d'avoir
         | choisi.
         |
         | La cloche, elle, reste alimentée : elle ne sollicite personne, elle
         | attend qu'on vienne.
         */
        app(PreferencesService::class)
            ->definir($this->cite, PreferenceNotification::ActiviteForum, false);

        app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Une question sur une vidéo qui circule',
            'Bonjour @kossi-adjovi, peux-tu regarder cette vidéo qui circule ?',
        );

        Notification::assertSentTo(
            $this->cite,
            MentionDansLeForum::class,
            function (MentionDansLeForum $notification, array $canaux): bool {
                return ! in_array('mail', $canaux, true) && in_array('database', $canaux, true);
            },
        );
    });

    it('ne prévient personne quand le message ne cite personne', function (): void {
        app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Une question sur une vidéo qui circule',
            'Une vidéo circule sur WhatsApp et je voudrais la faire vérifier.',
        );

        Notification::assertNothingSent();
    });

    it('mène vers la discussion, pas vers une page vide', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Une question sur une vidéo qui circule',
            'Bonjour @kossi-adjovi, ton avis sur cette vidéo ?',
        );

        Notification::assertSentTo(
            $this->cite,
            MentionDansLeForum::class,
            fn (MentionDansLeForum $notification): bool => $notification->lien() === route('citizen.thread', $sujet->slug),
        );
    });
});
