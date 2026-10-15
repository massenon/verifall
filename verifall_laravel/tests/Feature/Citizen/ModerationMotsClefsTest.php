<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\FiltreDeMots;
use App\Core\Community\Services\MessageBloque;
use App\Enums\ActionMotClef;
use App\Models\ContentReport;
use App\Models\ModerationKeyword;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| La modération par mots surveillés
|--------------------------------------------------------------------------
| Deux actions, et la distinction est le cœur du dispositif.
|
| Bloquer tout ce qui inquiète transformerait le forum en mur. Sur un site de
| fact-checking, les mots d'une rumeur sont précisément ceux qu'on vient
| signaler : quelqu'un qui écrit « on dit que le vaccin rend stérile » ne
| propage pas cette idée, il demande qu'on la vérifie. Le bloquer serait
| refuser le service au moment même où il sert.
*/

beforeEach(function (): void {
    $this->auteur = User::factory()->create(['otp_verified_at' => now()]);
    app(FiltreDeMots::class)->oublier();
});

function motSurveille(string $terme, ActionMotClef $action): ModerationKeyword
{
    $mot = ModerationKeyword::query()->create([
        'uuid' => (string) Str::uuid(),
        'term' => $terme,
        'action' => $action->value,
        'is_active' => true,
    ]);

    app(FiltreDeMots::class)->oublier();

    return $mot;
}

describe('la comparaison', function (): void {
    it('ignore la casse ET les accents', function (): void {
        /*
         | Sans cela, un mot surveillé s'esquive en retirant une cédille, ce que
         | fait n'importe qui de mal intentionné au bout de deux essais.
         */
        motSurveille('éscroc', ActionMotClef::Signaler);

        expect(app(FiltreDeMots::class)->examiner('Cet ESCROC ment'))->not->toBeNull();
    });

    it("n'attrape PAS un mot qui en contient un autre", function (): void {
        /*
         | Une recherche de sous-chaîne brute ferait qu'un mot surveillé
         | attrape tous les termes qui le contiennent, et la liste deviendrait
         | plus dangereuse que ce qu'elle surveille.
         */
        motSurveille('con', ActionMotClef::Bloquer);

        expect(app(FiltreDeMots::class)->examiner('Cette information est confirmée'))->toBeNull()
            ->and(app(FiltreDeMots::class)->examiner('Espèce de con'))->not->toBeNull();
    });

    it('laisse passer un message ordinaire sans rien déclencher', function (): void {
        motSurveille('injure', ActionMotClef::Bloquer);

        expect(app(FiltreDeMots::class)->examiner('Bonjour, une vidéo circule sur WhatsApp.'))->toBeNull();
    });

    it('retient le plus SÉVÈRE quand deux mots se déclenchent', function (): void {
        // L'inverse laisserait passer un mot bloquant au prétexte qu'un mot
        // plus doux figure aussi dans la phrase.
        motSurveille('interdit', ActionMotClef::Bloquer);
        motSurveille('rumeur', ActionMotClef::Signaler);

        $verdict = app(FiltreDeMots::class)->examiner('Cette rumeur est interdite ? Non : interdit.');

        expect($verdict['action'])->toBe(ActionMotClef::Bloquer);
    });

    it('ignore un terme qui se réduit à de la ponctuation', function (): void {
        // Normalisé, il deviendrait une chaîne vide et attraperait TOUT.
        motSurveille('!!!', ActionMotClef::Bloquer);

        expect(app(FiltreDeMots::class)->examiner('Un message parfaitement anodin'))->toBeNull();
    });
});

describe('à la publication', function (): void {
    it('REFUSE un sujet qui touche un mot bloquant', function (): void {
        motSurveille('ordure', ActionMotClef::Bloquer);

        expect(fn () => app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Un titre parfaitement acceptable ici',
            "Le corps du message traite quelqu'un d'ordure, ce qui n'a pas sa place.",
        ))->toThrow(MessageBloque::class);

        expect(Thread::query()->count())->toBe(0);
    });

    it('examine AUSSI le titre, pas seulement le corps', function (): void {
        // Le titre est ce qu'on voit en premier dans la liste : une injure y
        // reste une injure.
        motSurveille('ordure', ActionMotClef::Bloquer);

        expect(fn () => app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Cette ordure de ministre a encore menti',
            'Un corps de message tout à fait ordinaire et suffisamment long.',
        ))->toThrow(MessageBloque::class);
    });

    it('ne révèle JAMAIS le mot qui a déclenché', function (): void {
        /*
         | Révéler la liste transformerait chaque refus en indice : quelques
         | essais suffiraient à la reconstituer, puis à la contourner mot à mot.
         */
        motSurveille('ordure', ActionMotClef::Bloquer);

        try {
            app(AnimationDuForum::class)->publier(
                $this->auteur,
                'Un titre parfaitement acceptable ici',
                'Le corps du message contient le mot ordure, ce qui déclenche.',
            );
            expect(false)->toBeTrue('Le blocage aurait dû lever.');
        } catch (MessageBloque $e) {
            expect($e->getMessage())->not->toContain('ordure')
                // L'information précise reste disponible pour le journal.
                ->and($e->termes)->toContain('ordure');
        }
    });

    it('PUBLIE et signale quand le mot est seulement surveillé', function (): void {
        /*
         | Le cas qui justifie les deux actions. Le message existe, il est
         | lisible, et la modération est prévenue — au lieu de refuser le
         | service à quelqu'un qui signale une rumeur.
         */
        motSurveille('vaccin', ActionMotClef::Signaler);

        $sujet = app(AnimationDuForum::class)->publier(
            $this->auteur,
            'On me dit que le vaccin rend stérile, est-ce vrai ?',
            "Un message circule sur WhatsApp au sujet du vaccin. Je voudrais savoir ce qu'il en est.",
        );

        expect($sujet->exists)->toBeTrue();

        $signalement = ContentReport::query()->where('reportable_type', Thread::class)->first();

        expect($signalement)->not->toBeNull()
            ->and($signalement->reason)->toBe('mot_surveille')
            ->and($signalement->detail)->toContain('vaccin')
            /*
             | AUCUN signaleur. Y mettre l'auteur le ferait se dénoncer
             | lui-même dans l'écran de modération, ce qui est faux et se lit
             | comme un défaut.
             */
            ->and($signalement->user_id)->toBeNull();
    });

    it('compte les déclenchements, pour que la liste puisse être élaguée', function (): void {
        /*
         | Sans ce compteur, personne ne sait quelles entrées servent. Une liste
         | qui grossit sans jamais être relue finit par bloquer des choses que
         | plus personne n'assume.
         */
        $mot = motSurveille('vaccin', ActionMotClef::Signaler);

        app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Une question au sujet du vaccin qui circule',
            'Un message circule sur WhatsApp et je voudrais le faire vérifier.',
        );

        expect($mot->refresh()->hits)->toBe(1)
            ->and($mot->last_hit_at)->not->toBeNull();
    });

    it('ignore un mot désactivé', function (): void {
        // Désactiver plutôt que supprimer : on garde la trace de ce qui a été
        // surveillé, et la note qui explique pourquoi.
        $mot = motSurveille('ordure', ActionMotClef::Bloquer);
        $mot->forceFill(['is_active' => false])->save();
        app(FiltreDeMots::class)->oublier();

        $sujet = app(AnimationDuForum::class)->publier(
            $this->auteur,
            'Un titre parfaitement acceptable ici',
            'Le corps du message contient le mot ordure, sans conséquence.',
        );

        expect($sujet->exists)->toBeTrue();
    });
});
