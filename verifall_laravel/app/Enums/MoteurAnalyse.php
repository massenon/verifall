<?php

declare(strict_types=1);

namespace App\Enums;

use App\Core\FactChecking\Services\RegistreDesMoteurs;

/**
 * Moteurs d'analyse mobilisables.
 *
 * La valeur sert de clé de pondération dans `config/factcheck.weights` et
 * s'écrit dans le rapport : c'est ce qui rend la méthode auditable — un lecteur
 * peut voir QUI a dit quoi, et avec quel poids.
 */
enum MoteurAnalyse: string
{
    case Gemini = 'gemini';
    case DeepSeek = 'deepseek';
    case OpenAI = 'openai';
    case PerplexitySearch = 'perplexity_search';
    case GoogleFactCheck = 'google_factcheck';
    case GoogleCustomSearch = 'google_custom_search';
    case KnowledgeGraph = 'knowledge_graph';
    case AnalyseTechnique = 'technical_analysis';
    case TinEye = 'tineye';
    case Vision = 'vision';
    case DetecteurIA = 'ai_detector';
    case Transcription = 'transcription';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('analyse.moteur.'.$this->value);
    }

    public function poids(): float
    {
        return (float) config('factcheck.weights.'.$this->value, 0.5);
    }

    /**
     * Ce moteur peut-il porter un verdict, ou n'est-il qu'un signal ?
     *
     * Un détecteur d'IA dit qu'un texte ressemble à une production
     * automatique — pas qu'il est faux. Confondre les deux ferait condamner
     * une information vraie sur la seule foi de son style.
     */
    public function porteUnVerdict(): bool
    {
        return ! in_array($this, [self::DetecteurIA, self::Transcription, self::Vision], true);
    }

    /**
     * Ce moteur cite-t-il ses preuves en LIBRE, ou par identifiant d'un
     * paquet déjà vérifié ? — contrat V-10, phase 7.
     *
     * Seuls les trois moteurs d'analyse sémantique rendent eux-mêmes des
     * « sources » : les autres sont soit des moteurs de DÉCOUVERTE (Google
     * Fact Check, Google Custom Search, Perplexity…), dont les résultats
     * nourrissent {@see CollecteurDePreuves} plutôt que de citer, soit de
     * simples signaux (détecteur d'IA) sans preuve à citer du tout.
     *
     * Quand {@see config('factcheck.collecteur_de_preuves_actif')} est actif,
     * `OrchestrateurVerification` interroge d'abord les moteurs pour lesquels
     * cette méthode rend `false`, construit le paquet depuis leurs résultats,
     * puis n'interroge CES TROIS-LÀ qu'avec le paquet attaché — ils ne
     * peuvent alors plus citer que ce qu'il contient.
     */
    public function citeEnLibre(): bool
    {
        return in_array($this, [self::Gemini, self::OpenAI, self::DeepSeek], true);
    }

    /**
     * Le moteur est-il allumé tant que personne n'a touché aux réglages ?
     *
     * ## Ce sont des décisions d'EXPLOITATION, pas des états techniques
     *
     * Arrêtées le 1er septembre 2026 par le porteur du projet, et chacune a une
     * raison différente — c'est pourquoi elles sont énumérées et non déduites
     * d'un « la clé est-elle là ? » :
     *
     *   · **TinEye** est un service PAYANT que le budget ne permet pas encore.
     *     Rien n'est cassé ; on ne l'a pas acheté.
     *   · **Vision** dépend de la clé Google, que Google refuse aujourd'hui.
     *     Il se rallumera d'une case le jour où la clé repart.
     *   · **Transcription** a désormais un adaptateur réel (OpenAI Whisper /
     *     gpt-4o-transcribe, NEWINSTRUCTION32, {@see TranscriptionAdapter}) —
     *     mais reste ÉTEINTE par défaut : chaque appel est facturé, et rouvrir
     *     vidéo/audio à tous les citoyens est une décision d'exploitation, pas
     *     une conséquence automatique d'avoir écrit la classe. Le réglage
     *     `moteur_actif_transcription` la rallume d'une case, sans déploiement
     *     — et rouvre alors les deux types, voir {@see TypeContenu::moteurIndispensable()}.
     *   · **GoogleCustomSearch** est inutilisable depuis que Google a retiré
     *     l'option « rechercher sur l'ensemble du Web » des moteurs CSE
     *     récents : il échoue en silence, avec un message d'erreur vide. Il
     *     était jusqu'ici éteint par un `if` EN DUR dans
     *     {@see RegistreDesMoteurs::estAllume()},
     *     qui court-circuitait les réglages — l'interrupteur de
     *     l'administration existait, s'affichait allumé, et ne servait à rien.
     *     Il est désormais éteint ICI, comme les trois autres, donc rallumable
     *     d'une case le jour où Google rouvre l'option.
     *   · **DetecteurIA** (NEWINSTRUCTION31) : éteint le temps de remplacer
     *     ce signal unique — « ressemble à une production d'IA » — par des
     *     observations GPT Vision + Gemini Vision confrontées aux preuves
     *     externes, plus riches et dans le même esprit que le reste du
     *     pipeline (les modèles analysent, l'adjudicateur tranche). Pas
     *     supprimé : {@see AiOrNotAdapter} reste tel quel, rallumable d'une
     *     case si le remplacement n'est finalement pas retenu.
     *
     * Les autres restent allumés : une clé absente les rend simplement
     * indisponibles, sans coût ni bruit — {@see EtatMoteur}.
     */
    public function allumeParDefaut(): bool
    {
        return match ($this) {
            self::TinEye, self::Vision, self::Transcription,
            self::GoogleCustomSearch, self::DetecteurIA => false,
            default => true,
        };
    }

    /**
     * Pourquoi ce moteur part allumé ou éteint — montré dans les réglages.
     *
     * Un interrupteur sans explication se rallume par curiosité, et la facture
     * arrive un mois plus tard.
     */
    public function raisonDeLEtatParDefaut(): string
    {
        return match ($this) {
            self::TinEye => 'ÉTEINT : service payant, non souscrit. Allumez-le une fois l’abonnement pris — la clé TINEYE_API_KEY est déjà attendue par le code.',
            self::Vision => 'ÉTEINT : dépend de GOOGLE_API_KEY, que Google refuse actuellement. Rallumez-le dès que la clé est régénérée.',
            self::Transcription => 'ÉTEINT par défaut : un fournisseur existe désormais (OpenAI Whisper / gpt-4o-transcribe), facturé à l’appel. Activez-le pour rouvrir les dépôts vidéo et audio côté citoyen.',
            self::GoogleCustomSearch => 'ÉTEINT : Google a retiré « rechercher sur l’ensemble du Web » des moteurs CSE récents, et l’appel échoue en silence. Ne le rallumez que si l’option revient.',
            self::DetecteurIA => 'ÉTEINT : en cours de remplacement par des observations GPT Vision + Gemini Vision confrontées aux preuves externes (NEWINSTRUCTION31). Fournisseur du signal seul : AI or Not — GPTZero et HuggingFace ne sont pas souscrits.',
            self::OpenAI => 'Second avis de raisonnement, sans accès web. Facturé à l’appel.',
            self::Gemini => 'Moteur principal, avec ancrage sur la recherche Google. Plusieurs clés en rotation.',
            self::DeepSeek => 'Contre-avis indépendant : deux modèles de familles différentes hallucinent rarement la même chose.',
            self::GoogleFactCheck => 'Le poids le plus élevé du barème : un fact-check déjà publié est une preuve, pas une inférence.',
            default => 'Éteindre ce moteur le retire des analyses sans toucher à sa clé.',
        };
    }

    /**
     * Moteurs pertinents pour un type de contenu.
     *
     * ## OpenAI et DeepSeek sur Image/Vidéo/Audio — NEWINSTRUCTION32
     *
     * OpenAI rejoint Gemini sur l'Image : les deux lisent directement le
     * fichier ({@see OpenAiAdapter}, {@see GeminiAdapter}), et une comparaison
     * à deux moteurs multimodaux vaut mieux qu'un avis isolé.
     *
     * OpenAI et DeepSeek rejoignent Vidéo et Audio alors que ni l'un ni
     * l'autre ne lit de fichier : ils raisonnent sur le CLAIM déjà extrait en
     * texte ({@see ExtractionDesAffirmations}, qui observe elle-même le
     * fichier), exactement comme pour un contenu texte ou lien. Rien dans leur
     * adaptateur n'a dû changer pour cela.
     *
     * @return list<self>
     */
    public static function pour(TypeContenu $type): array
    {
        return match ($type) {
            TypeContenu::Texte => [
                self::OpenAI, self::Gemini, self::DeepSeek, self::GoogleFactCheck,
                self::GoogleCustomSearch, self::KnowledgeGraph, self::DetecteurIA,
            ],
            TypeContenu::Lien => [
                self::Gemini, self::DeepSeek, self::AnalyseTechnique,
                self::GoogleCustomSearch, self::PerplexitySearch, self::OpenAI,
            ],
            TypeContenu::Image => [
                self::Vision, self::TinEye, self::DetecteurIA,
                self::GoogleCustomSearch, self::Gemini, self::OpenAI,
            ],
            TypeContenu::Video, TypeContenu::Audio => [
                self::Transcription, self::DetecteurIA, self::Gemini,
                self::GoogleCustomSearch, self::OpenAI, self::DeepSeek,
            ],
        };
    }
}
