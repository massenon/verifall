<?php

declare(strict_types=1);

use App\Core\Lms\Notifications\RelanceDeCours;
use App\Core\Lms\Services\ConstructionDeCours;
use App\Core\Lms\Services\PiecesJointesDeLecon;
use App\Core\Lms\Services\StatistiquesDeCours;
use App\Core\Marketing\Seo\Referencement;
use App\Enums\StatutInscription;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Livewire\Staff\StatistiquesDuCours;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\HubArticle;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Ce que la fiche d'un cours ne pouvait pas porter
|--------------------------------------------------------------------------
|
| Prérequis, langue, prime d'XP, référencement, documents joints, support
| audio : rien de tout cela n'existait. Et l'écran de statistiques n'existait
| pas non plus — un cours se créait sans qu'on puisse jamais savoir ce qu'il
| produisait.
|
| Deux propriétés priment sur les autres et ont chacune leur test :
|
|   · **un support de cours ne s'ouvre pas sans être inscrit** — un cours payant
|     dont les PDF sont devinables est un cours vendu une fois et diffusé cent ;
|   · **on ne relance pas deux fois en une semaine** — une relance est un
|     service, deux relances en trois jours sont du harcèlement.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);

    $this->cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sourcer-et-citer-test',
        'title' => 'Sourcer et citer',
        'description' => 'Un cours de test suffisamment décrit pour passer la validation.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '4 h',
        'cost_units' => 5,
        'instructor_id' => $this->admin->id,
        'is_published' => true,
    ]);

    $this->lecon = Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $this->cours->id,
        'position' => 1,
        'title' => 'Première leçon',
        'type' => TypeLecon::Texte->value,
        'minutes' => 10,
        'content' => '<p>Contenu.</p>',
    ]);
});

/** Un citoyen inscrit au cours, à l'avancement voulu. */
function unInscrit(Course $cours, int $avancement, string $nom = 'Adderly Dossou'): Enrollment
{
    $citoyen = User::factory()->actif()->create(['name' => $nom]);
    $citoyen->assignRole(UserRole::Citoyen->value);

    return Enrollment::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $citoyen->id,
        'course_id' => $cours->id,
        'status' => StatutInscription::EnCours->value,
        'progress' => $avancement,
        'paid_units' => 5,
    ]);
}

describe('les documents joints', function (): void {
    it('range le fichier sur le disque PRIVÉ, sous un nom aléatoire', function () {
        $piece = app(PiecesJointesDeLecon::class)->attacher(
            $this->lecon,
            UploadedFile::fake()->create('module-3-premium.pdf', 400, 'application/pdf'),
        );

        // Le nom d'origine ne survit pas dans le chemin : un
        // « module-3-premium.pdf » sur un disque partagé se devine.
        expect($piece->path)->toStartWith('supports-de-cours/')
            ->and($piece->path)->not->toContain('module-3-premium')
            // …mais il est conservé pour l'affichage.
            ->and($piece->original_name)->toBe('module-3-premium.pdf');

        Storage::disk('local')->assertExists($piece->path);
    });

    it('REFUSE un type de fichier hors de la liste', function () {
        // Un formateur dépose des supports, pas des exécutables.
        expect(fn () => app(PiecesJointesDeLecon::class)->attacher(
            $this->lecon,
            UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ))->toThrow(RuntimeException::class);
    });

    it('n’ouvre PAS un support à qui n’est pas inscrit', function () {
        /*
         | Le test le plus important de ce lot. Un cours payant dont les PDF
         | sont accessibles par une URL devinable est un cours vendu une fois et
         | diffusé cent.
         */
        $piece = app(PiecesJointesDeLecon::class)->attacher(
            $this->lecon,
            UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
        );

        $etranger = User::factory()->actif()->create();
        $etranger->assignRole(UserRole::Citoyen->value);

        $lien = url()->signedRoute('citizen.lesson-attachment', ['piece' => $piece->uuid]);

        // Signé, connecté — mais pas inscrit : refusé.
        $this->actingAs($etranger)->get($lien)->assertForbidden();

        // Inscrit : servi.
        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $etranger->id,
            'course_id' => $this->cours->id,
            'status' => StatutInscription::EnCours->value,
            'progress' => 0,
            'paid_units' => 5,
        ]);

        $this->actingAs($etranger)->get($lien)->assertOk();
    });

    it('refuse une adresse NON signée, même à un inscrit', function () {
        $piece = app(PiecesJointesDeLecon::class)->attacher(
            $this->lecon,
            UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
        );

        $inscrit = unInscrit($this->cours, 10)->user;

        $this->actingAs($inscrit)
            ->get(route('citizen.lesson-attachment', ['piece' => $piece->uuid]))
            ->assertForbidden();
    });

    it('n’expose pas le chemin dans une sérialisation', function () {
        $piece = app(PiecesJointesDeLecon::class)->attacher(
            $this->lecon,
            UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
        );

        expect(json_encode($piece->toArray()))->not->toContain('supports-de-cours');
    });
});

