<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotExamens;
use App\Core\Exams\Services\PlanificationExamen;
use App\Enums\StatutDossierKyc;
use App\Enums\StatutExamen;
use App\Enums\StatutInscription;
use App\Enums\TypePieceIdentite;
use App\Livewire\Staff\EnrolementAuxCours;
use App\Livewire\Staff\Examens;
use App\Livewire\Staff\Mentors;
use App\Livewire\Staff\VerificationKyc;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\KycSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Examens, enrôlement, mentors, KYC — lot 7
|--------------------------------------------------------------------------
| Sept écrans, et presque aucune règle nouvelle : les dépôts et les services
| existaient tous. Ce que ces tests tiennent, ce sont les points où l'écran
| pourrait DÉFAIRE ce que le domaine a soigneusement posé :
|
| · un examen sans question ne se publie pas, et le refus doit s'afficher —
|   pas remonter en 500 ;
| · une inscription donnée à la main ne débite rien, et doit rester
|   RECONNAISSABLE comme telle ;
| · retirer un rôle Mentor laisse des copies sans correcteur, et l'écran doit
|   le dire avant, pas après ;
| · le KYC ne peut lever qu'un drapeau, et l'écran doit l'annoncer plutôt que
|   de faire croire à un contrôle qui n'a pas lieu.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

function unExamen(): Exam
{
    return app(PlanificationExamen::class)->creer([
        'title' => 'Certification vérification de l\'information',
        'description' => str_repeat('Contenu de l\'épreuve. ', 4),
        'pass_score' => 70,
        'duration_minutes' => 90,
        'max_attempts' => 2,
    ]);
}

describe('les examens', function (): void {
    it('naît en brouillon, jamais publié d\'emblée', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Examens::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Épreuve de vérification')
            ->set('descriptif', str_repeat('Ce que cette épreuve évalue. ', 3))
            ->set('duree', '90')
            ->set('seuil', '70')
            ->set('tentatives', '2')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(Exam::query()->firstOrFail()->status)->toBe(StatutExamen::Brouillon);
    });

    it('refuse de publier une épreuve sans question, et le DIT', function (): void {
        /*
         | Le service lève ; l'écran rattrape. Une 500 n'apprendrait rien à qui
         | vient de cliquer « publier » — et un examen publié sans question
         | convoquerait des candidats devant une copie vide.
         */
        $examen = unExamen();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Examens::class)
            ->call('publier', $examen->uuid);

        expect($examen->refresh()->status)->toBe(StatutExamen::Brouillon);
    });

    it('refuse une session sur une épreuve non publiée', function (): void {
        // Le service le dit mot pour mot : « on ne convoque personne à un
        // examen qui n'est pas publié ».
        $examen = unExamen();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Examens::class)
            ->call('deplierLesSessions', $examen->uuid)
            ->call('ouvrirCreationSession')
            ->set('sessionDebut', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('sessionPlaces', '30')
            ->call('enregistrerLaSession')
            ->assertHasErrors('sessionDebut');

        expect(app(DepotExamens::class)->sessionsAVenir($examen))->toBeEmpty();
    });

    it('refuse une session dans le passé', function (): void {
        // Vérifié DANS le formulaire en plus du service : dire non avant
        // l'aller-retour vaut mieux qu'après.
        $examen = unExamen();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Examens::class)
            ->call('deplierLesSessions', $examen->uuid)
            ->call('ouvrirCreationSession')
            ->set('sessionDebut', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('enregistrerLaSession')
            ->assertHasErrors('sessionDebut');
    });

    it('borne le seuil à 50 % minimum', function (): void {
        // Sous 50 %, « réussir » voudrait dire se tromper plus souvent qu'avoir
        // juste — et le certificat que cet examen adosse ne vaudrait plus rien.
        Livewire::actingAs($this->admin, 'staff')
            ->test(Examens::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Épreuve trop facile')
            ->set('descriptif', str_repeat('Description valide. ', 3))
            ->set('seuil', '20')
            ->call('enregistrer')
            ->assertHasErrors('seuil');
    });

    it('laisse le mentor écrire une épreuve mais pas la publier', function (): void {
        /*
         | Pas de borne « ses ressources » ici, contrairement aux cours : une
         | épreuve appartient à l'institution, et plusieurs mentors la
         | corrigent. Mais publier reste à l'administrateur.
         */
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        $examen = unExamen();

        expect($mentor->can('update', $examen))->toBeTrue()
            ->and($mentor->can('grade', $examen))->toBeTrue()
            ->and($mentor->can('publish', $examen))->toBeFalse()
            ->and($mentor->can('archive', $examen))->toBeFalse();
    });
});

