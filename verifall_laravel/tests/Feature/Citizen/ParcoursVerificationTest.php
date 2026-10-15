<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\CoherenceRapport;
use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\EtapePipeline;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Jobs\AnalyserVerification;
use App\Livewire\Citizen\Rapport;
use App\Livewire\Citizen\VerificationEnCours;
use App\Livewire\Public\VerifierInvite;
use App\Models\User;
use App\Models\Verification;
use App\Support\Hashing\IdentityHasher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Parcours : vérifier → analyse en cours → rapport
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 20])->save();

    Queue::fake();
});

function verifDe(User $utilisateur, string $texte = 'La taxe sur les transferts double lundi prochain.'): Verification
{
    return app(SoumissionVerificationService::class)
        ->soumettre($utilisateur, TypeContenu::Texte, $texte);
}

describe('analyse en cours', function (): void {
    it('refuse la vérification de quelqu\'un d\'autre', function (): void {
        $autre = User::factory()->actif()->create();
        $autre->forceFill(['units' => 5])->save();
        $verification = verifDe($autre);

        // Un uuid deviné ne doit renseigner sur rien, pas même sur
        // l'existence de la vérification.
        $this->actingAs($this->citoyen)
            ->get(route('citizen.verification', ['verification' => $verification->uuid]))
            ->assertNotFound();
    });

    it('affiche les six étapes du prototype', function (): void {
        $html = Livewire::actingAs($this->citoyen)
            ->test(VerificationEnCours::class, ['verification' => verifDe($this->citoyen)->uuid])
            ->html();

        foreach (EtapePipeline::cases() as $etape) {
            // Blade échappe les apostrophes : « l'analyse » devient
            // « l&#039;analyse ». Comparer le texte brut échouerait sur la
            // moitié des libellés.
            expect($html)->toContain(e($etape->libelle()));
        }
    });

    it('reflète l\'avancement réel du pipeline, pas un minuteur', function (): void {
        $verification = verifDe($this->citoyen);

        $avantAnalyse = Livewire::actingAs($this->citoyen)
            ->test(VerificationEnCours::class, ['verification' => $verification->uuid]);

        // Rien n'a encore tourné : la barre reste à zéro.
        expect($avantAnalyse->get('uuid'))->toBe($verification->uuid)
            ->and($verification->avancement())->toBe(0);

        app(OrchestrateurVerification::class)->executer($verification);

        expect($verification->fresh()->avancement())->toBe(100);
    });

    it('enregistre la chronologie horodatée des étapes', function (): void {
        $verification = verifDe($this->citoyen);
        app(OrchestrateurVerification::class)->executer($verification);

        $chronologie = $verification->fresh()->report->timeline;

        expect($chronologie)->toHaveCount(count(EtapePipeline::cases()))
            ->and($chronologie[0]['etape'])->toBe('Reception')
            ->and($chronologie[0])->toHaveKey('at');
    });

    it('emmène au rapport dès qu\'il est prêt', function (): void {
        $verification = verifDe($this->citoyen);
        app(OrchestrateurVerification::class)->executer($verification);

        Livewire::actingAs($this->citoyen)
            ->test(VerificationEnCours::class, ['verification' => $verification->uuid])
            ->call('suivre')
            ->assertRedirect(route('citizen.report', ['verification' => $verification->uuid]));
    });

    it('dit franchement quand l\'analyse traîne', function (): void {
        $composant = Livewire::actingAs($this->citoyen)
            ->test(VerificationEnCours::class, ['verification' => verifDe($this->citoyen)->uuid]);

        /*
         | SOIXANTE-ET-UN, et non trente-et-un.
         |
         | `VerificationEnCours::ATTENTES_AVANT_ALERTE` est passé de 30 à 60 —
         | soixante interrogations à ~2,5 s font plus de deux minutes, la marge
         | qu'exigent trois appels d'API successifs. Le seuil a bougé sans que
         | ce test suive, et il était rouge depuis.
         */
        for ($i = 0; $i < 61; $i++) {
            $composant->call('suivre');
        }

        // Une roue qui tourne indéfiniment laisse croire que le rapport va
        // arriver.
        expect($composant->html())->toContain('plus de temps que prévu');
    });
});

