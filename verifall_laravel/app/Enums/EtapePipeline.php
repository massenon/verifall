<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Étapes traversées par une vérification — chronologie du prototype.
 *
 * Elles sont posées par l'orchestrateur AU MOMENT où il les franchit. Le
 * prototype les fait défiler sur des minuteurs aléatoires ; ici, si l'analyse
 * cale sur les moteurs, l'affichage cale avec elle. Une barre qui avance toute
 * seule pendant qu'un service est en panne ment à qui la regarde — et fait
 * attendre pour rien.
 */
enum EtapePipeline: int
{
    case Reception = 1;
    case Preparation = 2;
    case Sources = 3;
    case AnalyseIA = 4;
    case Consolidation = 5;
    case Verdict = 6;

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('analyse.pipeline.libelle.'.$this->value);
    }

    /**
     * Le libellé dans la seule langue du repli, pour ce qui part EN BASE.
     *
     * La chronologie d'un rapport est écrite par le worker au moment où
     * l'étape est franchie, puis relue des mois plus tard — et exposée par
     * l'API. Y inscrire `libelle()` y figeait la langue de l'exécution : deux
     * analyses voisines pouvaient porter des étapes libellées différemment
     * selon ce qui traînait en session au moment du traitement.
     *
     * Le tort restait limité, la clé technique `etape` étant stockée à côté.
     * Mais la même règle vaut ici que pour le journal comptable : ce qui est
     * écrit ne se traduit pas, ce qui est affiché si.
     */
    public function libelleCanonique(): string
    {
        return (string) __(
            'analyse.pipeline.libelle.'.$this->value,
            [],
            (string) config('app.fallback_locale', 'fr'),
        );
    }

    public function description(): string
    {
        return (string) __('analyse.pipeline.description.'.$this->value);
    }

    /** Pourcentage affiché quand cette étape est atteinte. */
    public function pourcentage(): int
    {
        return (int) round($this->value / count(self::cases()) * 100);
    }
}
