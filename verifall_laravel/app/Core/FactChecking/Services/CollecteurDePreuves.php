<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\ValueObjects\ElementDuPaquet;
use App\Core\FactChecking\ValueObjects\PaquetDePreuves;
use App\Core\FactChecking\ValueObjects\Preuve;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;

/**
 * Le système récupère les pages, et transmet un paquet IDENTIFIÉ — phase 6.
 *
 * Objectif du plan, au mot près : lève D-02. Jusqu'ici, « Sources et preuves
 * consultées » affichait ce que les moteurs de DÉCOUVERTE (Perplexity, Google
 * Custom Search, Google Fact Check, le grounding de Gemini) rendaient tel
 * quel — une URL jamais ouverte par la plateforme elle-même. Ce collecteur
 * ouvre réellement ces pages, via {@see UrlSandbox}, et n'en garde que ce qui
 * s'avère pertinent.
 *
 * ## Ce que ce collecteur fait, et ce qu'il ne fait PAS encore
 *
 * Il sait fabriquer un {@see PaquetDePreuves} correct et testé à partir d'une
 * liste de {@see Preuve} candidates. Il n'est PAS encore appelé par
 * {@see OrchestrateurVerification}, et les trois adaptateurs LLM ne lisent
 * pas encore ce paquet — c'est le FIL DE BASCULE que le contrat V-10 demande
 * explicitement de ne pas tirer avant qu'il soit « explicite et testé » :
 * retirer aujourd'hui les `sources` que les modèles rendent eux-mêmes
 * laisserait les rapports sans preuve, Gemini et son ancrage Google en
 * fournissant aujourd'hui l'essentiel. Le basculement — brancher ce
 * collecteur dans l'orchestrateur, faire citer `source_ids` aux trois
 * adaptateurs — reste une étape distincte, délibérément non engagée ici.
 */
final class CollecteurDePreuves
{
    public function __construct(
        private readonly UrlSandbox $sandbox = new UrlSandbox,
        private readonly DomaineDeLaSource $domaines = new DomaineDeLaSource,
        private readonly PertinenceDeLaPreuve $pertinence = new PertinenceDeLaPreuve,
    ) {}

    /**
     * @param  list<Preuve>  $candidates
     */
    public function collecter(string $claim, array $candidates): PaquetDePreuves
    {
        $parDomaine = $this->dedupliquees($candidates);

        if ($parDomaine === []) {
            return PaquetDePreuves::vide();
        }

        $urls = array_map(static fn (Preuve $p): string => $p->url, $parDomaine);
        $recuperees = $this->sandbox->recupererPlusieurs($urls);

        $elements = [];
        $position = 1;

        foreach ($parDomaine as $preuve) {
            $resultat = $recuperees[$preuve->url] ?? null;

            if ($resultat === null) {
                continue;
            }

            $page = $this->analyserLaPage($resultat['corps']);
            $titre = $page['titre'] !== '' ? $page['titre'] : $preuve->nomSource;
            $extrait = $page['extrait'] !== '' ? $page['extrait'] : $preuve->extrait;

            if (! $this->pertinence->estPertinente($claim, $titre, $extrait)) {
                continue;
            }

            $editeurAffiche = $this->domaines->pour($resultat['url_finale'], $preuve->nomSource);

            $elements[] = new ElementDuPaquet(
                id: sprintf('SRC_%03d', $position),
                urlFinale: $resultat['url_finale'],
                urlCanonique: $page['canonique'],
                editeur: $this->domaines->estUnRedirecteur($editeurAffiche) ? null : $editeurAffiche,
                titre: $titre,
                extrait: mb_substr($extrait, 0, 300),
                publieeLe: $page['publieeLe'],
                fiabilite: $preuve->fiabilite,
            );

            $position++;

            if (count($elements) >= PaquetDePreuves::MAX_ELEMENTS) {
                break;
            }
        }

        return PaquetDePreuves::depuis($elements);
    }

    /**
     * Une candidate par domaine ENREGISTRABLE — fetcher deux fois le même
     * éditeur gaspillerait le budget de temps pour la même conclusion.
     * La plus FIABLE de chaque groupe est retenue.
     *
     * @param  list<Preuve>  $candidates
     * @return list<Preuve>
     */
    private function dedupliquees(array $candidates): array
    {
        /** @var array<string, Preuve> $parDomaine */
        $parDomaine = [];

        foreach ($candidates as $preuve) {
            $affiche = $this->domaines->pour($preuve->url, $preuve->nomSource);
            $cle = $this->domaines->domaineEnregistrable($affiche) ?? $affiche;

            if (! isset($parDomaine[$cle]) || $preuve->fiabilite > $parDomaine[$cle]->fiabilite) {
                $parDomaine[$cle] = $preuve;
            }
        }

        return array_values($parDomaine);
    }

    /**
     * Titre, canonique et date — lus dans le HTML réellement récupéré, pas
     * devinés. `libxml_use_internal_errors` : le web ordinaire ne produit
     * pas de HTML valide, et une page légèrement malformée ne doit pas faire
     * échouer tout le collecteur.
     *
     * @return array{titre: string, canonique: ?string, publieeLe: ?Carbon, extrait: string}
     */
    private function analyserLaPage(string $html): array
    {
        if (trim($html) === '') {
            return ['titre' => '', 'canonique' => null, 'publieeLe' => null, 'extrait' => ''];
        }

        $precedent = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($precedent);

        $xpath = new DOMXPath($dom);

        $titre = trim((string) $xpath->evaluate('string(//title)'));

        $canonique = null;
        $lienCanonique = $xpath->query('//link[@rel="canonical"]/@href');

        if ($lienCanonique !== false && $lienCanonique->length > 0) {
            $canonique = trim((string) $lienCanonique->item(0)?->nodeValue) ?: null;
        }

        $publieeLe = $this->dateDePublication($xpath);

        $corps = trim((string) $xpath->evaluate('string(//body)'));
        $extrait = (string) preg_replace('/\s+/u', ' ', $corps);

        return ['titre' => $titre, 'canonique' => $canonique, 'publieeLe' => $publieeLe, 'extrait' => trim($extrait)];
    }

    /**
     * La date de publication DÉCLARÉE par la page — jamais devinée.
     *
     * Plusieurs conventions de balisage coexistent sur le web réel ; on les
     * essaie dans l'ordre, et on s'arrête à la première qui se parse
     * réellement comme une date.
     */
    private function dateDePublication(DOMXPath $xpath): ?Carbon
    {
        $candidats = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@property="og:article:published_time"]/@content',
            '//meta[@name="date"]/@content',
            '//meta[@name="datePublished"]/@content',
            '//time/@datetime',
        ];

        foreach ($candidats as $requete) {
            $noeuds = $xpath->query($requete);

            if ($noeuds === false || $noeuds->length === 0) {
                continue;
            }

            $brute = trim((string) $noeuds->item(0)?->nodeValue);

            if ($brute === '') {
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
}
