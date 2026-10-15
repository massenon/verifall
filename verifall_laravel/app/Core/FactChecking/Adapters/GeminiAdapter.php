<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\Services\DomaineDeLaSource;
use App\Core\FactChecking\Services\RotationDeCles;
use App\Core\FactChecking\ValueObjects\ElementDuPaquet;
use App\Core\FactChecking\ValueObjects\IndiceHaine;
use App\Core\FactChecking\ValueObjects\PaquetDePreuves;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gemini — analyse sémantique principale, avec ancrage sur la recherche Google.
 */
final class GeminiAdapter extends AdaptateurHttp
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(
        private readonly ConstructeurDePrompt $prompts,
        private readonly DomaineDeLaSource $domaines = new DomaineDeLaSource,
        private readonly RotationDeCles $rotation = new RotationDeCles('gemini'),
    ) {}

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::Gemini;
    }

    protected function configure(): bool
    {
        return $this->cles() !== [];
    }

    private ?array $memoireDesCles = null;

    public function joignable(): ?bool
    {
        $etat = $this->etatDesCles();

        return $etat['total'] === 0 ? null : $etat['vivantes'] > 0;
    }

    public function precision(): ?string
    {
        $etat = $this->etatDesCles();

        if ($etat['total'] === 0) {
            return null;
        }

        return sprintf(
            '%d clé%s sur %d acceptée%s',
            $etat['vivantes'],
            $etat['vivantes'] > 1 ? 's' : '',
            $etat['total'],
            $etat['vivantes'] > 1 ? 's' : '',
        );
    }

    private function etatDesCles(): array
    {
        if ($this->memoireDesCles !== null) {
            return $this->memoireDesCles;
        }

        $cles = $this->cles();
        $vivantes = 0;

        foreach ($cles as $cle) {
            try {
                $ok = Http::timeout(8)
                    ->get(rtrim(self::BASE, '/').'?key='.urlencode($cle))
                    ->successful();
            } catch (Throwable) {
                $ok = false;
            }

            if ($ok) {
                $vivantes++;
            }
        }

        return $this->memoireDesCles = ['vivantes' => $vivantes, 'total' => count($cles)];
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);
        $prompt = $this->prompts->pour($entree);

        $donnees = $this->rotation->essayer(
            $this->cles(),
            function (string $cle) use ($prompt, $entree): ?array {
                foreach ([$this->modele(), $this->modeleDeRepli()] as $modele) {
                    $reponse = $this->appeler($cle, $modele, $prompt, $entree);

                    $this->refuserSiQuota($reponse);
                    $this->signalerSiModeleInconnu($reponse, $modele);

                    if ($reponse->successful()) {
                        return $reponse->json();
                    }
                }

                return null;
            },
            (int) config('factcheck.runtime.circuit_breaker.repos_minutes', 5),
        );

        if ($donnees === null) {
            return null;
        }

        $parts = (array) data_get($donnees, 'candidates.0.content.parts', []);
        $texte = null;

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text']) && trim($part['text']) !== '') {
                $texte .= $part['text'];
            }
        }

        if ($texte === null) {
            Log::warning('Gemini a répondu sans candidat', [
                'blocage' => data_get($donnees, 'promptFeedback.blockReason'),
                'arret' => data_get($donnees, 'candidates.0.finishReason'),
            ]);

            return null;
        }

        $resultat = $this->lireJson($texte);

        // Si le json_decode n'a pas trouvé de bloc direct, cherchons le JSON dans le texte
        if ($resultat === null && preg_match('/\{[\s\S]*\}/', $texte, $trouve)) {
            $resultat = json_decode($trouve[0], true);
        }

        $textePropre = (string) ($resultat['reasoning_fr'] ?? $resultat['explanation'] ?? $texte ?? '');
        $textePropre = (string) preg_replace('/^\s*\{[^\}]+\}\s*/s', '', $textePropre);

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            verdict: $this->lireVerdict($resultat['verdict'] ?? null),
            confiance: $this->confiance($resultat['confidence'] ?? $resultat['score'] ?? null),
            preuves: $this->preuves($resultat ?? [], $donnees, $entree->paquetDePreuves),
            raisonnement: mb_substr(trim($textePropre), 0, 4000),
            latenceMs: $this->latence($debut),
            indiceHaine: $this->haine($resultat ?? []),
        );
    }

    private function cles(): array
    {
        /** @var list<string> $cles */
        return config('services.gemini.keys', []);
    }

    private function modele(): string
    {
        return (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
    }

    private function modeleDeRepli(): string
    {
        return (string) config('services.gemini.model_fallback', 'gemini-3.1-flash-lite');
    }

    /**
     * Gemini a son propre délai, et il est RÉGLABLE.
     *
     * Il était figé à 60 s dans le code. Trop long pour un hébergement
     * mutualisé — 60 s d'attente pour un seul moteur sur trois —, et surtout
     * impossible à ajuster sans déploiement, alors que c'est exactement le
     * genre de valeur qu'on veut pouvoir bouger quand le fournisseur ralentit.
     *
     * 35 s couvre l'ancrage sur la recherche Google puis la rédaction. En
     * dessous, le moteur rendait `cURL 28` : un échec muet qui faisait tomber
     * le quorum à un seul avis, donc un rapport partiel et un remboursement.
     */
    protected function delai(): int
    {
        return (int) config('factcheck.runtime.timeout_gemini', 35);
    }

    private function appeler(string $cle, string $modele, string $prompt, SacDePreuves $entree): Response
    {
        $parties = [['text' => $prompt]];

        if ($entree->cheminFichier !== null && Storage::disk('local')->exists($entree->cheminFichier)) {
            $chemin = Storage::disk('local')->path($entree->cheminFichier);
            $type = mime_content_type($chemin);

            if ($type !== false) {
                $parties[] = ['inline_data' => [
                    'mime_type' => $type,
                    'data' => base64_encode((string) file_get_contents($chemin)),
                ]];
            }
        }

        return Http::timeout($this->delai())
            ->connectTimeout(15)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::BASE.$modele.':generateContent?key='.$cle, [
                'contents' => [['parts' => $parties]],
                'tools' => [['google_search' => new \stdClass]],
                'generationConfig' => [
                    'temperature' => 0.0,
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $resultat
     * @param  array<string, mixed>  $donnees
     * @return list<Preuve>
     */
    private function preuves(array $resultat, array $donnees, ?PaquetDePreuves $paquet): array
    {
        /*
         | CONTRAT V-10 — un paquet présent change intégralement la source de
         | vérité. Le modèle ne peut plus introduire sa propre URL, ni via
         | `sources` ni via l'ancrage Google : accepter l'un ou l'autre ici
         | laisserait une porte dérobée au contrat que le prompt vient
         | d'imposer. Voir {@see ConstructeurDePrompt::consignesDeCitation()}.
         */
        if ($paquet !== null) {
            return $this->preuvesDepuisLesIdentifiants($resultat, $paquet);
        }

        $preuves = [];

        // 1. Sources structurées du résultat JSON
        foreach ((array) ($resultat['sources'] ?? []) as $source) {
            $url = is_array($source) ? ($source['url'] ?? null) : null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            // Le modèle ne rend aucune date pour ce format — `publieeLe`
            // reste absent plutôt que d'en inventer une.
            $preuves[] = new Preuve(
                url: $url,
                nomSource: (string) ($source['name'] ?? parse_url($url, PHP_URL_HOST) ?? 'Source'),
                extrait: (string) ($source['snippet'] ?? ''),
                position: $this->position($source['stance'] ?? null),
                fiabilite: $this->fiabiliteDeLaSource($url, 70),
            );
        }

        // 2. Sources de l'ancrage Google Grounding — `groundingChunks` ne
        // porte pas davantage de date : même absence, même raison.
        foreach ((array) data_get($donnees, 'candidates.0.groundingMetadata.groundingChunks', []) as $bloc) {
            $url = data_get($bloc, 'web.uri');

            if (! is_string($url) || $url === '') {
                continue;
            }

            /*
             | L'ADRESSE EST CELLE D'UN REDIRECTEUR, PAS CELLE DE LA SOURCE.
             |
             | Gemini rend `vertexaisearch.cloud.google.com/grounding-api-redirect/…`
             | et place le vrai domaine dans `web.title`. Chercher la crédibilité
             | sur l'adresse brute revenait à l'interroger sur un redirecteur
             | Google, absent du référentiel : les 408 fiches tenues par la
             | rédaction n'avaient aucun effet sur les sources trouvées par
             | Gemini, c'est-à-dire sur presque toutes.
             */
            $domaine = $this->domaines->pour($url, data_get($bloc, 'web.title'));

            $preuves[] = new Preuve(
                url: $url,
                nomSource: $domaine,
                extrait: 'Source consultée lors du croisement sur le web.',
                position: PositionPreuve::Neutre,
                fiabilite: $this->fiabiliteDeLaSource('https://'.$domaine, 75),
            );
        }

        return $preuves;
    }

    /**
     * Résout `source_ids` contre le paquet — contrat V-10, phase 7.
     *
     * Un identifiant absent du paquet est IGNORÉ, jamais traité comme une
     * URL à deviner ({@see PaquetDePreuves::resoudre()}) : c'est tout le sens
     * du contrat « SOURCE_ID → URL, jamais LLM → URL ».
     *
     * @param  array<string, mixed>  $resultat
     * @return list<Preuve>
     */
    private function preuvesDepuisLesIdentifiants(array $resultat, PaquetDePreuves $paquet): array
    {
        $preuves = [];

        foreach ((array) ($resultat['source_ids'] ?? []) as $cite) {
            $id = is_array($cite) ? ($cite['id'] ?? null) : null;

            if (! is_string($id)) {
                continue;
            }

            $element = $paquet->resoudre($id);

            if ($element === null) {
                continue;
            }

            $preuves[] = $this->preuveDepuisLElement($element, $cite['stance'] ?? null);
        }

        return $preuves;
    }

    private function preuveDepuisLElement(ElementDuPaquet $element, mixed $stance): Preuve
    {
        return new Preuve(
            url: $element->urlFinale,
            nomSource: $element->editeur ?? $element->titre,
            extrait: $element->extrait,
            position: $this->position($stance),
            fiabilite: $element->fiabilite,
            publieeLe: $element->publieeLe,
            // Par construction du paquet : une page qui n'a pas été ouverte
            // n'y figure jamais ({@see CollecteurDePreuves::collecter()}).
            recuperee: true,
        );
    }

    private function position(mixed $brut): PositionPreuve
    {
        return match (mb_strtoupper((string) $brut)) {
            'SUPPORTS', 'VRAI', 'CONFIRME' => PositionPreuve::Confirme,
            'REFUTES', 'FAUX', 'CONTREDIT' => PositionPreuve::Contredit,
            default => PositionPreuve::Neutre,
        };
    }

    /**
     * @param  array<string, mixed>  $resultat
     */
    private function haine(array $resultat): IndiceHaine
    {
        $bloc = $resultat['hate_speech'] ?? null;

        if (! is_array($bloc) || ! isset($bloc['score'])) {
            return IndiceHaine::nonMesure();
        }

        return new IndiceHaine(
            score: $this->confiance($bloc['score'], 0),
            tags: array_values(array_filter(array_map(
                static fn (mixed $tag): string => (string) $tag,
                (array) ($bloc['tags'] ?? []),
            ))),
            note: isset($bloc['explanation']) ? (string) $bloc['explanation'] : null,
        );
    }
}
