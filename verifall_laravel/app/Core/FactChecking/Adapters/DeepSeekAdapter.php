<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\ValueObjects\ElementDuPaquet;
use App\Core\FactChecking\ValueObjects\IndiceHaine;
use App\Core\FactChecking\ValueObjects\PaquetDePreuves;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use Illuminate\Support\Facades\Http;

/**
 * DeepSeek — second avis, indépendant de Gemini.
 *
 * Sa raison d'être n'est pas d'être meilleur : c'est d'être AUTRE. Deux modèles
 * de familles différentes hallucinent rarement la même chose ; leur désaccord
 * est un signal, et le scoring le traite comme tel plutôt que de le masquer.
 *
 * Un seul modèle, si bon soit-il, ne peut pas se contredire lui-même.
 */
final class DeepSeekAdapter extends AdaptateurHttp
{
    private const ENDPOINT = 'https://api.deepseek.com/chat/completions';

    public function __construct(private readonly ConstructeurDePrompt $prompts) {}

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::DeepSeek;
    }

    protected function configure(): bool
    {
        return is_string(config('services.deepseek.key')) && config('services.deepseek.key') !== '';
    }

    /** DeepSeek expose `/models` en GET : gratuit, et il valide la clé. */
    protected function sonde(): array
    {
        return [
            'https://api.deepseek.com/models',
            ['Authorization' => 'Bearer '.(string) config('services.deepseek.key')],
        ];
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);

        $reponse = Http::timeout($this->delai())
            ->withToken((string) config('services.deepseek.key'))
            ->post(self::ENDPOINT, [
                'model' => (string) config('services.deepseek.model', 'deepseek-chat'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->prompts->pour($entree)],
                    ['role' => 'user', 'content' => 'Rends ton analyse au format JSON demandé.'],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.0,
            ]);

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return null;
        }

        $resultat = $this->lireJson(data_get($reponse->json(), 'choices.0.message.content'));

        if ($resultat === null) {
            return null;
        }

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            verdict: $this->lireVerdict($resultat['verdict'] ?? null),
            confiance: $this->confiance($resultat['confidence'] ?? null),
            preuves: $this->preuves($resultat, $entree->paquetDePreuves),
            raisonnement: mb_substr((string) ($resultat['reasoning_fr'] ?? ''), 0, 1200),
            latenceMs: $this->latence($debut),
            indiceHaine: $this->haine($resultat),
        );
    }

    /**
     * @param  array<string, mixed>  $resultat
     * @return list<Preuve>
     */
    private function preuves(array $resultat, ?PaquetDePreuves $paquet): array
    {
        // CONTRAT V-10 — voir GeminiAdapter::preuves() pour le raisonnement complet.
        if ($paquet !== null) {
            return $this->preuvesDepuisLesIdentifiants($resultat, $paquet);
        }

        $preuves = [];

        foreach ((array) ($resultat['sources'] ?? []) as $source) {
            $url = is_array($source) ? ($source['url'] ?? null) : null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            /*
             | DeepSeek n'a pas d'accès web : ses « sources » sont des souvenirs
             | d'entraînement, pas des pages consultées. Fiabilité volontairement
             | basse — elles servent de piste à recouper, pas de preuve.
             */
            $preuves[] = new Preuve(
                url: $url,
                nomSource: (string) ($source['name'] ?? parse_url($url, PHP_URL_HOST) ?? 'Source'),
                extrait: (string) ($source['snippet'] ?? ''),
                position: match (mb_strtoupper((string) ($source['stance'] ?? ''))) {
                    'SUPPORTS' => PositionPreuve::Confirme,
                    'REFUTES' => PositionPreuve::Contredit,
                    default => PositionPreuve::Neutre,
                },
                /*
                 | La note du RÉFÉRENTIEL, pas un nombre en dur.
                 |
                 | Ce paramètre valait `45` quoi qu'il arrive. Les 408 fiches de
                 | crédibilité tenues par la rédaction étaient donc sans effet sur
                 | les sources de ce moteur : `gouv.bj` s'affichait à 45 %, quand
                 | le référentiel le note entre 90 et 95. Amender une crédibilité
                 | dans l'administration ne changeait rien au rapport.
                 */
                fiabilite: $this->fiabiliteDeLaSource($url, 45),
                /*
                 | CITÉE, PAS OUVERTE. Ce moteur n'a pas d'accès web : cette
                 | adresse vient de son entraînement, et rien ne garantit
                 | qu'elle existe. Elle ne compte pas comme un recoupement.
                 */
                recuperee: false,
            );
        }

        return $preuves;
    }

    /**
     * Résout `source_ids` contre le paquet — contrat V-10, phase 7. Voir
     * {@see GeminiAdapter::preuvesDepuisLesIdentifiants()} pour le raisonnement.
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
            position: match (mb_strtoupper((string) $stance)) {
                'SUPPORTS' => PositionPreuve::Confirme,
                'REFUTES' => PositionPreuve::Contredit,
                default => PositionPreuve::Neutre,
            },
            fiabilite: $element->fiabilite,
            publieeLe: $element->publieeLe,
            // Par construction du paquet : une page qui n'a pas été ouverte
            // n'y figure jamais — à la différence des « sources » libres de
            // DeepSeek, toujours CITÉES, PAS OUVERTES (voir ci-dessus).
            recuperee: true,
        );
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