describe('cohérence du rapport', function (): void {
    it('accepte un verdict soutenu par les moteurs', function (): void {
        $resultats = [
            VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80),
            VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80),
        ];

        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Faux, 'Les sources consultées contredisent cette information.',
            $resultats, [PositionPreuve::Contredit],
        ))->toBeNull();
    });

    it('refuse une synthèse qui contredit le verdict', function (): void {
        // Un rapport qui titre « FAUX » et dont la synthèse dit « exacte »
        // détruit la confiance plus sûrement qu'une erreur franche.
        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Faux,
            'Après vérification, cette information est exacte selon nos sources.',
            [VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80)],
            [],
        ))->toContain('synthèse contredit');
    });

    it('refuse un verdict que les moteurs ne soutiennent pas', function (): void {
        $resultats = [
            VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80),
            VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80),
            VerdictMoteur::rendu(MoteurAnalyse::OpenAI, VerdictFactCheck::Vrai, 80),
        ];

        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Vrai, null, $resultats, [],
        ))->toContain('majorité des moteurs');
    });

    it('refuse un dossier où AUCUN raisonnement ne tient le verdict', function (): void {
        /*
        | Le contrôle porte sur ce que le rapport AFFICHERA, pas seulement sur
        | la phrase serveur — laquelle est composée depuis le verdict et ne
        | peut donc jamais le contredire. C'est le verrou 3 de
        | NEWINSTRUCTION14 : le texte du moteur occupe 80 % de la page.
        */
        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Faux,
            'Les sources consultées contredisent cette information.',
            [
                VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80,
                    [], 'Pourtant, les autorités confirment cette information.'),
                VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80,
                    [], 'Cette information est exacte selon nos sources.'),
            ],
            [PositionPreuve::Contredit],
        ))->toContain('Aucun raisonnement');
    });

    it('accepte un dossier dont UN SEUL raisonnement tient le verdict', function (): void {
        // Les moteurs ont le droit de diverger : un seul texte affichable
        // suffit à ce que le rapport puisse s'expliquer.
        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Faux,
            'Les sources consultées contredisent cette information.',
            [
                VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Vrai, 80,
                    [], 'Cette information est exacte.'),
                VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80,
                    [], 'Les sources consultées contredisent cette information.'),
            ],
            [PositionPreuve::Contredit],
        ))->toBeNull();
    });

    it('ignore les raisonnements vides et les moteurs en panne', function (): void {
        // Ni texte ni panne : rien à confronter, la phrase serveur suffit.
        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Faux, null,
            [VerdictMoteur::echec(MoteurAnalyse::Gemini, 'Service indisponible')],
            [],
        ))->toBeNull();
    });

    it('refuse un verdict que les sources renversent', function (): void {
        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Vrai,
            null,
            [VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Vrai, 80)],
            [PositionPreuve::Contredit, PositionPreuve::Contredit,
                PositionPreuve::Contredit, PositionPreuve::Confirme],
        ))->toContain('sources consultées contredisent');
    });

    it('tolère des sources des deux bords', function (): void {
        // Un dossier honnête contient normalement des sources contraires :
        // s'en alarmer bloquerait toutes les vérifications sérieuses.
        expect(app(CoherenceRapport::class)->incoherence(
            VerdictFactCheck::Faux,
            null,
            [VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80)],
            [PositionPreuve::Contredit, PositionPreuve::Contredit, PositionPreuve::Confirme],
        ))->toBeNull();
    });

    it('bloque la publication d\'un rapport incohérent', function (): void {
        $verification = verifDe($this->citoyen);

        // On force l'incohérence par un verdict que rien ne soutient.
        config()->set('factcheck.decision.ecart_minimum', 0);
        config()->set('factcheck.decision.score_minimum', 0);

        app(OrchestrateurVerification::class)->executer($verification);

        $verification->refresh();

        // Terminée ou en arbitrage, mais jamais publiée en se contredisant.
        if ($verification->status === StatutVerification::RevueHumaine) {
            expect($verification->report->published_at)->toBeNull();
        } else {
            expect(app(CoherenceRapport::class)->syntheseSuitLeVerdict(
                $verification->report->verdict, $verification->report->summary,
            ))->toBeTrue();
        }
    });
});

