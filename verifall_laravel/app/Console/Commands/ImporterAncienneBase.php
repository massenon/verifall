<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MotifGainXp;
use App\Enums\MotifMouvementUnite;
use App\Enums\StatutVerification;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Models\AnalysisReport;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use App\Models\XpLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reprend les comptes et l'historique de vérifications de l'ancienne plateforme.
 *
 * **Le raisonnement complet est dans `docs/MIGRATION_ANCIENNE_BASE.md`.** Ce
 * bloc n'en retient que ce qu'il faut avoir en tête en lisant le code.
 *
 * ## Aucun identifiant ancien n'est repris
 *
 * L'ancienne base a un `users.id = 1`, la nouvelle aussi. Chaque compte est créé
 * avec un nouvel auto-incrément, et une table de correspondance
 * `ancien_id → nouvel_id` recâble tout le reste. Réutiliser les anciens
 * identifiants aurait été plus court et aurait écrasé les comptes existants.
 *
 * ## Le compte existant gagne toujours
 *
 * Une adresse présente des deux côtés n'est pas écrasée : la ligne ancienne est
 * sautée. Écraser reviendrait à remplacer un mot de passe qu'on emploie
 * aujourd'hui par celui d'il y a six mois.
 *
 * ## `otp_verified_at` — le piège qui verrouillerait tout le monde dehors
 *
 * `User::estActive()` exige cette colonne, absente de l'ancienne base. Sans
 * traitement, les 133 comptes importés auraient un mot de passe correct et un
 * refus systématique. `email_verified_at` alimente les deux.
 *
 * ## L'empreinte est écrite hors du modèle
 *
 * Le cast `hashed` lève dès que le coût de l'empreinte diffère de
 * `BCRYPT_ROUNDS`, alors que `password_verify()` accepte n'importe quel coût.
 * Refuser aurait imposé une réinitialisation générale pour rien. Voir
 * `ecrireLEmpreinte()`.
 *
 * ## Le solde d'unités ne s'importe pas seul
 *
 * `unit_logs` doit toujours totaliser `users.units` — c'est l'invariant que
 * `EconomyService` maintient. Un solde importé sans ligne de journal le casserait
 * dès le premier compte. D'où la ligne de report, au motif `admin_adjustment`.
 */
