<?php

declare(strict_types=1);

use App\Core\Identity\Services\AuditLogService;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Staff\FaitsVerifies;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\VerifiedFact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Back-office — base des faits vérifiés (Sprint 6, lot 1)
|--------------------------------------------------------------------------
|
| C'est l'écran par lequel un fait tranché court-circuite douze moteurs
| payants. Deux choses y sont vérifiées plus que le reste : que personne ne
| publie sans en avoir le droit, et qu'on ne publie pas un verdict sans source.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);

    // Le modérateur rédige mais n'engage pas la plateforme : ni publish, ni
    // delete. C'est lui qui met les 403 à l'épreuve.
    $this->moderateur = User::factory()->create(['otp_verified_at' => now()]);
    $this->moderateur->assignRole(UserRole::Moderateur->value);
});

/** Session staff prête à l'emploi. */
function staffConnecte(User $user)
{
    return test()->actingAs($user, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp]);
}

function unFait(array $attributs = []): VerifiedFact
{
    return VerifiedFact::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'title' => 'Aucune taxe WhatsApp instaurée au Bénin',
        'summary' => '<p>L\'ARCEP a démenti l\'existence de cette taxe le 12 août.</p>',
        'verdict' => VerdictFactCheck::Faux->value,
        'keywords' => 'taxe whatsapp benin arcep',
        'sources' => [['url' => 'https://arcep.bj/communique', 'name' => 'ARCEP Bénin']],
        'is_published' => true,
    ], $attributs));
}

describe('accès', function (): void {
    it('refuse la page sans session staff', function (): void {
        $this->get(route('staff.knowledge'))->assertRedirect();
    });

    it('refuse un Mentor, à qui la matrice n\'accorde pas knowledge_base.view', function (): void {
        $mentor = User::factory()->create(['otp_verified_at' => now()]);
        $mentor->assignRole(UserRole::Mentor->value);

        staffConnecte($mentor)->get(route('staff.knowledge'))->assertForbidden();
    });

    it('autorise un administrateur et un modérateur', function (string $role): void {
        $acteur = $role === 'admin' ? $this->admin : $this->moderateur;

        staffConnecte($acteur)->get(route('staff.knowledge'))->assertOk();
    })->with(['admin', 'moderateur']);

    it('est reliée depuis la sidebar du shell staff', function (): void {
        staffConnecte($this->admin)->get(route('staff.knowledge'))
            ->assertSee(route('staff.knowledge'), escape: false)
            ->assertSee('Fact checks');
    });
});

describe('autorisation des actions sensibles', function (): void {
    /*
     | L'absence de bouton n'est pas une protection : chaque geste est appelé
     | SANS le droit correspondant, et doit rendre 403 côté serveur.
     */
    it('refuse la publication à qui n\'a pas knowledge_base.publish', function (): void {
        $fait = unFait(['is_published' => false]);

        staffConnecte($this->moderateur);

        Livewire::test(FaitsVerifies::class)
            ->call('basculerLaPublication', $fait->uuid)
            ->assertForbidden();

        expect($fait->refresh()->is_published)->toBeFalse();
    });

    it('refuse la publication depuis le tiroir à un modérateur', function (): void {
        staffConnecte($this->moderateur);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Une rumeur qui circule sur les réseaux')
            ->set('analyse', '<p>Une analyse assez longue pour être publiable.</p>')
            ->set('sourcesTexte', 'https://arcep.bj/communique | ARCEP')
            ->call('publier')
            ->assertForbidden();

        expect(VerifiedFact::count())->toBe(0);
    });

    it('refuse la suppression à qui n\'a pas knowledge_base.delete', function (): void {
        $fait = unFait();

        staffConnecte($this->moderateur);

        Livewire::test(FaitsVerifies::class)
            ->call('confirmerSuppression', $fait->uuid)
            ->assertForbidden();

        expect(VerifiedFact::count())->toBe(1);
    });

    it('refuse la création à qui n\'a pas knowledge_base.create', function (): void {
        // Le mentor n'a aucune permission sur ce module : il ne peut même pas
        // ouvrir le tiroir.
        $mentor = User::factory()->create(['otp_verified_at' => now()]);
        $mentor->assignRole(UserRole::Mentor->value);

        staffConnecte($mentor);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->assertForbidden();
    });

    it('laisse le modérateur rédiger un brouillon', function (): void {
        staffConnecte($this->moderateur);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Le concours de la fonction publique est reporté')
            ->set('analyse', '<p>Le ministère a publié un communiqué.</p>')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(VerifiedFact::first()->is_published)->toBeFalse();
    });
});

