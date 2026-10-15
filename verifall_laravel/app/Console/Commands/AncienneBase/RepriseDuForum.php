<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Models\ForumCategory;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Reply;
use App\Models\Thread;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * `--seulement=forum` — sujets, réponses et sondages de l'ancien forum.
 *
 * **Le raisonnement complet est dans `docs/MIGRATION_ANCIENNE_BASE.md`, §29 à
 * §31.** L'essentiel :
 *
 * ## Un sujet est repris s'il est un sujet que le forum accepterait
 *
 * Les règles sont celles du formulaire citoyen (`Citizen\Forum`) : un titre de
 * 10 à 180 caractères, un corps de 20 à 5 000. L'ancien forum était un fil
 * « à la réseau social » où le titre était FACULTATIF — et il est vide sur les
 * cinq sujets du dump, tous des messages d'essai de quelques mots (« Hello »,
 * « Salut a tous »). La colonne `threads.title` est obligatoire ; le titre
 * n'est pas inventé (ni recopié du corps : « Hello » ferait un titre et un
 * corps identiques, et resterait refusé par le formulaire). Ces sujets sont
 * donc SAUTÉS et comptés — avec leurs sondages.
 *
 * ## Le contenu est purifié AVANT persistance
 *
 * Exactement comme `AnimationDuForum::assainir()` : `Purifier::clean(…,
 * 'default')`. Les vues le repurifient à l'affichage (`{!! clean() !!}`) ; les
 * deux ne se remplacent pas (règle 3 du projet).
 *
 * ## Les auteurs sont recâblés par l'adresse
 *
 * `threads.user_id` et `replies.user_id` sont NULLABLES : un auteur introuvable
 * donne un message sans auteur, compté — le forum affiche déjà ce cas pour un
 * compte supprimé. Il n'y a pas lieu de perdre le message.
 */
final class RepriseDuForum
{
    use LitLAncienneBase;

    /** Les statuts de sujet anciens qu'on sait traduire : un seul, public. */
    private const STATUTS_ACCEPTES = ['public'];

    /**
     * Tables vides dans le dump analysé, sans règle écrite. Non vides, elles
     * arrêtent l'import AVANT la première écriture.
     *
     * @var array<string, string>
     */
    private const TABLES_SANS_REGLE = [
        'forum_comments' => 'second système de réponses (avec messages vocaux), concurrent de forum_replies : lequel fait foi reste à établir',
        'forum_reactions' => 'réactions de types libres, quand le forum nouveau ne connaît que le « j’aime »',
        'poll_votes' => 'les votes anciens portent sur une option, le nouveau vote porte sur le sondage (un vote par personne)',
        'forum_course_threads' => 'discussions de cours : le forum nouveau n’en a pas',
    ];

    /** @var array<string, int> */
    private array $compteurs = [];

    /** @var array<string, int> raison → nombre */
    private array $sauts = [];

    public function __construct(private readonly Command $sortie) {}

