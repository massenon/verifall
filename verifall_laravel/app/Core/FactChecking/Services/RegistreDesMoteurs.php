<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Reglages\Reglages;
use App\Core\FactChecking\Adapters\AdaptateurHttp;
use App\Core\FactChecking\Adapters\AdaptateurSimule;
use App\Core\FactChecking\Adapters\AiOrNotAdapter;
use App\Core\FactChecking\Adapters\AnalyseTechniqueAdapter;
use App\Core\FactChecking\Adapters\DeepSeekAdapter;
use App\Core\FactChecking\Adapters\GeminiAdapter;
use App\Core\FactChecking\Adapters\GoogleCustomSearchAdapter;
use App\Core\FactChecking\Adapters\GoogleFactCheckAdapter;
use App\Core\FactChecking\Adapters\OpenAiAdapter;
use App\Core\FactChecking\Adapters\PerplexitySearchAdapter;
use App\Core\FactChecking\Adapters\TranscriptionAdapter;
use App\Core\FactChecking\Contracts\AnalysisAdapterInterface;
use App\Enums\MoteurAnalyse;
use App\Enums\TypeContenu;
use RuntimeException;

/**
 * Décide quels moteurs interroger.
 *
 * Le choix « vrais moteurs ou simulation » se fait ICI et nulle part ailleurs :
 * l'orchestrateur reçoit une liste d'adaptateurs et ignore tout de leur nature.
 * C'est ce qui rend la bascule sans risque — aucune branche `if (simule)` ne
 * traîne dans la logique métier.
 *
 * En production, la simulation est refusée net : une plateforme de
 * fact-checking qui rendrait des verdicts inventés se détruirait elle-même.
 */
final class RegistreDesMoteurs
{
    /**
     * @return list<AnalysisAdapterInterface>
     */
    public function pour(TypeContenu $type): array
    {
        $simule = (bool) config('factcheck.fake_adapters', true);

        if ($simule && app()->isProduction()) {
            throw new RuntimeException(
                'FAKE_ADAPTERS=true en production : les verdicts seraient inventés. '
                .'Posez FAKE_ADAPTERS=false et configurez les clés des moteurs.'
            );
        }

        $adaptateurs = [];

        foreach (MoteurAnalyse::pour($type) as $moteur) {
            /*
             | ÉTEINT PAR DÉCISION D'EXPLOITATION : on n'appelle pas, même si la
             | clé est là. C'est le sens de l'interrupteur — TinEye est payant,
             | Vision dépend d'une clé refusée, aucun fournisseur de
             | transcription n'est souscrit.
             |
             | Le contrôle est ICI, dans le registre, et non dans chaque
             | adaptateur : un moteur qu'on croit éteint et qui s'appelle quand
             | même est une facture qu'on découvre à la fin du mois.
             */
            if (! $this->estAllume($moteur)) {
                continue;
            }

            $adaptateur = $simule ? new AdaptateurSimule($moteur) : $this->adaptateurReel($moteur);

            /*
             | Un moteur sans implémentation réelle est simplement absent — pas
             | une erreur. Le catalogue compte dix-neuf entrées ; toutes n'ont
             | pas encore leur adaptateur, et l'analyse doit tourner avec celles
             | qui existent plutôt que d'attendre les autres.
             */
            if ($adaptateur !== null && $adaptateur->supporte($type)) {
                $adaptateurs[] = $adaptateur;
            }
        }

        return $adaptateurs;
    }