describe('création', function (): void {
    it('enregistre un brouillon, non servi aux citoyens', function (): void {
        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Le calendrier des examens nationaux 2026 est publié')
            ->set('verdict', VerdictFactCheck::Vrai->value)
            ->set('analyse', '<p>Le ministère a diffusé le calendrier officiel.</p>')
            ->set('sourcesTexte', "https://gouv.bj/calendrier | Gouvernement\nligne sans url")
            ->call('enregistrer')
            ->assertHasNoErrors();

        $fait = VerifiedFact::first();

        expect($fait->is_published)->toBeFalse()
            ->and($fait->verdict)->toBe(VerdictFactCheck::Vrai)
            // Une ligne sans URL n'est pas une source : elle disparaît.
            ->and($fait->nombreDeSources())->toBe(1)
            ->and($fait->content_fingerprint)->not->toBeNull();
    });

    it('propose des mots-clés depuis l\'affirmation, et les garde s\'ils sont repris à la main', function (): void {
        staffConnecte($this->admin);

        $composant = Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Une taxe WhatsApp instaurée au Bénin dès lundi');

        // Le champ est PRÉ-REMPLI : voir ce que la plateforme retiendra est la
        // seule façon de corriger un rapprochement avant qu'il ne serve.
        expect($composant->get('motsCles'))->toContain('whatsapp');

        $composant->set('motsCles', 'taxe whatsapp benin rumeur virale')
            ->set('affirmation', 'Une taxe WhatsApp instaurée au Bénin dès mardi')
            // La correction de l'affirmation n'écrase pas un choix humain.
            ->assertSet('motsCles', 'taxe whatsapp benin rumeur virale')
            ->set('analyse', '<p>Démenti officiel de l\'ARCEP.</p>')
            ->call('enregistrer');

        expect(VerifiedFact::first()->keywords)->toBe('taxe whatsapp benin rumeur virale');
    });

    it('refuse une affirmation trop courte pour être rapprochée de quoi que ce soit', function (): void {
        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Non')
            ->call('enregistrer')
            ->assertHasErrors(['affirmation']);

        expect(VerifiedFact::count())->toBe(0);
    });

    it('purifie l\'analyse AVANT persistance', function (): void {
        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Une affirmation quelconque à vérifier')
            ->set('analyse', '<p>Analyse légitime.</p><script>alert(1)</script>')
            ->call('enregistrer');

        expect(VerifiedFact::first()->summary)
            ->not->toContain('<script')
            ->toContain('Analyse légitime');
    });
});

describe('publication', function (): void {
    it('est un geste distinct d\'enregistrer', function (): void {
        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Campagne de vaccination gratuite dans le Littoral')
            ->set('verdict', VerdictFactCheck::Vrai->value)
            ->set('analyse', '<p>Le ministère de la santé confirme la campagne.</p>')
            ->set('sourcesTexte', 'https://sante.gouv.bj/campagne | Ministère de la Santé')
            ->call('publier')
            ->assertHasNoErrors();

        expect(VerifiedFact::first()->is_published)->toBeTrue();
    });

    it('refuse de publier un verdict sans source vérifiable', function (): void {
        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Une affirmation sérieuse mais non sourcée')
            ->set('analyse', '<p>Une analyse suffisamment longue pour passer la règle.</p>')
            ->set('sourcesTexte', '')
            ->call('publier')
            ->assertHasErrors(['sourcesTexte']);

        expect(VerifiedFact::count())->toBe(0);
    });

    it('refuse de publier sans analyse : le lecteur ne saurait pas pourquoi', function (): void {
        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirCreation')
            ->set('affirmation', 'Une affirmation sans la moindre analyse')
            ->set('sourcesTexte', 'https://arcep.bj/communique | ARCEP')
            ->call('publier')
            ->assertHasErrors(['analyse']);
    });

    it('bascule la publication depuis la ligne, et le journalise', function (): void {
        $fait = unFait(['is_published' => false]);

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)->call('basculerLaPublication', $fait->uuid);

        expect($fait->refresh()->is_published)->toBeTrue()
            ->and(AuditLog::where('action', AuditLogService::PUBLICATION)
                ->where('module', 'knowledge_base')->count())->toBe(1);
    });

    it('refuse de publier depuis la ligne un fait sans aucune source', function (): void {
        $fait = unFait(['is_published' => false, 'sources' => []]);

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)->call('basculerLaPublication', $fait->uuid);

        expect($fait->refresh()->is_published)->toBeFalse();
    });
});

