<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\VerdictFactCheck;
use App\Models\EngineRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Rend lisible la synthèse écrite par un moteur d'analyse.
 *
 * ## Pourquoi un service, et pas deux lignes dans la vue
 *
 * Le gabarit faisait ceci :
 *
 *     {!! \Illuminate\Support\Str::markdown($texteRaisonnement) !!}
 *
 * Trois défauts, dont un de sécurité.
 *
 * 1. **`Str::markdown()` sans options laisse passer le HTML.** CommonMark vaut
 *    `html_input: allow` et `allow_unsafe_links: true` par défaut ; le
 *    `{!! !!}` l'injectait tel quel. Or ce texte est écrit par un modèle de
 *    langage DONT L'ENTRÉE EST L'AFFIRMATION DU CITOYEN. Une injection de
 *    consigne suffisait à faire ressortir un `<img onerror=…>` dans la page
 *    d'un autre lecteur. La règle 3 du CLAUDE.md — « jamais `{!! !!}` sur
 *    données utilisateur » — était enfreinte au seul endroit du produit où le
 *    texte vient d'un tiers non fiable.
 * 2. Le nettoyage du JSON parasite était recopié dans la vue ET dans deux
 *    adaptateurs, avec trois expressions régulières légèrement différentes.
 * 3. Le CHOIX du moteur dont on affiche le raisonnement était un
 *    `$moteurs->first(fn ($m) => ! empty($m->reasoning))` sur une collection
 *    triée par `id` — c'est-à-dire, en pratique, l'ordre de
 *    `MoteurAnalyse::pour()`, qui place OpenAI en tête. Le rapport montrait
 *    donc en priorité le moteur SANS accès web, pas le mieux étayé.
 *
 * La défense est en deux temps, et les deux comptent : le Markdown est rendu
 * avec le HTML d'entrée RETIRÉ, puis la sortie repasse par `mews/purifier`.
 * Le premier empêche le HTML d'entrer, le second garantit que ce qui sort est
 * un sous-ensemble connu — y compris si une version future de CommonMark
 * change ses réglages par défaut.
 */
final class RenduDeSynthese
{
    public function __construct(private readonly CoherenceRapport $coherence) {}

    /**
     * Un objet JSON collé en tête de réponse.
     *
     * Gemini a longtemps préfixé son Markdown d'un `{"final_verdict": "vrai",
     * "confidence": 90}` — un reliquat de l'ancienne orchestration, qui
     * s'affichait en clair au-dessus du rapport. Les adaptateurs le retirent
     * déjà ; ce filet rattrape les rapports DÉJÀ ENREGISTRÉS, qu'aucune
     * correction d'adaptateur ne pourra nettoyer rétroactivement.
     */
    private const JSON_PARASITE = '/^\s*\{[^{}]*\}\s*/s';

    /**
     * La synthèse la mieux étayée, prête à être affichée — ou `null`.
     *
     * ## Le verdict donne le droit de veto sur le texte
     *
     * Le poids décide de l'ORDRE ; le verdict décide de ce qui est AFFICHABLE.
     * Un rapport qui titre « FAUX » et qui laisse lire « cette information est
     * confirmée » se contredit en pleine page — c'est le pire défaut possible
     * pour une plateforme de fact-checking, pire qu'une erreur franche : le
     * lecteur ne sait plus quoi croire.
     *
     * On scanne les candidats DU MIEUX PESÉ AU MOINS PESÉ et on rend le
     * premier qui ne contredit pas le verdict. Le moteur le plus lourd garde
     * la priorité ; il la perd seulement quand son texte contredit la
     * décision. Si aucun ne passe le contrôle, `null` : la vue n'affiche que
     * `$rapport->summary`, composée côté serveur depuis le verdict — jamais
     * un texte qui le démente.
     *
     * @param  Collection<int, EngineRun>  $moteurs
     */
    public function pour(Collection $moteurs, VerdictFactCheck $verdict): ?string
    {
        foreach ($this->candidats($moteurs) as $texte) {
            if ($this->coherence->syntheseSuitLeVerdict($verdict, $texte)) {
                return $this->html($texte);
            }
        }

        return null;
    }

