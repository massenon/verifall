<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PorteeTemporelle;
use App\Enums\PositionPreuve;
use Illuminate\Support\Facades\Http;

/**
 * Perplexity — recherche web citée, en temps réel.
 *
 * Sa valeur tient aux CITATIONS : le service rend les URL qu'il a réellement
 * ouvertes, ce qui permet de vérifier son raisonnement au lieu de le croire.
 * Un modèle qui affirme sans citer ne fait pas de fact-checking.
 */
final class PerplexitySearchAdapter extends AdaptateurHttp
{
    private const ENDPOINT = 'https://api.perplexity.ai/chat/completions';

    public function __construct(private readonly ConstructeurDePrompt $prompts) {}

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::PerplexitySearch;
    }

    protected function configure(): bool
    {
        return is_string(config('services.perplexity.key')) && config('services.perplexity.key') !== '';
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);

        $corps = [
            'model' => 'sonar-reasoning',
            'messages' => [
                ['role' => 'system', 'content' => $this->prompts->pour($entree)],
                ['role' => 'user', 'content' => 'Rends ton analyse au format JSON demandé.'],
            ],
            'temperature' => 0.0,

            /*
             | AUCUN FILTRE DE DOMAINE.
             |
             | Ce paramètre valait `['gouv.bj', 'arcep.bj', 'instad.bj',
             | 'lanationbenin.info', 'banouto.info']`, avec pour commentaire
             | « priorité au contexte béninois ». Ce n'était pas une
             | priorité : `search_domain_filter` est un filtre DUR de l'API
             | Perplexity. La recherche ne voyait rien d'autre.
             |
             | Conséquence : une affirmation sur la France, l'OMS, une
             | université ou une entreprise était cherchée exclusivement dans
             | cinq sources béninoises, et ne pouvait donc structurellement
             | produire aucune preuve pertinente. C'était le seul endroit du
             | code qui rendait l'universalité impossible — et le moteur
             | rendait « aucune source » là où il aurait dû trouver.
             |
             | Les sources locales ne sont pas perdues pour autant : elles
             | restent privilégiées là où elles comptent, par le RÉFÉRENTIEL
             | `trusted_sources` qui les note, et par la stratégie de sources
             | que le prompt porte. Un indice de pertinence, pas un œilleton.
             */
        ];

        $fraicheur = $this->fraicheur($entree);

        // `null` — un claim à DATE EXPLICITE — omet le paramètre plutôt que
        // d'envoyer une valeur vide : la date du claim ancre déjà la
        // recherche mieux qu'une fenêtre glissante ne le ferait.
        if ($fraicheur !== null) {
            $corps['search_recency_filter'] = $fraicheur;
        }

        $reponse = Http::timeout($this->delai())
            ->withToken((string) config('services.perplexity.key'))
            ->post(self::ENDPOINT, $corps);

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return null;
        }

        $corps = $reponse->json();
        $resultat = $this->lireJson(data_get($corps, 'choices.0.message.content'));

        if ($resultat === null) {
            return null;
        }

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            verdict: $this->lireVerdict($resultat['verdict'] ?? null),
            confiance: $this->confiance($resultat['confidence'] ?? null),
            preuves: $this->preuves($corps),
            raisonnement: mb_substr((string) ($resultat['reasoning_fr'] ?? ''), 0, 1200),
            latenceMs: $this->latence($debut),
        );
    }

    /**
     * La fenêtre de fraîcheur de la recherche.
     *
     * Elle valait `'year'` en dur. C'est trop court pour un fait historique —
     * une photo de 2020, une loi de 2015, un chiffre de recensement — et trop
     * large pour un état présent, où une source de onze mois peut être périmée.
     *
     * Phase 3 : dépend désormais de la PORTÉE TEMPORELLE du claim quand
     * elle est connue — {@see PorteeTemporelle::fraicheurRecherche()}.
     * Sans classification (appels d'avant cette phase), repli sur le réglage
     * global `fraicheur_recherche`, à l'identique du comportement antérieur.
     *
     * `null` : aucun filtre ne doit être envoyé — une date explicite ancre
     * déjà la recherche.
     */
    private function fraicheur(SacDePreuves $entree): ?string
    {
        if ($entree->contexteTemporel !== null) {
            return $entree->contexteTemporel->portee->fraicheurRecherche();
        }

        return (string) config('factcheck.runtime.fraicheur_recherche', 'year');
    }

    /**
     * Les citations rendues par le service, pas celles annoncées par le modèle.
     *
     * @param  array<string, mixed>  $corps
     * @return list<Preuve>
     */
    private function preuves(array $corps): array
    {
        $preuves = [];

        foreach ((array) ($corps['citations'] ?? []) as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            $domaine = (string) (parse_url($url, PHP_URL_HOST) ?? $url);

            $preuves[] = new Preuve(
                url: $url,
                nomSource: $domaine,
                extrait: '',
                // Neutre par défaut : la citation prouve la consultation, pas
                // le sens. C'est aux moteurs sémantiques de qualifier.
                position: PositionPreuve::Neutre,
                /*
                 | LA NOTE DU RÉFÉRENTIEL, pas une heuristique de suffixe.
                 |
                 | Ce paramètre valait `str_ends_with($domaine, '.bj') ? 85 : 65`.
                 | Deux défauts. D'abord l'inégalité de traitement : un portail
                 | d'État français, nigérian ou canadien était noté 65 quand un
                 | blog en `.bj` recevait 85. Ensuite l'inutilité du référentiel :
                 | amender une crédibilité dans l'administration ne changeait
                 | rien aux preuves de ce moteur.
                 |
                 | Le défaut est volontairement MOYEN — 60 — et non pénalisant :
                 | une source absente du référentiel n'est pas une mauvaise
                 | source, c'est une source dont la réputation est inconnue. Le
                 | référentiel est une métadonnée de réputation, pas une base de
                 | vérité.
                 */
                fiabilite: $this->fiabiliteDeLaSource($url, 60),
            );
        }

        return $preuves;
    }
}