final class ImporterAncienneBase extends Command
{
    protected $signature = 'verifall:importer-ancienne-base
        {--fichier= : Chemin du dump SQL (défaut : ../existingdb/massenon_verifalltest.sql)}
        {--essai : Lecture seule — compte, signale, n\'écrit rien}';

    protected $description = 'Reprend comptes, identifiants et historique de vérifications de l\'ancienne base';

    /**
     * Les statuts anciens, traduits.
     *
     * Écrire la valeur ancienne dans la colonne nouvelle passerait sans erreur —
     * c'est une chaîne — et casserait le cast en enum à la PREMIÈRE lecture.
     * Toute valeur absente de cette table arrête l'import : deviner produirait
     * des vérifications dans un état que le produit ne sait pas afficher.
     *
     * @var array<string, string>
     */
    private const STATUTS = [
        'pending' => StatutVerification::EnFile->value,
        'processing' => StatutVerification::EnCours->value,
        'analyzing' => StatutVerification::EnCours->value,
        // « investigating » désignait une reprise humaine : c'est exactement ce
        // que `RevueHumaine` nomme aujourd'hui.
        'investigating' => StatutVerification::RevueHumaine->value,
        'verified_true' => StatutVerification::Terminee->value,
        'verified_false' => StatutVerification::Terminee->value,
        'debunked' => StatutVerification::Terminee->value,
        'failed' => StatutVerification::Echouee->value,
    ];

    /**
     * Les verdicts anciens, traduits.
     *
     * ## Le défaut que cette table répare, et pourquoi il avait échappé
     *
     * L'ancienne base écrit ses verdicts EN FRANÇAIS — `vrai` (294), `douteux`
     * (143), `faux` (45), `inconnu` (4) — tandis que `VerdictFactCheck` est
     * adossé à l'anglais. Écrire la valeur ancienne telle quelle passait
     * l'écriture sans broncher, puis levait un `ValueError` au premier cast.
     *
     * C'est EXACTEMENT le défaut que la table `STATUTS` ci-dessus prévient pour
     * les statuts, et il manquait ici. Deux raisons l'ont caché : la fixture de
     * test employait des verdicts anglais, donc n'était pas fidèle au dump
     * réel ; et l'essai à blanc ne construit aucun modèle, donc ne caste rien —
     * il ne pouvait structurellement pas l'attraper.
     *
     * ## L'appariement des quatre valeurs
     *
     * Les deux échelles ont quatre degrés et se correspondent terme à terme.
     * `inconnu` est le seul à désigner l'ABSENCE de conclusion, ce que dit
     * précisément `NonVerifiable` ; `douteux` occupe alors la place restante,
     * celle du verdict intermédiaire — `Trompeur`.
     *
     * **C'est le seul appariement qui demande un arbitrage**, et il porte sur
     * 143 vérifications. Le reste est littéral.
     *
     * @var array<string, string>
     */
    private const VERDICTS = [
        'vrai' => VerdictFactCheck::Vrai->value,
        'faux' => VerdictFactCheck::Faux->value,
        'douteux' => VerdictFactCheck::Trompeur->value,
        'inconnu' => VerdictFactCheck::NonVerifiable->value,
        // Les formes anglaises, au cas où un dump plus récent les emploie : le
        // produit a pu changer de langue en cours de route.
        'true' => VerdictFactCheck::Vrai->value,
        'false' => VerdictFactCheck::Faux->value,
        'misleading' => VerdictFactCheck::Trompeur->value,
        'unverifiable' => VerdictFactCheck::NonVerifiable->value,
    ];

    /** @var array<string, string> */
    private const TYPES = [
        'text' => 'text',
        'image' => 'image',
        'video' => 'video',
        'audio' => 'audio',
        'url' => 'url',
    ];

    /** @var array<string, string> */
    private const ROLES = [
        'Admin' => UserRole::Admin->value,
        'Mentor' => UserRole::Mentor->value,
        // « User » d'hier est le « Citoyen » d'aujourd'hui.
        'User' => UserRole::Citoyen->value,
    ];

    /**
     * ancien users.id => nouvel users.id.
     *
     * La valeur `-1` est le marqueur d'ESSAI : elle n'atteint jamais la base.
     *
     * @var array<int, int>
     */
    private array $correspondance = [];

    /** @var array<string, int> */
    private array $compteurs = [
        'comptes_importes' => 0,
        'comptes_sautes' => 0,
        'verifications_importees' => 0,
        'verifications_sautees' => 0,
        'rapports' => 0,
        'rapports_sautes' => 0,
        'lignes_unites' => 0,
        'auteurs_introuvables' => 0,
    ];

    public function handle(): int
    {
        $chemin = (string) ($this->option('fichier')
            ?: base_path('../existingdb/massenon_verifalltest.sql'));

        if (! is_readable($chemin)) {
            $this->error('Dump introuvable : '.$chemin);

            return self::FAILURE;
        }

        $essai = (bool) $this->option('essai');

        $this->info($essai
            ? 'ESSAI À BLANC — rien ne sera écrit.'
            : 'Import réel. Assurez-vous d\'avoir sauvegardé la base.');

        $this->line('Lecture de '.$chemin.'…');

        $anciens = $this->lireTable($chemin, 'users');
        $signalements = $this->lireTable($chemin, 'signalements');
        $rapports = $this->lireTable($chemin, 'analysis_reports');
        $roles = $this->lireTable($chemin, 'model_has_roles');

        $this->line(sprintf(
            '  %d comptes, %d signalements, %d rapports d\'outil.',
            count($anciens), count($signalements), count($rapports),
        ));

        if (($faute = $this->verifierLesEmpreintes($anciens)) !== null) {
            /*
             | Le refus est catégorique. Une empreinte qui n'est pas du bcrypt
             | signifie que l'ancienne application hachait autrement : importée
             | telle quelle, elle refuserait TOUS les mots de passe, et
             | l'anomalie ne se verrait qu'aux premières plaintes.
             */
            $this->error('Empreinte de mot de passe inattendue : '.$faute);
            $this->line('  Attendu : bcrypt ($2y$…). Import interrompu.');

            return self::FAILURE;
        }

        if (($faute = $this->verifierLesValeursContraintes($signalements)) !== null) {
            /*
             | LE contrôle qui manquait, et son absence a coûté un import à
             | moitié écrit : 133 comptes posés, puis un `ValueError` sur la
             | PREMIÈRE vérification parce que l'ancienne base rend ses verdicts
             | en français.
             |
             | Les empreintes, elles, étaient vérifiées d'avance. La règle vaut
             | pour toute valeur adossée à un enum : on la contrôle sur la
             | TOTALITÉ du dump avant la première écriture. Un import qui
             | s'arrête au milieu laisse une base dans un état que personne n'a
             | prévu — ni l'ancienne, ni la nouvelle.
             */
            $this->error($faute);
            $this->line('  Rien n’a été écrit. Complétez la table de traduction, puis relancez.');

            return self::FAILURE;
        }

        $this->importerLesComptes($anciens, $roles, $essai);
        $this->importerLesVerifications($signalements, $rapports, $essai);

        $this->rapport($essai);

        return self::SUCCESS;
    }

    /**
     * L'affirmation soumise — ce que la personne a réellement envoyé.
     *
     * ## Pourquoi ce n'est PAS l'ancien `title`
     *
     * C'était le premier choix, et il était faux sur deux plans.
     *
     * **Sur les URL.** L'ancienne base engendrait un libellé automatique et le
     * coupait à 60 caractères : « Analyse Invité : https://www.inserm.fr/c-est-quoi/ »
     * quand l'adresse réelle continue par « /debout-cest-quoi-le-systeme ».
     * Importer ce titre donnerait **138 liens tronqués**, donc morts, dans
     * l'historique de gens qui les ont soumis entiers. `source_url` porte
     * l'adresse complète, et n'était repris nulle part.
     *
     * **Sur le texte.** 221 signalements ont un `content_text` plus riche que
     * leur titre, et seuls 83 ont le titre pour préfixe. Retenir le titre
     * jetterait le texte soumis dans les autres cas — précisément ce qu'un
     * ancien utilisateur vient relire.
     *
     * ## Ce qui tranche : la définition du champ dans le produit
     *
     * `SoumissionVerificationService` écrit `'claim' => trim($affirmation)` —
     * `claim` EST le texte soumis, il n'existe pas de titre à côté. La colonne
     * est un `text` sans limite, et l'affichage tronque déjà de lui-même
     * (`Str::limit($verification->claim, 190)`). La reprise fidèle est donc le
     * contenu, pas son étiquette.
     *
     * @param  array<string, string|null>  $ancien
     */
    private function affirmation(array $ancien): string
    {
        $type = (string) ($ancien['content_type'] ?? 'text');
        $url = $this->propre($ancien['source_url'] ?? null);

        if ($type === 'url' && $url !== null) {
            return $url;
        }

        // Le titre ne sert qu'en dernier recours — les 33 signalements d'image
        // n'ont que lui.
        return $this->propreTexte($ancien['content_text'] ?? null)
            ?? $url
            ?? $this->propre($ancien['title'] ?? null)
            ?? '';
    }

    /**
     * La note courte qui accompagne l'affirmation.
     *
     * `verifications.context` est un `varchar(200)`, alors que l'ancienne
     * `description` monte à 4 770 caractères : **23 lignes dépassent la
     * limite**, et l'écriture s'arrête sur un « Data too long » au milieu de
     * l'import. C'est le second arrêt qu'a connu cette reprise.
     *
     * On garde donc l'étiquette la plus utile — le titre d'hier, qui servait
     * d'en-tête à l'écran et que la personne reconnaîtra — et on se rabat sur la
     * description. Le rognage à 200 est une GARDE, pas le mode normal : les
     * titres font 60 caractères au plus.
     *
     * La description n'est pas perdue pour autant : 22 des 23 longues sont le
     * doublon exact de `content_text`, qui devient l'affirmation.
     *
     * @param  array<string, string|null>  $ancien
     */
    private function note(array $ancien): ?string
    {
        $titre = $this->propre($ancien['title'] ?? null);
        $description = $this->propre($ancien['description'] ?? null);

        $note = $titre ?? $description;

        return $note === null ? null : Str::limit($note, 197);
    }

    /**
     * Contrôle, AVANT toute écriture, que statuts et verdicts sont traduisibles.
     *
     * Le pendant de `verifierLesEmpreintes()` pour les colonnes adossées à un
     * enum. Il parcourt tout le dump et rend le premier défaut trouvé, ou `null`
     * si l'ensemble passe.
     *
     * On signale le NOMBRE d'occurrences avec la valeur : « douteux (143 fois) »
     * dit tout de suite s'il s'agit d'un cas isolé ou d'une colonne entière à
     * arbitrer.
     *
     * @param  list<array<string, string|null>>  $signalements
     */
    private function verifierLesValeursContraintes(array $signalements): ?string
    {
        $statutsInconnus = [];
        $verdictsInconnus = [];

        foreach ($signalements as $ancien) {
            $statut = trim((string) ($ancien['status'] ?? ''));

            if ($statut !== '' && ! isset(self::STATUTS[$statut])) {
                $statutsInconnus[$statut] = ($statutsInconnus[$statut] ?? 0) + 1;
            }

            $verdict = mb_strtolower(trim((string) ($ancien['verdict'] ?? '')));

            if ($verdict !== '' && ! isset(self::VERDICTS[$verdict])) {
                $verdictsInconnus[$verdict] = ($verdictsInconnus[$verdict] ?? 0) + 1;
            }
        }

        if ($statutsInconnus !== []) {
            return 'Statut(s) inconnu(s) : '.$this->enumerer($statutsInconnus).'. À ajouter à la table STATUTS.';
        }

        if ($verdictsInconnus !== []) {
            return 'Verdict(s) inconnu(s) : '.$this->enumerer($verdictsInconnus).'. À ajouter à la table VERDICTS.';
        }

        return null;
    }

    /** @param  array<string, int>  $comptage */
    private function enumerer(array $comptage): string
    {
        $morceaux = [];

        foreach ($comptage as $valeur => $nombre) {
            $morceaux[] = sprintf('« %s » (%d fois)', $valeur, $nombre);
        }

        return implode(', ', $morceaux);
    }

    // ══════════════════════════════════════════════════════════ les comptes

    /**
     * @param  list<array<string, string|null>>  $anciens
     * @param  list<array<string, string|null>>  $roles
     */
    private function importerLesComptes(array $anciens, array $roles, bool $essai): void
    {
        $parRole = $this->rolesParUtilisateur($roles);
        $barre = $this->output->createProgressBar(count($anciens));

        foreach ($anciens as $ancien) {
            $barre->advance();

            // Minuscules ET espaces retirés. Le dump analysé n'avait ni l'un ni
            // l'autre, mais la comparaison qui l'a établi devait se faire en
            // BINARY : la collation `utf8mb4_unicode_ci` est insensible à la
            // casse, donc `email <> LOWER(email)` est TOUJOURS faux et ne prouve
            // rien. La garde reste, pour le dump de production qui peut différer.
            $email = mb_strtolower(trim((string) ($ancien['email'] ?? '')));

            if ($email === '') {
                $this->compteurs['comptes_sautes']++;

                continue;
            }

            $existant = User::withTrashed()->where('email', $email)->first();

            if ($existant !== null) {
                // Le compte existant gagne. On enregistre quand même la
                // correspondance : ses anciens signalements doivent lui revenir.
                $this->correspondance[(int) $ancien['id']] = (int) $existant->id;
                $this->compteurs['comptes_sautes']++;

                continue;
            }

            if ($essai) {
                if ((int) ($ancien['remaining_units'] ?? 0) > 0) {
                    $this->compteurs['lignes_unites']++;
                }

                /*
                 | La correspondance est renseignée MÊME à blanc, avec un
                 | identifiant fictif. Sans cela, l'essai annonce des centaines
                 | d'« auteurs introuvables » qui n'existent que parce qu'il n'a
                 | rien créé — un essai qui rapporte un problème inexistant est
                 | pire que pas d'essai : on renonce à l'import pour rien.
                 */
                $this->correspondance[(int) $ancien['id']] = -1;
                $this->compteurs['comptes_importes']++;

                continue;
            }

            DB::transaction(function () use ($ancien, $email, $parRole): void {
                $compte = new User;

                $verifie = $this->date($ancien['email_verified_at'] ?? null);
                $derniereEntree = $this->date($ancien['last_login_at'] ?? null);

                $compte->forceFill([
                    'uuid' => $ancien['uuid'] ?: (string) Str::uuid(),
                    'name' => $this->propre($ancien['name'] ?? null) ?? $email,
                    'first_name' => $this->propre($ancien['firstname'] ?? null),
                    'last_name' => $this->propre($ancien['lastname'] ?? null),
                    'email' => $email,
                    'phone' => $this->propre($ancien['phone'] ?? null),
                    'profession' => $this->propre($ancien['profession'] ?? null),
                    /*
                     | Un jeton JETABLE, au coût configuré, uniquement parce que
                     | la colonne est NOT NULL. Il est remplacé par la vraie
                     | empreinte à la ligne suivante, dans la même transaction —
                     | et il n'ouvre rien entre-temps : c'est le haché de 40
                     | caractères aléatoires que personne ne connaîtra jamais.
                     */
                    'password' => Hash::make(Str::random(40)),
                    'remember_token' => $ancien['remember_token'] ?: null,
                    'email_verified_at' => $verifie,
                    /*
                     | LE point qui décide de l'utilisabilité des comptes. Sans
                     | lui, `estActive()` refuse la connexion malgré un mot de
                     | passe correct — et le message ne dit pas pourquoi.
                     |
                     | ## Le repli sur la dernière connexion, et pourquoi il est
                     | ## plus sûr que la colonne qu'il remplace
                     |
                     | 22 comptes de l'ancienne base ont `email_verified_at` à
                     | NULL, et SIX d'entre eux se connectaient encore la semaine
                     | de l'extraction — dont un compte `@verifall.com` trois
                     | jours avant. Copier NULL les aurait tous verrouillés
                     | dehors : mot de passe juste, connexion refusée, et pour
                     | une case qu'ils n'ont jamais eu à cocher parce que
                     | l'ancienne plateforme ne l'exigeait pas.
                     |
                     | Une connexion RÉUSSIE est une preuve de possession du
                     | compte plus forte qu'un clic dans un courriel : le clic
                     | prouve l'accès à la boîte, la connexion prouve la
                     | connaissance du mot de passe. On l'accepte donc comme
                     | date de vérification.
                     |
                     | Les 16 qui n'ont NI vérifié NI jamais ouvert de session
                     | restent inactifs : ils n'ont jamais rien prouvé, et le
                     | parcours OTP normal les attend à la première connexion.
                     */
                    'otp_verified_at' => $verifie ?? $derniereEntree,
                    'last_login_at' => $derniereEntree,
                    'streak_days' => (int) ($ancien['current_streak'] ?? 0),
                    'streak_best' => (int) ($ancien['max_streak'] ?? 0),
                    'tour_done_at' => ($ancien['has_seen_tour'] ?? '0') === '1' ? now() : null,
                    'onboarding_quiz_done_at' => ($ancien['passed_onboarding_quiz'] ?? '0') === '1' ? now() : null,
                    // Les deux drapeaux anciens deviennent des préférences
                    // nommées : le produit en compte quatre, on renseigne celles
                    // qui existaient et on laisse les autres à leur défaut.
                    'notification_preferences' => [
                        'verification_results' => ($ancien['notify_alerts'] ?? '1') === '1',
                        'forum_activity' => ($ancien['notify_news'] ?? '0') === '1',
                    ],
                    'created_at' => $this->date($ancien['created_at'] ?? null) ?? now(),
                    'updated_at' => $this->date($ancien['updated_at'] ?? null) ?? now(),
                    'deleted_at' => $this->date($ancien['deleted_at'] ?? null),
                ])->save();

                $this->ecrireLEmpreinte($compte, $ancien['password'] ?? null);

                $this->correspondance[(int) $ancien['id']] = (int) $compte->id;

                $this->poserLesRoles($compte, $parRole[(int) $ancien['id']] ?? []);
                $this->reporterLesSoldes($compte, $ancien);
            });

            $this->compteurs['comptes_importes']++;
        }

        $barre->finish();
        $this->newLine(2);
    }

    /**
     * Écrit l'empreinte SANS passer par le modèle.
     *
     * ## Pourquoi contourner le cast, ici et nulle part ailleurs
     *
     * Le cast `hashed` de Laravel appelle `Hash::verifyConfiguration()` et
     * **lève** dès que le coût de l'empreinte diffère de `BCRYPT_ROUNDS`. Le
     * dump est en coût 12 ; toute installation configurée autrement verrait
     * l'import s'arrêter au premier compte.
     *
     * Or `password_verify()` — ce que fait `Hash::check()` — vérifie
     * correctement une empreinte bcrypt **de n'importe quel coût**. La
     * connexion fonctionnerait donc parfaitement ; c'est le cast qui est plus
     * strict que nécessaire, parce qu'il est écrit pour des mots de passe
     * SAISIS, pas pour des empreintes reprises.
     *
     * Refuser aurait imposé une réinitialisation générale à 133 personnes pour
     * une question de coût de hachage. Le contournement est donc volontaire,
     * borné à cette écriture-là, et la garde qui compte reste en amont :
     * `Hash::isHashed()` refuse tout ce qui n'est pas une vraie empreinte.
     */
    private function ecrireLEmpreinte(User $compte, ?string $empreinte): void
    {
        if ($empreinte === null || $empreinte === '') {
            return;
        }

        DB::table('users')->where('id', $compte->id)->update(['password' => $empreinte]);
    }

    /** @param list<string> $noms */
    private function poserLesRoles(User $compte, array $noms): void
    {
        $traduits = [];

        foreach ($noms as $nom) {
            if (isset(self::ROLES[$nom])) {
                $traduits[] = self::ROLES[$nom];
            }
        }

        // Un compte sans rôle reconnu devient Citoyen : la nouvelle application
        // n'a aucun écran pour quelqu'un qui n'a rien, et le laisser sans rôle
        // produirait un compte qui se connecte et ne voit rien.
        $compte->syncRoles($traduits !== [] ? $traduits : [UserRole::Citoyen->value]);
    }

    /**
     * Reporte solde d'unités et points, AVEC leur ligne de journal.
     *
     * @param  array<string, string|null>  $ancien
     */
    private function reporterLesSoldes(User $compte, array $ancien): void
    {
        $unites = (int) ($ancien['remaining_units'] ?? 0);
        $points = (int) ($ancien['points'] ?? 0);

        if ($unites > 0) {
            $compte->forceFill(['units' => $unites])->save();

            /*
             | La ligne de report. `unit_logs` doit toujours totaliser
             | `users.units` : l'écart est le signal qu'une écriture a échappé au
             | service. Importer le solde sans elle casserait l'invariant dès le
             | premier compte, et personne ne s'en apercevrait avant la première
             | réclamation.
             */
            UnitLog::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $compte->id,
                'delta' => $unites,
                'balance_after' => $unites,
                'reason' => MotifMouvementUnite::AjustementAdmin->value,
                'label' => 'Report de l’ancienne plateforme',
            ]);

            $this->compteurs['lignes_unites']++;
        }

