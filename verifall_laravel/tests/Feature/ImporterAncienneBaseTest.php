<?php

declare(strict_types=1);

use App\Console\Commands\AncienneBase\RepriseDesSources;
use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Enums\StatutInscription;
use App\Enums\StatutVerification;
use App\Enums\ThematiqueVerification;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\Forum as ForumCitoyen;
use App\Livewire\Citizen\MesTutoriels;
use App\Livewire\Citizen\Outils as OutilsCitoyen;
use App\Livewire\Public\SourcesPubliques;
use App\Livewire\Staff\EnrolementAuxCours;
use App\Livewire\Staff\ForumCitoyen as ModerationDuForumStaff;
use App\Livewire\Staff\Outils as OutilsStaff;
use App\Livewire\Staff\PortailsOfficiels as PortailsOfficielsStaff;
use App\Livewire\Staff\SourcesVerifiees;
use App\Models\AnalysisReport;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ForumCategory;
use App\Models\HubArticle;
use App\Models\HubJob;
use App\Models\HubPortal;
use App\Models\LessonCompletion;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\QuizAttempt;
use App\Models\SourceCategory;
use App\Models\Thread;
use App\Models\Tool;
use App\Models\TrustedSource;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use App\Models\XpLog;
use Database\Seeders\AcademieSeeder;
use Database\Seeders\CommunauteSeeder;
use Database\Seeders\HubSeeder;
use Database\Seeders\OutilsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SourcesFiablesSeeder;
use Database\Seeders\SourcesVeraSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La reprise de l'ancienne base
|--------------------------------------------------------------------------
|
| Cette commande ÉCRIT dans la base de production. Ce qu'elle doit garantir n'est
| pas « les données arrivent » — c'est **qu'elle ne casse rien** :
|
|   · aucun identifiant ancien n'est repris, donc aucune collision ;
|   · un compte existant n'est jamais écrasé ;
|   · `otp_verified_at` est posé, sans quoi les comptes importés seraient
|     inutilisables — mot de passe correct, refus systématique ;
|   · le solde d'unités arrive AVEC sa ligne de journal, sinon l'invariant
|     « somme du journal = solde » est faux dès le premier compte ;
|   · aucun statut ancien n'est écrit tel quel.
|
| Le jeu d'essai (`tests/Fixtures/ancienne-base.sql`) est au format exact de
| mysqldump, virgules dans les textes comprises.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->fixture = base_path('tests/Fixtures/ancienne-base.sql');
});

/** Lance la commande sur le jeu d'essai. */
function importer(string $fixture, bool $essai = false): int
{
    return Artisan::call('verifall:importer-ancienne-base', array_filter([
        '--fichier' => $fixture,
        '--essai' => $essai,
    ]));
}

it('n’écrit RIEN en essai à blanc', function () {
    importer($this->fixture, essai: true);

    expect(User::count())->toBe(0)
        ->and(Verification::count())->toBe(0);
});

it('importe les comptes avec un NOUVEL identifiant', function () {
    /*
     | L'ancienne base a un `users.id = 1`, la nouvelle aussi. Reprendre les
     | identifiants aurait écrasé les comptes existants — c'est le risque
     | numéro un de toute reprise.
     */
    $temoin = User::factory()->actif()->create(['email' => 'temoin@nouvelle.test']);

    importer($this->fixture);

    expect($temoin->fresh())->not->toBeNull()
        ->and(User::where('email', 'aicha@ancienne.test')->first()->id)->not->toBe(1);
});

it('n’écrase JAMAIS un compte déjà présent', function () {
    /*
     | Écraser reviendrait à remplacer un mot de passe qu'on emploie aujourd'hui
     | par celui d'il y a six mois.
     */
    $existant = User::factory()->actif()->create([
        'email' => 'deja@nouvelle.test',
        'name' => 'Le compte actuel',
        'password' => Hash::make('le-mot-de-passe-actuel'),
    ]);
    $motDePasseActuel = $existant->password;

    importer($this->fixture);

    $apres = User::where('email', 'deja@nouvelle.test')->firstOrFail();

    expect($apres->name)->toBe('Le compte actuel')
        ->and($apres->password)->toBe($motDePasseActuel);
});

it('pose otp_verified_at, sans quoi personne ne pourrait se connecter', function () {
    /*
     | LE piège de cette reprise. `User::estActive()` exige cette colonne, absente
     | de l'ancienne base : sans traitement, les comptes auraient un mot de passe
     | correct et un refus systématique, sans message qui l'explique.
     */
    importer($this->fixture);

    $verifiee = User::where('email', 'aicha@ancienne.test')->firstOrFail();
    $jamais = User::where('email', 'jamais@ancienne.test')->firstOrFail();

    expect($verifiee->estActive())->toBeTrue()
        // Un compte jamais vérifié dans l'ancienne base reste inactif : c'est
        // l'état correct, pas un oubli.
        ->and($jamais->estActive())->toBeFalse();
});

it('reprend l’empreinte bcrypt TELLE QUELLE, coût compris', function () {
    /*
     | C'est ce qui évite une réinitialisation générale des 133 mots de passe.
     |
     | Le test tourne avec `BCRYPT_ROUNDS=4` (phpunit.xml) et le jeu d'essai est
     | en coût 12 : il éprouve donc exactement le cas où le cast `hashed`
     | LÈVERAIT. C'est pour lui que l'empreinte est écrite hors du modèle.
     */
    importer($this->fixture);

    $empreinte = (string) User::where('email', 'aicha@ancienne.test')->value('password');

    expect($empreinte)->toStartWith('$2y$12$')
        // Et elle vérifie : `password_verify` se moque du coût configuré.
        ->and(password_verify('motdepasse', $empreinte))->toBeTrue();
});

it('traduit les rôles anciens', function () {
    // « User » d'hier est le « Citoyen » d'aujourd'hui ; « Modérateur » n'existait
    // pas et ne reçoit personne.
    importer($this->fixture);

    expect(User::where('email', 'aicha@ancienne.test')->firstOrFail()
        ->hasRole(UserRole::Citoyen->value))->toBeTrue()
        ->and(User::where('email', 'koffi@ancienne.test')->firstOrFail()
            ->hasRole(UserRole::Mentor->value))->toBeTrue()
        ->and(User::where('email', 'admin@ancienne.test')->firstOrFail()
            ->hasRole(UserRole::Admin->value))->toBeTrue();
});

it('donne le rôle Citoyen à un compte sans rôle reconnu', function () {
    // Sans rôle, la nouvelle application n'a aucun écran à lui montrer : le
    // compte se connecterait et ne verrait rien.
    importer($this->fixture);

    expect(User::where('email', 'efface@ancienne.test')->withTrashed()->firstOrFail()
        ->hasRole(UserRole::Citoyen->value))->toBeTrue();
});

it('reporte le solde AVEC sa ligne de journal', function () {
    /*
     | L'invariant que `EconomyService` maintient : la somme de `unit_logs` égale
     | `users.units`. Importer un solde sans ligne le casserait dès le premier
     | compte, et personne ne s'en apercevrait avant la première réclamation.
     */
    importer($this->fixture);

    $compte = User::where('email', 'aicha@ancienne.test')->firstOrFail();

    expect($compte->units)->toBe(12)
        ->and((int) UnitLog::where('user_id', $compte->id)->sum('delta'))->toBe(12)
        ->and($compte->xp)->toBe(340)
        ->and((int) XpLog::where('user_id', $compte->id)->sum('delta'))->toBe(340);
});

it('traduit les statuts, sans jamais écrire la valeur ancienne', function () {
    /*
     | Écrire « verified_false » passerait sans erreur — la colonne est une
     | chaîne — et casserait le cast en enum à la première lecture.
     */
    importer($this->fixture);

    $statuts = Verification::withTrashed()->pluck('status', 'uuid');

    // La colonne est CASTÉE : `pluck` rend l'enum, pas la chaîne. Comparer à
    // `->value` échouerait — et c'est la preuve que le cast fonctionne, donc que
    // la valeur écrite est bien une valeur d'enum valide.
    expect($statuts['bbbbbbbb-0000-4000-8000-000000000001'])->toBe(StatutVerification::Terminee)
        ->and($statuts['bbbbbbbb-0000-4000-8000-000000000002'])->toBe(StatutVerification::EnFile)
        ->and($statuts['bbbbbbbb-0000-4000-8000-000000000003'])->toBe(StatutVerification::RevueHumaine)
        ->and($statuts['bbbbbbbb-0000-4000-8000-000000000004'])->toBe(StatutVerification::Echouee);
});