    /**
     * Les raisonnements affichables, du mieux étayé au moins étayé.
     *
     * NEWINSTRUCTION30 — un moteur qui N'A RENDU AUCUN VERDICT (`verdict ===
     * null`) est écarté, même si son champ `reasoning` n'est pas vide. Un
     * moteur sans verdict n'a pas analysé le claim : il explique pourquoi il
     * s'abstient — Google Fact Check, le poids le plus fort du barème
     * ({@see config('factcheck.weights.google_factcheck')} = 1.0), rend
     * ainsi « Aucune vérification antérieure trouvée dans la base
     * ClaimReview. » pour CHAQUE claim sans fact-check préexistant, c'est-à-
     * dire la quasi-totalité d'entre eux. Non filtré, ce texte gagnait
     * systématiquement le tri par poids et s'affichait comme LA synthèse, à
     * la place de l'analyse réelle d'un moteur sémantique. La même règle
     * exclut un détecteur de style ({@see MoteurAnalyse::porteUnVerdict()}
     * = faux) pour une raison identique : un jugement de forme n'est pas une
     * explication de verdict.
     *
     * @param  Collection<int, EngineRun>  $moteurs
     * @return list<string>
     */
    private function candidats(Collection $moteurs): array
    {
        return $moteurs
            ->reject(static fn (EngineRun $m): bool => $m->failed
                || $m->verdict === null
                || $m->reasoning === null
                || trim($m->reasoning) === '')
            ->sortByDesc(static fn (EngineRun $m): array => [
                $m->engine->poids(),
                mb_strlen((string) $m->reasoning),
            ])
            ->pluck('reasoning')
            ->map(static fn ($texte): string => (string) $texte)
            ->values()
            ->all();
    }

    /**
     * Le raisonnement du moteur qui pèse le plus lourd.
     *
     * Le poids vient de `config('factcheck.weights')` — le même barème qui
     * décide du verdict. Afficher le raisonnement d'un moteur qui a peu compté
     * dans la décision donnerait à lire une explication qui n'est pas celle du
     * résultat. À poids égal, le texte le plus long l'emporte : entre deux
     * moteurs d'autorité comparable, c'est celui qui a documenté.
     *
     * @param  Collection<int, EngineRun>  $moteurs
     */
    public function meilleurRaisonnement(Collection $moteurs): ?string
    {
        return $this->candidats($moteurs)[0] ?? null;
    }

    /**
     * Markdown → HTML sûr.
     *
     * `html_input: strip` retire le HTML écrit par le modèle plutôt que de
     * l'échapper : un rapport n'a aucune raison de porter des balises venues
     * d'un moteur, et les afficher échappées — « &lt;div&gt; » en toutes
     * lettres — serait illisible sans être plus sûr.
     */
    public function html(string $markdown): string
    {
        $propre = (string) preg_replace(self::JSON_PARASITE, '', trim($markdown));

        if ($propre === '') {
            return '';
        }

        $rendu = Str::markdown($propre, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return trim((string) Purifier::clean($rendu, 'default'));
    }

    /**
     * Le même nettoyage, appliqué au texte brut d'un moteur.
     *
     * Partagé avec les adaptateurs pour que le JSON parasite soit retiré au
     * même endroit et de la même façon — une seule expression régulière, pas
     * trois variantes.
     */
    public function nettoyer(?string $brut): string
    {
        return trim((string) preg_replace(self::JSON_PARASITE, '', trim((string) $brut)));
    }

    /**
     * Le raisonnement d'un verdict de moteur, nettoyé.
     *
     * Sert aux adaptateurs, qui manipulent des {@see VerdictMoteur} et non des
     * lignes de base.
     */
    public function depuisLeVerdict(VerdictMoteur $verdict): string
    {
        return $this->nettoyer($verdict->raisonnement);
    }
}
