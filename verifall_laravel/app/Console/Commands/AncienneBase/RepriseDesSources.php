<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * `--seulement=sources` — l'ancienne liste des sources vérifiées devient le
 * référentiel `trusted_sources`.
 *
 * **Le raisonnement complet est dans `docs/MIGRATION_ANCIENNE_BASE.md`, §19 à
 * §23.** Ce bloc n'en retient que ce qu'il faut avoir en tête en lisant le code.
 *
 * ## Une fiche importée CHANGE le score des analyses
 *
 * Depuis le commit `0993de9`, `AdaptateurHttp::fiabiliteDeLaSource()` rend la
 * crédibilité de la fiche au lieu du défaut de l'adaptateur, et
 * `AnalyseTechniqueAdapter` saute l'heuristique d'usurpation pour un domaine
 * référencé. Ce n'est donc pas une liste décorative : chaque fiche ACTIVE
 * pondère les preuves de toutes les vérifications futures qui citent son
 * domaine, et paraît sur la page publique des sources. D'où deux règles.
 *
 * **1. Aucune crédibilité sans source réelle.** L'ancien `trust_index` vaut
 * 100 sur 141 lignes sur 143 — c'est la valeur PAR DÉFAUT de la colonne, et
 * 139 de ces lignes ont été insérées d'un bloc le 6 mai 2026, jamais retouchées.
 * Un 100 ne se distingue donc pas d'un « jamais renseigné ». Il n'est repris
 * que s'il diffère du défaut (Africa Check : 95 ; le site d'hameçonnage : 0) ;
 * sinon la fiche reçoit la crédibilité par défaut de sa FAMILLE — exactement
 * la règle de `SourcesVeraSeeder`.
 *
 * **2. La liste noire ne devient pas une source de confiance.** Voir
 * `attributsDeLaFiche()`.
 *
 * ## Les doublons de domaine : une fiche par domaine, la ligne « site entier » gagne
 *
 * `trusted_sources.domain` est unique, et le moteur interroge PAR DOMAINE. Les
 * huit doublons de l'ancienne base sont presque tous une RUBRIQUE de
 * fact-checking à côté du titre entier — `lemonde.fr/les-decodeurs` et
 * `lemonde.fr`. Le crédit porte sur tout le domaine : la ligne qui décrit le
 * site entier (sans chemin) est donc la bonne ; à égalité, la première du
 * dump. La ligne absorbée n'est pas perdue : elle est citée dans la note.
 */
final class RepriseDesSources
{
    use LitLAncienneBase;

    /**
     * La valeur par défaut de l'ancienne colonne `trust_index`.
     *
     * Une ligne qui la porte n'a rien décidé qu'on puisse prouver.
     */
    private const INDICE_PAR_DEFAUT = 100;

    /**
     * L'ancienne catégorie (colonne `category`, chaîne) → la famille nouvelle.
     *
     * La table `verified_source_categories` (3 lignes) n'est PAS la bonne
     * source : elle range le site d'hameçonnage parmi les « Medias Publics »,
     * et ne distingue pas une institution d'un titre de presse. La chaîne
     * `category`, portée par chaque ligne, est la plus précise des deux.
     *
     * Toute catégorie absente d'ici arrête l'import AVANT la première écriture.
     *
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'fact-checking' => 'reseaux-de-fact-checking',
        'fact-checker' => 'reseaux-de-fact-checking',
        'reference' => 'publications-de-reference',
        // Les deux seules lignes sont `gouv.bj` et `sante.gouv.bj`. La
        // correspondance est bornée aux domaines `.bj` : une institution
        // étrangère dans un dump plus récent arrêterait l'import au lieu
        // d'être rangée parmi les institutions béninoises.
        'institution' => 'institutions-beninoises',
    ];

    /**
     * Les familles nouvelles, avec les valeurs EXACTES des seeders qui les
     * définissent (`SourcesFiablesSeeder`, `SourcesVeraSeeder`) — un test le
     * vérifie. Sur une base où elles existent déjà, elles ne sont pas
     * touchées : la rédaction a pu amender leur crédibilité.
     *
     * @var array<string, array{0: string, 1: int, 2: string, 3: int}>
     */
    public const FAMILLES = [
        'institutions-beninoises' => [
            'Institutions béninoises', 85,
            'Portails officiels de l\'État béninois et de ses démembrements.',
            20,
        ],
        'reseaux-de-fact-checking' => [
            'Réseaux de fact-checking', 88,
            'Rédactions membres de réseaux de vérification reconnus.',
            50,
        ],
        'publications-de-reference' => [
            'Publications de référence', 78,
            'Titres de presse et publications établies, hors agences. Le suivi éditorial reste nécessaire.',
            60,
        ],
    ];