describe('suppression', function (): void {
    it('nomme la cible avant de supprimer', function (): void {
        $fait = unFait();

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('confirmerSuppression', $fait->uuid)
            ->assertSet('libelleASupprimer', $fait->title)
            ->assertSee($fait->title);
    });

    it('supprime et laisse une trace au journal d\'audit', function (): void {
        $fait = unFait();

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('confirmerSuppression', $fait->uuid)
            ->call('supprimer');

        expect(VerifiedFact::count())->toBe(0)
            ->and(AuditLog::where('action', AuditLogService::SUPPRESSION)
                ->where('module', 'knowledge_base')->count())->toBe(1);
    });

    it('renonce sans rien détruire', function (): void {
        $fait = unFait();

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('confirmerSuppression', $fait->uuid)
            ->call('annulerSuppression')
            ->assertSet('uuidASupprimer', null);

        expect(VerifiedFact::count())->toBe(1);
    });
});

describe('filtres', function (): void {
    beforeEach(function (): void {
        unFait(['title' => 'Taxe WhatsApp au Bénin', 'verdict' => VerdictFactCheck::Faux->value]);
        unFait([
            'title' => 'Calendrier des examens nationaux',
            'keywords' => 'calendrier examens nationaux',
            'verdict' => VerdictFactCheck::Vrai->value,
            'is_published' => false,
        ]);

        staffConnecte($this->admin);
    });

    /*
     | Depuis la migration DataTables (14 septembre 2026), la liste n'est plus
     | rendue par le composant : elle est servie par `staff.knowledge.data`.
     | Ces tests lisent donc la source JSON — la même promesse (recherche sur
     | l'affirmation ET les mots-clés, filtres verdict et statut), sur le jeu
     | entier. Le détail du contrat est dans `TablesDuFactCheckingTest`.
     */
    $titres = static fn (array $parametres): array => collect(
        staffConnecte(test()->admin)->getJson(route('staff.knowledge.data', $parametres))->assertOk()->json('data'),
    )->pluck('affirmation')->all();

    it('recherche sur l\'affirmation et sur les mots-clés', function () use ($titres): void {
        expect($titres(['search' => ['value' => 'examens']]))->toBe(['Calendrier des examens nationaux']);
    });

    it('filtre par verdict', function () use ($titres): void {
        expect($titres(['filtres' => ['verdict' => VerdictFactCheck::Vrai->value]]))->toBe(['Calendrier des examens nationaux']);
    });

    it('filtre par statut', function () use ($titres): void {
        expect($titres(['filtres' => ['statut' => 'brouillon']]))->toBe(['Calendrier des examens nationaux']);
    });

    it('réinitialise tous les filtres à la fois', function () use ($titres): void {
        // L'état Livewire (#[Url]) reste celui que lit l'export CSV historique.
        Livewire::test(FaitsVerifies::class)
            ->set('recherche', 'examens')
            ->set('filtreVerdict', VerdictFactCheck::Vrai->value)
            ->set('filtreStatut', 'brouillon')
            ->call('reinitialiser')
            ->assertSet('recherche', '')
            ->assertSet('filtreVerdict', '')
            ->assertSet('filtreStatut', '');

        // Sans critère, la table rend tout.
        expect($titres([]))->toHaveCount(2)->toContain('Taxe WhatsApp au Bénin');
    });
});

describe('états vides', function (): void {
    it('distingue « rien encore créé » de « aucun résultat »', function (): void {
        /*
         | Deux vides DISTINCTS, désormais rendus par DataTables : `emptyTable`
         | quand la base est vide (recordsTotal = 0), `zeroRecords` et un bouton
         | « Réinitialiser les filtres » quand rien ne correspond (recordsTotal
         | > 0). Le serveur fournit la distinction ; la page déclare les deux
         | messages.
         */
        staffConnecte($this->admin)->getJson(route('staff.knowledge.data'))
            ->assertJsonPath('recordsTotal', 0)
            ->assertJsonPath('recordsFiltered', 0);

        unFait();

        staffConnecte($this->admin)->getJson(route('staff.knowledge.data', ['search' => ['value' => 'une rumeur qui n\'existe pas']]))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 0);

        expect((string) file_get_contents(public_path('assets/js/pages/staff-faits-verifies.js')))
            ->toContain('La base ne contient encore aucun fact check.')
            ->toContain('Aucun fact check ne correspond à vos critères.');

        // L'entrée pour saisir le premier fait reste dans l'en-tête.
        Livewire::test(FaitsVerifies::class)->assertSee('Nouveau fact check');
    });
});