    public function executer(string $chemin, bool $essai): int
    {
        $this->compteurs = [
            'sujets_lus' => 0, 'sujets_importes' => 0, 'reponses_importees' => 0, 'sondages_importes' => 0,
            'categories_rapprochees' => 0, 'categories_creees' => 0, 'categories_ecartees' => 0,
            'auteurs_introuvables' => 0, 'liens_invalides' => 0, 'medias_non_repris' => 0,
        ];
        $this->sauts = [];

        $categories = $this->lireTable($chemin, 'forum_categories');
        $sujets = $this->lireTable($chemin, 'forum_threads');
        $reponses = $this->lireTable($chemin, 'forum_replies');
        $sondages = $this->lireTable($chemin, 'forum_polls');
        $options = $this->lireTable($chemin, 'forum_poll_options');

        $this->compteurs['sujets_lus'] = count($sujets);

        $this->sortie->line(sprintf(
            '  %d catégories, %d sujets, %d réponses, %d sondages.',
            count($categories), count($sujets), count($reponses), count($sondages),
        ));

        // 1. Le contrôle préalable de TOUT le dump.
        if (($faute = $this->verifier($chemin, $sujets)) !== null) {
            $this->sortie->error($faute);
            $this->sortie->line('  Rien n’a été écrit.');

            return Command::FAILURE;
        }

        $comptes = $this->comptesParAdresse($chemin);

        // 2. Le tri : ce qui sera repris, avant d'écrire quoi que ce soit.
        $retenus = [];

        foreach ($sujets as $ancien) {
            $raison = $this->raisonDeSauterLeSujet($ancien);

            if ($raison !== null) {
                $this->sauter($raison);

                continue;
            }

            $retenus[(int) ($ancien['id'] ?? 0)] = $ancien;
        }

        $categoriesCibles = $this->rapprocherLesCategories($categories, $retenus, $essai);

        // 3. L'écriture : sujet, puis ses réponses et son sondage, dans UNE transaction.
        $optionsParSondage = [];

        foreach ($options as $option) {
            $optionsParSondage[(int) ($option['forum_poll_id'] ?? 0)][] = $option;
        }

        $sondagesParSujet = [];

        foreach ($sondages as $sondage) {
            $sondagesParSujet[(int) ($sondage['forum_thread_id'] ?? 0)][] = $sondage;
        }

        $reponsesParSujet = [];

        foreach ($reponses as $reponse) {
            $reponsesParSujet[(int) ($reponse['forum_thread_id'] ?? 0)][] = $reponse;
        }

        foreach ($sujets as $ancien) {
            $id = (int) ($ancien['id'] ?? 0);

            if (! isset($retenus[$id])) {
                foreach ($reponsesParSujet[$id] ?? [] as $reponse) {
                    $this->sauter('Réponse d’un sujet non repris');
                }

                foreach ($sondagesParSujet[$id] ?? [] as $sondage) {
                    $this->sauter('Sondage d’un sujet non repris');
                }

                continue;
            }

            $titre = (string) $this->propre($ancien['title'] ?? null);
            $cree = $this->date($ancien['created_at'] ?? null);

            if (Thread::withTrashed()->where('title', $titre)->where('created_at', $cree)->exists()) {
                // Idempotence : un sujet ancien n'a pas d'uuid, son titre et sa
                // date de création le désignent sans ambiguïté.
                $this->sauter('Sujet déjà présent (import antérieur)');

                continue;
            }

            $auteur = $comptes[(int) ($ancien['user_id'] ?? 0)] ?? null;

            if ($auteur === null) {
                $this->compteurs['auteurs_introuvables']++;
            }

            $lien = $this->lienValide($ancien['source_link'] ?? null);

            if ($lien === null && $this->propre($ancien['source_link'] ?? null) !== null) {
                $this->compteurs['liens_invalides']++;
            }

            if ($this->propre($ancien['media_path'] ?? null) !== null) {
                $this->compteurs['medias_non_repris']++;
            }

            $sondagesRetenus = [];

            foreach ($sondagesParSujet[$id] ?? [] as $sondage) {
                $libelles = $this->libellesUtiles($optionsParSondage[(int) ($sondage['id'] ?? 0)] ?? []);
                $question = (string) $this->propre($sondage['question'] ?? null);

                if (mb_strlen($question) < 5 || mb_strlen($question) > 300 || count($libelles) < 2 || count($libelles) > 6 || $sondagesRetenus !== []) {
                    $this->sauter('Sondage que le forum refuserait (question, ou 2 à 6 réponses distinctes de 120 caractères au plus)');

                    continue;
                }

                $sondagesRetenus[] = [$sondage, $libelles];
            }

            $reponsesRetenues = [];

            foreach ($reponsesParSujet[$id] ?? [] as $reponse) {
                if (trim(strip_tags($this->assainir((string) ($reponse['content'] ?? '')))) === '') {
                    $this->sauter('Réponse vide une fois purifiée');

                    continue;
                }

                if (! isset($comptes[(int) ($reponse['user_id'] ?? 0)])) {
                    $this->compteurs['auteurs_introuvables']++;
                }

                $reponsesRetenues[] = $reponse;
            }

            $this->compteurs['sujets_importes']++;
            $this->compteurs['sondages_importes'] += count($sondagesRetenus);
            $this->compteurs['reponses_importees'] += count($reponsesRetenues);

            if ($essai) {
                continue;
            }

            DB::transaction(function () use ($ancien, $titre, $cree, $auteur, $lien, $categoriesCibles, $sondagesRetenus, $reponsesRetenues, $comptes): void {
                $maj = $this->date($ancien['updated_at'] ?? null) ?? $cree;

                $sujet = (new Thread)->forceFill([
                    'uuid' => (string) Str::uuid(),
                    'slug' => $this->slugLibre($titre),
                    'user_id' => $auteur,
                    'forum_category_id' => $categoriesCibles[(int) ($ancien['category_id'] ?? 0)] ?? null,
                    'title' => $titre,
                    'body' => $this->assainir((string) $ancien['content']),
                    'source_url' => $lien,
                    'is_locked' => ($ancien['is_locked'] ?? '0') === '1',
                    'is_pinned' => ($ancien['is_pinned'] ?? '0') === '1',
                    'created_at' => $cree,
                    'updated_at' => $maj,
                    'deleted_at' => $this->date($ancien['deleted_at'] ?? null),
                ]);
                $sujet->save();

                $derniere = $maj;

                foreach ($reponsesRetenues as $ancienne) {
                    $auteurReponse = $comptes[(int) ($ancienne['user_id'] ?? 0)] ?? null;
                    $date = $this->date($ancienne['created_at'] ?? null) ?? $cree;

                    (new Reply)->forceFill([
                        'uuid' => (string) Str::uuid(),
                        'thread_id' => $sujet->id,
                        'user_id' => $auteurReponse,
                        'body' => $this->assainir((string) $ancienne['content']),
                        'is_official' => ($ancienne['is_mentor_reply'] ?? '0') === '1',
                        'created_at' => $date,
                        'updated_at' => $this->date($ancienne['updated_at'] ?? null) ?? $date,
                        'deleted_at' => $this->date($ancienne['deleted_at'] ?? null),
                    ])->save();

                    $derniere = max((string) $derniere, $date);
                }

                foreach ($sondagesRetenus as [$sondage, $libelles]) {
                    $nouveau = (new Poll)->forceFill([
                        'uuid' => (string) Str::uuid(),
                        'thread_id' => $sujet->id,
                        'user_id' => $auteur,
                        'question' => (string) $this->propre($sondage['question'] ?? null),
                        // Recalculé des votes — et aucun vote ancien n'est repris.
                        'votes_count' => 0,
                        'created_at' => $this->date($sondage['created_at'] ?? null) ?? $cree,
                        'updated_at' => $this->date($sondage['updated_at'] ?? null) ?? $cree,
                    ]);
                    $nouveau->save();

                    foreach ($libelles as $position => $libelle) {
                        (new PollOption)->forceFill([
                            'uuid' => (string) Str::uuid(),
                            'poll_id' => $nouveau->id,
                            'label' => $libelle,
                            'position' => $position,
                            'votes_count' => 0,
                        ])->save();
                    }
                }

                // Les compteurs dénormalisés, RECALCULÉS comme le fait le service.
                $sujet->forceFill([
                    'replies_count' => Reply::query()->where('thread_id', $sujet->id)->count(),
                    'last_activity_at' => $derniere,
                ])->save();
            });
        }

        $this->rapport($essai);

        return Command::SUCCESS;
    }

