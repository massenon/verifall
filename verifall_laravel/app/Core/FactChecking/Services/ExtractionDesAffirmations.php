<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\Adapters\AdaptateurHttp;
use App\Core\FactChecking\Adapters\ConstructeurDePrompt;
use App\Core\FactChecking\ValueObjects\AffirmationExtraite;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\ActeDeLangage;
use App\Enums\PorteeTemporelle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Décompose un contenu soumis en claims — phase 2 du plan en 13 phases.
 *
 * ## Le contrat : NE LÈVE JAMAIS, ne rend JAMAIS un tableau vide
 *
 * Même garantie que {@see AdaptateurHttp} :
 * quoi qu'il arrive — clé absente, service éteint, JSON malformé — cette
 * méthode rend AU MOINS un claim. Le repli est l'affirmation unique : traiter
 * tout le contenu soumis comme UN SEUL claim, exactement le comportement
 * d'avant cette phase. Une extraction qui échouerait en silence et rendrait
 * zéro claim tuerait une vérification payée sur une panne de service tiers —
 * c'est précisément ce que `AdaptateurHttp` refuse de laisser faire un moteur,
 * et l'extraction n'a pas une exigence moindre.
 *
 * ## Pourquoi ce n'est pas un `AdaptateurHttp`
 *
 * Le contrat de sortie diffère entièrement — `list<AffirmationExtraite>`, pas
 * un `VerdictMoteur` — et cette classe ne participe à aucun score : elle
 * tourne UNE FOIS, avant que les moteurs ne soient appelés, et pour CHAQUE
 * claim qu'elle rend.
 *
 * ## Le fichier joint fait partie du contenu à décomposer — NEWINSTRUCTION32
 *
 * Une image, un son ou une vidéo sont envoyés à Gemini au même titre que le
 * texte saisi ({@see partieFichier()}) : ce que le modèle Y OBSERVE — texte
 * visible, paroles, événements décrits — entre dans la même décomposition en
 * claims que la légende qui l'accompagne. Sans cela, un fichier déposé sans
 * légende ne produisait qu'un claim vide de sens, alors même que les moteurs
 * d'analyse, eux, regardaient bien le fichier.
 */
final class ExtractionDesAffirmations
{
    /**
     * Cinq au plus.
     *
     * Au-delà, chaque claim supplémentaire multiplie le temps de chaîne de
     * TOUS les moteurs — un coût qui ne devient soutenable qu'au
     * parallélisme de la phase 6. Cinq propositions bien formées valent mieux
     * que douze fragments, et c'est aussi la limite que le prompt impose au
     * modèle lui-même.
     */
    private const MAX_CLAIMS = 5;

    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(
        private readonly ConstructeurDePrompt $prompts,
        private readonly RotationDeCles $rotation = new RotationDeCles('gemini'),
    ) {}

    /**
     * @return list<AffirmationExtraite> jamais vide
     */
    public function extraire(SacDePreuves $entree): array
    {
        if ((bool) config('factcheck.fake_adapters', true)) {
            return $this->simuler($entree);
        }

        try {
            $resultat = $this->appelerLeModele($entree);
        } catch (Throwable $erreur) {
            Log::error('Extraction en échec, repli sur une affirmation unique', [
                'erreur' => $erreur->getMessage(),
            ]);

            return [AffirmationExtraite::affirmationUnique($entree->affirmation)];
        }

        return $resultat !== [] ? $resultat : [AffirmationExtraite::affirmationUnique($entree->affirmation)];
    }

    /**
     * @return list<AffirmationExtraite>
     */
    private function appelerLeModele(SacDePreuves $entree): array
    {
        $prompt = $this->prompts->pourExtraction($entree->affirmation, $entree->contexte);
        $parties = [['text' => $prompt], ...$this->partieFichier($entree)];

        /** @var list<string> $cles */
        $cles = config('services.gemini.keys', []);
        $modele = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $delai = (int) config('factcheck.runtime.timeout_extraction', 15);

        $donnees = $this->rotation->essayer(
            $cles,
            function (string $cle) use ($parties, $modele, $delai): ?array {
                $reponse = Http::timeout($delai)
                    ->connectTimeout(10)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post(self::BASE.$modele.':generateContent?key='.$cle, [
                        'contents' => [['parts' => $parties]],
                        'generationConfig' => ['temperature' => 0.0],
                    ]);

                return $reponse->successful() ? $reponse->json() : null;
            },
            (int) config('factcheck.runtime.circuit_breaker.repos_minutes', 5),
        );

        if ($donnees === null) {
            return [];
        }

        $texte = null;

        foreach ((array) data_get($donnees, 'candidates.0.content.parts', []) as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texte .= $part['text'];
            }
        }

        $resultat = $this->lireJson($texte);
        $claims = [];

