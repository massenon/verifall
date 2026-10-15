<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\TransmissionAuLabo;
use App\Enums\AvisCommunaute;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\Forum;
use App\Livewire\Citizen\Sujet;
use App\Models\ForumCategory;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\ThreadVote;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Forum citoyen — avis, réponses, verdict officiel
|--------------------------------------------------------------------------
| La faute à ne jamais commettre ici : laisser l'avis de la communauté passer
| pour une vérification. Une majorité qui se trompe reste une majorité qui se
| trompe — c'est même le mécanisme central de la désinformation.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    $this->rubrique = ForumCategory::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sante',
        'name' => 'Santé',
        'position' => 1,
    ]);
});

/** Un sujet publié par le service, comme en production. */
function sujetDeTest(User $auteur, ?string $lien = null): Thread
{
    return app(AnimationDuForum::class)->publier(
        $auteur,
        'Cette vidéo montre-t-elle un nouveau vaccin au Bénin ?',
        'Elle circule massivement sur WhatsApp depuis hier matin.',
        null,
        $lien,
    );
}

describe('publication', function (): void {
    it('purifie le message avant de le stocker', function (): void {
        $sujet = app(AnimationDuForum::class)->publier(
            $this->citoyen,
            'Un titre parfaitement ordinaire',
            '<p>Bonjour</p><script>alert(1)</script>',
        );

        // Purifié À L'ÉCRITURE : une seule vue qui oublierait de le refaire à
        // l'affichage suffirait à exécuter ce qui a été stocké.
        expect($sujet->body)->not->toContain('<script')
            ->and($sujet->body)->toContain('Bonjour');
    });

    it('écarte une « source » qui n\'est pas une URL', function (): void {
        $sujet = sujetDeTest($this->citoyen, 'je l\'ai vu sur whatsapp');

        // La stocker produirait un lien mort affiché comme une preuve.
        expect($sujet->source_url)->toBeNull();
    });

    it('ne fait pas échouer deux sujets au même titre', function (): void {
        $premier = sujetDeTest($this->citoyen);
        $second = sujetDeTest($this->citoyen);

        expect($second->slug)->not->toBe($premier->slug)
            ->and(Thread::count())->toBe(2);
    });

    it('exige un lien quand on coche « je signale une intox »', function (): void {
        Livewire::actingAs($this->citoyen)
            ->test(Forum::class)
            ->set('titre', 'Une offre d\'emploi qui demande des frais')
            ->set('corps', 'Le site réclame 5 000 FCFA de frais de dossier avant tout entretien.')
            ->set('signalementFakeNews', true)
            ->set('lienSignale', '')
            ->call('publier')
            ->assertHasErrors('lienSignale');

        expect(Thread::count())->toBe(0);
    });
});

describe('avis de la communauté', function (): void {
    it('n\'enregistre qu\'un avis par personne', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        $forum->voter($this->citoyen, $sujet, AvisCommunaute::Vrai);
        $forum->voter($this->citoyen, $sujet, AvisCommunaute::Faux);

        // Changer d'avis est légitime ; peser deux fois ne l'est pas.
        expect(ThreadVote::where('thread_id', $sujet->id)->count())->toBe(1)
            ->and($sujet->fresh()->votes_true)->toBe(0)
            ->and($sujet->fresh()->votes_false)->toBe(1);
    });

    it('retire l\'avis quand on reclique le même', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        $forum->voter($this->citoyen, $sujet, AvisCommunaute::Doute);
        $forum->voter($this->citoyen, $sujet, AvisCommunaute::Doute);

        expect(ThreadVote::count())->toBe(0)
            ->and($sujet->fresh()->totalDesVotes())->toBe(0);
    });

    it('n\'affiche aucune barre tant que personne n\'a voté', function (): void {
        $sujet = sujetDeTest($this->citoyen);

        // Trois barres à zéro laisseraient croire à un partage d'avis là où
        // personne ne s'est prononcé.
        expect($sujet->repartitionDesAvis())->toBe([
            'true' => 0, 'doubt' => 0, 'false' => 0,
        ]);
    });

    it('répartit en pourcentages une fois les avis donnés', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        $forum->voter($this->citoyen, $sujet, AvisCommunaute::Faux);

        foreach (range(1, 3) as $ignore) {
            $autre = User::factory()->actif()->create();
            $forum->voter($autre, $sujet, AvisCommunaute::Vrai);
        }

        expect($sujet->fresh()->repartitionDesAvis())->toBe([
            'true' => 75, 'doubt' => 0, 'false' => 25,
        ]);
    });
});