describe('page de rapport', function (): void {
    it('refuse le rapport de quelqu\'un d\'autre', function (): void {
        $autre = User::factory()->actif()->create();
        $autre->forceFill(['units' => 5])->save();
        $verification = verifDe($autre);
        app(OrchestrateurVerification::class)->executer($verification);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.report', ['verification' => $verification->uuid]))
            ->assertNotFound();
    });

    it('affiche verdict, synthèse, moteurs et sources sans se contredire', function (): void {
        $verification = verifDe($this->citoyen);
        app(OrchestrateurVerification::class)->executer($verification);

        $rapport = $verification->fresh()->report;

        /*
         | LE TEXTE EST DÉCODÉ AVANT D'ÊTRE CHERCHÉ.
         |
         | Depuis que ces libellés passent par les fichiers de langue, Blade les
         | rend avec la syntaxe échappée et l'apostrophe devient `&#039;`. Ce
         | que le visiteur LIT est inchangé ; seul l'encodage du source diffère.
         |
         | Chercher la chaîne brute reviendrait à éprouver la façon dont le HTML
         | est encodé plutôt que ce qui s'affiche, et ferait échouer ce test à
         | chaque libellé traduit.
         */
        $html = html_entity_decode(
            Livewire::actingAs($this->citoyen)
                ->test(Rapport::class, ['verification' => $verification->uuid])->html(),
            ENT_QUOTES,
            'UTF-8',
        );

        /*
         | Le libellé du RAPPORT, pas le libellé court.
         |
         | Ce test cherchait `mb_strtoupper($rapport->verdict->libelle())`,
         | c'est-à-dire « FAUX » en capitales dans le source HTML. La page n'a
         | jamais affiché cela : elle annonce « Information fausse », et les
         | capitales viennent du STYLE — règle posée dans `lang/fr/verdicts.php`,
         | parce qu'une chaîne écrite en capitales est lue lettre par lettre par
         | certains lecteurs d'écran. Le test était rouge avant le lot 1.
         */
        expect($html)->toContain($rapport->verdict->libelleRapport())
            ->toContain('Moteurs d\'analyse mobilisés')
            ->toContain('Sources et preuves consultées')
            ->toContain('Hate Speech Indicator');

        // Le contrôle qui garde le pipeline garde aussi ce qui est affiché.
        expect(app(CoherenceRapport::class)->syntheseSuitLeVerdict(
            $rapport->verdict, $rapport->summary,
        ))->toBeTrue();
    });

    it('colore la barre de fiabilité selon le verdict', function (): void {
        $verification = verifDe($this->citoyen);
        app(OrchestrateurVerification::class)->executer($verification);

        $rapport = $verification->fresh()->report;

        $html = Livewire::actingAs($this->citoyen)
            ->test(Rapport::class, ['verification' => $verification->uuid])->html();

        // Une barre verte sous « non vérifiable » dirait « tout va bien » là où
        // il n'y a aucune assurance ; sous « faux », elle féliciterait le
        // lecteur d'avoir trouvé une intox.
        expect($html)->toContain($rapport->verdict->couleurBarre());
    });

    it('renvoie vers l\'attente quand le rapport n\'existe pas encore', function (): void {
        $verification = verifDe($this->citoyen);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.report', ['verification' => $verification->uuid]))
            ->assertRedirect(route('citizen.verification', ['verification' => $verification->uuid]));
    });

    it('distingue un moteur en panne d\'un moteur qui apporte des sources', function (): void {
        $verification = verifDe($this->citoyen);
        app(OrchestrateurVerification::class)->executer($verification);

        // Les deux cas sont fabriqués explicitement : les faire dépendre du
        // hasard de la simulation rendrait le test muet une fois sur deux.
        $verification->engineRuns()->create([
            'engine' => MoteurAnalyse::TinEye->value,
            'failed' => true,
            'fail_reason' => 'Service indisponible',
        ]);

        $verification->engineRuns()->create([
            'engine' => MoteurAnalyse::GoogleCustomSearch->value,
            'verdict' => null,
            'failed' => false,
        ]);

        $html = Livewire::actingAs($this->citoyen)
            ->test(Rapport::class, ['verification' => $verification->uuid])->html();

        // « Indisponible » et « Sources apportées » ne veulent pas dire la même
        // chose : confondre les deux ferait croire à des pannes.
        expect($html)->toContain('Indisponible')
            ->and($html)->toContain('Sources apport');
    });
});

