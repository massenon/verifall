<?php

declare(strict_types=1);

use App\Console\Commands\CalculerLesScoresDePontage;
use App\Core\Application\Contracts\DepotNotes;
use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\NotesDeCommunaute;
use App\Core\Community\Services\ReputationCitoyenne;
use App\Core\Community\Services\ScoreDePontage;
use App\Enums\StatutNoteCommunaute;
use App\Enums\StatutSignalementContenu;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\NotesDuSujet;
use App\Models\AnalysisReport;
use App\Models\CommunityNote;
use App\Models\CommunityNoteVote;
use App\Models\ContentReport;
use App\Models\Thread;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\CommunauteSeeder;
use Database\Seeders\NotesEtSondagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Notes de communauté — le pontage, pas la majorité
|--------------------------------------------------------------------------
| Le test central de ce lot est celui du BORD : une note approuvée par un seul
| camp ne doit PAS être publiée, même à l'unanimité de ce camp. C'est toute la
| différence entre « ce qui a des chances d'être vrai » et « ce qui plaît au
| groupe le plus nombreux » — et c'est la seule raison d'écrire un algorithme
| de pontage plutôt qu'un compteur de pouces levés.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->auteur = compteCitoyen('auteur');
    $this->sujet = app(AnimationDuForum::class)->publier(
        $this->auteur,
        'Cette ordonnance de 2026 supprime-t-elle la taxe sur le carburant ?',
        'Le texte circule en capture d\'écran depuis ce matin.',
    );
});

function compteCitoyen(string $nom): User
{
    $utilisateur = User::factory()->actif()->create(['name' => $nom]);
    $utilisateur->assignRole(UserRole::Citoyen->value);

    return $utilisateur;
}

/** Une note enregistrée directement, pour les scénarios de vote. */
function noteDeTest(Thread $sujet, User $auteur, string $texte = 'Le texte cité date en réalité de 2019 et a été abrogé depuis.'): CommunityNote
{
    return app(NotesDeCommunaute::class)->proposer(
        $auteur,
        $sujet,
        $texte,
        ['https://sgg.gouv.bj/journal-officiel/'],
    );
}

/**
 * Pose une série de votes d'un coup.
 *
 * @param  array<string, bool>  $votes  nom de compte => utile
 * @param  array<string, User>  $comptes
 */
function voterEnBloc(CommunityNote $note, array $votes, array $comptes): void
{
    foreach ($votes as $nom => $utile) {
        CommunityNoteVote::create([
            'community_note_id' => $note->id,
            'user_id' => $comptes[$nom]->id,
            'helpful' => $utile,
        ]);
    }

    app(DepotNotes::class)->recompter($note->refresh());
}

describe('proposition d\'une note', function (): void {
    it('refuse une note sans source', function (): void {
        // Une note sans source n'est qu'un avis de plus — exactement ce dont un
        // fil polarisé n'a pas besoin.
        expect(fn () => app(NotesDeCommunaute::class)->proposer(
            $this->auteur,
            $this->sujet,
            'Cette information est fausse, tout le monde le sait très bien ici.',
            [],
        ))->toThrow(ValidationException::class);

        expect(CommunityNote::count())->toBe(0);
    });

    it('refuse une « source » qui n\'est pas une adresse web', function (): void {
        expect(fn () => app(NotesDeCommunaute::class)->proposer(
            $this->auteur,
            $this->sujet,
            'Cette information est fausse, je l\'ai lue quelque part ce matin.',
            ['je l\'ai vu sur WhatsApp', 'javascript:alert(1)'],
        ))->toThrow(ValidationException::class);
    });

    it('naît toujours en attente, jamais publiée', function (): void {
        $note = noteDeTest($this->sujet, compteCitoyen('note-auteur'));

        // Aucune note n'est publiée par le seul fait d'avoir été écrite.
        expect($note->status)->toBe(StatutNoteCommunaute::EnAttente)
            ->and($note->bridging_score)->toBeNull();
    });

    it('n\'accepte qu\'une note par personne et par sujet', function (): void {
        $redacteur = compteCitoyen('redacteur');
        noteDeTest($this->sujet, $redacteur);

        expect(fn () => noteDeTest($this->sujet, $redacteur, 'Une seconde version de la même note, au cas où.'))
            ->toThrow(ValidationException::class);
    });
});