    /**
     * @param  list<array<string, string|null>>  $sujets
     */
    private function verifier(string $chemin, array $sujets): ?string
    {
        foreach (self::TABLES_SANS_REGLE as $table => $raison) {
            $lignes = count($this->lireTable($chemin, $table));

            if ($lignes > 0) {
                return sprintf('La table ancienne « %s » contient %d ligne(s), sans règle de reprise écrite : %s.', $table, $lignes, $raison);
            }
        }

        $statutsInconnus = [];
        $typesInconnus = [];

        foreach ($sujets as $sujet) {
            $statut = trim((string) ($sujet['status'] ?? ''));

            if (! in_array($statut, self::STATUTS_ACCEPTES, true)) {
                $statutsInconnus[$statut] = ($statutsInconnus[$statut] ?? 0) + 1;
            }

            $type = trim((string) ($sujet['type'] ?? ''));

            if (! in_array($type, ['text', 'image', 'video'], true)) {
                $typesInconnus[$type] = ($typesInconnus[$type] ?? 0) + 1;
            }
        }

        if ($statutsInconnus !== []) {
            return 'Statut(s) de sujet inconnu(s) : '.$this->enumerer($statutsInconnus).'. Un sujet masqué ou en attente ne doit pas devenir public par la reprise.';
        }

        if ($typesInconnus !== []) {
            return 'Type(s) de sujet inconnu(s) : '.$this->enumerer($typesInconnus).'.';
        }

        return null;
    }

