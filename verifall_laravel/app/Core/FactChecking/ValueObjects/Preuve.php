<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use App\Enums\PositionPreuve;
use Illuminate\Support\Carbon;

/**
 * Une source consultée, avec ce qu'elle dit de l'affirmation.
 *
 * C'est l'unité de base du rapport : sans preuves citées, un verdict n'est
 * qu'une opinion. La règle de l'orchestrateur en découle — toute affirmation du
 * raisonnement doit renvoyer à l'une d'elles.
 */
final readonly class Preuve
{
    public function __construct(
        public string $url,
        public string $nomSource,
        public string $extrait,
        public PositionPreuve $position,
        /** Fiabilité de la source, 0-100 — issue de `trusted_sources`. */
        public int $fiabilite = 50,
        public ?Carbon $publieeLe = null,
        /*
         | LA SOURCE A-T-ELLE ÉTÉ OUVERTE, OU SEULEMENT CITÉE DE MÉMOIRE ?
         |
         | La distinction décide de ce que le rapport a le droit d'affirmer, et
         | son absence a produit le défaut le plus grave relevé à ce jour.
         |
         | OpenAI et DeepSeek n'ont PAS d'accès web. Leur champ `sources` est
         | une liste de domaines qu'ils tiennent de leur entraînement :
         | `gouv.bj`, `rfi.fr`, `bbc.com`, avec pour extrait la phrase générique
         | « Élément factuel recoupé. » Aucune de ces pages n'a été ouverte, et
         | aucune n'a de contenu vérifiable derrière l'adresse — le modèle peut
         | même avoir inventé le chemin.
         |
         | Ces entrées étaient enregistrées comme des preuves consultées. Le
         | rapport annonçait alors « 5 sources consultées » là où UNE seule
         | avait été réellement récupérée. C'est la même faute que les vingt
         | redirections comptées pour vingt sources, en pire : là, les documents
         | existaient.
         |
         | `false` ne veut pas dire « sans valeur » : un modèle qui cite
         | `travail.gouv.bj` comme canal légitime rend un service réel. Cela
         | veut dire « ne compte pas comme un recoupement », et le rapport doit
         | le présenter autrement — voir `ReportEvidence::$fetched`.
         */
        public bool $recuperee = true,
    ) {}

    /**
     * Poids effectif de cette preuve.
     *
     * Une preuve contestée compte pour moitié : le désaccord de deux moteurs
     * sur son sens n'invalide pas la source, il invite à s'en méfier.
     */
    public function poids(): float
    {
        $base = $this->fiabilite / 100;

        /*
         | Une source seulement CITÉE, jamais ouverte, ne pèse pas comme une
         | source récupérée. Elle garde un poids — le modèle peut avoir raison
         | de nommer `travail.gouv.bj` — mais divisé, parce qu'il n'y a aucun
         | document derrière pour l'étayer.
         */
        if (! $this->recuperee) {
            $base /= 2;
        }

        return $this->position === PositionPreuve::Contestee ? $base / 2 : $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function versTableau(): array
    {
        return [
            'url' => $this->url,
            'source' => $this->nomSource,
            // Borné à 300 caractères : au-delà, on republierait l'article
            // plutôt que de le citer.
            'extrait' => mb_substr($this->extrait, 0, 300),
            'position' => $this->position->value,
            'fiabilite' => $this->fiabilite,
            'publiee_le' => $this->publieeLe?->toIso8601String(),
            'recuperee' => $this->recuperee,
        ];
    }
}