describe('vote sur une note', function (): void {
    it('remplace le vote précédent au lieu d\'en ajouter un', function (): void {
        $note = noteDeTest($this->sujet, compteCitoyen('note-auteur'));
        $votant = compteCitoyen('votant');

        app(NotesDeCommunaute::class)->voter($votant, $note, true);
        app(NotesDeCommunaute::class)->voter($votant, $note, false);

        // Une seule ligne : deux feraient croire à l'algorithme de pontage
        // qu'un votant acharné est une communauté.
        expect(CommunityNoteVote::where('community_note_id', $note->id)->count())->toBe(1)
            ->and($note->refresh()->helpful_count)->toBe(0)
            ->and($note->not_helpful_count)->toBe(1);
    });

    it('refuse qu\'on vote sur sa propre note', function (): void {
        $redacteur = compteCitoyen('redacteur');
        $note = noteDeTest($this->sujet, $redacteur);

        expect(fn () => app(NotesDeCommunaute::class)->voter($redacteur, $note, true))
            ->toThrow(ValidationException::class);
    });
});

describe('algorithme de pontage', function (): void {
    /*
     | Le décor : deux camps qui votent systématiquement à l'opposé.
     |
     | Trois notes d'historique suffisent à établir la polarisation — en
     | dessous, aucun accord n'est mesurable et l'algorithme se tait, ce qui
     | est justement le comportement testé plus bas.
     */
    beforeEach(function (): void {
        $this->comptes = [];

        foreach (['a1', 'a2', 'a3', 'b1', 'b2', 'b3'] as $nom) {
            $this->comptes[$nom] = compteCitoyen($nom);
        }

        $this->redacteur = compteCitoyen('redacteur');

        $historique = app(AnimationDuForum::class)->publier(
            $this->redacteur,
            'Fil d\'historique servant à établir qui vote avec qui',
            'Support des notes qui polarisent.',
        );

        $camps = [
            ['a1' => true, 'a2' => true, 'a3' => true, 'b1' => false, 'b2' => false, 'b3' => false],
            ['a1' => true, 'a2' => true, 'a3' => true, 'b1' => false, 'b2' => false, 'b3' => false],
            ['a1' => false, 'a2' => false, 'a3' => false, 'b1' => true, 'b2' => true, 'b3' => true],
        ];

        foreach ($camps as $index => $votes) {
            $note = CommunityNote::create([
                'uuid' => (string) Str::uuid(),
                'thread_id' => $historique->id,
                'user_id' => $this->redacteur->id,
                'body' => 'Note d\'historique numéro '.($index + 1),
                'sources' => ['https://example.org/historique'],
                'status' => StatutNoteCommunaute::EnAttente->value,
            ]);

            voterEnBloc($note, $votes, $this->comptes);
        }
    });

    it('publie une note que les DEUX camps jugent utile', function (): void {
        $note = noteDeTest($this->sujet, $this->redacteur);

        // a1, a2 d'un bord ; b1, b2 de l'autre. a3 n'est pas convaincu.
        voterEnBloc($note, ['a1' => true, 'a2' => true, 'a3' => false, 'b1' => true, 'b2' => true], $this->comptes);

        app(ScoreDePontage::class)->recalculerTout();

        expect($note->refresh()->status)->toBe(StatutNoteCommunaute::Publiee)
            ->and((float) $note->bridging_score)->toBeGreaterThanOrEqual(ScoreDePontage::SEUIL_DE_PUBLICATION);
    });

    it('NE publie PAS une note approuvée par un seul bord', function (): void {
        $note = noteDeTest($this->sujet, $this->redacteur);

        // Approuvée à l'unanimité du camp A, rejetée par le camp B : c'est un
        // vote majoritaire au sein d'un groupe, pas un pont.
        voterEnBloc($note, ['a1' => true, 'a2' => true, 'a3' => true, 'b1' => false, 'b2' => false], $this->comptes);

        app(ScoreDePontage::class)->recalculerTout();

        expect($note->refresh()->status)->toBe(StatutNoteCommunaute::NonRetenue)
            ->and((float) $note->bridging_score)->toBeLessThan(ScoreDePontage::SEUIL_DE_PUBLICATION);
    });

    it('ne publie rien en dessous du minimum de votes, et dit « en attente »', function (): void {
        $note = noteDeTest($this->sujet, $this->redacteur);

        // Quatre votes, tous favorables, et venus des deux camps : le pont
        // serait parfait — sauf qu'on n'en sait rien avec si peu de monde.
        voterEnBloc($note, ['a1' => true, 'a2' => true, 'b1' => true, 'b2' => true], $this->comptes);

        expect(count(['a1', 'a2', 'b1', 'b2']))->toBeLessThan(ScoreDePontage::VOTES_MINIMUM);

        app(ScoreDePontage::class)->recalculerTout();

        $note->refresh();

        expect($note->status)->toBe(StatutNoteCommunaute::EnAttente)
            // Pas de zéro : « pas mesurable » n'est pas « nul ». Écrire 0
            // effacerait la différence entre une note qui attend et une note
            // qui a échoué.
            ->and($note->bridging_score)->toBeNull();
    });

    it('se tait quand aucune paire d\'approbateurs n\'a d\'historique commun', function (): void {
        // Cinq votants tout neufs : le nombre de votes suffit, mais aucune
        // polarisation n'est mesurable. C'est le démarrage à froid.
        $inconnus = [];

        foreach (['n1', 'n2', 'n3', 'n4', 'n5'] as $nom) {
            $inconnus[$nom] = compteCitoyen($nom);
        }

        $note = noteDeTest($this->sujet, $this->redacteur);

        voterEnBloc($note, ['n1' => true, 'n2' => true, 'n3' => true, 'n4' => true, 'n5' => false], $inconnus);

        app(ScoreDePontage::class)->recalculerTout();

        expect($note->refresh()->status)->toBe(StatutNoteCommunaute::EnAttente)
            ->and($note->bridging_score)->toBeNull();
    });

    it('n\'écrit rien tant que la commande planifiée n\'a pas tourné', function (): void {
        $note = noteDeTest($this->sujet, $this->redacteur);

        voterEnBloc($note, ['a1' => true, 'a2' => true, 'a3' => false, 'b1' => true, 'b2' => true], $this->comptes);

        // Le calcul est quadratique : il ne vit pas dans la requête web. Une
        // note franchit donc le pont au prochain passage de la commande, pas
        // au moment du vote qui la fait basculer.
        expect($note->refresh()->status)->toBe(StatutNoteCommunaute::EnAttente);

        $this->artisan(CalculerLesScoresDePontage::class)->assertSuccessful();

        expect($note->refresh()->status)->toBe(StatutNoteCommunaute::Publiee);
    });
});