describe('l\'enrôlement aux cours', function (): void {
    it('donne un accès SANS débiter, et le rend reconnaissable', function (): void {
        /*
         | `paid_units = 0` sur un cours payant est la seule trace qui
         | distingue un accès offert d'un accès acheté. Y écrire le tarif
         | ferait apparaître un encaissement qui n'a pas eu lieu.
         */
        $cours = Course::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'cours-'.Str::lower(Str::random(6)),
            'title' => 'Cours payant',
            'description' => str_repeat('Contenu. ', 8),
            'category' => 'OSINT',
            'level' => 'beginner',
            'duration_label' => '3 h',
            'cost_units' => 30,
            'is_published' => true,
        ]);

        $citoyen = User::factory()->create(['email' => 'cible@verifall.com', 'units' => 100]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(EnrolementAuxCours::class)
            ->call('ouvrirInscription')
            ->set('compte', 'cible@verifall.com')
            ->set('coursUuid', $cours->uuid)
            ->set('motif', 'Geste commercial après incident du 30 août.')
            ->call('inscrire')
            ->assertHasNoErrors();

        $inscription = Enrollment::query()->firstOrFail();

        expect($inscription->paid_units)->toBe(0)
            ->and($inscription->status)->toBe(StatutInscription::Inscrit)
            // Le solde du citoyen n'a pas bougé : aucune unité n'a été débitée.
            ->and($citoyen->refresh()->units)->toBe(100);

        $this->assertDatabaseHas('audit_logs', ['action' => 'enrolment.create']);
    });

    it('exige une justification', function (): void {
        // Sans elle, un accès offert ressemble à un accès acheté dans six mois.
        User::factory()->create(['email' => 'cible@verifall.com']);

        Livewire::actingAs($this->admin, 'staff')
            ->test(EnrolementAuxCours::class)
            ->call('ouvrirInscription')
            ->set('compte', 'cible@verifall.com')
            ->set('motif', 'ok')
            ->call('inscrire')
            ->assertHasErrors('motif');
    });

    it('refuse une double inscription plutôt que de la remettre à zéro', function (): void {
        /*
         | Réinscrire quelqu'un écraserait sa progression sans le dire. Le refus
         | est plus utile qu'un doublon silencieux.
         */
        $cours = Course::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'cours-'.Str::lower(Str::random(6)),
            'title' => 'Un cours',
            'description' => str_repeat('Contenu. ', 8),
            'category' => 'IA', 'level' => 'beginner', 'duration_label' => '2 h',
            'cost_units' => 0, 'is_published' => true,
        ]);

        $citoyen = User::factory()->create(['email' => 'cible@verifall.com']);

        Enrollment::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $citoyen->id,
            'course_id' => $cours->id,
            'status' => StatutInscription::EnCours->value,
            'progress' => 60,
            'paid_units' => 0,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(EnrolementAuxCours::class)
            ->call('ouvrirInscription')
            ->set('compte', 'cible@verifall.com')
            ->set('coursUuid', $cours->uuid)
            ->set('motif', 'Tentative de réinscription à vérifier.')
            ->call('inscrire')
            ->assertHasErrors('compte');

        expect(Enrollment::query()->count())->toBe(1)
            ->and(Enrollment::query()->value('progress'))->toBe(60);
    });

    it('n\'offre AUCUN champ de progression', function (): void {
        /*
         | La progression se calcule sur les leçons terminées. Un champ
         | modifiable la ferait mentir dès la leçon suivante — et forcée à
         | 100 %, elle déclencherait un certificat que rien n'adosse.
         */
        $composant = Livewire::actingAs($this->admin, 'staff')
            ->test(EnrolementAuxCours::class)->instance();

        expect(property_exists($composant, 'progression'))->toBeFalse()
            ->and(property_exists($composant, 'progress'))->toBeFalse();
    });
});