describe('les statistiques du cours', function (): void {
    it('écarte de la moyenne ceux qui n’ont jamais commencé', function () {
        /*
         | Un inscrit à 0 % n'a pas abandonné le cours : il ne l'a pas commencé.
         | Le compter tirerait la moyenne vers le bas sans rien apprendre du
         | contenu — et masquerait le décrochage qu'on cherche.
         */
        unInscrit($this->cours, 0, 'Jamais commencé');
        unInscrit($this->cours, 40, 'À mi-chemin');
        unInscrit($this->cours, 60, 'Bien avancé');

        $chiffres = app(StatistiquesDeCours::class)->pour($this->cours);

        expect($chiffres['inscrits'])->toBe(3)
            ->and($chiffres['jamais_commences'])->toBe(1)
            // (40 + 60) / 2 = 50, et non (0 + 40 + 60) / 3 = 33.
            ->and($chiffres['progression_moyenne'])->toBe(50);
    });

    it('ne compte pas un certificat RÉVOQUÉ', function () {
        $inscrit = unInscrit($this->cours, 100)->user;

        $certificat = Certificate::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'VF-TEST-0001',
            'user_id' => $inscrit->id,
            'course_id' => $this->cours->id,
            'holder_name' => $inscrit->name,
            'course_title' => $this->cours->title,
            'issued_at' => now(),
        ]);

        expect(app(StatistiquesDeCours::class)->pour($this->cours)['certificats'])->toBe(1);

        // Un certificat retiré ne prouve plus rien : le compter gonflerait le
        // seul chiffre censé mesurer l'aboutissement.
        $certificat->forceFill(['revoked_at' => now(), 'revoked_reason' => 'Fraude.'])->save();

        expect(app(StatistiquesDeCours::class)->pour($this->cours)['certificats'])->toBe(0);
    });

    it('liste les MOINS avancés d’abord', function () {
        unInscrit($this->cours, 90, 'Presque fini');
        unInscrit($this->cours, 10, 'Vient de commencer');

        $liste = app(StatistiquesDeCours::class)->inscrits($this->cours);

        expect($liste->first()->user->name)->toBe('Vient de commencer');
    });
});

describe('la relance', function (): void {
    it('prévient l’inscrit par notification ET par courriel', function () {
        Notification::fake();

        $inscription = unInscrit($this->cours, 40);

        Livewire::actingAs($this->admin, 'staff')
            ->test(StatistiquesDuCours::class, ['slug' => $this->cours->slug])
            ->call('relancer', $inscription->uuid);

        Notification::assertSentTo(
            $inscription->user,
            RelanceDeCours::class,
            fn (RelanceDeCours $n): bool => $n->via($inscription->user) === ['database', 'mail']
                && str_contains($n->texte(), '40 %'),
        );

        expect($inscription->fresh()->last_reminder_at)->not->toBeNull();
    });

    it('REFUSE de relancer deux fois en moins de sept jours', function () {
        /*
         | Une relance est un service ; deux relances en trois jours sont du
         | harcèlement. C'est à l'application de tenir le délai — pas à la
         | mémoire de la personne qui clique, ni à la coordination de deux
         | membres de l'équipe qui ouvrent le même écran le même matin.
         */
        Notification::fake();

        $inscription = unInscrit($this->cours, 40);
        $service = app(StatistiquesDeCours::class);

        $service->relancer($inscription, $this->admin);

        expect(fn () => $service->relancer($inscription->fresh(), $this->admin))
            ->toThrow(RuntimeException::class);

        // …et sept jours plus tard, c'est de nouveau possible.
        $this->travel(8)->days();
        $service->relancer($inscription->fresh(), $this->admin);

        Notification::assertSentToTimes($inscription->user, RelanceDeCours::class, 2);
    });

    it('ne relance PAS quelqu’un qui a terminé', function () {
        Notification::fake();

        $inscription = unInscrit($this->cours, 100);
        $inscription->forceFill(['completed_at' => now()])->save();

        expect(fn () => app(StatistiquesDeCours::class)->relancer($inscription->fresh(), $this->admin))
            ->toThrow(RuntimeException::class);

        Notification::assertNothingSent();
    });

    it('refuse de relancer l’inscrit d’un AUTRE cours', function () {
        Notification::fake();

        $autre = Course::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'un-autre-cours',
            'title' => 'Un autre cours',
            'description' => 'Un second cours, appartenant à quelqu’un d’autre.',
            'category' => 'OSINT',
            'level' => 'beginner',
            'duration_label' => '2 h',
            'cost_units' => 0,
            'is_published' => true,
        ]);

        $ailleurs = unInscrit($autre, 20);

        // Un uuid deviné ne doit pas permettre d'écrire aux inscrits d'un autre
        // formateur depuis l'écran de son propre cours.
        Livewire::actingAs($this->admin, 'staff')
            ->test(StatistiquesDuCours::class, ['slug' => $this->cours->slug])
            ->call('relancer', $ailleurs->uuid);

        Notification::assertNothingSent();
        expect($ailleurs->fresh()->last_reminder_at)->toBeNull();
    });
});