        foreach ((array) ($resultat['claims'] ?? []) as $brut) {
            if (! is_array($brut) || ! is_string($brut['texte'] ?? null) || trim($brut['texte']) === '') {
                continue;
            }

            $acte = $this->lireActe($brut['acte_de_langage'] ?? null);

            $claims[] = new AffirmationExtraite(
                texte: trim($brut['texte']),
                acteDeLangage: $acte,
                texteOriginal: is_string($brut['texte_original'] ?? null) && trim($brut['texte_original']) !== ''
                    ? trim($brut['texte_original'])
                    : null,
                // N'a de sens que pour une affirmation : une opinion ou une
                // question n'ont rien à voir avec une fenêtre de fraîcheur.
                porteeTemporelle: $acte === ActeDeLangage::Affirmation
                    ? $this->lirePortee($brut['portee_temporelle'] ?? null)
                    : null,
            );

            if (count($claims) >= self::MAX_CLAIMS) {
                break;
            }
        }

        return $claims;
    }

    /**
     * Le fichier joint (image, audio, vidéo), en partie `inline_data` — ou
     * rien du tout quand il n'y en a pas.
     *
     * NEWINSTRUCTION32 : avant ce changement, l'extraction ne lisait QUE le
     * texte saisi par la personne — jamais le fichier lui-même. Un citoyen qui
     * déposait une image sans légende voyait son contenu décomposé en UN SEUL
     * claim vide de sens, pendant que {@see GeminiAdapter} regardait bien
     * l'image, mais pour juger un claim que personne n'avait formulé depuis
     * elle. Même pattern que {@see GeminiAdapter::appeler()} : MIME détecté,
     * octets bruts encodés, aucun découpage par type de média.
     *
     * @return list<array{inline_data: array{mime_type: string, data: string}}>
     */
    private function partieFichier(SacDePreuves $entree): array
    {
        if ($entree->cheminFichier === null || ! Storage::disk('local')->exists($entree->cheminFichier)) {
            return [];
        }

        $chemin = Storage::disk('local')->path($entree->cheminFichier);
        $type = mime_content_type($chemin);

        if ($type === false) {
            return [];
        }

        return [['inline_data' => [
            'mime_type' => $type,
            'data' => base64_encode((string) file_get_contents($chemin)),
        ]]];
    }

    /**
     * Lit l'acte de langage rendu — tolérant, comme toute lecture de sortie de modèle.
     *
     * Jamais `NonFactuel` par défaut : un acte non reconnu doit se voir comme
     * une affirmation plutôt que disparaître silencieusement du rapport.
     */
    private function lireActe(mixed $brut): ActeDeLangage
    {
        return match (mb_strtoupper(trim((string) $brut))) {
            'QUESTION' => ActeDeLangage::Question,
            'OPINION' => ActeDeLangage::Opinion,
            'PREDICTION' => ActeDeLangage::Prediction,
            'NON_FACTUEL', 'NONFACTUEL' => ActeDeLangage::NonFactuel,
            default => ActeDeLangage::Affirmation,
        };
    }

    /**
     * Lit la portée temporelle rendue — tolérant, et PRUDENT par défaut.
     *
     * Un acte non reconnu devient `PRESENT`, pas `HISTORIQUE` : la mémoire
     * du modèle est interdite pour `PRESENT`, admise pour `HISTORIQUE`. En
     * cas de doute sur ce que le modèle a voulu dire, le défaut le plus
     * sûr est celui qui refuse la mémoire plutôt que celui qui l'autorise.
     */
    private function lirePortee(mixed $brut): PorteeTemporelle
    {
        return match (mb_strtoupper(trim((string) $brut))) {
            'HISTORIQUE' => PorteeTemporelle::Historique,
            'DATE_EXPLICITE', 'DATEEXPLICITE' => PorteeTemporelle::DateExplicite,
            'RELATIF' => PorteeTemporelle::Relatif,
            default => PorteeTemporelle::Present,
        };
    }

    /**
     * Même tolérance que {@see AdaptateurHttp::lireJson()} :
     * les modèles enrobent volontiers leur JSON dans un bloc markdown.
     *
     * @return array<string, mixed>|null
     */
    private function lireJson(?string $brut): ?array
    {
        $texte = trim((string) $brut);
        $texte = (string) preg_replace('/^```(?:json)?\s*/i', '', $texte);
        $texte = (string) preg_replace('/\s*```$/', '', $texte);

        $decode = json_decode(trim($texte), true);

        return is_array($decode) ? $decode : null;
    }

    /**
     * Extraction SIMULÉE — déterministe, dérivée du texte.
     *
     * ## Pourquoi le cas par défaut reste UN SEUL claim
     *
     * Toute la suite de tests écrite avant cette phase soumet une phrase
     * unique et attend le comportement d'avant : un claim, qui reçoit tout le
     * pipeline. Une décomposition par défaut aurait changé silencieusement le
     * résultat de centaines de tests déjà verts. La décomposition ne se
     * déclenche donc que sur des signaux EXPLICITES : plusieurs phrases
     * séparées par une ponctuation forte, un point d'interrogation final, ou
     * les marqueurs qu'une fixture de test pose exprès.
     *
     * @return list<AffirmationExtraite>
     */
    private function simuler(SacDePreuves $entree): array
    {
        $texte = trim($entree->affirmation);

        if ($texte === '') {
            return [AffirmationExtraite::affirmationUnique($entree->affirmation)];
        }

        if (str_ends_with(rtrim($texte), '?') && ! str_contains($texte, '. ')) {
            return [new AffirmationExtraite($texte, ActeDeLangage::Question)];
        }

        $texteMinuscule = mb_strtolower($texte);

        foreach (['quelle belle journée', 'bonjour', 'merci beaucoup', 'haha'] as $marqueur) {
            if (str_contains($texteMinuscule, $marqueur)) {
                return [new AffirmationExtraite($texte, ActeDeLangage::NonFactuel)];
            }
        }

        foreach (['à mon avis', 'je pense que', 'est une honte', 'est magnifique', 'est scandaleux'] as $marqueur) {
            if (str_contains($texteMinuscule, $marqueur)) {
                return [new AffirmationExtraite($texte, ActeDeLangage::Opinion)];
            }
        }

        foreach (['va bientôt', 'va prochainement', "l'année prochaine", 'dans les années à venir'] as $marqueur) {
            if (str_contains($texteMinuscule, $marqueur)) {
                return [new AffirmationExtraite($texte, ActeDeLangage::Prediction)];
            }
        }

        // Plusieurs phrases déclarées, séparées par un point suivi d'une
        // majuscule : seul signal de décomposition retenu en simulation.
        $segments = preg_split('/(?<=[.!])\s+(?=\p{Lu})/u', $texte) ?: [$texte];
        $segments = array_values(array_filter(array_map('trim', $segments), static fn (string $s): bool => $s !== ''));

        if (count($segments) <= 1) {
            return [new AffirmationExtraite($texte, ActeDeLangage::Affirmation, porteeTemporelle: $this->classerLaPortee($texte))];
        }

        return array_map(
            fn (string $segment): AffirmationExtraite => new AffirmationExtraite(
                $segment, ActeDeLangage::Affirmation, porteeTemporelle: $this->classerLaPortee($segment),
            ),
            array_slice($segments, 0, self::MAX_CLAIMS),
        );
    }

    /**
     * Classification temporelle SIMULÉE — déterministe, par mots-clés.
     *
     * L'équivalent, en simulation, de ce que le modèle réel classe via le
     * schéma de `extraction.md`. L'ordre des contrôles compte : une date
     * explicite l'emporte sur un marqueur présent (« le prix a doublé EN
     * 2026 » est DATE_EXPLICITE même si 2026 est l'année en cours), et un
     * marqueur relatif sur le défaut historique.
     */
    private function classerLaPortee(string $texte): PorteeTemporelle
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $texte) === 1) {
            return PorteeTemporelle::DateExplicite;
        }

        $minuscule = mb_strtolower($texte);

        foreach ([
            'hier', 'avant-hier', 'cette semaine', 'ce mois', 'cette année',
            'récemment', 'la semaine dernière', 'le mois dernier',
        ] as $marqueur) {
            if (str_contains($minuscule, $marqueur)) {
                return PorteeTemporelle::Relatif;
            }
        }

        foreach ([
            'actuellement', 'actuel', 'en exercice', 'en poste', 'désormais',
            'à ce jour', "aujourd'hui", 'est actuellement',
        ] as $marqueur) {
            if (str_contains($minuscule, $marqueur)) {
                return PorteeTemporelle::Present;
            }
        }

        /*
         | UN TITULAIRE DE FONCTION EST SENSIBLE AU TEMPS MÊME SANS
         | « ACTUELLEMENT ».
         |
         | « Patrice Talon est président du Bénin » ne porte aucun des
         | marqueurs ci-dessus, et pourtant c'est exactement le genre de
         | claim qu'une mémoire périmée rend FAUSSEMENT avec l'autorité d'une
         | vérification — le cas réel qui a motivé `PertinenceEtProvenanceTest`.
         | Un verbe de fonction au présent classe donc en PRESENT par défaut,
         | et seule une date explicite (contrôlée plus haut, donc prioritaire)
         | l'en sort : « X a été président de 2006 à 2016 » n'est pas de
         | la même nature que « X est président ».
         */
        if (preg_match(
            '/\b(est|sont|demeure|reste)\s+(le\s+|la\s+|l\')?(président|ministre|maire|gouverneur|directeur|directrice|pdg|chef|dirigeant|dirigeante)\b|\bdirige\b|\best\s+à\s+la\s+tête\b/u',
            $minuscule,
        ) === 1) {
            return PorteeTemporelle::Present;
        }

        return PorteeTemporelle::Historique;
    }
}
