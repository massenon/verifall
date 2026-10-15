<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Identity\Services\PreferencesService;
use App\Core\Marketing\Emails\RenduDeGabarit;
use App\Enums\PreferenceNotification;
use App\Enums\UserRole;
use App\Mail\CourrielDeGabarit;
use App\Models\EmailTemplate;
use App\Models\ForumCategory;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| « Une réaction sur votre message »
|--------------------------------------------------------------------------
|
| Le dernier courriel câblable, et le plus facile à rendre insupportable. Ce que
| ces tests tiennent n'est presque QUE du silence :
|
|   · pas de courriel quand on retire son « j'aime » ;
|   · pas de courriel à soi-même ;
|   · **un seul courriel pour cinquante réactions** — c'est le mode de
|     défaillance classique de cette fonctionnalité, celui qui fait désactiver
|     toutes les notifications d'un coup ;
|   · rien si la personne a coupé « activité du forum ».
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(GabaritsDEmailSeeder::class);

    $this->auteur = citoyenDuForum('Auteure');
    $this->reacteur = citoyenDuForum('Réacteur');

    // Le catalogue le livre ÉTEINT : ces tests décrivent le comportement une
    // fois que l'administration l'a armé.
    EmailTemplate::query()->where('key', 'reaction_recue')->update(['is_active' => true]);
    app(RenduDeGabarit::class)->oublier();

    Mail::fake();
});

function citoyenDuForum(string $nom): User
{
    $citoyen = User::factory()->actif()->create(['name' => $nom]);
    $citoyen->assignRole(UserRole::Citoyen->value);

    return $citoyen;
}

function sujetDe(User $auteur): Thread
{
    $categorie = ForumCategory::first() ?? ForumCategory::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'general',
        'name' => 'Général',
        'position' => 1,
    ]);

    return Thread::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'un-sujet-'.Str::lower(Str::random(5)),
        'user_id' => $auteur->id,
        'forum_category_id' => $categorie->id,
        'title' => 'Ce message circule-t-il vraiment ?',
        'body' => 'Le message reçu ce matin sur WhatsApp, mot pour mot.',
    ]);
}

it('prévient l’auteur qu’on a réagi', function () {
    $sujet = sujetDe($this->auteur);

    app(AnimationDuForum::class)->aimer($this->reacteur, $sujet);

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'reaction_recue'
            && $m->variables['auteur'] === 'Réacteur',
    );
});

it('n’écrit RIEN quand on retire son « j’aime »', function () {
    // Se raviser n'est pas un événement dont on prévient qui que ce soit.
    $sujet = sujetDe($this->auteur);
    $animation = app(AnimationDuForum::class);

    $animation->aimer($this->reacteur, $sujet);
    Mail::fake();

    $animation->aimer($this->reacteur, $sujet->fresh());

    Mail::assertNotQueued(CourrielDeGabarit::class);
});

it('n’écrit PAS à quelqu’un qui aime son propre message', function () {
    // Geste courant. On ne s'écrit pas pour se l'annoncer.
    $sujet = sujetDe($this->auteur);

    app(AnimationDuForum::class)->aimer($this->auteur, $sujet);

    Mail::assertNotQueued(CourrielDeGabarit::class);
});

it('n’envoie QU’UN courriel pour dix réactions', function () {
    /*
     | LE test qui compte. Sans repos, un billet qui décolle noierait son auteur
     | — et la personne désactiverait TOUTES ses notifications, y compris celles
     | qui comptaient.
     |
     | Le repos porte sur le CONTENU, pas sur la personne qui réagit : dix
     | comptes différents, un seul message.
     */
    $sujet = sujetDe($this->auteur);
    $animation = app(AnimationDuForum::class);

    foreach (range(1, 10) as $n) {
        $animation->aimer(citoyenDuForum('Lecteur '.$n), $sujet->fresh());
    }

    Mail::assertQueued(CourrielDeGabarit::class, 1);
});

it('reprend la parole une fois le repos écoulé', function () {
    // Assez court pour qu'une réaction du lendemain se signale encore.
    $sujet = sujetDe($this->auteur);
    $animation = app(AnimationDuForum::class);

    $animation->aimer($this->reacteur, $sujet);

    $this->travel(AnimationDuForum::REPOS_REACTION_HEURES + 1)->hours();

    $animation->aimer(citoyenDuForum('Une autre'), $sujet->fresh());

    Mail::assertQueued(CourrielDeGabarit::class, 2);
});

it('respecte la préférence « activité du forum »', function () {
    app(PreferencesService::class)->definir(
        $this->auteur,
        PreferenceNotification::ActiviteForum,
        false,
    );

    $sujet = sujetDe($this->auteur);
    app(AnimationDuForum::class)->aimer($this->reacteur, $sujet);

    Mail::assertNotQueued(CourrielDeGabarit::class);
});

it('ne CONSOMME pas le repos quand rien n’est parti', function () {
    /*
     | Subtil, et c'est le genre de défaut qu'on ne trouve qu'en production :
     | poser la marque dans tous les cas ferait qu'un envoi éteint consomme
     | quand même le repos. Le jour où l'administration l'allume, les six
     | premières heures seraient muettes sans raison visible.
     */
    EmailTemplate::query()->where('key', 'reaction_recue')->update(['is_active' => false]);
    app(RenduDeGabarit::class)->oublier();

    $sujet = sujetDe($this->auteur);
    app(AnimationDuForum::class)->aimer($this->reacteur, $sujet);

    expect($sujet->fresh()->reaction_notified_at)->toBeNull();
});

it('prévient aussi sur une RÉPONSE, avec le lien du sujet', function () {
    // Le lien pointe le sujet et non la réponse : le forum n'a pas de route
    // par réponse, et une ancre vers un message hors contexte ne se lit pas.
    $sujet = sujetDe($this->auteur);

    $reponse = Reply::create([
        'uuid' => (string) Str::uuid(),
        'thread_id' => $sujet->id,
        'user_id' => $this->auteur->id,
        'body' => 'Une réponse de l’autrice du sujet.',
    ]);

    app(AnimationDuForum::class)->aimer($this->reacteur, $reponse);

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'reaction_recue'
            && $m->variables['sujet'] === 'Ce message circule-t-il vraiment ?',
    );
});