    /** @var array<string, int> */
    private array $compteurs = [];

    /** @var list<array{0: string, 1: string}> domaine, fiche existante */
    private array $sautees = [];

    /** @var list<array{0: string, 1: string}> ligne absorbée, domaine de la fiche */
    private array $fusions = [];

    public function __construct(private readonly Command $sortie) {}

    public function executer(string $chemin, bool $essai): int
    {
        $this->compteurs = [
            'lues' => 0, 'domaines' => 0, 'fusionnees' => 0, 'importees' => 0,
            'sautees' => 0, 'liste_noire' => 0, 'credibilite_reprise' => 0, 'credibilite_famille' => 0,
        ];
        $this->sautees = [];
        $this->fusions = [];

        $lignes = $this->lireTable($chemin, 'verified_sources');
        $this->compteurs['lues'] = count($lignes);

        $this->sortie->line(sprintf('  %d sources vérifiées dans l’ancienne base.', count($lignes)));

        // 1. Contrôle préalable de TOUTES les lignes (incident n° 1).
        if (($faute = $this->verifier($lignes)) !== null) {
            $this->sortie->error($faute);
            $this->sortie->line('  Rien n’a été écrit. Complétez la table de traduction ou corrigez la source, puis relancez.');

            return Command::FAILURE;
        }

        // 2. Une fiche par domaine.
        $groupes = $this->regrouperParDomaine($lignes);
        $this->compteurs['domaines'] = count($groupes);

        // 3. L'écriture — l'existant gagne toujours.
        foreach ($groupes as $domaine => $groupe) {
            [$gagnante, $absorbees] = $this->departager($groupe);

            foreach ($absorbees as $absorbee) {
                $this->fusions[] = [$this->propre($absorbee['name'] ?? null) ?? '?', $domaine];
                $this->compteurs['fusionnees']++;
            }

            $existante = TrustedSource::query()->where('domain', $domaine)->first();

            if ($existante !== null) {
                $this->sautees[] = [$domaine, (string) $existante->name];
                $this->compteurs['sautees']++;

                continue;
            }

            $attributs = $this->attributsDeLaFiche($domaine, $gagnante, $absorbees);

            $this->compteurs[$attributs['is_active'] ? 'importees' : 'liste_noire']++;
            $this->compteurs[$this->indiceReel($gagnante) || ! $attributs['is_active'] ? 'credibilite_reprise' : 'credibilite_famille']++;

            if ($essai) {
                continue;
            }

            DB::transaction(function () use ($attributs, $gagnante): void {
                $famille = $this->famille($gagnante, creer: true);

                // Aucun identifiant ancien : nouvel auto-incrément, nouvel uuid.
                (new TrustedSource)->forceFill($attributs + [
                    'uuid' => (string) Str::uuid(),
                    'source_category_id' => $famille?->id,
                ])->save();
            });
        }

        if (! $essai && $this->compteurs['importees'] + $this->compteurs['liste_noire'] > 0) {
            /*
             | Le moteur lit le référentiel dans un cache de 15 minutes. Sans cet
             | oubli, les fiches importées n'auraient aucun effet pendant un
             | quart d'heure — et l'écran d'administration, qui lit la table,
             | contredirait le moteur.
             */
            app(DepotSourcesFiables::class)->oublier();
        }

        $this->rapport($essai);

        return Command::SUCCESS;
    }