    /**
     * Pourquoi ce sujet n'est pas repris, ou `null`.
     *
     * @param  array<string, string|null>  $ancien
     */
    private function raisonDeSauterLeSujet(array $ancien): ?string
    {
        if (($ancien['is_sensitive'] ?? '0') === '1') {
            // La nouvelle table n'a pas de drapeau « sensible » : le reprendre
            // publierait sans avertissement un contenu que l'ancienne équipe
            // avait voulu signaler.
            return 'Sujet marqué sensible (aucune colonne pour l’avertissement)';
        }

        $validateur = Validator::make([
            'titre' => $this->propre($ancien['title'] ?? null),
            'corps' => $this->propreTexte($ancien['content'] ?? null),
        ], [
            // Les règles de `Citizen\Forum::publier()`.
            'titre' => ['required', 'string', 'min:10', 'max:180'],
            'corps' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        if ($validateur->errors()->has('titre')) {
            return $this->propre($ancien['title'] ?? null) === null
                ? 'Sujet sans titre (colonne obligatoire, titre non inventé)'
                : 'Sujet au titre que le forum refuserait (10 à 180 caractères)';
        }

        if ($validateur->errors()->has('corps')) {
            return 'Sujet au message que le forum refuserait (20 à 5 000 caractères)';
        }

        return trim(strip_tags($this->assainir((string) $ancien['content']))) === ''
            ? 'Sujet vide une fois purifié'
            : null;
    }

    /**
     * Ancien `forum_categories.id` → nouvel id.
     *
     * Rapprochée PAR SLUG d'une rubrique existante (l'existante gagne, son nom
     * et sa couleur ne bougent pas). Une rubrique sans équivalent n'est créée
     * que si un sujet REPRIS s'y trouve : créer « Débats » pour zéro sujet
     * ajouterait un filtre vide au forum.
     *
     * @param  list<array<string, string|null>>  $categories
     * @param  array<int, array<string, string|null>>  $retenus
     * @return array<int, int|null>
     */
    private function rapprocherLesCategories(array $categories, array $retenus, bool $essai): array
    {
        $utilisees = array_flip(array_map(static fn (array $s): int => (int) ($s['category_id'] ?? 0), $retenus));
        $cibles = [];

        foreach ($categories as $ancienne) {
            $id = (int) ($ancienne['id'] ?? 0);
            $slug = Str::slug((string) ($ancienne['slug'] ?? '')) ?: Str::slug((string) ($ancienne['name'] ?? ''));
            $existante = ForumCategory::query()->where('slug', $slug)->first();

            if ($existante !== null) {
                $cibles[$id] = (int) $existante->id;
                $this->compteurs['categories_rapprochees']++;

                continue;
            }

            if (! isset($utilisees[$id])) {
                $cibles[$id] = null;
                $this->compteurs['categories_ecartees']++;

                continue;
            }

            $this->compteurs['categories_creees']++;

            if ($essai) {
                $cibles[$id] = null;

                continue;
            }

            $couleur = (string) ($ancienne['color'] ?? '');

            $cibles[$id] = (int) ForumCategory::query()->create([
                'uuid' => (string) Str::uuid(),
                'slug' => $slug,
                'name' => Str::limit($this->propre($ancienne['name'] ?? null) ?? $slug, 80, ''),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur) === 1 ? $couleur : null,
                'position' => (int) ForumCategory::query()->max('position') + 1,
                'is_active' => true,
            ])->id;
        }

        return $cibles;
    }

    /**
     * Les libellés d'options retenus — la règle de `Sondages::libellesUtiles()`
     * (sans balise, dédoublonnés), bornée à 120 comme le formulaire.
     *
     * Le second sondage du dump a deux options « 1 » et « 1 » : une seule
     * réponse distincte, donc refusé.
     *
     * @param  list<array<string, string|null>>  $options
     * @return list<string>
     */
    private function libellesUtiles(array $options): array
    {
        $libelles = [];

        foreach ($options as $option) {
            $libelle = Str::squish(strip_tags((string) ($option['option_text'] ?? '')));

            if ($libelle === '' || mb_strlen($libelle) > 120) {
                continue;
            }

            if (! in_array($libelle, $libelles, true)) {
                $libelles[] = $libelle;
            }
        }

        return $libelles;
    }

    /** La purification du service de forum, à l'identique. */
    private function assainir(string $texte): string
    {
        return trim((string) Purifier::clean(trim($texte), 'default'));
    }

    /** Un lien signalé n'est gardé que s'il est une vraie URL http(s). */
    private function lienValide(?string $lien): ?string
    {
        $lien = $this->propre($lien);

        if ($lien === null || mb_strlen($lien) > 500 || filter_var($lien, FILTER_VALIDATE_URL) === false
            || ! in_array(mb_strtolower((string) parse_url($lien, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        return $lien;
    }

    private function slugLibre(string $titre): string
    {
        $base = Str::slug(Str::limit($titre, 80, '')) ?: 'sujet';
        $slug = $base;

        while (Thread::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    private function sauter(string $raison): void
    {
        $this->sauts[$raison] = ($this->sauts[$raison] ?? 0) + 1;
    }

    private function rapport(bool $essai): void
    {
        $this->sortie->newLine();
        $this->sortie->info($essai ? '— Forum : essai à blanc, rien n’a été écrit —' : '— Forum : reprise terminée —');

        $lignes = [
            ['Sujets lus', $this->compteurs['sujets_lus']],
            ['Sujets importés', $this->compteurs['sujets_importes']],
            ['Réponses importées', $this->compteurs['reponses_importees']],
            ['Sondages importés', $this->compteurs['sondages_importes']],
            ['Catégories rapprochées d’une rubrique existante', $this->compteurs['categories_rapprochees']],
            ['Catégories créées (portant un sujet repris)', $this->compteurs['categories_creees']],
            ['Catégories écartées (sans équivalent ni sujet repris)', $this->compteurs['categories_ecartees']],
            ['Auteurs introuvables (message sans auteur)', $this->compteurs['auteurs_introuvables']],
            ['Liens signalés invalides (non repris)', $this->compteurs['liens_invalides']],
            ['Médias non repris (fichiers de l’ancien disque)', $this->compteurs['medias_non_repris']],
        ];

        foreach ($this->sauts as $raison => $nombre) {
            $lignes[] = ['Sautés · '.$raison, $nombre];
        }

        $this->sortie->table(['Forum', 'Nombre'], $lignes);
    }
}
