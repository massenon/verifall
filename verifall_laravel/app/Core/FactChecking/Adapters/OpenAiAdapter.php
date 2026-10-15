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
use App\Enums\TypeContenu;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * OpenAI — second avis de raisonnement indépendant.
 * Mobilise son raisonnement sur le prompt unifié de fact-checking.
 *
 * ## Image — NEWINSTRUCTION32
 *
 * Sur une Image avec un fichier joint, le message utilisateur devient un
 * contenu MULTIPARTIE : le texte de consigne, puis l'image en `image_url`
 * data-URI. `gpt-4o-mini` (le modèle par défaut) lit nativement l'image — rien
 * à changer côté modèle. Sur tout autre type, ou sans fichier, le message
 * reste la simple chaîne d'avant : c'est strictement le même appel.
 */
final class OpenAiAdapter extends AdaptateurHttp
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private readonly ConstructeurDePrompt $prompts) {}

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::OpenAI;
    }

    protected function configure(): bool
    {
        return is_string(config('services.openai.key')) && config('services.openai.key') !== '';
    }

    /** `/v1/models` est gratuit, en lecture seule, et valide la clé. */
    protected function sonde(): array
    {
        return [
            'https://api.openai.com/v1/models',
            ['Authorization' => 'Bearer '.(string) config('services.openai.key')],
        ];
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);

        $reponse = Http::timeout($this->delai())
            ->withToken((string) config('services.openai.key'))
            ->post(self::ENDPOINT, [
                'model' => $this->modele(),
                'messages' => [
                    ['role' => 'system', 'content' => $this->prompts->pour($entree)],
                    ['role' => 'user', 'content' => $this->messageUtilisateur($entree)],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.0,
            ]);

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return null;
        }

        $contenu = data_get($reponse->json(), 'choices.0.message.content');
        $resultat = $this->lireJson($contenu);

        if ($resultat === null) {
            return null;
        }

        $textePropre = (string) ($resultat['reasoning_fr'] ?? $resultat['explanation'] ?? '');
        $textePropre = (string) preg_replace('/^\s*\{[^\}]+\}\s*/s', '', $textePropre);

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            verdict: $this->lireVerdict($resultat['verdict'] ?? null),
            confiance: $this->confiance($resultat['confidence'] ?? $resultat['score'] ?? null),
            preuves: $this->preuves($resultat, $entree->paquetDePreuves),
            raisonnement: mb_substr(trim($textePropre), 0, 4000),
            latenceMs: $this->latence($debut),
            indiceHaine: $this->haine($resultat),
        );
    }

    private function modele(): string
    {
        return (string) config('services.openai.model', 'gpt-4o-mini');
    }

    /**
     * Le contenu du message utilisateur — texte seul, ou texte + image.
     *
     * @return string|list<array<string, mixed>>
     */
    private function messageUtilisateur(SacDePreuves $entree): string|array
    {
        $consigne = 'Rends ton analyse au format JSON demandé.';

        if ($entree->type !== TypeContenu::Image
            || $entree->cheminFichier === null
            || ! Storage::disk('local')->exists($entree->cheminFichier)
        ) {
            return $consigne;
        }

        $chemin = Storage::disk('local')->path($entree->cheminFichier);
        $type = mime_content_type($chemin);

        if ($type === false || ! str_starts_with($type, 'image/')) {
            return $consigne;
        }

        return [
            ['type' => 'text', 'text' => $consigne],
            ['type' => 'image_url', 'image_url' => [
                'url' => 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($chemin)),
            ]],
        ];
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
             | OpenAI n'a pas d'accès web : ses « sources » sont des domaines
             | tenus de son entraînement, pas des pages consultées. Aucune date
             | de publication n'est disponible — `publieeLe` reste donc absent
             | plutôt que d'en inventer une.
             */
            $preuves[] = new Preuve(
                url: $url,
                nomSource: (string) ($source['name'] ?? parse_url($url, PHP_URL_HOST) ?? 'Référence analytique'),
                extrait: (string) ($source['snippet'] ?? 'Élément factuel recoupé.'),
                position: match (mb_strtoupper((string) ($source['stance'] ?? ''))) {
                    'SUPPORTS', 'VRAI' => PositionPreuve::Confirme,
                    'REFUTES', 'FAUX' => PositionPreuve::Contredit,
                    default => PositionPreuve::Neutre,
                },
                /*
                 | La note du RÉFÉRENTIEL, pas un nombre en dur.
                 |
                 | Ce paramètre valait `50` quoi qu'il arrive. Les 408 fiches de
                 | crédibilité tenues par la rédaction étaient donc sans effet sur
                 | les sources de ce moteur : `gouv.bj` s'affichait à 50 %, quand
                 | le référentiel le note entre 90 et 95. Amender une crédibilité
                 | dans l'administration ne changeait rien au rapport.
                 */
                fiabilite: $this->fiabiliteDeLaSource($url, 50),
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
                'SUPPORTS', 'VRAI' => PositionPreuve::Confirme,
                'REFUTES', 'FAUX' => PositionPreuve::Contredit,
                default => PositionPreuve::Neutre,
            },
            fiabilite: $element->fiabilite,
            publieeLe: $element->publieeLe,
            // Par construction du paquet : une page qui n'a pas été ouverte
            // n'y figure jamais — à la différence des « sources » libres
            // d'OpenAI, toujours CITÉES, PAS OUVERTES (voir ci-dessus).
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