        if ($points > 0) {
            $compte->forceFill(['xp' => $points])->save();

            XpLog::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $compte->id,
                'delta' => $points,
                'balance_after' => $points,
                'reason' => MotifGainXp::AjustementAdmin->value,
                'label' => 'Report de l’ancienne plateforme',
            ]);
        }
    }

    // ═════════════════════════════════════════════════════ les vérifications

    /**
     * @param  list<array<string, string|null>>  $signalements
     * @param  list<array<string, string|null>>  $rapports
     */
    private function importerLesVerifications(array $signalements, array $rapports, bool $essai): void
    {
        // Les rapports d'OUTIL, regroupés par signalement : l'ancien modèle en
        // pose un par outil, le nouveau un par vérification.
        $parSignalement = [];

        foreach ($rapports as $rapport) {
            $parSignalement[(int) $rapport['signalement_id']][] = $rapport;
        }

        $barre = $this->output->createProgressBar(count($signalements));

        foreach ($signalements as $ancien) {
            $barre->advance();

            $uuid = (string) ($ancien['uuid'] ?? '');

            if ($uuid !== '' && Verification::withTrashed()->where('uuid', $uuid)->exists()) {
                // Idempotence : un import repris ne duplique pas.
                $this->compteurs['verifications_sautees']++;

                continue;
            }

            $statutAncien = (string) ($ancien['status'] ?? 'pending');

            if (! isset(self::STATUTS[$statutAncien])) {
                throw new RuntimeException(sprintf(
                    'Statut ancien inconnu : « %s ». Ajoutez-le à ImporterAncienneBase::STATUTS '.
                    'plutôt que de laisser la commande deviner.',
                    $statutAncien,
                ));
            }

            $auteur = $this->correspondance[(int) ($ancien['created_by'] ?? 0)] ?? null;

            if ($auteur === null && ($ancien['created_by'] ?? null) !== null) {
                $this->compteurs['auteurs_introuvables']++;
            }

            if ($essai) {
                $this->compteurs['verifications_importees']++;

                // Compté à blanc aussi : le nombre de rapports est le meilleur
                // indicateur de ce que l'historique vaut réellement.
                if (trim((string) ($ancien['verdict'] ?? '')) !== '') {
                    $this->compteurs['rapports']++;
                }

                continue;
            }

            DB::transaction(function () use ($ancien, $uuid, $statutAncien, $auteur, $parSignalement): void {
                $verification = new Verification;

                $verification->forceFill([
                    'uuid' => $uuid !== '' ? $uuid : (string) Str::uuid(),
                    // Recâblé, jamais copié : l'identifiant ancien désignerait
                    // n'importe qui dans la nouvelle base.
                    // `-1` est le marqueur d'essai : il ne doit jamais atteindre
                    // la base. Si on le voit ici, c'est un bug de branchement.
                    'user_id' => $auteur === -1 ? null : $auteur,
                    'assigned_to' => ($this->correspondance[(int) ($ancien['assigned_to'] ?? 0)] ?? null) === -1
                        ? null
                        : ($this->correspondance[(int) ($ancien['assigned_to'] ?? 0)] ?? null),
                    'type' => self::TYPES[(string) ($ancien['content_type'] ?? 'text')] ?? 'text',
                    'theme' => $this->propre($ancien['theme'] ?? null),
                    'status' => self::STATUTS[$statutAncien],
                    'claim' => $this->affirmation($ancien),
                    'context' => $this->note($ancien),
                    'file_path' => $this->propreTexte($ancien['evidence_file'] ?? null),
                    'cost_units' => 1,
                    'created_at' => $this->date($ancien['created_at'] ?? null) ?? now(),
                    'updated_at' => $this->date($ancien['updated_at'] ?? null) ?? now(),
                    'deleted_at' => $this->date($ancien['deleted_at'] ?? null),
                ])->save();

                $this->poserLeRapport($verification, $ancien, $parSignalement[(int) $ancien['id']] ?? []);
            });

            $this->compteurs['verifications_importees']++;
        }

        $barre->finish();
        $this->newLine(2);
    }

    /**
     * Agrège les N rapports d'outil en UN rapport d'analyse.
     *
     * @param  array<string, string|null>  $ancien
     * @param  list<array<string, string|null>>  $outils
     */
    private function poserLeRapport(Verification $verification, array $ancien, array $outils): void
    {
        $verdict = mb_strtolower(trim((string) ($ancien['verdict'] ?? '')));

        if ($verdict === '') {
            // Pas de verdict, pas de rapport. Une ligne vide dirait « analysé,
            // sans conclusion », ce qui est faux.
            return;
        }

        if (! isset(self::VERDICTS[$verdict])) {
            /*
             | Même règle que pour les statuts : on ARRÊTE plutôt que de deviner.
             |
             | Un verdict inventé est la pire donnée que ce produit puisse
             | porter — il publie une conclusion de fact-checking que personne
             | n'a rendue. Mieux vaut un import qui refuse de finir et nomme la
             | valeur qu'il ne connaît pas.
             */
            throw new RuntimeException(sprintf(
                'Verdict inconnu « %s » sur le signalement %s. Ajoutez-le à la table VERDICTS avant de reprendre.',
                $verdict,
                (string) ($ancien['id'] ?? '?'),
            ));
        }

        // Les réponses par outil ne sont pas perdues : elles deviennent le
        // tableau `scores`, indexé par nom d'outil.
        $scores = [];

        foreach ($outils as $outil) {
            $nom = (string) ($outil['tool_name'] ?? '');

            if ($nom !== '') {
                $scores[$nom] = (int) ($outil['confidence_score'] ?? 0);
            }
        }

        /*
         | `firstOrCreate` et non `create`, sur la clé de la contrainte unique.
         |
         | Ce n'est PAS la correction d'un défaut reproduit : sur le dump réel,
         | les 524 signalements portent tous un uuid distinct et non vide, et la
         | garde d'idempotence plus haut suffit — l'import rejoué deux fois de
         | suite rend des compteurs identiques, c'est vérifié par un test.
         |
         | C'est une garde de ROBUSTESSE, et elle a une histoire : le journal de
         | production du 2 septembre 2026 porte
         | « Duplicate entry '527' for key analysis_reports_verification_id_unique »,
         | et `docs/MIGRATION_ANCIENNE_BASE.md` §9 enregistre deux arrêts de
         | l'import. La cause exacte n'a pas été retrouvée — un état de base
         | qu'on ne peut plus observer, très probablement des rapports orphelins
         | dont l'identifiant a été réattribué.
         |
         | Ce qu'on peut dire, en revanche : cette commande écrit dans la base de
         | PRODUCTION, elle traite 524 lignes, et un `create` nu transforme une
         | seule ligne déjà présente en arrêt de tout l'import — après avoir
         | validé les précédentes, puisque les transactions sont par
         | enregistrement. Un saut compté coûte une ligne de rapport ; un arrêt
         | coûte une reprise entière, à la main, sur un serveur mutualisé.
         */
        $rapport = AnalysisReport::firstOrCreate([
            'verification_id' => $verification->id,
        ], [
            'uuid' => (string) Str::uuid(),
            'verdict' => self::VERDICTS[$verdict],
            'confidence' => (int) ($ancien['confidence_score'] ?? 0),
            'summary' => (string) ($ancien['ai_explanation'] ?? ''),
            'scores' => $scores !== [] ? $scores : null,
            'hate_speech_score' => $ancien['hate_speech_score'] !== null
                ? (int) $ancien['hate_speech_score']
                : null,
            'hate_speech_note' => $ancien['hate_speech_explanation'] ?: null,
        ]);

        // Le compteur dit ce qui a été POSÉ, pas ce qui a été traversé. Un
        // rapport déjà là gonflerait le total et ferait croire à un import plus
        // complet qu'il ne l'est — exactement le genre de chiffre qu'on relit
        // six mois plus tard en le croyant.
        if (! $rapport->wasRecentlyCreated) {
            $this->compteurs['rapports_sautes']++;

            return;
        }

        $this->compteurs['rapports']++;
    }

    // ════════════════════════════════════════════════════════════ la lecture

    /**
     * Extrait les lignes d'une table du dump, sans charger les 32 Mo en mémoire.
     *
     * Le dump est produit par `mysqldump` : les colonnes sont déclarées dans
     * l'`INSERT INTO … (…) VALUES`, et chaque tuple suit. On lit en flux et on
     * ne garde que la table demandée.
     *
     * @return list<array<string, string|null>>
     */
    private function lireTable(string $chemin, string $table): array
    {
        $flux = fopen($chemin, 'rb');

        if ($flux === false) {
            throw new RuntimeException('Lecture impossible : '.$chemin);
        }

        $colonnes = [];
        $lignes = [];
        $dansLaTable = false;

        while (($ligne = fgets($flux)) !== false) {
            if (str_starts_with($ligne, 'INSERT INTO `'.$table.'`')) {
                $dansLaTable = true;

                if (preg_match('/\(([^)]*)\) VALUES/', $ligne, $trouve) === 1) {
                    $colonnes = array_map(
                        static fn (string $c): string => trim($c, " `\r\n"),
                        explode(',', $trouve[1]),
                    );
                }

                continue;
            }

            if (! $dansLaTable) {
                continue;
            }

            $ligne = rtrim($ligne);

            if (! str_starts_with($ligne, '(')) {
                $dansLaTable = false;

                continue;
            }

            $valeurs = $this->decouper(rtrim($ligne, ',;'));

            if (count($valeurs) === count($colonnes)) {
                $lignes[] = array_combine($colonnes, $valeurs);
            }
        }

        fclose($flux);

        return $lignes;
    }

    /**
     * Découpe un tuple SQL en valeurs, en respectant les chaînes échappées.
     *
     * Un `explode(',')` couperait au milieu de « Bonjour, madame » et décalerait
     * toutes les colonnes suivantes — le genre d'erreur qui produit un import
     * qui « marche » avec des données mélangées.
     *
     * @return list<string|null>
     */
    private function decouper(string $tuple): array
    {
        $tuple = substr($tuple, 1, -1);
        $valeurs = [];
        $courante = '';
        $dansChaine = false;
        $echappe = false;

        for ($i = 0, $n = strlen($tuple); $i < $n; $i++) {
            $c = $tuple[$i];

            if ($echappe) {
                $courante .= match ($c) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '0' => "\0",
                    default => $c,
                };
                $echappe = false;

                continue;
            }

            if ($c === '\\' && $dansChaine) {
                $echappe = true;

                continue;
            }

            if ($c === "'") {
                $dansChaine = ! $dansChaine;

                continue;
            }

            if ($c === ',' && ! $dansChaine) {
                $valeurs[] = $this->normaliser($courante);
                $courante = '';

                continue;
            }

            $courante .= $c;
        }

        $valeurs[] = $this->normaliser($courante);

        return $valeurs;
    }

    private function normaliser(string $brut): ?string
    {
        $brut = trim($brut);

        return $brut === 'NULL' ? null : $brut;
    }

    /**
     * @param  list<array<string, string|null>>  $anciens
     * @return string|null la première empreinte suspecte
     */
    private function verifierLesEmpreintes(array $anciens): ?string
    {
        foreach ($anciens as $ancien) {
            $empreinte = (string) ($ancien['password'] ?? '');

            /*
             | `Hash::isHashed()` et non un simple préfixe — c'est LA MÊME
             | vérification que le cast `hashed` du modèle. Une empreinte
             | malformée qui commencerait par `$2y$` passerait le test du préfixe,
             | puis serait REHACHÉE en silence par le cast : le compte
             | deviendrait inaccessible, et rien ne le signalerait avant la
             | première plainte.
             */
            if ($empreinte !== '' && ! Hash::isHashed($empreinte)) {
                return substr($empreinte, 0, 12).'…';
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, string|null>>  $roles
     * @return array<int, list<string>>
     */
    private function rolesParUtilisateur(array $roles): array
    {
        // Les identifiants de rôle anciens : 1 Admin, 2 Mentor, 3 User.
        $noms = [1 => 'Admin', 2 => 'Mentor', 3 => 'User'];
        $parUtilisateur = [];

        foreach ($roles as $ligne) {
            $id = (int) ($ligne['model_id'] ?? 0);
            $role = $noms[(int) ($ligne['role_id'] ?? 0)] ?? null;

            if ($id > 0 && $role !== null) {
                $parUtilisateur[$id][] = $role;
            }
        }

        return $parUtilisateur;
    }

    /**
     * Nettoie une valeur de texte reprise telle quelle de l'ancienne base.
     *
     * ## Ce que ça corrige, et pourquoi ça ne se voyait pas
     *
     * 41 noms sur 133 portent une espace de bord, et 40 une espace double
     * interne — « Jean marie  EYEMI ». Importés tels quels, ils s'affichent
     * ainsi partout : entête de tableau de bord, fil du forum, liste du
     * back-office. Et ils cassent la recherche par nom, qui ne trouve pas
     * « Jean marie EYEMI ».
     *
     * Le relevé qui les a manqués la première fois cherchait
     * `name <> TRIM(name)`. En collation `utf8mb4_unicode_ci` — PAD SPACE — les
     * espaces de FIN sont ignorées à la comparaison : l'expression est toujours
     * fausse. Il faut comparer les LONGUEURS. La leçon vaut au-delà d'ici : un
     * contrôle de propreté écrit en SQL doit se méfier de la collation qui
     * l'exécute.
     *
     * `Str::squish` fait les deux d'un coup — bords et répétitions internes —
     * et traite les espaces insécables, fréquentes dans du texte collé depuis
     * un traitement de texte ou WhatsApp.
     *
     * Une chaîne qui ne contenait QUE des espaces devient `null` et non `''` :
     * une colonne facultative doit être vide, pas contenir du vide.
     */
    private function propre(?string $valeur): ?string
    {
        $net = Str::squish((string) $valeur);

        return $net === '' ? null : $net;
    }

    /**
     * Nettoie un texte LONG, sans écraser sa mise en forme.
     *
     * ## Pourquoi ce second nettoyeur, et pas `propre()`
     *
     * `Str::squish` remplace toute suite d'espaces par une seule — retours à la
     * ligne compris. Sur un nom, c'est ce qu'on veut. Sur une allégation de
     * 4 995 caractères, cela colle les paragraphes en un bloc : 75 contenus et
     * 32 descriptions de l'ancienne base sont multilignes, et cette mise en
     * forme est celle que la personne a écrite. La détruire à l'import serait
     * réécrire son propos.
     *
     * On retire donc les bords — 64 contenus en portent — et rien d'autre.
     *
     * Le titre, lui, passe par `propre()` : un titre EST une ligne, et les 28
     * qui contiennent un retour à la ligne sont eux-mêmes le défaut à corriger.
     */
    private function propreTexte(?string $valeur): ?string
    {
        $net = trim((string) $valeur);

        return $net === '' ? null : $net;
    }

    private function date(?string $valeur): ?string
    {
        return ($valeur === null || $valeur === '' || str_starts_with($valeur, '0000')) ? null : $valeur;
    }

    private function rapport(bool $essai): void
    {
        $this->newLine();
        $this->info($essai ? '— Essai à blanc, rien n’a été écrit —' : '— Import terminé —');

        $this->table(['Ce qui a été fait', 'Nombre'], [
            ['Comptes importés', $this->compteurs['comptes_importes']],
            ['Comptes sautés (adresse déjà présente)', $this->compteurs['comptes_sautes']],
            ['Vérifications importées', $this->compteurs['verifications_importees']],
            ['Vérifications sautées (déjà là)', $this->compteurs['verifications_sautees']],
            ['Rapports d’analyse écrits', $this->compteurs['rapports']],
            ['Rapports sautés (déjà présents)', $this->compteurs['rapports_sautes']],
            ['Lignes de report d’unités', $this->compteurs['lignes_unites']],
            ['Auteurs introuvables (mis à null)', $this->compteurs['auteurs_introuvables']],
        ]);

        if ($this->compteurs['auteurs_introuvables'] > 0) {
            $this->warn(sprintf(
                '%d vérification(s) sans auteur retrouvable : leur compte n’existait plus dans l’ancienne base.',
                $this->compteurs['auteurs_introuvables'],
            ));
        }

        if ($essai) {
            $this->line('');
            $this->line('  Relisez docs/MIGRATION_ANCIENNE_BASE.md, sauvegardez la base,');
            $this->line('  puis relancez sans --essai.');
        }
    }
}