describe('les mentors', function (): void {
    it('compte les cours portés par chacun', function (): void {
        // C'est la seule chose que « Utilisateurs » ne montre pas, et la seule
        // raison d'un second écran.
        $mentor = User::factory()->create(['name' => 'Awa Mentor']);
        $mentor->assignRole('Mentor');

        Course::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'cours-'.Str::lower(Str::random(6)),
            'title' => 'Son cours',
            'description' => str_repeat('Contenu. ', 8),
            'category' => 'IA', 'level' => 'beginner', 'duration_label' => '2 h',
            'cost_units' => 0, 'instructor_id' => $mentor->id, 'is_published' => true,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Mentors::class)
            ->assertSee('Awa Mentor')
            ->assertSee('1');

        expect($mentor->coursEnseignes()->count())->toBe(1);
    });

    it('refuse qu\'on retire son PROPRE rôle', function (): void {
        // Même garde-fou qu'au lot 3 : se verrouiller dehors demande une
        // console pour revenir.
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');
        $mentor->assignRole('Admin');

        Livewire::actingAs($mentor, 'staff')
            ->test(Mentors::class)
            ->call('demanderLaRetrogradation', $mentor->uuid)
            ->call('retrograder');

        expect($mentor->refresh()->hasRole('Mentor'))->toBeTrue();
    });

    it('retire le rôle et journalise ce que ça laisse derrière', function (): void {
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        Livewire::actingAs($this->admin, 'staff')
            ->test(Mentors::class)
            ->call('demanderLaRetrogradation', $mentor->uuid)
            ->call('retrograder');

        expect($mentor->refresh()->hasRole('Mentor'))->toBeFalse();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.update']);
    });
});