    /**
     * Le contrôle préalable : chaque ligne est traduisible, et la fiche qu'on
     * écrirait passe les règles de la colonne ET de l'écran `Staff\SourcesVerifiees`.
     *
     * @param  list<array<string, string|null>>  $lignes
     */
    private function verifier(array $lignes): ?string
    {
        $categoriesInconnues = [];
        $fautes = [];

        foreach ($lignes as $ligne) {
            $nom = $this->propre($ligne['name'] ?? null) ?? '(sans nom)';
            $noire = $this->estEnListeNoire($ligne);
            $domaine = TrustedSource::normaliserLeDomaine((string) ($ligne['url'] ?? ''));

            if (! in_array((string) ($ligne['is_blacklisted'] ?? ''), ['0', '1'], true)) {
                $fautes[] = sprintf('« %s » : is_blacklisted inattendu (« %s »)', $nom, (string) ($ligne['is_blacklisted'] ?? ''));
            }

            $indice = (string) ($ligne['trust_index'] ?? '');

            if (preg_match('/^\d{1,3}$/', $indice) !== 1 || (int) $indice > 100) {
                $fautes[] = sprintf('« %s » : indice de confiance hors bornes (« %s »)', $nom, $indice);
            }

            $categorie = $this->categorieAncienne($ligne);

            if (! $noire && ! isset(self::CATEGORIES[$categorie])) {
                $categoriesInconnues[$categorie] = ($categoriesInconnues[$categorie] ?? 0) + 1;
            }

            if (! $noire && $categorie === 'institution' && ! str_ends_with($domaine, '.bj')) {
                $fautes[] = sprintf('« %s » : institution hors du Bénin (%s) — la famille « Institutions béninoises » ne lui convient pas', $nom, $domaine);
            }
        }

        if ($categoriesInconnues !== []) {
            return 'Catégorie(s) de source inconnue(s) : '.$this->enumerer($categoriesInconnues).'. À ajouter à RepriseDesSources::CATEGORIES.';
        }

        if ($fautes !== []) {
            return 'Source(s) invalide(s) : '.implode(' | ', $fautes);
        }

        foreach ($this->regrouperParDomaine($lignes) as $domaine => $groupe) {
            /*
             | La collision DOUTEUSE : un domaine en liste noire sur une ligne et
             | digne de confiance sur une autre. Aucune règle de départage ne
             | peut trancher cela à la place de la rédaction.
             */
            $etats = array_unique(array_map(fn (array $l): bool => $this->estEnListeNoire($l), $groupe));

            if (count($etats) > 1) {
                $fautes[] = sprintf('%s est À LA FOIS en liste noire et référencé comme fiable', $domaine);

                continue;
            }

            [$gagnante, $absorbees] = $this->departager($groupe);
            $attributs = $this->attributsDeLaFiche($domaine, $gagnante, $absorbees);

            $validateur = Validator::make([
                'nom' => $attributs['name'],
                'domaine' => $attributs['domain'],
                'lien' => $attributs['url'],
                'credibilite' => $attributs['credibility'],
                'note' => $attributs['notes'],
            ], [
                // Les règles de `Staff\SourcesVerifiees::rules()`, à l'unicité
                // près : elle est traitée par le saut, pas par un refus.
                'nom' => ['required', 'string', 'min:2', 'max:160'],
                'domaine' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/'],
                'lien' => ['nullable', 'url', 'max:500'],
                'credibilite' => ['required', 'integer', 'min:0', 'max:100'],
                'note' => ['nullable', 'string', 'max:2000'],
            ]);

            if ($validateur->fails()) {
                $fautes[] = sprintf('« %s » : %s', $attributs['name'], implode(' ', $validateur->errors()->all()));

                continue;
            }

            // Et la fiche est CASTÉE, comme le fera la première lecture.
            (new TrustedSource)->forceFill($attributs)->toArray();
        }

        return $fautes === [] ? null : 'Source(s) invalide(s) : '.implode(' | ', $fautes);
    }

    /**
     * @param  list<array<string, string|null>>  $lignes
     * @return array<string, list<array<string, string|null>>>
     */
    private function regrouperParDomaine(array $lignes): array
    {
        $groupes = [];

        foreach ($lignes as $ligne) {
            $groupes[TrustedSource::normaliserLeDomaine((string) ($ligne['url'] ?? ''))][] = $ligne;
        }

        return $groupes;
    }

