<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Models\HubPortal;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * `--seulement=hub` — les e-services de l'ancien Hub deviennent des portails
 * officiels, en brouillon.
 *
 * **Le raisonnement complet est dans `docs/MIGRATION_ANCIENNE_BASE.md`, §32 à
 * §35.** L'essentiel :
 *
 * ## Ce qui est repris : les 36 e-services
 *
 * `e_services` (nom, adresse, rubrique, drapeau « vérifié ») recouvre
 * exactement `hub_portals`. Les fiches arrivent en BROUILLON, comme les outils :
 * trente-cinq liens d'un coup noieraient les cinq portails de la page, et
 * aucune n'a de descriptif (colonne absente de l'ancienne table), que l'écran
 * staff exige à l'enregistrement.
 *
 * ## Ce qui est écarté : les actualités
 *
 * `news_articles` relayait des communiqués officiels (« Discours
 * d'investiture… »), avec un verdict « En attente ». `hub_articles` est une
 * BRÈVE DE VÉRIFICATION : l'écran staff exige une affirmation, un corps d'au
 * moins 50 caractères et un verdict. Reprendre un communiqué comme brève
 * vérifiée afficherait un bandeau « EN COURS » sur une vérification que
 * personne n'a menée. Les trois articles sont nommés au rapport.
 *
 * ## Les offres d'emploi
 *
 * Table vide dans le dump. La décision est écrite quand même : une offre
 * EXPIRÉE est écartée (le produit ne publie pas une offre dont l'échéance est
 * passée — l'écran staff refuse une échéance antérieure à aujourd'hui) ; une
 * offre EN COURS arrête l'import, car lieu, type et portée — obligatoires dans
 * `Staff\Opportunites` — n'existent pas dans l'ancienne table.
 */
final class RepriseDuHub
{
    use LitLAncienneBase;

    /**
     * Les rubriques anciennes acceptées, reprises TELLES QUELLES.
     *
     * `hub_portals.category` est une liste ouverte (l'écran propose les valeurs
     * déjà présentes). Traduire « Administration » en « Démarches » serait un
     * arbitrage éditorial : la rédaction renomme d'un geste si elle le
     * souhaite. La table sert de CONTRÔLE — une rubrique inconnue arrête
     * l'import.
     *
     * @var array<string, string>
     */
    private const RUBRIQUES = [
        'Administration' => 'Administration',
        'Justice' => 'Justice',
        'Éducation' => 'Éducation',
        'Santé' => 'Santé',
    ];

    /** @var array<string, string> */
    private const TABLES_SANS_REGLE = [
        'hub_items' => 'éléments de types mêlés (news, service, job) sans équivalent unique',
    ];

    /** @var array<string, int> */
    private array $compteurs = [];

    /** @var list<array{0: string, 1: string}> nom, raison */
    private array $details = [];

    public function __construct(private readonly Command $sortie) {}

    public function executer(string $chemin, bool $essai): int
    {
        $this->compteurs = [
            'services_lus' => 0, 'portails_importes' => 0, 'portails_sautes' => 0,
            'actualites_ecartees' => 0, 'offres_expirees' => 0,
        ];
        $this->details = [];

        $services = $this->lireTable($chemin, 'e_services');
        $actualites = $this->lireTable($chemin, 'news_articles');
        $offres = $this->lireTable($chemin, 'job_offers');

        $this->compteurs['services_lus'] = count($services);

        $this->sortie->line(sprintf(
            '  %d e-services, %d actualités, %d offres d’emploi.',
            count($services), count($actualites), count($offres),
        ));

        if (($faute = $this->verifier($chemin, $services, $offres)) !== null) {
            $this->sortie->error($faute);
            $this->sortie->line('  Rien n’a été écrit.');

            return Command::FAILURE;
        }

        $connues = $this->portailsExistants();

        foreach ($services as $rang => $service) {
            $attributs = $this->attributsDuPortail($service, $rang);
            $cle = $this->cleDAdresse($attributs['url']);
            $nom = mb_strtolower($attributs['name']);

            $collision = $connues['adresses'][$cle] ?? $connues['noms'][$nom] ?? null;

            if ($collision !== null) {
                $this->details[] = [$attributs['name'], 'sauté : existe déjà (« '.$collision.' »)'];
                $this->compteurs['portails_sautes']++;

                continue;
            }

            // Indexé AVANT l'écriture, et à blanc aussi : l'essai et l'import
            // rendent les mêmes compteurs, doublons internes compris.
            $connues['adresses'][$cle] = $attributs['name'];
            $connues['noms'][$nom] = $attributs['name'];

            $this->compteurs['portails_importes']++;

            if ($essai) {
                continue;
            }

            DB::transaction(function () use ($attributs): void {
                (new HubPortal)->forceFill($attributs + ['uuid' => (string) Str::uuid()])->save();
            });
        }

        foreach ($actualites as $actualite) {
            $this->details[] = [$this->propre($actualite['title'] ?? null) ?? '?', 'écartée : communiqué relayé, pas une brève de vérification'];
            $this->compteurs['actualites_ecartees']++;
        }

        foreach ($offres as $offre) {
            // Le contrôle préalable a garanti qu'elles sont toutes expirées.
            $this->details[] = [$this->propre($offre['title'] ?? null) ?? '?', 'écartée : offre expirée'];
            $this->compteurs['offres_expirees']++;
        }

        $this->rapport($essai);

        return Command::SUCCESS;
    }

    /**
     * @param  list<array<string, string|null>>  $services
     * @param  list<array<string, string|null>>  $offres
     */
    private function verifier(string $chemin, array $services, array $offres): ?string
    {
        foreach (self::TABLES_SANS_REGLE as $table => $raison) {
            $lignes = count($this->lireTable($chemin, $table));

            if ($lignes > 0) {
                return sprintf('La table ancienne « %s » contient %d ligne(s), sans règle de reprise écrite : %s.', $table, $lignes, $raison);
            }
        }

        $enCours = [];

        foreach ($offres as $offre) {
            $echeance = $this->date($offre['deadline'] ?? null);

            if ($echeance === null || Carbon::parse($echeance)->endOfDay()->isFuture()) {
                $enCours[] = '« '.($this->propre($offre['title'] ?? null) ?? '?').' »';
            }
        }

        if ($enCours !== []) {
            return 'Offre(s) d’emploi encore ouverte(s) : '.implode(', ', $enCours)
                .'. Lieu, type et portée, obligatoires dans le produit, n’existent pas dans l’ancienne table : à arbitrer avant toute reprise.';
        }

        $rubriquesInconnues = [];
        $fautes = [];

        foreach ($services as $rang => $service) {
            $rubrique = $this->propre($service['category'] ?? null) ?? '';

            if (! isset(self::RUBRIQUES[$rubrique])) {
                $rubriquesInconnues[$rubrique] = ($rubriquesInconnues[$rubrique] ?? 0) + 1;

                continue;
            }

            if (! in_array((string) ($service['is_verified'] ?? ''), ['0', '1'], true)) {
                $fautes[] = sprintf('« %s » : is_verified inattendu', (string) ($service['name'] ?? '?'));
            }

            $attributs = $this->attributsDuPortail($service, $rang);

            // Les règles de `Staff\PortailsOfficiels::enregistrer()`, hors
            // descriptif : il n'existe pas dans l'ancienne table (voir l'en-tête).
            $validateur = Validator::make($attributs, [
                'name' => ['required', 'string', 'max:120', 'not_regex:/<[a-z!\/?][^>]*>/i'],
                'url' => ['required', 'url', 'starts_with:https://', 'max:255'],
                'category' => ['required', 'string', 'max:60'],
            ]);

            if ($validateur->fails()) {
                $fautes[] = sprintf('« %s » : %s', $attributs['name'], implode(' ', $validateur->errors()->all()));

                continue;
            }

            (new HubPortal)->forceFill($attributs)->toArray();
        }

        if ($rubriquesInconnues !== []) {
            return 'Rubrique(s) d’e-service inconnue(s) : '.$this->enumerer($rubriquesInconnues).'. À ajouter à RepriseDuHub::RUBRIQUES.';
        }

        return $fautes === [] ? null : 'E-service(s) invalide(s) : '.implode(' | ', $fautes);
    }

    /**
     * @param  array<string, string|null>  $service
     * @return array{name: string, url: string, category: string, description: null, logo_path: null, is_official: bool, position: int, is_published: bool, created_at: string, updated_at: string}
     */
    private function attributsDuPortail(array $service, int $rang): array
    {
        $now = now()->toDateTimeString();

        return [
            // « ␣Attestation fiscale » : deux noms du dump portent une espace de tête.
            'name' => $this->propre($service['name'] ?? null) ?? '',
            'url' => $this->propre($service['url'] ?? null) ?? '',
            'category' => self::RUBRIQUES[$this->propre($service['category'] ?? null) ?? ''] ?? '',
            'description' => null,
            'logo_path' => null,
            // « Vérifié » par l'ancienne équipe : un service public authentique,
            // ce que dit la pastille « officiel » d'un portail.
            'is_official' => ($service['is_verified'] ?? '0') === '1',
            // Après les portails existants, dans l'ordre du dump.
            'position' => 100 + $rang,
            'is_published' => false,
            'created_at' => $this->date($service['created_at'] ?? null) ?? $now,
            'updated_at' => $this->date($service['updated_at'] ?? null) ?? $now,
        ];
    }

    /**
     * Les portails existants, indexés par adresse normalisée et par nom.
     *
     * @return array{adresses: array<string, string>, noms: array<string, string>}
     */
    private function portailsExistants(): array
    {
        $connues = ['adresses' => [], 'noms' => []];

        foreach (HubPortal::query()->get(['name', 'url']) as $portail) {
            $connues['adresses'][$this->cleDAdresse((string) $portail->url)] = (string) $portail->name;
            $connues['noms'][mb_strtolower(Str::squish((string) $portail->name))] = (string) $portail->name;
        }

        return $connues;
    }

    /**
     * Hôte sans `www.`, chemin sans `/` de bord, requête conservée.
     *
     * ## Pourquoi l'adresse EXACTE, et non le chemin prolongé des outils
     *
     * Trente des e-services sont des pages PROFONDES de `service-public.bj`
     * (`/public/services/service/PS00373` — l'extrait de casier judiciaire).
     * Le critère « chemin qui prolonge » de la reprise des outils les ferait
     * TOUS sauter devant le portail « Service Public Bénin », alors que chacun
     * mène à une démarche distincte. Seule l'adresse identique désigne le même
     * portail : `https://service-public.bj/` et `https://service-public.bj`.
     */
    private function cleDAdresse(string $url): string
    {
        $hote = (string) preg_replace('/^www\./', '', mb_strtolower((string) parse_url(trim($url), PHP_URL_HOST)));
        $chemin = trim(mb_strtolower((string) parse_url(trim($url), PHP_URL_PATH)), '/');
        $requete = (string) parse_url(trim($url), PHP_URL_QUERY);

        return $hote.'/'.$chemin.($requete !== '' ? '?'.$requete : '');
    }

    private function rapport(bool $essai): void
    {
        $this->sortie->newLine();
        $this->sortie->info($essai ? '— Hub : essai à blanc, rien n’a été écrit —' : '— Hub : reprise terminée —');

        $this->sortie->table(['Hub', 'Nombre'], [
            ['E-services lus', $this->compteurs['services_lus']],
            ['Portails importés (brouillon, sans descriptif)', $this->compteurs['portails_importes']],
            ['Portails sautés (déjà présents)', $this->compteurs['portails_sautes']],
            ['Actualités écartées', $this->compteurs['actualites_ecartees']],
            ['Offres d’emploi expirées écartées', $this->compteurs['offres_expirees']],
        ]);

        foreach ($this->details as [$nom, $raison]) {
            $this->sortie->line(sprintf('  %s → %s', $nom, $raison));
        }
    }
}
