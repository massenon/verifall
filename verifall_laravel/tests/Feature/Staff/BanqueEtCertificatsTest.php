<?php

declare(strict_types=1);

use App\Enums\MotifMouvementUnite;
use App\Enums\StatutQuestion;
use App\Livewire\Staff\BanqueDeQuestions;
use App\Livewire\Staff\Certificats;
use App\Livewire\Staff\UnitesDesCoursPremium;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\QuestionBankItem;
use App\Models\UnitLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Banque de questions, certificats, tarifs — lot 6
|--------------------------------------------------------------------------
| Trois écrans, trois règles qui n'ont rien de cosmétique :
|
| · une question de banque incomplète ne doit PAS pouvoir être publiée : posée
|   en examen, elle note faux tout le monde ;
| · un certificat ne s'EFFACE pas — il se révoque, avec un motif public. Une
|   ligne supprimée rendrait le contrôle public négatif, et le porteur d'un
|   certificat authentique passerait pour un fraudeur ;
| · le revenu d'un cours se LIT AU JOURNAL. Le recalculer au tarif du jour
|   donnerait un chiffre faux qui a l'air juste.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

function unCoursPayant(int $cout = 20): Course
{
    return Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(6)),
        'title' => 'Enquête en source ouverte',
        'description' => str_repeat('Contenu. ', 8),
        'category' => 'OSINT',
        'level' => 'intermediate',
        'duration_label' => '6 h',
        'cost_units' => $cout,
        'delivers_certificate' => true,
        'is_published' => true,
    ]);
}

describe('la banque de questions', function (): void {
    it('crée une question en BROUILLON, jamais publiée d\'emblée', function (): void {
        // Publier, c'est exposer la question à un examen certifiant. Ce n'est
        // pas l'effet de bord d'une création.
        Livewire::actingAs($this->admin, 'staff')
            ->test(BanqueDeQuestions::class)
            ->call('ouvrirCreation')
            ->set('enonce', 'Qu\'est-ce qu\'une recherche d\'image inversée ?')
            ->set('type', 'qcm')
            ->set('points', '10')
            ->set('propositions', [
                ['label' => 'Retrouver l\'origine d\'une image', 'correcte' => true],
                ['label' => 'Retourner une image', 'correcte' => false],
            ])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $question = QuestionBankItem::query()->firstOrFail();

        expect($question->status)->toBe(StatutQuestion::Brouillon)
            ->and($question->options()->count())->toBe(2);
    });

    it('remplace les propositions plutôt que de les fusionner', function (): void {
        // Le contrat du dépôt le dit : « une mise à jour partielle laisserait
        // une ancienne bonne réponse en place à côté de la nouvelle ».
        $composant = Livewire::actingAs($this->admin, 'staff')->test(BanqueDeQuestions::class);

        $composant->call('ouvrirCreation')
            ->set('enonce', 'Une question à corriger')
            ->set('points', '10')
            ->set('propositions', [
                ['label' => 'Bonne', 'correcte' => true],
                ['label' => 'Mauvaize', 'correcte' => false],
            ])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $question = QuestionBankItem::query()->firstOrFail();

        $composant->call('ouvrirEdition', $question->uuid)
            ->set('propositions', [
                ['label' => 'Bonne', 'correcte' => true],
                ['label' => 'Mauvaise', 'correcte' => false],
            ])
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($question->refresh()->options()->pluck('label')->all())
            ->toBe(['Bonne', 'Mauvaise']);
    });

    it('rattrape le refus du service plutôt que de rendre une 500', function (): void {
        /*
         | `publier()` appelle `verifierQuElleTientDebout()` et LÈVE si la
         | question est incomplète. Sans rattrapage, l'écran renverrait une 500
         | là où il doit dire ce qui manque.
         */
        $question = QuestionBankItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'qcm',
            'prompt' => 'Une question sans aucune proposition',
            'points' => 10,
            'status' => StatutQuestion::Brouillon->value,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(BanqueDeQuestions::class)
            ->call('publier', $question->uuid);

        expect($question->refresh()->status)->toBe(StatutQuestion::Brouillon);
    });

    it('borne les points à 100', function (): void {
        // Une question à 1 000 points écraserait toutes les autres d'une
        // session : la note ne dépendrait plus que d'elle.
        Livewire::actingAs($this->admin, 'staff')
            ->test(BanqueDeQuestions::class)
            ->call('ouvrirCreation')
            ->set('enonce', 'Une question démesurée')
            ->set('points', '5000')
            ->set('propositions', [
                ['label' => 'A', 'correcte' => true],
                ['label' => 'B', 'correcte' => false],
            ])
            ->call('enregistrer')
            ->assertHasErrors('points');
    });

    it('laisse le mentor écrire mais pas publier', function (): void {
        // Un fonds commun est commun : la borne « seulement ses ressources »
        // qui vaut pour les cours ne vaut pas ici. Mais publier reste à l'admin.
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        $question = QuestionBankItem::query()->create([
            'uuid' => (string) Str::uuid(), 'type' => 'qcm',
            'prompt' => 'Écrite par quelqu\'un d\'autre', 'points' => 10,
            'status' => StatutQuestion::Brouillon->value,
        ]);

        expect($mentor->can('update', $question))->toBeTrue()
            ->and($mentor->can('publish', $question))->toBeFalse()
            ->and($mentor->can('delete', $question))->toBeFalse();
    });
});

