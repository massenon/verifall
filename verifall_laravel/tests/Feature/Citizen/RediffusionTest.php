<?php

declare(strict_types=1);

use App\Livewire\Citizen\LiveClasses;
use App\Livewire\Citizen\Rediffusion;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| B-06 — « Voir le replay » ramenait à la liste des tutoriels
|--------------------------------------------------------------------------
| Le réflexe, devant ce bouton, est de corriger son `href`. Il n'y avait rien à
| corriger : `live_classes` ne portait AUCUNE colonne d'enregistrement. Le lien
| vers la liste était un repli assumé faute de quoi que ce soit à ouvrir — le
| défaut vivait dans le schéma, pas dans le gabarit.
|
| D'où l'ordre de ce fichier : on éprouve d'abord que la carte ne promet plus
| ce qu'elle ne peut pas tenir, puis que la rediffusion s'ouvre quand elle
| existe, et enfin les deux refus — qui expliquent au lieu de renvoyer un code.
*/

/**
 * Une séance terminée, rattachée à un cours.
 *
 * @param  array<string, mixed>  $enPlus
 * @return array{0: LiveClass, 1: Course}
 */
function seanceTerminee(array $enPlus = []): array
{
    $cours = Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(6)),
        'title' => 'Vérifier une vidéo virale',
        'description' => 'Description.',
        'category' => 'investigation',
        'level' => 'beginner',
        'duration_label' => '30 min',
        'cost_units' => 0,
        'outcomes' => ['Un acquis'],
        'delivers_certificate' => false,
        'is_published' => true,
        'published_at' => now()->subMonth(),
    ]);

    $seance = LiveClass::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'host_id' => null,
        'title' => 'Atelier — remonter à la source d\'une vidéo',
        'description' => 'Trois cas réels, pas à pas.',
        'starts_at' => now()->subWeek(),
        'minutes' => 60,
        'join_url' => 'https://example.test/salle',
        'seats' => 30,
        'is_published' => true,
    ], $enPlus));

    return [$seance, $cours];
}

function inscritA(Course $cours): User
{
    $apprenant = User::factory()->create(['otp_verified_at' => now()]);

    Enrollment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $apprenant->id,
        'course_id' => $cours->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return $apprenant;
}

describe('la carte d\'une séance terminée', function (): void {
    it('ne renvoie plus vers la liste des tutoriels', function (): void {
        /*
         | Le défaut signalé, dans sa forme exacte : on cliquait « Voir le
         | replay » et on atterrissait sur un catalogue de cours.
         */
        [, $cours] = seanceTerminee();
        $apprenant = inscritA($cours);

        Livewire::actingAs($apprenant)
            ->test(LiveClasses::class)
            ->assertDontSee(route('citizen.tutorials'), escape: false);
    });

    it('annonce « rediffusion à venir » quand il n\'y a rien à ouvrir', function (): void {
        /*
         | Le point le plus important du correctif, et le moins spectaculaire :
         | une séance sans enregistrement le DIT, au lieu d'offrir un bouton
         | qui déçoit. C'est le cas de la majorité des séances.
         */
        [$seance, $cours] = seanceTerminee();
        $apprenant = inscritA($cours);

        Livewire::actingAs($apprenant)
            ->test(LiveClasses::class)
            ->assertSee('Rediffusion à venir')
            ->assertDontSee(route('citizen.live-class.replay', $seance), escape: false);
    });

    it('n\'offre PAS le lien à qui n\'est pas inscrit au cours', function (): void {
        /*
         | Défaut trouvé au passage NAVIGATEUR, pas par un test — et c'est
         | précisément le genre que les tests ne voient pas : la page répondait
         | 200, le bouton s'affichait, et le clic tombait sur un refus.
         |
         | La carte n'interrogeait que `rediffusionDisponible()`, un fait sur la
         | SÉANCE. Elle offrait donc un bouton vert et engageant à quelqu'un de
         | non inscrit — exactement l'impasse que le lot 1 s'était donné pour
         | but de supprimer, reproduite deux écrans plus loin.
         |
         | On ne cache pas la rediffusion pour autant : on dit ce qui
         | l'ouvrirait, et on mène au cours.
         */
        [$seance, $cours] = seanceTerminee([
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
        ]);

        $etranger = User::factory()->create(['otp_verified_at' => now()]);

        Livewire::actingAs($etranger)
            ->test(LiveClasses::class)
            ->assertDontSee(route('citizen.live-class.replay', $seance), escape: false)
            ->assertSee('Réservée aux inscrits')
            ->assertSee(route('citizen.course', $cours), escape: false);
    });

    it('offre le lien dès qu\'une rediffusion est publiée', function (): void {
        [$seance, $cours] = seanceTerminee([
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
            'recording_minutes' => 47,
        ]);
        $apprenant = inscritA($cours);

        Livewire::actingAs($apprenant)
            ->test(LiveClasses::class)
            ->assertSee(route('citizen.live-class.replay', $seance), escape: false);
    });
});