it('recâble l’auteur sur le NOUVEL identifiant', function () {
    // Copié tel quel, l'ancien `created_by` désignerait n'importe qui.
    importer($this->fixture);

    $aicha = User::where('email', 'aicha@ancienne.test')->firstOrFail();
    $verification = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000001')->firstOrFail();

    expect($verification->user_id)->toBe($aicha->id)
        ->and($verification->assigned_to)
        ->toBe(User::where('email', 'koffi@ancienne.test')->value('id'));
});

it('accepte une soumission SANS auteur', function () {
    // Le produit a toujours accepté les soumissions d'invités ; l'ancienne base
    // en contient. Les refuser perdrait de l'historique réel.
    importer($this->fixture);

    expect(Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000003')->firstOrFail()->user_id)
        ->toBeNull();
});

it('agrège les rapports d’OUTIL en un rapport par vérification', function () {
    /*
     | L'ancien modèle pose une ligne par outil, le nouveau une par vérification.
     | Les réponses par outil ne sont pas perdues : elles deviennent `scores`.
     */
    importer($this->fixture);

    $verification = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000001')->firstOrFail();
    $rapport = AnalysisReport::where('verification_id', $verification->id)->firstOrFail();

    expect($rapport->confidence)->toBe(92)
        ->and($rapport->summary)->toContain('médicale')
        ->and($rapport->scores)->toHaveKey('gemini')
        ->and($rapport->scores)->toHaveKey('google_fact_check')
        ->and($rapport->scores['google_fact_check'])->toBe(95);
});

it('n’écrit AUCUN rapport pour une vérification sans verdict', function () {
    // Une ligne vide dirait « analysé, sans conclusion » : c'est faux.
    importer($this->fixture);

    $sansVerdict = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000002')->firstOrFail();

    expect(AnalysisReport::where('verification_id', $sansVerdict->id)->exists())->toBeFalse();
});

it('ne coupe PAS un texte qui contient une virgule', function () {
    /*
     | Un `explode(',')` naïf couperait « Koffi, le mentor » et décalerait toutes
     | les colonnes suivantes — un import qui « marche » avec des données
     | mélangées est pire qu'un import qui échoue.
     */
    importer($this->fixture);

    expect(User::where('email', 'koffi@ancienne.test')->value('name'))->toBe('Koffi, le mentor');
});

it('préserve la suppression douce de l’ancienne base', function () {
    importer($this->fixture);

    expect(User::where('email', 'efface@ancienne.test')->exists())->toBeFalse()
        ->and(User::withTrashed()->where('email', 'efface@ancienne.test')->exists())->toBeTrue()
        ->and(Verification::withTrashed()->where('uuid', 'bbbbbbbb-0000-4000-8000-000000000005')->firstOrFail()->trashed())
        ->toBeTrue();
});

it('est IDEMPOTENTE : relancée, elle ne duplique rien', function () {
    // Un import interrompu doit pouvoir se reprendre sans doublon.
    importer($this->fixture);
    $comptes = User::withTrashed()->count();
    $verifications = Verification::withTrashed()->count();

    importer($this->fixture);

    expect(User::withTrashed()->count())->toBe($comptes)
        ->and(Verification::withTrashed()->count())->toBe($verifications);
});

it('REFUSE un dump dont les mots de passe ne sont pas du bcrypt', function () {
    /*
     | Importés tels quels, ils refuseraient TOUS les mots de passe, et l'anomalie
     | ne se verrait qu'aux premières plaintes. Le refus est donc catégorique.
     */
    $faux = sys_get_temp_dir().'/faux-'.Str::random(6).'.sql';

    file_put_contents($faux, <<<'SQL'
        INSERT INTO `users` (`id`, `email`, `name`, `password`) VALUES
        (1, 'x@y.test', 'X', 'motdepasseenclair');
        SQL);

    $code = importer($faux);

    expect($code)->toBe(1)
        ->and(User::count())->toBe(0);

    @unlink($faux);
});

// ═══════════════════════════════════════════ le nettoyage AVANT la reprise

it('remet les noms sur une seule ligne, sans espace parasite', function () {
    /*
     | 41 noms sur 133 portent une espace de bord, 40 une espace double interne
     | — « Jean marie  EYEMI ». Importés tels quels, ils s'affichent ainsi
     | PARTOUT : entête du tableau de bord, fil du forum, liste du back-office.
     | Et ils cassent la recherche, qui ne trouve pas « Jean marie EYEMI ».
     |
     | Le relevé qui les avait manqués comparait `name <> TRIM(name)`. En
     | collation PAD SPACE, les espaces de FIN sont ignorées à la comparaison :
     | l'expression est toujours fausse. Il faut comparer les LONGUEURS.
     */
    importer($this->fixture);

    $compte = User::where('email', 'sale@ancienne.test')->firstOrFail();

    expect($compte->name)->toBe('Jean marie EYEMI')
        ->and($compte->first_name)->toBe('Jean marie')
        ->and($compte->last_name)->toBe('EYEMI')
        ->and($compte->phone)->toBe('97000007')
        // Une colonne facultative qui ne contenait que des espaces doit être
        // VIDE, et non contenir du vide.
        ->and($compte->profession)->toBeNull();
});

it('met l’adresse en minuscules, et la retrouve ainsi', function () {
    // La fixture porte «   Sale@Ancienne.TEST  ». Sans normalisation, le compte
    // existe mais aucune connexion ne le retrouve.
    importer($this->fixture);

    expect(User::where('email', 'sale@ancienne.test')->exists())->toBeTrue();
});

it('range le TEXTE SOUMIS en affirmation, et le titre en note', function () {
    /*
     | Deux nettoyages différents, et c'est le cœur de l'affaire : un titre EST
     | une ligne — les 28 qui contiennent un retour à la ligne sont eux-mêmes le
     | défaut à corriger. Un texte soumis de 4 995 caractères, lui, a une mise
     | en forme que la personne a écrite ; l'écraser reviendrait à réécrire son
     | propos.
     */
    importer($this->fixture);

    $verification = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000006')->firstOrFail();

    expect($verification->claim)->toBe('Le texte soumis.')
        /*
         | `theme` est CASTÉ en enum, et c'est ce qui rend le nettoyage porteur
         | plutôt que cosmétique : « ␣␣sante␣␣ » ne correspond à aucun cas de
         | `ThematiqueVerification`. Sans le rognage, la valeur ne se serait pas
         | castée — et l'échec serait tombé à la première LECTURE de la fiche,
         | pas à l'import.
         */
        ->and($verification->theme)->toBe(ThematiqueVerification::Sante)
        // Le titre d'hier devient la NOTE : remis sur une ligne, bords rognés.
        // C'est l'étiquette que la personne reconnaîtra dans son historique.
        ->and($verification->context)->toBe('Un titre coupé en deux lignes');
});

it('laisse entrer un compte qui se connectait SANS avoir vérifié son courriel', function () {
    /*
     | 22 comptes de l'ancienne base ont `email_verified_at` à NULL, et SIX
     | d'entre eux se connectaient encore la semaine de l'extraction — dont un
     | compte `@verifall.com` trois jours avant. Copier NULL les aurait
     | verrouillés dehors : mot de passe juste, connexion refusée, pour une case
     | qu'ils n'ont jamais eu à cocher.
     |
     | Une connexion RÉUSSIE prouve la connaissance du mot de passe ; un clic
     | dans un courriel prouve seulement l'accès à la boîte. On accepte donc la
     | première comme date de vérification.
     */
    importer($this->fixture);

    $actif = User::where('email', 'actif@ancienne.test')->firstOrFail();

    expect($actif->estActive())->toBeTrue()
        ->and($actif->otp_verified_at?->toDateTimeString())->toBe('2026-08-30 13:38:30')
        // On ne MENT pas sur la vérification du courriel : elle n'a pas eu lieu.
        ->and($actif->email_verified_at)->toBeNull();
});

it('laisse inactif celui qui n’a NI vérifié NI jamais ouvert de session', function () {
    // Il n'a rien prouvé du tout. Le parcours OTP normal l'attend à sa première
    // connexion — c'est l'état correct, pas un oubli.
    importer($this->fixture);

    expect(User::where('email', 'jamais@ancienne.test')->firstOrFail()->estActive())->toBeFalse();
});

// ═══════════════════════════════════════════════ les verdicts

it('traduit les verdicts FRANÇAIS de l’ancienne base', function () {
    /*
     | Le défaut qui a arrêté le premier import réel, après 133 comptes écrits.
     |
     | L'ancienne base rend ses verdicts en français — `vrai` (294), `douteux`
     | (143), `faux` (45), `inconnu` (4) — quand `VerdictFactCheck` est adossé à
     | l'anglais. La valeur passait l'ÉCRITURE sans broncher, puis levait un
     | `ValueError` au premier cast.
     |
     | Deux raisons l'avaient caché, et les deux sont réparées ici : cette
     | fixture employait des verdicts anglais, donc mentait sur le dump réel ; et
     | l'essai à blanc ne construit aucun modèle, donc ne caste rien — il ne
     | pouvait STRUCTURELLEMENT pas l'attraper. Un essai vert ne prouve pas
     | qu'un import passera.
     */
    importer($this->fixture);

    $faux = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000001')->firstOrFail();
    $vrai = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000006')->firstOrFail();

    expect(AnalysisReport::where('verification_id', $faux->id)->firstOrFail()->verdict)
        ->toBe(VerdictFactCheck::Faux)
        ->and(AnalysisReport::where('verification_id', $vrai->id)->firstOrFail()->verdict)
        ->toBe(VerdictFactCheck::Vrai);
});

it('REFUSE un verdict qu’il ne sait pas traduire', function () {
    /*
     | Même règle que pour les statuts, et elle compte davantage ici : un verdict
     | inventé est la pire donnée que ce produit puisse porter — il publie une
     | conclusion de fact-checking que personne n'a rendue.
     |
     | Mieux vaut un import qui refuse de finir et NOMME la valeur inconnue.
     */
    $dump = str_replace("'completed', 'vrai', 88", "'completed', 'plutot_vrai', 88", file_get_contents($this->fixture));
    $chemin = sys_get_temp_dir().'/ancienne-verdict-inconnu.sql';
    file_put_contents($chemin, $dump);

    $this->artisan('verifall:importer-ancienne-base', ['--fichier' => $chemin])
        ->expectsOutputToContain('plutot_vrai')
        ->assertFailed();

    /*
     | Et surtout : le refus tombe AVANT la premiere ecriture. C'est ce que
     | l'incident du 2 septembre a coute — 133 comptes poses, puis un arret sur
     | la premiere verification, laissant la base a moitie remplie.
     */
    expect(User::count())->toBe(0)
        ->and(Verification::count())->toBe(0);

    @unlink($chemin);
});

it('reprend l’URL COMPLÈTE, et non le libellé tronqué à 60', function () {
    /*
     | L'ancienne base engendrait un libellé automatique coupé à 60 caractères —
     | « Analyse Invité : https://www.inserm.fr/c-est-quoi/ » — quand l'adresse
     | continue par « /debout-cest-quoi-le-systeme-vestibulaire ».
     |
     | Le reprendre comme affirmation donnerait 138 liens MORTS dans l'historique
     | de gens qui les ont soumis entiers. `source_url` porte l'adresse complète,
     | et n'était repris nulle part.
     */
    importer($this->fixture);

    $url = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000007')->firstOrFail();

    expect($url->claim)->toBe('https://www.inserm.fr/c-est-quoi/debout-cest-quoi-le-systeme-vestibulaire')
        ->and($url->claim)->not->toContain('Analyse Invité');
});

it('ne déborde PAS de la note, quelle que soit la description', function () {
    /*
     | `verifications.context` est un `varchar(200)`, l'ancienne `description`
     | monte à 4 770 caractères : 23 lignes dépassent, et l'écriture s'arrêtait
     | sur un « Data too long » AU MILIEU de l'import — le second arrêt de cette
     | reprise.
     */
    importer($this->fixture);

    $fleuve = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000008')->firstOrFail();

    expect(mb_strlen((string) $fleuve->context))->toBeLessThanOrEqual(200)
        // Le contenu réel, lui, n'est pas rogné : il est passé dans l'affirmation.
        ->and($fleuve->claim)->toBe('Le contenu réel.');
});

it('traduit « douteux » en verdict Trompeur', function () {
    // Le SEUL appariement de verdict qui demandait un arbitrage, et il porte sur
    // 143 vérifications. `inconnu` désigne l'absence de conclusion — donc
    // `NonVerifiable` — ce qui laisse à `douteux` la place intermédiaire.
    importer($this->fixture);

    $douteux = Verification::where('uuid', 'bbbbbbbb-0000-4000-8000-000000000008')->firstOrFail();

    expect(AnalysisReport::where('verification_id', $douteux->id)->firstOrFail()->verdict)
        ->toBe(VerdictFactCheck::Trompeur);
});

/*
|--------------------------------------------------------------------------
| Reprendre l'import sans rien casser
|--------------------------------------------------------------------------
|
| Le journal de production du 2 septembre 2026 porte
| « Duplicate entry '527' for key analysis_reports_verification_id_unique »,
| et §9 de `docs/MIGRATION_ANCIENNE_BASE.md` enregistre deux arrêts de cette
| reprise.
|
| La cause exacte n'a PAS été retrouvée, et il faut le dire : le dump réel a
| été sondé — 524 signalements, aucun uuid vide, aucun uuid en double —, si
| bien que la garde d'idempotence par uuid couvre chaque ligne. L'état de base
| qui a produit ce doublon n'existe plus et ne s'observe pas.
|
| Ce qui se teste, en revanche, c'est le comportement qu'on attend d'une
| commande qui écrit dans la base de PRODUCTION : rejouée, elle ne duplique
| rien ; et si une ligne est déjà là, elle la SAUTE au lieu d'arrêter les 523
| autres.
|
*/

it('rejouée deux fois, ne duplique rien', function () {
    importer($this->fixture);

    $apres = [
        'comptes' => User::count(),
        'verifications' => Verification::withTrashed()->count(),
        'rapports' => AnalysisReport::count(),
        'unites' => UnitLog::count(),
        'xp' => XpLog::count(),
    ];

    expect(importer($this->fixture))->toBe(0);

    // Les journaux comptent double au premier faux pas : `unit_logs` doit
    // toujours totaliser `users.units`, et une seconde ligne de report casse
    // l'invariant sans rien afficher.
    expect(User::count())->toBe($apres['comptes'])
        ->and(Verification::withTrashed()->count())->toBe($apres['verifications'])
        ->and(AnalysisReport::count())->toBe($apres['rapports'])
        ->and(UnitLog::count())->toBe($apres['unites'])
        ->and(XpLog::count())->toBe($apres['xp']);
});

/*
 | Le cas du RAPPORT ORPHELIN n'est pas testé, et l'omission est délibérée.
 |
 | Il faudrait poser une ligne de `analysis_reports` dont le `verification_id`
 | ne désigne rien, pour qu'un auto-incrément le réattribue ensuite — l'état
 | que la production a très probablement connu. Cela demande de désactiver la
 | clé étrangère, et sous SQLite `PRAGMA foreign_keys` est SANS EFFET à
 | l'intérieur d'une transaction : la suite en ouvre une par test.
 |
 | Le `firstOrCreate` de `poserLeRapport()` reste donc une garde que rien ne
 | traverse ici. C'est assumé : elle ne coûte rien, elle ne peut rien casser,
 | et elle transforme un arrêt de tout l'import en un saut compté. Le dire vaut
 | mieux que de fabriquer un test tordu qui donnerait l'illusion d'une preuve.
 */

/*
|--------------------------------------------------------------------------
| Le répertoire d'outils — passe du 14 septembre 2026
|--------------------------------------------------------------------------
|
| `--seulement=outils`. Même patron que les comptes : aucun identifiant ancien,
| l'existant gagne, contrôle de TOUTES les lignes avant la première écriture.
|
| La fixture porte les 21 lignes RÉELLES de l'ancienne `tools`, recopiées du
| dump — la leçon de l'incident n° 1 : un jeu d'essai retapé ne prouve rien.
| Les quinze fiches du prototype sont semées par `OutilsSeeder`, exactement
| comme sur la base cible : ce sont elles qui provoquent les collisions.
|
*/

/** Lance la reprise des outils seule. */
function importerLesOutils(string $fixture, bool $essai = false): int
{
    return Artisan::call('verifall:importer-ancienne-base', array_filter([
        '--fichier' => $fixture,
        '--essai' => $essai,
        '--seulement' => 'outils',
    ]));
}

/** La fixture, modifiée à la volée, dans un fichier temporaire. */
function fixtureModifiee(string $fixture, string $avant, string $apres): string
{
    $contenu = (string) file_get_contents($fixture);

    expect($contenu)->toContain($avant);

    $chemin = sys_get_temp_dir().'/ancienne-outils-'.Str::random(6).'.sql';
    file_put_contents($chemin, str_replace($avant, $apres, $contenu));

    return $chemin;
}

describe('Reprise des outils', function (): void {
    beforeEach(function (): void {
        $this->seed(OutilsSeeder::class);
    });

    it('n’écrit RIEN à blanc, et annonce ce que l’import fera', function () {
        importerLesOutils($this->fixture, essai: true);

        expect(Tool::count())->toBe(15)
            ->and(Artisan::output())->toMatch('/Importés \(en brouillon\)\s*\|\s*15/');

        // L'essai et l'import doivent rendre les MÊMES compteurs.
        importerLesOutils($this->fixture);

        expect(Tool::count())->toBe(30)
            ->and(Artisan::output())->toMatch('/Importés \(en brouillon\)\s*\|\s*15/');
    });

    it('ne touche ni aux comptes ni aux vérifications', function () {
        importerLesOutils($this->fixture);

        expect(User::count())->toBe(0)
            ->and(Verification::count())->toBe(0);
    });

    it('n’est PAS lancée par l’import des comptes', function () {
        // Le comportement de la commande sans option est inchangé.
        Tool::query()->delete();

        importer($this->fixture);

        expect(Tool::count())->toBe(0);
    });

    it('traduit la catégorie par OUTIL, et garde l’ancienne en pastille', function () {
        /*
         | L'ancienne `TEXTE` réunit GPTZero, un détecteur d'IA, et Snopes, une
         | rédaction de fact-checking. Traduire catégorie par catégorie en
         | aurait rangé un de travers.
         */
        importerLesOutils($this->fixture);

        $gptzero = Tool::where('slug', 'gptzero')->firstOrFail();
        $snopes = Tool::where('slug', 'snopes')->firstOrFail();
        $virustotal = Tool::where('slug', 'virustotal')->firstOrFail();

        expect($gptzero->category)->toBe('Intelligence artificielle')
            ->and($gptzero->tag)->toBe('TEXTE')
            ->and($gptzero->icon)->toBe('smart_toy')
            ->and($snopes->category)->toBe('Fact-checking')
            ->and($snopes->tag)->toBe('TEXTE')
            ->and($virustotal->category)->toBe('Web & domaines')
            ->and($virustotal->tag)->toBe('WEB');

        // Aucune fiche hors de la liste fermée : elle serait invisible au filtre citoyen.
        expect(Tool::pluck('category')->diff(array_keys(OutilsCitoyen::CATEGORIES))->all())->toBe([]);
    });

    it('reprend la fiche avec un NOUVEL identifiant, en brouillon', function () {
        importerLesOutils($this->fixture);

        $forensic = Tool::where('slug', 'forensic')->firstOrFail();

        // L'ancien `tools.id = 1` : la fiche nouvelle n° 1 reste celle du prototype.
        expect($forensic->id)->not->toBe(1)
            ->and(Tool::orderBy('id')->firstOrFail()->title)->toBe('Recherche d\'image inversée (Google Lens)')
            ->and($forensic->title)->toBe('Forensic')
            ->and($forensic->url)->toBe('https://29a.ch/photo-forensics/')
            ->and($forensic->description)->toBe('Suite d\'outils forensiques pour images numériques: détection de clonage, ELA')
            ->and($forensic->use_cases)->toBe(['Analyse complète d\'authenticité d\'image, clonage, bruit visuel'])
            ->and($forensic->is_published)->toBeFalse()
            ->and($forensic->is_recommended)->toBeFalse()
            ->and($forensic->created_at?->toDateTimeString())->toBe('2026-03-14 05:44:44')
            // La sélection publiée aux citoyens n'a pas bougé.
            ->and(Tool::publiees()->count())->toBe(15);
    });

    it('écarte le déchet de test « Test1test », logo compris', function () {
        importerLesOutils($this->fixture);

        expect(Tool::where('slug', 'test1test')->exists())->toBeFalse()
            ->and(Tool::where('url', 'like', '%test.com%')->exists())->toBeFalse()
            ->and(Artisan::output())->toContain('Test1test');
    });

    it('écarte une fiche supprimée dans l’ancienne base, faute de suppression douce', function () {
        $chemin = fixtureModifiee($this->fixture,
            "'Standard industriel en cybersécurité', NULL, 1, NULL,",
            "'Standard industriel en cybersécurité', NULL, 1, '2026-05-01 10:00:00',");

        importerLesOutils($chemin);

        expect(Tool::where('slug', 'virustotal')->exists())->toBeFalse()
            ->and(Tool::count())->toBe(29);

        @unlink($chemin);
    });

    it('SAUTE un outil déjà présent sous un autre nom ou une autre adresse, sans l’écraser', function () {
        /*
         | Cinq collisions réelles, et chacune éprouve un critère :
         |   · Wayback Machine, Google Fact Check Explorer — même nom ;
         |   · Deepware Scanner — même nom, adresse différente (scanner.) ;
         |   · InVID / WeVerify Plugin — autre nom, même page (www., « / » final) ;
         |   · Google Earth Pro — autre nom, chemin qui prolonge la fiche existante.
         */
        $champs = ['title', 'url', 'description', 'category', 'is_published', 'updated_at'];
        $avant = Tool::where('slug', 'wayback-machine')->firstOrFail()->only($champs);

        importerLesOutils($this->fixture);

        expect(Tool::where('slug', 'wayback-machine')->firstOrFail()->only($champs))->toEqual($avant)
            ->and(Tool::where('slug', 'invid-weverify-plugin')->exists())->toBeFalse()
            ->and(Tool::where('slug', 'google-earth-pro')->exists())->toBeFalse()
            ->and(Tool::where('title', 'like', 'Deepware%')->count())->toBe(1)
            ->and(Tool::where('title', 'like', 'Google Fact Check%')->count())->toBe(1)
            ->and(Artisan::output())->toMatch('/Sautés[^|]*\|\s*5/');
    });

    it('rejouée deux fois, ne duplique ni ne modifie rien', function () {
        importerLesOutils($this->fixture);
        $apres = Tool::orderBy('id')->get(['id', 'slug', 'updated_at'])->toArray();

        expect(importerLesOutils($this->fixture))->toBe(0)
            ->and(Artisan::output())->toMatch('/Importés \(en brouillon\)\s*\|\s*0/')
            ->and(Tool::orderBy('id')->get(['id', 'slug', 'updated_at'])->toArray())->toBe($apres);
    });

    it('REFUSE une catégorie ancienne inconnue, AVANT la première écriture', function () {
        $chemin = fixtureModifiee($this->fixture, "'gptzero', 'TEXTE'", "'gptzero', 'PODCAST'");

        $this->artisan('verifall:importer-ancienne-base', ['--fichier' => $chemin, '--seulement' => 'outils'])
            ->expectsOutputToContain('PODCAST')
            ->assertFailed();

        // La ligne fautive est la ONZIÈME : les dix précédentes ne sont pas écrites.
        expect(Tool::count())->toBe(15);

        @unlink($chemin);
    });

    it('REFUSE un outil nouveau dont la catégorie ancienne est hétérogène, plutôt que deviner', function () {
        // `TEXTE` n'a pas de repli : GPTZero et Snopes n'ont rien en commun.
        $chemin = fixtureModifiee($this->fixture, "'Snopes', 'snopes', 'TEXTE'", "'Full Fact', 'full-fact', 'TEXTE'");

        $this->artisan('verifall:importer-ancienne-base', ['--fichier' => $chemin, '--seulement' => 'outils'])
            ->expectsOutputToContain('full-fact')
            ->assertFailed();

        expect(Tool::count())->toBe(15);

        @unlink($chemin);
    });

    it('REFUSE une adresse qui n’est pas en https, AVANT la première écriture', function () {
        $chemin = fixtureModifiee($this->fixture, "'https://www.virustotal.com'", "'http://www.virustotal.com'");

        $this->artisan('verifall:importer-ancienne-base', ['--fichier' => $chemin, '--seulement' => 'outils'])
            ->expectsOutputToContain('VirusTotal')
            ->assertFailed();

        expect(Tool::count())->toBe(15);

        @unlink($chemin);
    });

    it('ne reprend JAMAIS une clé d’API de fact_check_tools', function () {
        importerLesOutils($this->fixture);

        expect((string) json_encode(DB::table('tools')->get()))->not->toContain('CLE-API-ANCIENNE')
            ->and(Tool::where('slug', 'google-fact-check')->exists())->toBeFalse();
    });

    it('refuse une valeur inconnue pour --seulement', function () {
        // « forum » servait d'exemple jusqu'à la seconde passe du 14 septembre,
        // qui en a fait une valeur acceptée. « blog » n'a pas de reprise.
        $this->artisan('verifall:importer-ancienne-base', ['--fichier' => $this->fixture, '--seulement' => 'blog'])
            ->assertFailed();

        expect(User::count())->toBe(0);
    });

    it('rend les fiches importées dans l’écran staff et l’écran citoyen', function () {
        importerLesOutils($this->fixture);

        $admin = User::factory()->create(['otp_verified_at' => now(), 'two_factor_confirmed_at' => now()]);
        $admin->assignRole(UserRole::Admin->value);

        Livewire::actingAs($admin, 'staff')
            ->withQueryParams(['etat' => 'brouillons'])
            ->test(OutilsStaff::class)
            ->assertOk()
            ->assertSee('GPTZero')
            // Et la fiche importée s'enregistre SANS retouche : les règles de
            // l'écran ont été appliquées avant l'écriture.
            ->call('ouvrirEdition', (string) Tool::where('slug', 'pimeyes')->value('uuid'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        Tool::query()->update(['is_published' => true]);

        Livewire::actingAs(User::factory()->actif()->create())
            ->test(OutilsCitoyen::class)
            ->assertOk()
            ->assertSee('Africa Check');
    });
});

/*
|--------------------------------------------------------------------------
| Seconde passe du 14 septembre 2026 — sources, Académie, forum, Hub
|--------------------------------------------------------------------------
|
| Même patron que les outils. La fixture porte, pour chaque table, les lignes
| RÉELLES du dump recopiées par numéro de ligne ; seules les adresses des
| comptes 26 à 136 sont fictives (données personnelles). Les cas limites sont
| produits en MODIFIANT une ligne réelle, jamais en en inventant une — sauf
| pour les tables vides du dump, où la ligne ajoutée est dite synthétique.
|
*/

/** Lance une reprise partielle. */
function reprendre(string $fixture, string $perimetre, bool $essai = false): int
{
    return Artisan::call('verifall:importer-ancienne-base', array_filter([
        '--fichier' => $fixture,
        '--essai' => $essai,
        '--seulement' => $perimetre,
    ]));
}

/** La fixture, augmentée d'un bloc SQL SYNTHÉTIQUE, dans un fichier temporaire. */
function fixtureAugmentee(string $fixture, string $sql): string
{
    $chemin = sys_get_temp_dir().'/ancienne-augmentee-'.Str::random(6).'.sql';
    file_put_contents($chemin, file_get_contents($fixture)."\n".$sql."\n");

    return $chemin;
}

/** Un administrateur staff, comme pour l'écran des outils. */
function administrateurStaff(): User
{
    $admin = User::factory()->create(['otp_verified_at' => now(), 'two_factor_confirmed_at' => now()]);
    $admin->assignRole(UserRole::Admin->value);

    return $admin;
}

// ═══════════════════════════════════════════════════════════ les sources

describe('Reprise des sources vérifiées', function (): void {
    it('n’écrit RIEN à blanc, et annonce les compteurs de l’import', function () {
        reprendre($this->fixture, 'sources', essai: true);

        expect(TrustedSource::count())->toBe(0)
            ->and(SourceCategory::count())->toBe(0)
            ->and(Artisan::output())->toMatch('/Importées \(actives\)\s*\|\s*134/');

        reprendre($this->fixture, 'sources');
        $sortie = Artisan::output();

        expect(TrustedSource::count())->toBe(135)
            ->and($sortie)->toMatch('/Importées \(actives\)\s*\|\s*134/')
            ->and($sortie)->toMatch('/liste noire[^|]*\|\s*1/');
    });

    it('ne fait qu’UNE fiche par domaine, et c’est la ligne du site entier qui gagne', function () {
        /*
         | 143 lignes, 135 domaines : huit doublons, dont sept « rubrique de
         | fact-checking + titre entier ». Le moteur crédite TOUT le domaine :
         | la ligne qui décrit le site entier est donc la bonne.
         */
        reprendre($this->fixture, 'sources');

        $lemonde = TrustedSource::where('domain', 'lemonde.fr')->sole();
        $reuters = TrustedSource::where('domain', 'reuters.com')->sole();

        expect($lemonde->name)->toBe('Le Monde')
            ->and($lemonde->categorie?->slug)->toBe('publications-de-reference')
            // La rubrique absorbée n'est pas perdue : elle est citée.
            ->and($lemonde->notes)->toContain('Le Monde - Les Décodeurs')
            ->and($reuters->name)->toBe('Reuters')
            ->and($reuters->notes)->toContain('Reuters Fact Check')
            ->and(Artisan::output())->toMatch('/fusionnées[^|]*\|\s*8/');
    });

    it('à égalité, garde la PREMIÈRE ligne du dump — et son indice réel', function () {
        // africacheck.org : deux lignes sans chemin. La première (95, retouchée
        // le 26 mars) gagne ; la seconde (100, le défaut) est absorbée.
        reprendre($this->fixture, 'sources');

        $africa = TrustedSource::where('domain', 'africacheck.org')->sole();

        expect($africa->name)->toBe('Africa Check (Partenaire IFCN)')
            ->and($africa->credibility)->toBe(95)
            ->and($africa->categorie?->slug)->toBe('reseaux-de-fact-checking')
            ->and($africa->notes)->toContain('ancien indice de confiance (95)');
    });

    it('n’importe PAS l’indice 100 : c’est la valeur par défaut de la colonne', function () {
        /*
         | Une fiche importée change le score des analyses futures. Un 100 qui
         | n'est que le défaut ne prouve aucune décision : la fiche reçoit la
         | crédibilité de sa FAMILLE, comme les sources de SourcesVeraSeeder.
         */
        reprendre($this->fixture, 'sources');

        expect(TrustedSource::where('domain', 'lessurligneurs.eu')->value('credibility'))->toBe(88)
            ->and(TrustedSource::where('domain', 'lemonde.fr')->value('credibility'))->toBe(78)
            ->and(TrustedSource::where('domain', 'gouv.bj')->value('credibility'))->toBe(85)
            ->and(TrustedSource::where('domain', 'gouv.bj')->sole()->categorie?->slug)->toBe('institutions-beninoises')
            ->and(TrustedSource::where('credibility', 100)->exists())->toBeFalse()
            ->and(TrustedSource::where('domain', 'lessurligneurs.eu')->value('url'))->toBe('https://lessurligneurs.eu');
    });

    it('ne fait PAS d’un site en liste noire une source de confiance', function () {
        reprendre($this->fixture, 'sources');

        $noire = TrustedSource::where('domain', 'concours-benin.top')->sole();
        $depot = app(DepotSourcesFiables::class);

        expect($noire->is_active)->toBeFalse()
            ->and($noire->credibility)->toBe(0)
            ->and($noire->source_category_id)->toBeNull()
            ->and($noire->notes)->toContain('LISTE NOIRE')
            // Inactive : le moteur ne la trouve pas, la page publique ne la liste pas.
            ->and($depot->trouverParDomaine('concours-benin.top'))->toBeNull()
            ->and($depot->parFamille()->flatten()->pluck('domain'))->not->toContain('concours-benin.top');
    });

    it('CHANGE le score dès l’import : le cache du moteur est vidé', function () {
        $depot = app(DepotSourcesFiables::class);

        // Le cache est amorcé AVANT l'import, comme sur un serveur qui tourne.
        expect($depot->trouverParDomaine('www.lemonde.fr'))->toBeNull();

        reprendre($this->fixture, 'sources');

        expect($depot->trouverParDomaine('www.lemonde.fr')?->credibility)->toBe(78)
            // Sous-domaine : remonte au domaine référencé.
            ->and($depot->trouverParDomaine('edition.cnn.com')?->name)->toBe('CNN');
    });

    it('l’existant gagne : ni la fiche ni sa famille ne sont écrasées', function () {
        $this->seed(SourcesFiablesSeeder::class);
        $avant = TrustedSource::where('domain', 'reuters.com')->sole()->only(['name', 'credibility', 'source_category_id', 'notes']);

        reprendre($this->fixture, 'sources');

        expect(TrustedSource::where('domain', 'reuters.com')->sole()->only(['name', 'credibility', 'source_category_id', 'notes']))->toBe($avant)
            ->and(SourceCategory::where('slug', 'reseaux-de-fact-checking')->count())->toBe(1)
            // gouv.bj, sante.gouv.bj, reuters, apnews, afp, rfi, africacheck, poynter.
            ->and(Artisan::output())->toMatch('/Sautées[^|]*\|\s*8/');
    });

    it('crée ses familles avec les valeurs EXACTES des seeders du référentiel', function () {
        $this->seed(SourcesFiablesSeeder::class);
        $this->seed(SourcesVeraSeeder::class);

        foreach (RepriseDesSources::FAMILLES as $slug => [$nom, $credibilite, $description, $ordre]) {
            expect(SourceCategory::where('slug', $slug)->sole()->only(['name', 'default_credibility', 'description', 'sort_order']))
                ->toBe(['name' => $nom, 'default_credibility' => $credibilite, 'description' => $description, 'sort_order' => $ordre]);
        }
    });

    it('rejouée deux fois, ne duplique ni ne modifie rien', function () {
        reprendre($this->fixture, 'sources');
        $apres = TrustedSource::orderBy('id')->get(['id', 'domain', 'credibility', 'updated_at'])->toArray();

        expect(reprendre($this->fixture, 'sources'))->toBe(0);
        $sortie = Artisan::output();

        expect($sortie)->toMatch('/Importées \(actives\)\s*\|\s*0/')
            ->and($sortie)->toMatch('/Sautées[^|]*\|\s*135/')
            ->and(TrustedSource::orderBy('id')->get(['id', 'domain', 'credibility', 'updated_at'])->toArray())->toBe($apres)
            ->and(SourceCategory::count())->toBe(3);
    });

    it('REFUSE une catégorie inconnue, AVANT la première écriture', function () {
        $chemin = fixtureModifiee($this->fixture, "95, 1, 'fact-checker', 0", "95, 1, 'blog', 0");

        expect(reprendre($chemin, 'sources'))->toBe(1)
            ->and(Artisan::output())->toContain('blog')
            ->and(TrustedSource::count())->toBe(0);

        @unlink($chemin);
    });

    it('REFUSE un domaine à la fois en liste noire et fiable : collision douteuse', function () {
        $chemin = fixtureModifiee($this->fixture, "'https://concours-benin.top'", "'https://africacheck.org'");

        expect(reprendre($chemin, 'sources'))->toBe(1)
            ->and(Artisan::output())->toContain('africacheck.org est À LA FOIS en liste noire')
            ->and(TrustedSource::count())->toBe(0);

        @unlink($chemin);
    });

    it('REFUSE de ranger une institution étrangère parmi les institutions béninoises', function () {
        $chemin = fixtureModifiee($this->fixture, "'https://www.gouv.bj', 100, 1, 'institution'", "'https://www.gouv.fr', 100, 1, 'institution'");

        expect(reprendre($chemin, 'sources'))->toBe(1)
            ->and(Artisan::output())->toContain('gouv.fr')
            ->and(TrustedSource::count())->toBe(0);

        @unlink($chemin);
    });

    it('rend les fiches dans l’écran du référentiel, et une fiche s’enregistre sans retouche', function () {
        reprendre($this->fixture, 'sources');

        $administrateur = administrateurStaff();

        // Depuis la migration DataTables (14 sept. 2026), la liste de l'écran
        // est servie par `staff.trusted-sources.data` : la fiche en liste
        // noire, reprise inactive, s'y lit sous le filtre `?etat=inactives`.
        $inactives = $this->actingAs($administrateur, 'staff')
            ->getJson(route('staff.trusted-sources.data', ['filtres' => ['etat' => 'inactives']]))
            ->assertOk()
            ->json('data');

        expect(collect($inactives)->pluck('nom')->implode(' | '))->toContain('Concours-Benin.top');

        Livewire::actingAs($administrateur, 'staff')
            ->withQueryParams(['etat' => 'inactives'])
            ->test(SourcesVerifiees::class)
            ->assertOk()
            ->call('ouvrirEdition', (string) TrustedSource::where('domain', 'lemonde.fr')->value('uuid'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        Livewire::test(SourcesPubliques::class)
            ->assertOk()
            ->assertSee('Les Surligneurs')
            ->assertDontSee('Concours-Benin.top');
    });
});

// ═══════════════════════════════════════════════════════════ l'Académie

describe('Reprise de l’Académie', function (): void {
    beforeEach(function (): void {
        // Le catalogue d'abord, comme sur la base cible : ce sont ses cours
        // qui reçoivent les inscriptions. Puis les comptes.
        $this->seed(AcademieSeeder::class);
        importer($this->fixture);
    });

    it('n’écrit RIEN à blanc, et annonce les compteurs de l’import', function () {
        reprendre($this->fixture, 'academie', essai: true);

        expect(Enrollment::count())->toBe(0)
            ->and(Artisan::output())->toMatch('/Inscriptions importées\s*\|\s*18/');

        reprendre($this->fixture, 'academie');

        expect(Enrollment::count())->toBe(18)
            ->and(Artisan::output())->toMatch('/Inscriptions importées\s*\|\s*18/');
    });

    it('recâble les inscriptions sur les cours EXISTANTS, sans créer de cours', function () {
        $cours = Course::withTrashed()->count();

        reprendre($this->fixture, 'academie');

        $intro = Course::where('slug', 'introduction-fact-checking')->sole();
        $siecle = Course::where('slug', 'un-siecle-de-fact-checking')->sole();

        expect(Course::withTrashed()->count())->toBe($cours)
            ->and(Course::where('slug', 'journalistes-vs-infox')->exists())->toBeFalse()
            ->and(Enrollment::where('course_id', $intro->id)->count())->toBe(10)
            ->and(Enrollment::where('course_id', $siecle->id)->count())->toBe(8)
            // Le compteur dénormalisé suit, comme à une inscription ordinaire.
            ->and($intro->students_count)->toBe(10)
            ->and($siecle->students_count)->toBe(8);
    });

    it('recâble le compte par l’ADRESSE, et reprend l’inscription à 0 %', function () {
        /*
         | L'ancienne inscription n° 19 (compte 74) était à 50 % sur « Un siècle
         | de fact-checking » : elle avait vu la vidéo, leçon sans jumeau dans le
         | cours nouveau. L'avancement est dérivé des leçons lues — 0.
         */
        reprendre($this->fixture, 'academie');

        $compte = User::where('email', 'compte-74@ancienne.test')->sole();
        $inscription = Enrollment::where('user_id', $compte->id)->sole();

        expect($inscription->course?->slug)->toBe('un-siecle-de-fact-checking')
            ->and($inscription->progress)->toBe(0)
            ->and($inscription->status)->toBe(StatutInscription::Inscrit)
            ->and($inscription->paid_units)->toBe(0)
            ->and($inscription->started_at?->toDateTimeString())->toBe('2026-07-26 14:22:59')
            ->and($inscription->completed_at)->toBeNull();
    });

    it('ne rattache AUCUNE leçon lue ni tentative au hasard, et ne fabrique aucun certificat', function () {
        reprendre($this->fixture, 'academie');
        $sortie = Artisan::output();

        expect(LessonCompletion::count())->toBe(0)
            ->and(QuizAttempt::count())->toBe(0)
            ->and(Certificate::count())->toBe(0)
            ->and($sortie)->toMatch('/Leçon lue sans leçon équivalente[^|]*\|\s*4/')
            ->and($sortie)->toMatch('/Tentative de quiz d’un cours écarté\s*\|\s*11/')
            ->and($sortie)->toMatch('/Tentative de quiz sans quiz équivalent[^|]*\|\s*2/');
    });

    it('saute et compte une inscription dont le compte est introuvable', function () {
        $chemin = fixtureModifiee($this->fixture, '(6, 26, 3, 0,', '(6, 999, 3, 0,');

        reprendre($chemin, 'academie');

        expect(Enrollment::count())->toBe(17)
            ->and(Artisan::output())->toMatch('/compte est introuvable\s*\|\s*1/');

        @unlink($chemin);
    });

    it('l’existant gagne : une inscription déjà présente n’est pas touchée', function () {
        $compte = User::where('email', 'compte-26@ancienne.test')->sole();
        $cours = Course::where('slug', 'introduction-fact-checking')->sole();
        $existante = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'course_id' => $cours->id,
            'status' => StatutInscription::EnCours->value, 'progress' => 40,
        ]);

        reprendre($this->fixture, 'academie');

        expect($existante->fresh()?->progress)->toBe(40)
            ->and(Enrollment::count())->toBe(18);
    });

    it('rejouée deux fois, ne duplique rien et ne recompte pas les inscrits', function () {
        reprendre($this->fixture, 'academie');
        $apres = [Enrollment::count(), Course::where('slug', 'introduction-fact-checking')->value('students_count')];

        expect(reprendre($this->fixture, 'academie'))->toBe(0)
            ->and([Enrollment::count(), Course::where('slug', 'introduction-fact-checking')->value('students_count')])->toBe($apres)
            ->and(Artisan::output())->toMatch('/Inscriptions importées\s*\|\s*0/');
    });

    it('REFUSE un cours ancien sans correspondance, AVANT la première écriture', function () {
        $chemin = fixtureModifiee($this->fixture, "'Journalistes vs Infox', 'journalistes-vs-infox'", "'Journalistes vs Infox', 'journalistes-et-infox'");

        expect(reprendre($chemin, 'academie'))->toBe(1)
            ->and(Artisan::output())->toContain('journalistes-et-infox')
            ->and(Enrollment::count())->toBe(0);

        @unlink($chemin);
    });

    it('REFUSE d’ouvrir gratuitement un cours devenu payant', function () {
        Course::where('slug', 'un-siecle-de-fact-checking')->update(['cost_units' => 5]);

        expect(reprendre($this->fixture, 'academie'))->toBe(1)
            ->and(Artisan::output())->toContain('payant')
            ->and(Enrollment::count())->toBe(0);
    });

    it('REFUSE de reprendre des certificats sans décision', function () {
        // `certificates` est VIDE dans le dump : ligne SYNTHÉTIQUE, colonnes réelles.
        $chemin = fixtureAugmentee($this->fixture, "INSERT INTO `certificates` (`id`, `uuid`, `user_id`, `course_id`, `certificate_number`, `file_path`, `digital_signature`, `metadata`, `issued_at`, `deleted_at`, `created_at`, `updated_at`) VALUES\n(1, 'dddddddd-0000-4000-8000-000000000001', 26, 3, 'VF-0001', NULL, NULL, NULL, '2026-08-01 10:00:00', NULL, NULL, NULL);");

        expect(reprendre($chemin, 'academie'))->toBe(1)
            ->and(Artisan::output())->toContain('certificates')
            ->and(Enrollment::count())->toBe(0)
            ->and(Certificate::count())->toBe(0);

        @unlink($chemin);
    });

    it('rend les inscriptions dans l’écran staff et dans « Mes tutoriels »', function () {
        reprendre($this->fixture, 'academie');

        Livewire::actingAs(administrateurStaff(), 'staff')
            ->test(EnrolementAuxCours::class)
            ->assertOk()
            ->assertSee('Compte 26');

        Livewire::actingAs(User::where('email', 'compte-26@ancienne.test')->sole())
            ->test(MesTutoriels::class)
            ->assertOk()
            ->assertSee('Introduction au fact-checking');
    });
});

// ═══════════════════════════════════════════════════════════ le forum

describe('Reprise du forum', function (): void {
    beforeEach(function (): void {
        // Sans compte, ce seeder ne plante que les rubriques : exactement les
        // cinq de la base cible.
        $this->seed(CommunauteSeeder::class);
        importer($this->fixture);
    });

    it('ne reprend AUCUN des cinq sujets réels : ils n’ont pas de titre', function () {
        reprendre($this->fixture, 'forum');
        $sortie = Artisan::output();

        expect(Thread::withTrashed()->count())->toBe(0)
            ->and(Poll::count())->toBe(0)
            ->and(ForumCategory::count())->toBe(5)
            ->and($sortie)->toMatch('/Sujet sans titre[^|]*\|\s*5/')
            ->and($sortie)->toMatch('/Sondage d’un sujet non repris\s*\|\s*2/')
            ->and($sortie)->toMatch('/Catégories rapprochées[^|]*\|\s*3/');
    });

    it('reprend un sujet valide, PURIFIÉ, auteur et rubrique recâblés', function () {
        $chemin = fixtureModifiee($this->fixture,
            "(4, 36, 1, NULL, 'Salut a tous', 'text'",
            "(4, 36, 1, 'Salut a tous, une question sérieuse', '<p>Salut a tous, <b>voici</b> une vraie question.</p><script>alert(1)</script>', 'text'");

        reprendre($chemin, 'forum', essai: true);
        expect(Thread::count())->toBe(0)->and(Artisan::output())->toMatch('/Sujets importés\s*\|\s*1/');

        reprendre($chemin, 'forum');

        $sujet = Thread::sole();

        expect($sujet->body)->toContain('<b>voici</b>')
            ->and($sujet->body)->not->toContain('script')
            ->and($sujet->body)->not->toContain('alert')
            ->and($sujet->author?->email)->toBe('compte-36@ancienne.test')
            ->and($sujet->category?->slug)->toBe('fake-news')
            ->and($sujet->slug)->toBe('salut-a-tous-une-question-serieuse')
            ->and($sujet->created_at?->toDateTimeString())->toBe('2026-07-09 13:15:30')
            ->and($sujet->last_activity_at?->toDateTimeString())->toBe('2026-07-09 13:15:30');

        @unlink($chemin);
    });

    it('reprend un sondage à réponses distinctes, et saute celui dont les réponses sont « 1 » et « 1 »', function () {
        $chemin = fixtureModifiee($this->fixture,
            "(1, 5, 5, NULL, 'Dites moi', 'text'",
            "(1, 5, 5, 'Le président a-t-il révoqué son Premier ministre ?', 'Dites moi ce que vous en savez, sources à l’appui.', 'text'");
        $chemin = fixtureModifiee($chemin,
            "(2, 5, 1, NULL, 'Hello', 'text'",
            "(2, 5, 1, 'Diomaye Faye a-t-il révoqué Sonko ?', 'Hello, quelqu’un a-t-il une source fiable là-dessus ?', 'text'");

        reprendre($chemin, 'forum');

        $sondage = Poll::sole();

        expect(Thread::count())->toBe(2)
            ->and($sondage->question)->toBe('C\'est vrai que le président sénégalais a révoquer son premier ministre ?')
            ->and($sondage->options()->pluck('label')->all())->toBe(['Oui', 'Non', 'Je ne sais pas'])
            ->and($sondage->author?->email)->toBe('admin@ancienne.test')
            ->and(Artisan::output())->toMatch('/Sondage que le forum refuserait[^|]*\|\s*1/');

        @unlink($chemin);
    });

    it('crée une rubrique sans équivalent SEULEMENT si un sujet repris s’y trouve', function () {
        $chemin = fixtureModifiee($this->fixture,
            "(3, 26, 1, NULL, 'Hello...', 'text'",
            "(3, 26, 3, 'Faut-il débattre des rumeurs ?', 'Hello... je propose d’en débattre ici, calmement.', 'text'");

        reprendre($chemin, 'forum');

        expect(ForumCategory::count())->toBe(6)
            ->and(ForumCategory::where('slug', 'debats')->sole()->color)->toBe('#6f42c1')
            ->and(ForumCategory::where('slug', 'election')->exists())->toBeFalse()
            ->and(Thread::sole()->category?->slug)->toBe('debats');

        @unlink($chemin);
    });

    it('garde le message d’un auteur introuvable, sans auteur, et le compte', function () {
        $chemin = fixtureModifiee($this->fixture,
            "(4, 36, 1, NULL, 'Salut a tous', 'text'",
            "(4, 999, 1, 'Salut a tous, une question sérieuse', 'Salut a tous, voici une vraie question.', 'text'");

        reprendre($chemin, 'forum');

        expect(Thread::sole()->user_id)->toBeNull()
            ->and(Artisan::output())->toMatch('/Auteurs introuvables[^|]*\|\s*1/');

        @unlink($chemin);
    });

    it('rejouée deux fois, ne duplique rien', function () {
        $chemin = fixtureModifiee($this->fixture,
            "(1, 5, 5, NULL, 'Dites moi', 'text'",
            "(1, 5, 5, 'Le président a-t-il révoqué son Premier ministre ?', 'Dites moi ce que vous en savez, sources à l’appui.', 'text'");

        reprendre($chemin, 'forum');
        $apres = [Thread::count(), Poll::count(), PollOption::count(), ForumCategory::count()];

        expect(reprendre($chemin, 'forum'))->toBe(0)
            ->and([Thread::count(), Poll::count(), PollOption::count(), ForumCategory::count()])->toBe($apres)
            ->and(Artisan::output())->toMatch('/Sujet déjà présent[^|]*\|\s*1/');

        @unlink($chemin);
    });

    it('REFUSE un statut de sujet inconnu, AVANT la première écriture', function () {
        // Un sujet masqué ne doit pas devenir public par la reprise.
        $chemin = fixtureModifiee($this->fixture, "'public', '2026-08-13 08:19:39'", "'hidden', '2026-08-13 08:19:39'");

        expect(reprendre($chemin, 'forum'))->toBe(1)
            ->and(Artisan::output())->toContain('hidden')
            ->and(Thread::withTrashed()->count())->toBe(0);

        @unlink($chemin);
    });

    it('REFUSE une table sans règle écrite qui ne serait plus vide', function () {
        // `forum_reactions` est VIDE dans le dump : ligne SYNTHÉTIQUE, colonnes réelles.
        $chemin = fixtureAugmentee($this->fixture, "INSERT INTO `forum_reactions` (`id`, `user_id`, `reactable_type`, `reactable_id`, `type`, `created_at`, `updated_at`) VALUES\n(1, 26, 'ForumThread', 1, 'love', NULL, NULL);");

        expect(reprendre($chemin, 'forum'))->toBe(1)
            ->and(Artisan::output())->toContain('forum_reactions');

        @unlink($chemin);
    });

    it('rend le sujet repris dans le forum citoyen et l’écran de modération', function () {
        $chemin = fixtureModifiee($this->fixture,
            "(4, 36, 1, NULL, 'Salut a tous', 'text'",
            "(4, 36, 1, 'Salut a tous, une question sérieuse', '<p>Salut a tous, <b>voici</b> une vraie question.</p>', 'text'");

        reprendre($chemin, 'forum');

        Livewire::actingAs(User::where('email', 'compte-36@ancienne.test')->sole())
            ->test(ForumCitoyen::class)
            ->assertOk()
            ->assertSee('Salut a tous, une question sérieuse');

        Livewire::actingAs(administrateurStaff(), 'staff')
            ->test(ModerationDuForumStaff::class)
            ->assertOk()
            ->assertSee('Salut a tous, une question sérieuse');

        @unlink($chemin);
    });
});

// ═══════════════════════════════════════════════════════════ le Hub

describe('Reprise du Hub', function (): void {
    beforeEach(function (): void {
        $this->seed(HubSeeder::class);
        $this->portailsAvant = HubPortal::count();
        $this->brevesAvant = HubArticle::count();
    });

    it('n’écrit RIEN à blanc, et annonce les compteurs de l’import', function () {
        reprendre($this->fixture, 'hub', essai: true);

        expect(HubPortal::count())->toBe($this->portailsAvant)
            ->and(Artisan::output())->toMatch('/Portails importés[^|]*\|\s*35/');

        reprendre($this->fixture, 'hub');

        expect(HubPortal::count())->toBe($this->portailsAvant + 35)
            ->and(Artisan::output())->toMatch('/Portails importés[^|]*\|\s*35/');
    });

    it('reprend les e-services en BROUILLON, rubrique et nom nettoyés', function () {
        reprendre($this->fixture, 'hub');

        $fiscale = HubPortal::where('url', 'https://service-public.bj/public/services/service/PS00689')->sole();

        // « ␣Attestation fiscale » dans le dump.
        expect($fiscale->name)->toBe('Attestation fiscale')
            ->and($fiscale->category)->toBe('Administration')
            ->and($fiscale->is_official)->toBeTrue()
            ->and($fiscale->is_published)->toBeFalse()
            ->and($fiscale->description)->toBeNull()
            ->and(HubPortal::where('category', 'Justice')->count())->toBe(1)
            // La sélection publiée n'a pas bougé.
            ->and(HubPortal::where('is_published', true)->count())->toBe($this->portailsAvant);
    });

    it('saute le portail déjà présent à la MÊME adresse, pas les démarches du même site', function () {
        $existant = HubPortal::where('url', 'https://service-public.bj')->sole()->only(['name', 'category', 'is_published']);

        reprendre($this->fixture, 'hub');

        expect(HubPortal::where('url', 'like', 'https://service-public.bj/%')->count())->toBe(33)
            ->and(HubPortal::where('url', 'https://service-public.bj')->sole()->only(['name', 'category', 'is_published']))->toBe($existant)
            ->and(HubPortal::where('name', 'Portail National des services publics du Bénin')->exists())->toBeFalse();
    });

    it('écarte les actualités : un communiqué n’est pas une brève de vérification', function () {
        reprendre($this->fixture, 'hub');
        $sortie = Artisan::output();

        expect(HubArticle::count())->toBe($this->brevesAvant)
            ->and($sortie)->toMatch('/Actualités écartées\s*\|\s*3/')
            ->and($sortie)->toContain('Discours d\'investiture');
    });

    it('rejouée deux fois, ne duplique rien', function () {
        reprendre($this->fixture, 'hub');
        $apres = HubPortal::count();

        expect(reprendre($this->fixture, 'hub'))->toBe(0)
            ->and(HubPortal::count())->toBe($apres)
            ->and(Artisan::output())->toMatch('/Portails sautés[^|]*\|\s*36/');
    });

    it('REFUSE une rubrique inconnue ou une adresse non https, AVANT la première écriture', function () {
        $rubrique = fixtureModifiee($this->fixture, "'Justice', 1, 1", "'Loisirs', 1, 1");
        $http = fixtureModifiee($this->fixture, "'https://eresultats.bj/'", "'http://eresultats.bj/'");

        expect(reprendre($rubrique, 'hub'))->toBe(1)
            ->and(Artisan::output())->toContain('Loisirs')
            ->and(reprendre($http, 'hub'))->toBe(1)
            ->and(Artisan::output())->toContain('E-Résultats')
            ->and(HubPortal::count())->toBe($this->portailsAvant);

        @unlink($rubrique);
        @unlink($http);
    });

    it('écarte une offre d’emploi expirée, et S’ARRÊTE sur une offre encore ouverte', function () {
        // `job_offers` est VIDE dans le dump : lignes SYNTHÉTIQUES, colonnes réelles.
        $entete = 'INSERT INTO `job_offers` (`id`, `title`, `company`, `description`, `apply_link`, `deadline`, `verified_by`, `created_by`, `created_at`, `updated_at`) VALUES';
        $expiree = fixtureAugmentee($this->fixture, $entete."\n(1, 'Stage en rédaction', 'SRTB', 'Stage de six mois.', 'https://srtb.bj/stage', '2026-05-30', 1, 1, NULL, NULL);");
        $ouverte = fixtureAugmentee($this->fixture, $entete."\n(1, 'Stage en rédaction', 'SRTB', 'Stage de six mois.', 'https://srtb.bj/stage', '2099-01-01', 1, 1, NULL, NULL);");

        expect(reprendre($expiree, 'hub'))->toBe(0)
            ->and(Artisan::output())->toMatch('/expirées écartées\s*\|\s*1/')
            ->and(HubJob::where('title', 'Stage en rédaction')->exists())->toBeFalse();

        expect(reprendre($ouverte, 'hub'))->toBe(1)
            ->and(Artisan::output())->toContain('Lieu, type et portée');

        @unlink($expiree);
        @unlink($ouverte);
    });

    it('rend les portails repris dans l’écran staff', function () {
        reprendre($this->fixture, 'hub');

        Livewire::actingAs(administrateurStaff(), 'staff')
            ->withQueryParams(['q' => 'casier'])
            ->test(PortailsOfficielsStaff::class)
            ->assertOk()
            ->assertSee('Casier judiciaire');
    });
});