describe('les certificats', function (): void {
    it('n\'offre AUCUNE suppression', function (): void {
        /*
         | La règle centrale. Un certificat effacé rend le contrôle public
         | négatif : son porteur passe pour un fraudeur, sans recours et sans
         | trace de ce qui s'est passé.
         */
        $composant = Livewire::actingAs($this->admin, 'staff')->test(Certificats::class)->instance();

        expect(method_exists($composant, 'supprimer'))->toBeFalse()
            ->and(method_exists($composant, 'demanderLaSuppression'))->toBeFalse()
            // La révocation, elle, existe — et son inverse aussi.
            ->and(method_exists($composant, 'revoquer'))->toBeTrue()
            ->and(method_exists($composant, 'retablir'))->toBeTrue();
    });

    it('exige un motif, parce qu\'il sera PUBLIC', function (): void {
        $certificat = unCertificat();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Certificats::class)
            ->call('demanderLaRevocation', $certificat->uuid)
            ->set('motifDeRevocation', 'non')
            ->call('revoquer')
            ->assertHasErrors('motifDeRevocation');

        expect($certificat->refresh()->revoked_at)->toBeNull();
    });

    it('révoque, journalise, et laisse la ligne en place', function (): void {
        $certificat = unCertificat();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Certificats::class)
            ->call('demanderLaRevocation', $certificat->uuid)
            ->set('motifDeRevocation', 'Parcours validé par un tiers, fraude établie.')
            ->call('revoquer')
            ->assertHasNoErrors();

        $certificat->refresh();

        expect($certificat->revoked_at)->not->toBeNull()
            ->and($certificat->revoked_reason)->toContain('fraude')
            // La ligne EXISTE toujours : c'est elle que la page publique lit.
            ->and(Certificate::query()->whereKey($certificat->id)->exists())->toBeTrue();

        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.revoke']);
    });

    it('rétablit une révocation faite par erreur, et le journalise', function (): void {
        // Sur un document opposable, on doit pouvoir raconter l'aller ET le
        // retour.
        $certificat = unCertificat();
        $certificat->forceFill(['revoked_at' => now(), 'revoked_reason' => 'Erreur de saisie.'])->save();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Certificats::class)
            ->call('retablir', $certificat->uuid);

        expect($certificat->refresh()->revoked_at)->toBeNull()
            ->and($certificat->revoked_reason)->toBeNull();

        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.restore']);
    });

    it('refuse la révocation au mentor', function (): void {
        // Délivrer relève de la pédagogie ; annuler un certificat déjà remis
        // relève d'une décision institutionnelle.
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        expect($mentor->can('view', unCertificat()))->toBeTrue()
            ->and($mentor->can('revoke', unCertificat()))->toBeFalse();
    });
});

describe('la grille tarifaire', function (): void {
    it('lit le revenu AU JOURNAL, pas au tarif du jour', function (): void {
        /*
         | Le cours a été acheté 10 unités par deux personnes, puis son tarif
         | est passé à 50. Le revenu réel est 20, pas 100. Recalculer
         | « tarif × inscrits » donnerait le second — un chiffre faux qui a
         | l'air juste.
         */
        $cours = unCoursPayant(50);
        $acheteur = User::factory()->create();

        foreach ([10, 10] as $paye) {
            UnitLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $acheteur->id,
                'delta' => -$paye,
                'balance_after' => 0,
                'reason' => MotifMouvementUnite::CoursPremium->value,
                'label' => 'Achat du cours',
                'source_type' => Course::class,
                'source_id' => $cours->id,
            ]);
        }

        Livewire::actingAs($this->admin, 'staff')
            ->test(UnitesDesCoursPremium::class)
            // 20 unités encaissées, et non 100.
            ->assertSee('20')
            ->assertDontSee('100 ');
    });

    it('enregistre un tarif et le journalise', function (): void {
        $cours = unCoursPayant(20);

        Livewire::actingAs($this->admin, 'staff')
            ->test(UnitesDesCoursPremium::class)
            ->set('couts.'.$cours->uuid, '35')
            ->call('enregistrerLeTarif', $cours->uuid)
            ->assertHasNoErrors();

        expect($cours->refresh()->cost_units)->toBe(35);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.price']);
    });

    it('borne le tarif à 500', function (): void {
        // Au-delà, le cours serait plus cher que tous les packs vendus : donc
        // inachetable, et rien ne le dirait avant l'échec du débit.
        $cours = unCoursPayant(20);

        Livewire::actingAs($this->admin, 'staff')
            ->test(UnitesDesCoursPremium::class)
            ->set('couts.'.$cours->uuid, '9000')
            ->call('enregistrerLeTarif', $cours->uuid)
            ->assertHasErrors('couts.'.$cours->uuid);

        expect($cours->refresh()->cost_units)->toBe(20);
    });
});

/** Un certificat délivré, prêt à être contrôlé publiquement. */
function unCertificat(): Certificate
{
    $cours = unCoursPayant();
    $titulaire = User::factory()->create(['name' => 'Awa Gbaguidi']);

    return Certificate::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'VA-'.Str::upper(Str::random(8)),
        'user_id' => $titulaire->id,
        'course_id' => $cours->id,
        'holder_name' => $titulaire->name,
        'course_title' => $cours->title,
        'issued_at' => now()->subDays(3),
    ]);
}