describe('ce que l\'écran montre', function (): void {
    it('dit « en attente » à l\'auteur, et jamais « rejetée »', function (): void {
        $redacteur = compteCitoyen('redacteur');
        noteDeTest($this->sujet, $redacteur);

        Livewire::actingAs($redacteur)
            ->test(NotesDuSujet::class, ['slug' => $this->sujet->slug])
            ->assertSee('En attente d\'évaluation')
            ->assertDontSee('rejetée')
            ->assertDontSee('refusée');
    });

    it('cache la note en attente aux autres lecteurs, hors file d\'évaluation', function (): void {
        $redacteur = compteCitoyen('redacteur');
        noteDeTest($this->sujet, $redacteur, 'Le décret cité a été abrogé en février 2020 par un texte ultérieur.');

        $passant = compteCitoyen('passant');

        // Repliée par défaut : le texte de la note n'est pas rendu tant qu'on
        // n'a pas ouvert la file. Elle n'est donc pas publiée de fait.
        Livewire::actingAs($passant)
            ->test(NotesDuSujet::class, ['slug' => $this->sujet->slug])
            ->assertDontSee('Le décret cité a été abrogé')
            ->assertSee('à évaluer');
    });

    it('n\'affiche jamais le score de pontage à un citoyen', function (): void {
        $redacteur = compteCitoyen('redacteur');
        $note = noteDeTest($this->sujet, $redacteur);

        $note->forceFill([
            'status' => StatutNoteCommunaute::Publiee->value,
            'bridging_score' => 0.4267,
        ])->save();

        // Hors contexte, ce nombre ne veut rien dire — et l'exposer invite à
        // le jouer.
        Livewire::actingAs(compteCitoyen('lecteur'))
            ->test(NotesDuSujet::class, ['slug' => $this->sujet->slug])
            ->assertSee('Contexte ajouté par la communauté')
            ->assertDontSee('0.4267')
            ->assertDontSee('0,4267');
    });
});

