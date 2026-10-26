<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * L'état d'une copie, de la composition à la note rendue.
 *
 * Les libellés suivent `correction-examens.html`, qui parle du point de vue du
 * CORRECTEUR : « En cours » y désigne une correction commencée, pas un candidat
 * en train de composer. D'où le cinquième état, « En composition », que la file
 * de correction ne voit jamais — une copie non rendue n'a rien à y faire.
 *
 * La RÉUSSITE n'est pas un état : elle se lit sur `passed`, une fois la copie
 * corrigée. La mettre ici obligerait à écrire deux fois le même fait.
 */
enum StatutCopieExamen: string
{
    case EnComposition = 'in_progress';
    case EnAttente = 'pending';
    case EnCorrection = 'grading';
    case Corrigee = 'graded';
    case Abandonnee = 'abandoned';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.copie_examen.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::EnComposition => 'pastille p-info',
            self::EnAttente => 'pastille p-alerte',
            self::EnCorrection => 'pastille p-info',
            self::Corrigee => 'pastille p-bon',
            self::Abandonnee => 'pastille p-neutre',
        };
    }

    /** Les deux états que la file de correction affiche. */
    public function dansLaFileDeCorrection(): bool
    {
        return $this === self::EnAttente || $this === self::EnCorrection;
    }

    /**
     * Compte-t-elle dans le taux de réussite de l'examen ?
     *
     * Une copie abandonnée ou encore en composition n'est pas un échec : la
     * compter comme tel ferait chuter un taux affiché en tête d'écran sans que
     * personne ne comprenne pourquoi.
     */
    public function compteDansLesStatistiques(): bool
    {
        return $this === self::Corrigee;
    }

    /** Le sort de la copie est-il scellé ? On ne la re-corrige plus. */
    public function estFigee(): bool
    {
        return $this === self::Corrigee || $this === self::Abandonnee;
    }
}
