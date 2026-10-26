<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * L'état d'une question dans la banque.
 *
 * « À réviser » est un vrai état, pas un commentaire : `questions.html` en fait
 * un indicateur de tête (« 14 à réviser · signalées ambiguës »). Une question
 * dont le taux de réussite s'effondre est probablement mal écrite, pas
 * seulement difficile — la marquer la retire des tirages sans l'effacer, et
 * sans fausser les copies déjà rendues.
 */
enum StatutQuestion: string
{
    case Brouillon = 'draft';
    case Publiee = 'published';
    case AReviser = 'to_review';
    case Archivee = 'archived';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.question.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Brouillon => 'pastille p-neutre',
            self::Publiee => 'pastille p-bon',
            self::AReviser => 'pastille p-alerte',
            self::Archivee => 'pastille p-neutre',
        };
    }

    /** Peut-elle être posée dans un examen ? */
    public function utilisable(): bool
    {
        return $this === self::Publiee;
    }
}
