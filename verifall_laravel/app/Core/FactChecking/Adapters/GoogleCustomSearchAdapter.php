<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\Services\DomaineDeLaSource;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Custom Search — presse béninoise et sources officielles.
 *
 * Ce moteur ne juge pas : il APPORTE des sources. Il rend donc un verdict nul
 * et n'entre pas dans le score — ce serait absurde qu'une recherche « décide »
 * de la vérité. Sa contribution est la matière que les moteurs sémantiques
 * pourront citer.
 *
 * Il interroge en outre la formulation ADVERSE (« X démenti », « X faux ») :
 * ne chercher que l'affirmation reviendrait à ne trouver que ce qui la répète,
 * et c'est ainsi qu'une rumeur se confirme elle-même.
 */
final class GoogleCustomSearchAdapter extends AdaptateurHttp
{
    public function __construct(
        private readonly DomaineDeLaSource $domaines = new DomaineDeLaSource,
    ) {}

    private const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::GoogleCustomSearch;
    }

    protected function configure(): bool
    {
        return is_string(config('services.google_cse.key')) && config('services.google_cse.key') !== ''
            && is_string(config('services.google_cse.id')) && config('services.google_cse.id') !== '';
    }

    /**
     * Une recherche à un résultat.
     *
     * Le quota gratuit est de CENT requêtes par jour : la sonde est donc mise
     * en cache longuement par `SanteDesMoteurs`, et n'est jamais relancée à
     * chaque affichage d'un écran. Un tableau de bord qui épuise le quota du
     * moteur qu'il surveille serait une plaisanterie.
     */
    protected function sonde(): array
    {
        return [
            self::ENDPOINT.'?num=1&q=test&cx='.urlencode((string) config('services.google_cse.id'))
                .'&key='.urlencode((string) config('services.google_cse.key')),
            [],
        ];
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);
        $requete = mb_substr(trim($entree->affirmation), 0, 120);

        $preuves = [];

        foreach ([$requete, $requete.' démenti faux rumeur'] as $variante) {
            foreach ($this->chercher($variante) as $preuve) {
                $preuves[$preuve->url] = $preuve;
            }
        }

        if ($preuves === []) {
            return null;
        }

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            // Verdict NUL, délibérément : une recherche apporte des sources,
            // elle ne tranche pas.
            verdict: null,
            confiance: 0,
            preuves: array_values($preuves),
            raisonnement: count($preuves).' source(s) trouvée(s), dont la recherche adverse.',
            latenceMs: $this->latence($debut),
        );
    }

    /**
     * @return list<Preuve>
     */
    private function chercher(string $requete): array
    {
        $reponse = Http::timeout($this->delai())->get(self::ENDPOINT, [
            'key' => (string) config('services.google_cse.key'),
            'cx' => (string) config('services.google_cse.id'),
            'q' => $requete,
            'num' => 5,
            'lr' => 'lang_fr',
        ]);

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return [];
        }

        $preuves = [];

        foreach ((array) ($reponse->json()['items'] ?? []) as $item) {
            $url = $item['link'] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $domaine = (string) (parse_url($url, PHP_URL_HOST) ?? '');

            $preuves[] = new Preuve(
                url: $url,
                nomSource: (string) ($item['displayLink'] ?? $domaine),
                extrait: mb_substr((string) ($item['snippet'] ?? ''), 0, 300),
                position: PositionPreuve::Neutre,
                fiabilite: $this->fiabiliteDeLaSource($url, $this->fiabilite($domaine)),
                publieeLe: $this->dateDeLaPage($item),
            );
        }

        return $preuves;
    }

    /**
     * La date de publication DÉCLARÉE par la page elle-même — jamais devinée.
     *
     * Google ne la rend pas comme un champ propre : elle vit dans
     * `pagemap.metatags[0]`, sous l'une des balises que les éditeurs posent
     * eux-mêmes (`article:published_time`, Open Graph, Dublin Core…). Une
     * page qui n'en déclare aucune rend `null`, et ce n'est pas une anomalie
     * à corriger — c'est l'état réel de l'information disponible.
     *
     * @param  array<string, mixed>  $item
     */
    private function dateDeLaPage(array $item): ?Carbon
    {
        $metatags = (array) data_get($item, 'pagemap.metatags.0', []);

        foreach (['article:published_time', 'og:article:published_time', 'datepublished', 'date'] as $cle) {
            $brute = $metatags[$cle] ?? null;

            if (! is_string($brute) || $brute === '') {
                continue;
            }

            try {
                return Carbon::parse($brute);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Fiabilité par DÉFAUT d'un domaine — le référentiel la surclasse.
     *
     * ## Ce que cette méthode ne fait plus
     *
     * Elle rendait `95` pour `gouv.bj`, `80` pour tout `.bj`, `90` pour
     * `afp.com` et `africacheck`, `25` pour quatre plateformes, `55` sinon.
     * Trois défauts :
     *
     *   · **la géographie décidait.** Un portail d'État nigérian, mexicain ou
     *     canadien était noté 55 quand n'importe quel `.bj` recevait 80. Pour
     *     une plateforme qui doit vérifier n'importe quelle affirmation, c'est
     *     la liste fermée qui empêchait l'universalité.
     *   · **deux rédactions étaient nommées dans le code.** `afp.com` et
     *     `africacheck` ont leur place dans `trusted_sources`, où la rédaction
     *     peut les amender — pas dans un `match` qu'il faut déployer.
     *   · le commentaire annonçait que `trusted_sources` remplacerait cela
     *     « en attendant ». Le référentiel EST consulté depuis le lot 5 ; cette
     *     méthode n'est plus que son défaut.
     *
     * ## Ce qu'elle garde
     *
     * Deux signaux de FORME, vrais sous toutes les juridictions, délégués à
     * {@see DomaineDeLaSource} pour qu'une seule définition en réponde :
     *
     *   · un suffixe réservé aux institutions s'obtient auprès d'un registre
     *     qui vérifie la qualité du demandeur ;
     *   · une plateforme de contenu déposé par ses utilisateurs n'apporte
     *     aucune garantie éditoriale — ce qui ne dit rien de la véracité du
     *     contenu, seulement que l'hôte n'en est pas la caution.
     *
     * Le reste vaut 55 : une réputation INCONNUE, pas une mauvaise réputation.
     */
    private function fiabilite(string $domaine): int
    {
        return match (true) {
            $this->domaines->estInstitutionnel($domaine) => 90,
            $this->domaines->estUnePlateformeOuverte($domaine) => 25,
            default => 55,
        };
    }
}
