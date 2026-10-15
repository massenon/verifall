<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pourquoi le compteur d'XP a bougé.
 *
 * Un enum et non une chaîne libre, pour la même raison que
 * {@see MotifMouvementUnite} : ces motifs sont écrits dans un journal qu'on
 * relira pour expliquer un total à quelqu'un qui le conteste, et une variante
 * orthographique y créerait une catégorie fantôme.
 *
 * Les quatre premiers correspondent exactement aux quatre endroits qui
 * incrémentaient `users.xp` à la main avant ce journal. `AjustementAdmin` n'a
 * pas encore d'écran : il est déclaré parce que c'est le seul motif qui peut
 * être NÉGATIF, et que la colonne a été dimensionnée pour lui.
 */
enum MotifGainXp: string
{
    /** Un quiz de cours réussi. Versé une fois, à la première réussite. */
    case Quiz = 'quiz';

    /** Le quiz d'accueil, à l'inscription. Une seule fois par compte. */
    case QuizDAccueil = 'welcome_quiz';

    /** Un badge décroché. */
    case Badge = 'badge';

    /** La prime de fin de cours, à 100 % d'avancement. */
    case Cours = 'course';

    /** Correction manuelle. Le seul motif dont le delta peut être négatif. */
    case AjustementAdmin = 'admin_adjustment';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR. */
    public function libelle(): string
    {
        return (string) __('gamification.motif_xp.'.$this->value);
    }

    /**
     * Le libellé dans la langue de REPLI, quelle que soit celle de l'écran.
     *
     * C'est lui qu'on écrit en base : `xp_logs.label` est une colonne persistée
     * et relue longtemps après. Y déposer la langue du navigateur du jour
     * donnerait un journal panaché — mêmes opérations, libellés en deux langues
     * selon l'humeur —, et la recherche cesserait de trouver la moitié des
     * lignes. Ce qui identifie une ligne reste `reason` ; le libellé n'est
     * qu'une commodité de relecture.
     */
    public function libelleCanonique(): string
    {
        return (string) __('gamification.motif_xp.'.$this->value, [], config('app.fallback_locale'));
    }
}