    /**
     * La ligne qui fait la fiche, et celles qu'elle absorbe.
     *
     * 1. la ligne dont l'adresse n'a PAS de chemin — elle décrit le site
     *    entier, et c'est le site entier que le moteur créditera ;
     * 2. à égalité, la première du dump (l'ordre d'insertion ancien).
     *
     * @param  list<array<string, string|null>>  $groupe
     * @return array{0: array<string, string|null>, 1: list<array<string, string|null>>}
     */
    private function departager(array $groupe): array
    {
        $rang = 0;
        $classees = [];

        foreach ($groupe as $ligne) {
            $classees[] = [$this->aUnChemin((string) ($ligne['url'] ?? '')) ? 1 : 0, $rang++, $ligne];
        }

        usort($classees, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $gagnante = array_shift($classees)[2];

        return [$gagnante, array_map(static fn (array $c): array => $c[2], $classees)];
    }

    private function aUnChemin(string $url): bool
    {
        $url = trim($url);
        $sansSchema = str_contains($url, '://') ? Str::after($url, '://') : $url;

        return trim(Str::after($sansSchema, '/'), '/') !== '' && str_contains($sansSchema, '/');
    }

    /**
     * La fiche nouvelle.
     *
     * ## La liste noire
     *
     * `trusted_sources` n'a pas d'équivalent : un référentiel de CONFIANCE,
     * `is_active` et `credibility`. Aucune colonne n'est inventée, et aucune
     * migration n'est ajoutée — une colonne `is_blacklisted` que le moteur ne
     * lirait pas serait un drapeau décoratif, et la lire demanderait de
     * modifier le scoring, hors de cette reprise.
     *
     * La traduction la plus fidèle SANS effet de bord est donc : fiche
     * **inactive**, crédibilité **0**, et la mention en tête de note. Inactive
     * est le point décisif — une fiche ACTIVE, même à 0, serait trouvée par le
     * moteur, annoncée « Source référencée par la rédaction » par
     * `AnalyseTechniqueAdapter` (qui sauterait alors l'heuristique
     * d'usurpation), et LISTÉE sur la page publique des sources. Inactive, elle
     * n'a aucun effet sur le score, garde la trace de la décision, et empêche
     * qu'on référence le domaine par mégarde : l'écran refuse un doublon.
     *
     * @param  array<string, string|null>  $gagnante
     * @param  list<array<string, string|null>>  $absorbees
     * @return array{name: string, domain: string, url: string|null, credibility: int, is_active: bool, notes: string, created_at: string, updated_at: string}
     */
    private function attributsDeLaFiche(string $domaine, array $gagnante, array $absorbees): array
    {
        $noire = $this->estEnListeNoire($gagnante);
        $famille = $noire ? null : $this->famille($gagnante, creer: false);
        $indice = (int) ($gagnante['trust_index'] ?? self::INDICE_PAR_DEFAUT);

        $credibilite = match (true) {
            $noire => 0,
            $this->indiceReel($gagnante) => $indice,
            default => $famille['credibilite'] ?? 70,
        };

        $note = [sprintf(
            'Reprise de l’ancienne plateforme (sources vérifiées) : « %s », %s, catégorie « %s ».',
            $this->propre($gagnante['name'] ?? null) ?? '',
            $this->propre($gagnante['url'] ?? null) ?? '',
            $this->categorieAncienne($gagnante),
        )];

        if ($noire) {
            $note[] = 'LISTE NOIRE dans l’ancienne plateforme : fiche désactivée, crédibilité 0. '
                .'Ne pas la réactiver — active, elle serait créditée par le moteur et listée sur la page publique des sources.';
        } elseif ($this->indiceReel($gagnante)) {
            $note[] = sprintf('Crédibilité reprise de l’ancien indice de confiance (%d).', $indice);
        } else {
            $note[] = sprintf(
                'Crédibilité : défaut de la famille « %s ». L’ancien indice (%d) est la valeur par défaut de la colonne : rien ne prouve qu’il ait été décidé.',
                $famille['nom'] ?? '?',
                $indice,
            );
        }

        foreach ($absorbees as $absorbee) {
            $note[] = sprintf(
                'Également référencée sous « %s » (%s, catégorie « %s »).',
                $this->propre($absorbee['name'] ?? null) ?? '',
                $this->propre($absorbee['url'] ?? null) ?? '',
                $this->categorieAncienne($absorbee),
            );
        }

        $now = now()->toDateTimeString();

        return [
            'name' => Str::limit($this->propre($gagnante['name'] ?? null) ?? $domaine, 160, ''),
            'domain' => $domaine,
            'url' => $this->adresse($gagnante['url'] ?? null),
            'credibility' => $credibilite,
            'is_active' => ! $noire,
            'notes' => implode("\n", $note),
            'created_at' => $this->date($gagnante['created_at'] ?? null) ?? $now,
            'updated_at' => $this->date($gagnante['updated_at'] ?? null) ?? $now,
        ];
    }

    /**
     * L'adresse complète, schéma compris.
     *
     * 139 lignes sur 143 n'ont pas de schéma (`lemonde.fr/les-decodeurs`).
     * `https://` est ajouté, comme le font les seeders du référentiel : sans
     * schéma, la règle `url` de l'écran refuserait la fiche à la première
     * modification.
     */
    private function adresse(?string $url): ?string
    {
        $url = $this->propre($url);

        if ($url === null) {
            return null;
        }

        return str_contains($url, '://') ? $url : 'https://'.$url;
    }

    /**
     * La famille nouvelle d'une ligne : celle qui existe déjà, sinon celle
     * qu'on créerait. `creer` n'est vrai qu'à l'écriture réelle.
     *
     * @param  array<string, string|null>  $ligne
     * @return ($creer is true ? SourceCategory|null : array{nom: string, credibilite: int}|null)
     */
    private function famille(array $ligne, bool $creer): SourceCategory|array|null
    {
        $slug = self::CATEGORIES[$this->categorieAncienne($ligne)] ?? null;

        if ($slug === null || $this->estEnListeNoire($ligne)) {
            return null;
        }

        [$nom, $credibilite, $description, $ordre] = self::FAMILLES[$slug];

        if ($creer) {
            // L'existante gagne : sa crédibilité a pu être amendée depuis l'écran.
            return SourceCategory::query()->firstOrCreate(['slug' => $slug], [
                'uuid' => (string) Str::uuid(),
                'name' => $nom,
                'description' => $description,
                'default_credibility' => $credibilite,
                'sort_order' => $ordre,
            ]);
        }

        $existante = SourceCategory::query()->where('slug', $slug)->first();

        return $existante !== null
            ? ['nom' => (string) $existante->name, 'credibilite' => (int) $existante->default_credibility]
            : ['nom' => $nom, 'credibilite' => $credibilite];
    }

    /**
     * L'ancien indice a-t-il été DÉCIDÉ ? Seulement s'il diffère du défaut.
     *
     * @param  array<string, string|null>  $ligne
     */
    private function indiceReel(array $ligne): bool
    {
        return (int) ($ligne['trust_index'] ?? self::INDICE_PAR_DEFAUT) !== self::INDICE_PAR_DEFAUT;
    }

    /** @param  array<string, string|null>  $ligne */
    private function estEnListeNoire(array $ligne): bool
    {
        return ($ligne['is_blacklisted'] ?? '0') === '1';
    }

    /** @param  array<string, string|null>  $ligne */
    private function categorieAncienne(array $ligne): string
    {
        return mb_strtolower($this->propre($ligne['category'] ?? null) ?? '');
    }

    private function rapport(bool $essai): void
    {
        $this->sortie->newLine();
        $this->sortie->info($essai ? '— Sources : essai à blanc, rien n’a été écrit —' : '— Sources : reprise terminée —');

        $this->sortie->table(['Sources', 'Nombre'], [
            ['Lignes lues dans l’ancienne base', $this->compteurs['lues']],
            ['Domaines distincts (une fiche par domaine)', $this->compteurs['domaines']],
            ['Lignes fusionnées (domaine en double)', $this->compteurs['fusionnees']],
            ['Importées (actives)', $this->compteurs['importees']],
            ['Importées en liste noire (inactives, crédibilité 0)', $this->compteurs['liste_noire']],
            ['Sautées (domaine déjà référencé)', $this->compteurs['sautees']],
            ['Crédibilité reprise de l’ancien indice', $this->compteurs['credibilite_reprise']],
            ['Crédibilité par défaut de la famille', $this->compteurs['credibilite_famille']],
        ]);

        foreach ($this->fusions as [$nom, $domaine]) {
            $this->sortie->line(sprintf('  Fusion · « %s » → fiche %s', $nom, $domaine));
        }

        foreach ($this->sautees as [$domaine, $existante]) {
            $this->sortie->line(sprintf('  Sautée · %s → existe déjà : « %s »', $domaine, $existante));
        }
    }
}