describe('la publication différée', function (): void {
    it('cache une rediffusion dont la date n\'est pas atteinte', function (): void {
        /*
         | Une séance s'enregistre le mardi et se publie le jeudi, une fois
         | relue et coupée. Sans `replay_published_at`, déposer l'adresse
         | publierait aussitôt — il faudrait la retenir ailleurs en attendant,
         | c'est-à-dire nulle part.
         */
        [$seance, $cours] = seanceTerminee([
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->addDays(2),
        ]);
        $apprenant = inscritA($cours);

        expect($seance->rediffusionDisponible())->toBeFalse();

        Livewire::actingAs($apprenant)
            ->test(Rediffusion::class, ['liveClass' => $seance])
            ->assertSet('blocage', 'sans-rediffusion');
    });

    it('ne présente jamais une séance à venir comme une rediffusion', function (): void {
        [$seance] = seanceTerminee([
            'starts_at' => now()->addWeek(),
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
        ]);

        expect($seance->rediffusionDisponible())->toBeFalse();
    });
});

describe('l\'écran de rediffusion', function (): void {
    it('affiche le lecteur à un inscrit', function (): void {
        [$seance, $cours] = seanceTerminee([
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
            'recording_minutes' => 47,
        ]);
        $apprenant = inscritA($cours);

        Livewire::actingAs($apprenant)
            ->test(Rediffusion::class, ['liveClass' => $seance])
            ->assertOk()
            ->assertSet('blocage', null)
            ->assertSee('https://example.test/enregistrement', escape: false)
            // La durée RÉELLE, pas celle prévue : annoncer 60 min sur une
            // vidéo de 47 fait renoncer avant de lancer la lecture.
            ->assertSee('47 min');
    });

    it('explique au lieu de refuser quand on n\'est pas inscrit', function (): void {
        [$seance] = seanceTerminee([
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
        ]);

        $etranger = User::factory()->create(['otp_verified_at' => now()]);

        Livewire::actingAs($etranger)
            ->test(Rediffusion::class, ['liveClass' => $seance])
            ->assertOk()
            ->assertSet('blocage', 'non-inscrit')
            ->assertDontSee('https://example.test/enregistrement', escape: false);
    });

    it('ouvre une séance HORS COURS à tout compte actif', function (): void {
        /*
         | Une séance sans cours n'est rattachée à rien qui puisse borner
         | l'accès : exiger une inscription reviendrait à la fermer à tout le
         | monde, pour toujours.
         */
        [$seance] = seanceTerminee([
            'course_id' => null,
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
        ]);

        $quelquun = User::factory()->create(['otp_verified_at' => now()]);

        Livewire::actingAs($quelquun)
            ->test(Rediffusion::class, ['liveClass' => $seance])
            ->assertSet('blocage', null);
    });

    it('reste un 404 sur une séance non publiée', function (): void {
        [$seance, $cours] = seanceTerminee([
            'is_published' => false,
            'replay_url' => 'https://example.test/enregistrement',
            'replay_published_at' => now()->subDay(),
        ]);
        $apprenant = inscritA($cours);

        Livewire::actingAs($apprenant)
            ->test(Rediffusion::class, ['liveClass' => $seance])
            ->assertStatus(404);
    });
});