it('range les prérequis et les objectifs en LISTE, pas en bloc de texte', function () {
    $this->cours->forceFill([
        'prerequisites' => ['Savoir lire un lien', 'Avoir un compte'],
        'outcomes' => ['Reconnaître une source douteuse'],
        'language' => 'fr',
        'xp_reward' => 150,
    ])->save();

    $relu = $this->cours->fresh();

    expect($relu->prerequisites)->toBe(['Savoir lire un lien', 'Avoir un compte'])
        ->and($relu->outcomes)->toBe(['Reconnaître une source douteuse'])
        ->and($relu->xp_reward)->toBe(150);
});

it('vide l’adresse de l’AUTRE support quand la leçon change de type', function () {
    /*
     | Une leçon repassée de vidéo à audio ne doit pas garder l'adresse de la
     | vidéo : invisible à l'écran, et prête à ressurgir au prochain changement.
     */
    $construction = app(ConstructionDeCours::class);

    $construction->modifierUneLecon($this->lecon, [
        'titre' => 'Leçon vidéo', 'type' => TypeLecon::Video->value, 'minutes' => 5,
        'contenu' => null, 'video' => 'https://exemple.bj/video.mp4', 'audio' => null,
    ]);

    expect($this->lecon->fresh()->video_url)->toBe('https://exemple.bj/video.mp4');

    $construction->modifierUneLecon($this->lecon->fresh(), [
        'titre' => 'Leçon audio', 'type' => TypeLecon::Audio->value, 'minutes' => 5,
        'contenu' => null, 'video' => 'https://exemple.bj/video.mp4', 'audio' => 'https://exemple.bj/audio.mp3',
    ]);

    $relue = $this->lecon->fresh();

    expect($relue->audio_url)->toBe('https://exemple.bj/audio.mp3')
        ->and($relue->video_url)->toBeNull();
});

it('HONORE la surcharge SEO d’une actualité du Hub', function () {
    /*
     | `pourFaitVerifie()` composait titre et description à partir des seuls
     | champs éditoriaux : le bloc « Référencement » du formulaire enregistrait
     | des valeurs que RIEN ne lisait. Trouvé à la passe navigateur du
     | 2 septembre 2026, en vérifiant que la valeur saisie ressortait bien du
     | service — elle ne ressortait pas.
     */
    $article = HubArticle::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'un-dementi-de-test',
        'title' => 'Titre éditorial du démenti',
        'claim' => 'L’affirmation telle qu’elle a circulé.',
        'excerpt' => 'Le chapeau éditorial.',
        'body' => '<p>Corps.</p>',
        'category' => 'Société',
        'verdict' => 'false',
        'is_published' => true,
        'published_at' => now(),
        'meta_title' => 'Titre pensé pour la recherche',
        'meta_description' => 'Description pensée pour la recherche.',
    ]);

    $seo = app(Referencement::class)->pourFaitVerifie($article);

    expect($seo->titre)->toBe('Titre pensé pour la recherche')
        ->and($seo->description)->toBe('Description pensée pour la recherche.');

    // …et sans surcharge, le repli éditorial reste le comportement normal.
    $article->forceFill(['meta_title' => null, 'meta_description' => null])->save();

    $repli = app(Referencement::class)->pourFaitVerifie($article->fresh());

    expect($repli->titre)->toBe('Titre éditorial du démenti')
        ->and($repli->description)->toBe('Le chapeau éditorial.');
});