    /**
     * Le moteur est-il allumé dans les réglages ?
     *
     * La valeur par défaut vient de l'enum ({@see MoteurAnalyse::allumeParDefaut()}) :
     * table de réglages vide, la plateforme se comporte comme prévu, sans
     * qu'aucun seeder n'ait à tourner.
     */
    /**
     * Ce type de contenu peut-il être analysé aujourd'hui ?
     *
     * UNE seule définition, pour les trois appelants : l'écran de soumission
     * (qui grise l'onglet), le service de soumission (qui refuse), et l'API
     * mobile. Elle vivait dans `Livewire\Citizen\Verifier` — donc inopposable
     * à l'API, qui acceptait vidéo et audio sans rien vérifier.
     *
     * La condition est DÉDUITE, jamais écrite en dur : le jour où un
     * fournisseur de transcription est souscrit et le moteur rallumé dans les
     * réglages, les deux types se rouvrent d'eux-mêmes, sans déploiement.
     */
    public function moduleOuvert(TypeContenu $type): bool
    {
        $indispensable = $type->moteurIndispensable();

        if ($indispensable === null) {
            return true;
        }

        return $this->estAllume($indispensable)
            && $this->adaptateurReel($indispensable) !== null;
    }

    public function estAllume(MoteurAnalyse $moteur): bool
    {
        /*
         | Un `if` EN DUR sur `GoogleCustomSearch` vivait ici. Il éteignait le
         | moteur quoi qu'en disent les réglages — donc l'interrupteur de
         | l'administration s'affichait allumé et ne servait à rien, ce qui est
         | pire qu'une case grisée.
         |
         | La décision est remontée dans `MoteurAnalyse::allumeParDefaut()`, où
         | vivaient déjà les trois autres extinctions d'exploitation. Elle y est
         | documentée, visible dans l'écran des réglages avec son motif, et
         | réversible d'une case.
         */
        return app(Reglages::class)->actif('moteur_actif_'.$moteur->value);
    }

    /**
     * Adaptateur réel d'un moteur, s'il en existe un.
     *
     * Public depuis le 1er septembre 2026 : {@see SanteDesMoteurs} en a besoin
     * pour interroger chaque moteur sur son propre état. Le type de retour est
     * resserré sur {@see AdaptateurHttp} — les six moteurs portés en héritent
     * tous, et c'est lui qui porte `estConfigure()` et `joignable()`.
     */
    public function adaptateurReel(MoteurAnalyse $moteur): ?AdaptateurHttp
    {
        return match ($moteur) {
            MoteurAnalyse::Gemini => app(GeminiAdapter::class),
            MoteurAnalyse::DeepSeek => app(DeepSeekAdapter::class),
            MoteurAnalyse::OpenAI => app(OpenAiAdapter::class),
            MoteurAnalyse::GoogleFactCheck => app(GoogleFactCheckAdapter::class),
            MoteurAnalyse::PerplexitySearch => app(PerplexitySearchAdapter::class),
            MoteurAnalyse::GoogleCustomSearch => app(GoogleCustomSearchAdapter::class),
            MoteurAnalyse::AnalyseTechnique => app(AnalyseTechniqueAdapter::class),
            MoteurAnalyse::DetecteurIA => app(AiOrNotAdapter::class),
            MoteurAnalyse::Transcription => app(TranscriptionAdapter::class),

            // Pas encore portés : KnowledgeGraph, TinEye, Vision.
            // OpenAI l'a été le 1er septembre 2026, le détecteur d'IA le
            // 10 septembre sur la documentation d'AI or Not, et la
            // transcription le 13 octobre sur OpenAI Whisper / gpt-4o-transcribe
            // (NEWINSTRUCTION32) — le fournisseur a une clé déjà configurée,
            // il ne manquait que la classe.
            default => null,
        };
    }

    /*
    |---------------------------------------------------------------------------
    | `etat()` a été retiré le 1er septembre 2026
    |---------------------------------------------------------------------------
    |
    | Il rendait « disponible » dès qu'une CLASSE d'adaptateur existait, sans
    | regarder ni la clé ni le service. Sur la configuration de ce jour-là, il
    | annonçait Perplexity opérationnel avec une clé vide, et Google Fact Check
    | opérationnel avec une clé que Google refuse — vérifié par appel.
    |
    | Il n'avait aucun appelant : la page « Santé du système » pour laquelle il
    | avait été écrit n'existait pas encore. Le remplacer valait mieux que de
    | laisser DEUX réponses à la même question.
    |
    | La source unique est désormais {@see SanteDesMoteurs}.
    |
    */

}