describe('indicateurs', function (): void {
    it('comptent ce qui est réellement en base, jamais des chiffres de gabarit', function (): void {
        unFait();
        unFait(['title' => 'Un deuxième fait', 'verdict' => VerdictFactCheck::Vrai->value]);
        unFait(['title' => 'Un brouillon', 'is_published' => false]);

        staffConnecte($this->admin);

        // 2 publiés, dont 1 démenti — et rien qui ressemble aux 1 962 du
        // prototype.
        Livewire::test(FaitsVerifies::class)
            ->assertSee('Fact checks publiés')
            ->assertSeeHtml('<p class="st-kpi-v">2</p>')
            ->assertDontSee('1 962');
    });
});

describe('import et export', function (): void {
    it('exporte le périmètre filtré en CSV', function (): void {
        unFait();

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('exporter')
            ->assertFileDownloaded('fact-checks-'.now()->format('Y-m-d').'.csv');

        expect(AuditLog::query()->where('action', 'knowledge_base.export')->exists())->toBeTrue();
    });

    it('refuse l’export à qui consulte sans knowledge_base.export, et masque le bouton', function (): void {
        // Arbitrage du 14 septembre 2026 : le modérateur rédige et consulte la
        // base, il ne l'exporte pas — brouillons compris.
        unFait();

        staffConnecte($this->moderateur);

        Livewire::test(FaitsVerifies::class)
            ->assertDontSeeHtml('wire:click="exporter"')
            ->call('exporter')
            ->assertForbidden();

        expect(AuditLog::query()->where('action', 'knowledge_base.export')->exists())->toBeFalse();
    });

    it('importe en BROUILLONS et rend compte des lignes ignorées', function (): void {
        staffConnecte($this->admin);

        $csv = "affirmation;verdict;sources;analyse;mots-cles\n"
            ."Le concours de police est reporté;false;https://gouv.bj/police | Gouvernement;<p>Démenti officiel.</p>;concours police\n"
            ."Non;false;;;\n"
            ."Un verdict inconnu du référentiel;peut-etre;;;\n";

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirImport')
            ->set('fichierImport', UploadedFile::fake()->createWithContent('faits.csv', $csv))
            ->call('importer')
            ->assertHasNoErrors();

        expect(VerifiedFact::count())->toBe(1)
            ->and(VerifiedFact::first()->is_published)->toBeFalse();
    });

    it("accepte le libellé français du tableur même quand l'écran est en anglais", function (): void {
        /*
         | UN FICHIER NE CHANGE PAS DE LANGUE PARCE QUE SON LECTEUR EN A CHANGÉ.
         |
         | Le lecteur de verdicts comparait la colonne à `libelle()`, qui suit
         | la langue de l'interface. Il suffisait qu'un membre du personnel ait
         | consulté le site public en anglais — la préférence vit en session —
         | pour que « trompeur » cesse d'être reconnu. Le même tableur, importé
         | la veille sans encombre, voyait ses lignes ignorées sans explication.
         |
         | Les deux langues sont désormais acceptées, dans les deux sens : un
         | tableur préparé en anglais passe aussi, et personne n'a à deviner
         | laquelle était attendue.
         */
        staffConnecte($this->admin);

        app()->setLocale('en');

        $csv = "affirmation;verdict;sources;analyse;mots-cles\n"
            ."Une affirmation en partie exacte;trompeur;;;\n"
            ."Une affirmation reprise en anglais;misleading;;;\n"
            ."Une affirmation par sa valeur;unverifiable;;;\n";

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirImport')
            ->set('fichierImport', UploadedFile::fake()->createWithContent('faits.csv', $csv))
            ->call('importer')
            ->assertHasNoErrors();

        expect(VerifiedFact::count())->toBe(3)
            ->and(VerifiedFact::where('verdict', VerdictFactCheck::Trompeur->value)->count())->toBe(2)
            ->and(VerifiedFact::where('verdict', VerdictFactCheck::NonVerifiable->value)->count())->toBe(1);
    });

    it('refuse l\'import à qui ne peut pas créer', function (): void {
        $mentor = User::factory()->create(['otp_verified_at' => now()]);
        $mentor->assignRole(UserRole::Mentor->value);

        staffConnecte($mentor);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirImport')
            ->assertForbidden();
    });
});

describe('aperçu', function (): void {
    it('purifie l\'analyse À L\'AFFICHAGE, en plus de la persistance', function (): void {
        // Contenu injecté hors du service — un import passé, une commande de
        // maintenance : la vue ne doit pas s'y fier.
        $fait = unFait(['summary' => '<p>Analyse.</p><script>alert(1)</script>']);

        staffConnecte($this->admin);

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirApercu', $fait->uuid)
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSee('Analyse.');
    });
});