describe('verdict officiel', function (): void {
    it('reste distinct de l\'avis de la communauté', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        // La communauté croit l'information vraie…
        foreach (range(1, 4) as $ignore) {
            $forum->voter(User::factory()->actif()->create(), $sujet, AvisCommunaute::Vrai);
        }

        // …et la plateforme établit qu'elle est fausse.
        $agent = User::factory()->actif()->create();
        app(TransmissionAuLabo::class)->apposerLeVerdict($agent, $sujet, VerdictFactCheck::Faux);

        $sujet->refresh();

        expect($sujet->official_verdict)->toBe(VerdictFactCheck::Faux)
            ->and($sujet->votes_true)->toBe(4)
            // Le verdict n'écrase pas le sondage, et le sondage n'infléchit pas
            // le verdict : ce sont deux choses différentes.
            ->and($sujet->repartitionDesAvis()['true'])->toBe(100);
    });

    it('n\'en affiche aucun tant que l\'équipe n\'a pas tranché', function (): void {
        $sujet = sujetDeTest($this->citoyen);

        expect($sujet->porteUnVerdictOfficiel())->toBeFalse();

        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $sujet->slug])
            ->assertOk()
            ->assertDontSee('VÉRIF\'ALL CONFIRME');
    });
});

describe('transmission au laboratoire', function (): void {
    it('crée une analyse sans débiter personne', function (): void {
        Queue::fake();

        $sujet = sujetDeTest($this->citoyen, 'https://arnaque.example/offre');
        $solde = $this->citoyen->fresh()->units;

        $verification = app(TransmissionAuLabo::class)->transmettre($this->citoyen, $sujet);

        // C'est la plateforme qui décide de vérifier, pas le citoyen qui
        // commande : lui facturer son signalement serait absurde.
        expect($verification->cost_units)->toBe(0)
            ->and($this->citoyen->fresh()->units)->toBe($solde)
            ->and($sujet->fresh()->verification_id)->toBe($verification->id);
    });

    it('ne transmet qu\'une fois', function (): void {
        Queue::fake();

        $sujet = sujetDeTest($this->citoyen, 'https://arnaque.example/offre');
        $service = app(TransmissionAuLabo::class);

        $premiere = $service->transmettre($this->citoyen, $sujet);
        $seconde = $service->transmettre($this->citoyen, $sujet->fresh());

        // Deux analyses de la même rumeur coûteraient deux fois, et pourraient
        // rendre deux verdicts différents sur le même fil.
        expect($seconde->id)->toBe($premiere->id)
            ->and(Verification::count())->toBe(1);
    });

    it('vérifie le LIEN signalé plutôt que le texte du message', function (): void {
        Queue::fake();

        $sujet = sujetDeTest($this->citoyen, 'https://arnaque.example/offre');

        $verification = app(TransmissionAuLabo::class)->transmettre($this->citoyen, $sujet);

        // C'est le lien la pièce à vérifier ; le texte n'en est que le résumé.
        expect($verification->claim)->toBe('https://arnaque.example/offre');
    });
});

describe('réponses', function (): void {
    it('tient le compteur de réponses à jour', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        $forum->repondre($this->citoyen, $sujet, 'Je l\'ai vue aussi.');
        $forum->repondre($this->citoyen, $sujet->fresh(), 'Le compte source est récent.');

        expect($sujet->fresh()->replies_count)->toBe(2);
    });

    it('refuse une réponse sur un fil clos', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $sujet->forceFill(['is_locked' => true])->save();

        $resultat = app(AnimationDuForum::class)->repondre($this->citoyen, $sujet->fresh(), 'Encore un mot.');

        // Contourner une décision de modération par l'API serait une faille.
        expect($resultat)->toBeNull()
            ->and(Reply::count())->toBe(0);
    });

    it('n\'imbrique jamais au-delà d\'un niveau', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        $premiere = $forum->repondre($this->citoyen, $sujet, 'Premier niveau.');
        $seconde = $forum->repondre($this->citoyen, $sujet->fresh(), 'Deuxième niveau.', $premiere);
        $troisieme = $forum->repondre($this->citoyen, $sujet->fresh(), 'Troisième niveau.', $seconde);

        // Au-delà, un fil devient illisible sur téléphone : la réponse se
        // rattache au même parent.
        expect($troisieme->parent_id)->toBe($premiere->id);
    });

    it('ne compte qu\'un « j\'aime » par personne', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $forum = app(AnimationDuForum::class);

        expect($forum->aimer($this->citoyen, $sujet))->toBeTrue()
            ->and($sujet->fresh()->likes_count)->toBe(1);

        expect($forum->aimer($this->citoyen, $sujet->fresh()))->toBeFalse()
            ->and($sujet->fresh()->likes_count)->toBe(0);
    });
});

describe('les écrans', function (): void {
    it('liste les sujets et leurs avis', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        app(AnimationDuForum::class)->voter($this->citoyen, $sujet, AvisCommunaute::Doute);

        Livewire::actingAs($this->citoyen)
            ->test(Forum::class)
            ->assertOk()
            ->assertSee($sujet->title)
            ->assertSee('DOUTEUX');
    });

    it('ne propose pas de répondre sur un fil clos', function (): void {
        $sujet = sujetDeTest($this->citoyen);
        $sujet->forceFill(['is_locked' => true])->save();

        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $sujet->slug])
            ->assertSee('Ce fil est clos')
            ->assertDontSee('Répondre au sujet');
    });
});