describe('vérification sans compte', function (): void {
    beforeEach(function (): void {
        RateLimiter::clear('verif-invite:'.app(IdentityHasher::class)->hash('127.0.0.1'));
    });

    it('est accessible sans session', function (): void {
        $this->get(route('public.verify'))->assertOk();
    });

    it('lance l\'analyse sans compte ni unités', function (): void {
        $composant = Livewire::test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'La taxe WhatsApp entre en vigueur lundi au Bénin.')
            ->call('lancer')
            ->assertHasNoErrors();

        $verification = Verification::whereNull('user_id')->latest('id')->first();

        expect($verification)->not->toBeNull()
            // Offerte : aucun débit, donc aucune dette au moment de créer un
            // compte.
            ->and($verification->cost_units)->toBe(0)
            ->and($composant->get('uuid'))->toBe($verification->uuid);

        // Mise en file, pas exécutée dans la requête : l'exécution synchrone
        // gelait la page — et avec de vrais moteurs elle la gèlerait une minute.
        Queue::assertPushed(AnalyserVerification::class);
    });

    it('annonce le même nombre de sources que le rapport en montre', function (): void {
        $verification = verifDe($this->citoyen);
        app(OrchestrateurVerification::class)->executer($verification);

        $verification->refresh();

        preg_match('/(\d+) sources? consultée/', (string) $verification->report->summary, $trouve);

        // Annoncer « 14 sources » sous un rapport qui en montre 13 fait douter
        // de tout le reste.
        expect((int) ($trouve[1] ?? -1))->toBe($verification->evidences()->count());
    });

    it('affiche le résultat À LA SUITE du formulaire, sans le remplacer', function (): void {
        $composant = Livewire::test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'La taxe WhatsApp entre en vigueur lundi au Bénin.')
            ->call('lancer');

        app(OrchestrateurVerification::class)
            ->executer(Verification::whereNull('user_id')->latest('id')->first());

        $html = $composant->call('suivre')->html();

        /*
         | Le formulaire RESTE À L'ÉCRAN. Le remplacer obligeait à chercher un
         | bouton « recommencer » pour vérifier une seconde chose, et faisait
         | disparaître ce qu'on venait de coller — on ne savait plus ce qui
         | avait été analysé.
         */
        expect($html)->toContain('dossier complet')
            ->and($html)->toContain('Lancer la vérification gratuite')
            ->and($html)->toContain('Texte / Message');

        // Le résultat vient APRÈS le formulaire dans l'ordre du document.
        expect(mb_strpos($html, 'dossier complet'))
            ->toBeGreaterThan(mb_strpos($html, 'Lancer la vérification gratuite'));
    });

    it('n\'offre que le lien et le texte, jamais de média', function (): void {
        $composant = Livewire::test(VerifierInvite::class);
        $html = $composant->html();

        /*
         | Image, vidéo et audio mobilisent des moteurs lourds, facturés à
         | l'appel : ce n'est pas ce qu'on offre à un visiteur anonyme. La
         | limite est dite à l'écran plutôt que laissée à deviner devant un
         | bouton d'envoi absent.
         */
        expect($html)->toContain('Lien URL')
            ->and($html)->toContain('Texte / Message')
            ->and($html)->toContain('lien ou texte uniquement')
            ->and($html)->not->toContain('type="file"');

        // Et le serveur ne connaît pas d'autre onglet : un onglet forgé
        // retombe sur celui qui était actif.
        $composant->call('choisirOnglet', 'video')->assertSet('onglet', 'url');
    });

    it('ne crée jamais qu\'une vérification de type lien ou texte', function (): void {
        Livewire::test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'Une rumeur reçue sur WhatsApp ce matin même.')
            ->call('lancer');

        Livewire::test(VerifierInvite::class)
            ->call('recommencer')
            ->set('onglet', 'url')
            ->set('lien', 'https://exemple.bj/annonce')
            ->call('lancer');

        expect(Verification::whereNull('user_id')->pluck('type')->all())
            ->each->toBeIn([TypeContenu::Texte, TypeContenu::Lien]);
    });

    it('n\'accorde que deux vérifications par adresse', function (): void {
        $composant = Livewire::test(VerifierInvite::class)->set('onglet', 'texte');

        for ($i = 0; $i < 3; $i++) {
            $composant->set('texte', 'Une rumeur numéro '.$i.' à vérifier ici même.')
                ->call('lancer')
                ->call('recommencer');
        }

        // Sans compte, rien d'autre ne borne l'appel à des services facturés.
        expect(Verification::whereNull('user_id')->count())->toBe(VerifierInvite::QUOTA_INVITE);
    });

    it('ne stocke jamais l\'adresse en clair', function (): void {
        Livewire::test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'Une rumeur quelconque à vérifier immédiatement.')
            ->call('lancer');

        // Le quota a besoin de distinguer deux visiteurs, pas de savoir qui ils
        // sont : ce garde-fou ne doit pas devenir un fichier de connexions.
        expect(Verification::whereNull('user_id')->first()->guest_hash)
            ->not->toContain('127.0.0.1');
    });

    it('refuse un média : réservé aux comptes', function (): void {
        $composant = Livewire::test(VerifierInvite::class);

        // Le composant n'expose que deux onglets : un média coûte cinq unités
        // et mobilise des moteurs lourds.
        expect($composant->get('onglet'))->toBe('url');
        $composant->call('choisirOnglet', 'video')->assertSet('onglet', 'url');
    });

    it('oriente vers l\'inscription en nommant ce qui reste derrière', function (): void {
        $composant = Livewire::test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'Une rumeur à vérifier pour voir le résultat.')
            ->call('lancer');

        app(OrchestrateurVerification::class)
            ->executer(Verification::whereNull('user_id')->latest('id')->first());

        $html = $composant->call('suivre')->html();

        // Une promesse vague ne convaincrait personne et laisserait croire
        // qu'on retient le verdict.
        expect($html)->toContain('moteurs mobilisés')
            ->toContain('sources consultées')
            ->toContain('Créer un compte');
    });

    it('renvoie un visiteur déjà connecté vers son espace', function (): void {
        Livewire::actingAs($this->citoyen)->test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'Une rumeur soumise alors que je suis connecté.')
            ->call('lancer')
            ->assertRedirect(route('citizen.verify'));

        expect(Verification::whereNull('user_id')->count())->toBe(0);
    });
});