describe('réputation citoyenne', function (): void {
    it('se calcule depuis les vérifications confirmées, pas depuis les messages', function (): void {
        $bavard = compteCitoyen('bavard');
        $vigie = compteCitoyen('vigie');

        // Quarante messages : beaucoup de bruit, aucune vérification.
        for ($i = 0; $i < 40; $i++) {
            app(AnimationDuForum::class)->repondre($bavard, $this->sujet, 'Message numéro '.$i);
        }

        // Un seul signalement, mais il a produit un démenti.
        $verification = Verification::create([
            'uuid' => (string) Str::uuid(),
            'type' => TypeContenu::Texte->value,
            'status' => StatutVerification::Terminee->value,
            'claim' => 'La taxe sur le carburant a été supprimée en 2026.',
            // Gratuite : c'est la plateforme qui décide de vérifier, pas le
            // citoyen qui la commande.
            'cost_units' => 0,
        ]);

        AnalysisReport::create([
            'uuid' => (string) Str::uuid(),
            'verification_id' => $verification->id,
            'verdict' => VerdictFactCheck::Faux->value,
            'confidence' => 88,
        ]);

        $sujetEscalade = app(AnimationDuForum::class)->publier($vigie, 'Rumeur sur la taxe carburant', 'Vue sur WhatsApp.');
        $sujetEscalade->forceFill([
            'verification_id' => $verification->id,
            'escalated_by' => $vigie->id,
            'escalated_at' => now(),
        ])->save();

        $reputation = app(ReputationCitoyenne::class);

        expect($reputation->pour($bavard)['points'])->toBe(0)
            ->and($reputation->pour($bavard)['palier'])->toBe('Aucune vérification confirmée')
            ->and($reputation->pour($vigie)['dementis'])->toBe(1)
            ->and($reputation->pour($vigie)['points'])->toBe(ReputationCitoyenne::POINTS_DEMENTI);
    });

    it('compte les signalements retenus et retranche les signalements rejetés', function (): void {
        $signaleur = compteCitoyen('signaleur');

        foreach ([StatutSignalementContenu::Retenu, StatutSignalementContenu::Retenu, StatutSignalementContenu::Rejete] as $statut) {
            ContentReport::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $signaleur->id,
                'reportable_type' => (new Thread)->getMorphClass(),
                'reportable_id' => $this->sujet->id,
                'reason' => 'desinformation',
                'status' => $statut->value,
            ]);
        }

        // 2 × 3 − 2 = 4. Quelqu'un qui se trompe une fois sur trois reste
        // largement bénéficiaire : on veut qu'il continue de signaler.
        expect(app(ReputationCitoyenne::class)->pour($signaleur)['points'])
            ->toBe(2 * ReputationCitoyenne::POINTS_SIGNALEMENT_RETENU + ReputationCitoyenne::POINTS_SIGNALEMENT_REJETE);
    });

    it('ne descend jamais en dessous de zéro', function (): void {
        $maladroit = compteCitoyen('maladroit');

        foreach (range(1, 5) as $ignore) {
            ContentReport::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $maladroit->id,
                'reportable_type' => (new Thread)->getMorphClass(),
                'reportable_id' => $this->sujet->id,
                'reason' => 'spam',
                'status' => StatutSignalementContenu::Rejete->value,
            ]);
        }

        // Un score négatif serait une sanction publique que personne n'a
        // prononcée : le malus freine la montée, il ne salit pas.
        expect(app(ReputationCitoyenne::class)->pour($maladroit)['points'])->toBe(0);
    });
});