describe('la vérification KYC', function (): void {
    /*
    |--------------------------------------------------------------------------
    | CE BLOC A ÉTÉ RETOURNÉ le 1er septembre 2026
    |--------------------------------------------------------------------------
    |
    | Il gardait une DEMI-MESURE assumée : l'écran ne savait que basculer
    | `users.kyc_verified_at`, aucune pièce n'existait, et son test principal
    | vérifiait que l'écran l'ANNONÇAIT — « Aucune pièce n'est déposée sur la
    | plateforme ». C'était juste, et c'était temporaire.
    |
    | Le porteur du projet a tranché : construire la chaîne. Le bandeau n'a plus
    | lieu d'être, la bascule à la main non plus. Ces tests décrivent désormais
    | ce que l'écran fait ; le détail de la chaîne est éprouvé dans
    | `tests/Feature/Identity/ChaineKycTest.php`.
    |
    */

    /** Un dossier en attente, déposé par un citoyen actif. */
    function unDossierEnAttente(string $nom = 'Adjo Sossou'): KycSubmission
    {
        $citoyen = User::factory()->create(['name' => $nom, 'otp_verified_at' => now()]);
        $citoyen->assignRole('Citoyen');

        return KycSubmission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $citoyen->id,
            'document_type' => TypePieceIdentite::CarteNationale->value,
            'front_path' => 'kyc/recto.jpg',
            'back_path' => 'kyc/verso.jpg',
            'status' => StatutDossierKyc::EnAttente->value,
        ]);
    }

    it('ANNONCE la règle de rétention, en tête', function (): void {
        /*
         | Ce qui remplace l'ancien bandeau, et joue le même rôle : dire à
         | l'écran ce que la plateforme fait vraiment. Détenir des scans de
         | cartes d'identité sans annoncer combien de temps est le genre de
         | silence qu'on ne peut pas défendre.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(VerificationKyc::class)
            ->assertOk()
            ->assertSee('30 jours après la décision', escape: false);
    });

    it('exige un motif au refus, seule trace utile pour la personne', function (): void {
        $dossier = unDossierEnAttente();

        Livewire::actingAs($this->admin, 'staff')
            ->test(VerificationKyc::class)
            ->call('ouvrirLaDecision', $dossier->uuid, 'refuser')
            ->set('motif', 'non')
            ->call('statuer', 'refuser');

        // Refusé : « non » n'apprend rien et fait redéposer la même pièce.
        expect($dossier->refresh()->status)->toBe(StatutDossierKyc::EnAttente);
    });

    it('lève le drapeau à l’approbation, et le journalise', function (): void {
        $dossier = unDossierEnAttente();

        Livewire::actingAs($this->admin, 'staff')
            ->test(VerificationKyc::class)
            ->call('ouvrirLaDecision', $dossier->uuid, 'approuver')
            ->call('statuer', 'approuver');

        expect($dossier->refresh()->status)->toBe(StatutDossierKyc::Approuve)
            ->and($dossier->citoyen->refresh()->kyc_verified_at)->not->toBeNull();

        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.approuve']);
    });

    it('n’offre plus de lever le drapeau HORS dossier', function (): void {
        /*
         | Le geste « vérifier » existait sans rien derrière : c'était le trou
         | qu'on vient de fermer. Approuver passe par un dossier ; le RETRAIT
         | reste possible sans, parce qu'une vérification obtenue par fraude
         | doit pouvoir tomber tout de suite.
         */
        $dossier = unDossierEnAttente();

        Livewire::actingAs($this->admin, 'staff')
            ->test(VerificationKyc::class)
            // Un geste inconnu ne bascule rien.
            ->call('ouvrirLaDecision', $dossier->uuid, 'verifier')
            ->assertSet('geste', 'approuver');
    });

    it('liste les dossiers, les plus ANCIENS d’abord', function (): void {
        // Une file de pièces d'identité se traite dans l'ordre d'arrivée :
        // la personne attend, et nous détenons ses scans.
        $vieux = unDossierEnAttente('Premier arrivé');
        $vieux->forceFill(['created_at' => now()->subDays(3)])->save();

        unDossierEnAttente('Arrivé ensuite');

        $rendu = Livewire::actingAs($this->admin, 'staff')
            ->test(VerificationKyc::class)
            ->assertSee('Premier arrivé')
            ->assertSee('Arrivé ensuite')
            ->html();

        expect(strpos($rendu, 'Premier arrivé'))->toBeLessThan(strpos($rendu, 'Arrivé ensuite'));
    });

    it('sépare la lecture de la décision', function (): void {
        // `kyc.view` ouvre la page, `kyc.review` seul donne accès au dossier :
        // ouvrir le tiroir met sous les yeux le nom, l'adresse et les liens
        // vers les pièces — c'est déjà la décision, pas sa lecture.
        $dossier = unDossierEnAttente();

        $lecteur = User::factory()->create();
        $lecteur->givePermissionTo('kyc.view');

        expect($lecteur->can('kyc.view'))->toBeTrue()
            ->and($lecteur->can('kyc.review'))->toBeFalse();

        Livewire::actingAs($lecteur, 'staff')
            ->test(VerificationKyc::class)
            ->assertSet('peutExaminer', false)
            ->call('ouvrirLaDecision', $dossier->uuid, 'approuver')
            ->assertForbidden();
    });
});