describe('jeu de démonstration', function (): void {
    it('produit les trois états, calculés par le vrai algorithme', function (): void {
        // Un seeder qui écrirait les statuts à la main finirait par diverger de
        // ce que l'algorithme produit, et la démonstration mentirait sur le
        // produit. Ce test l'interdit.
        // Huit comptes : le pontage a besoin de deux camps de trois, d'un
        // rédacteur et d'un second rédacteur. En dessous, le seeder refuse de
        // faire semblant — et il a raison.
        foreach (range(1, 8) as $rang) {
            compteCitoyen('demo-'.$rang);
        }

        $this->seed(CommunauteSeeder::class);
        $this->seed(NotesEtSondagesSeeder::class);

        $statuts = CommunityNote::query()->pluck('status');

        expect($statuts->contains(StatutNoteCommunaute::Publiee))->toBeTrue()
            ->and($statuts->contains(StatutNoteCommunaute::NonRetenue))->toBeTrue()
            ->and($statuts->contains(StatutNoteCommunaute::EnAttente))->toBeTrue();
    });
});

describe('modération des notes', function (): void {
    it('laisse signaler une note à la modération', function (): void {
        // L'algorithme de pontage juge des ponts, pas des insultes : une note
        // injurieuse doit avoir une issue humaine, sinon elle traîne dans la
        // file d'évaluation jusqu'à ce qu'assez de monde l'ait lue.
        $redacteur = compteCitoyen('redacteur');
        $note = noteDeTest($this->sujet, $redacteur);

        Livewire::actingAs(compteCitoyen('lecteur'))
            ->test(NotesDuSujet::class, ['slug' => $this->sujet->slug])
            ->call('ouvrirSignalement', $note->uuid)
            ->set('motifSignalement', 'haine')
            ->call('signaler')
            ->assertHasNoErrors();

        expect(ContentReport::where('reportable_type', (new CommunityNote)->getMorphClass())
            ->where('reportable_id', $note->id)
            ->where('status', StatutSignalementContenu::EnAttente->value)
            ->exists())->toBeTrue();
    });

    it('exige un motif', function (): void {
        $note = noteDeTest($this->sujet, compteCitoyen('redacteur'));

        Livewire::actingAs(compteCitoyen('lecteur'))
            ->test(NotesDuSujet::class, ['slug' => $this->sujet->slug])
            ->call('ouvrirSignalement', $note->uuid)
            ->call('signaler')
            ->assertHasErrors('motifSignalement');
    });
});

describe('vote depuis l\'écran', function (): void {
    it('enregistre le vote sans passer par le service à la main', function (): void {
        // La méthode du composant reçoit deux arguments du navigateur PUIS un
        // service injecté : c'est l'ordre qui casse en silence si on l'inverse.
        $note = noteDeTest($this->sujet, compteCitoyen('redacteur'));

        Livewire::actingAs(compteCitoyen('lecteur'))
            ->test(NotesDuSujet::class, ['slug' => $this->sujet->slug])
            ->call('voter', $note->uuid, true)
            ->assertHasNoErrors();

        expect($note->refresh()->helpful_count)->toBe(1);
    });
});
