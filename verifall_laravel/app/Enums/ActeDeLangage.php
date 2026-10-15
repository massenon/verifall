<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ce qu'une phrase EST, avant même de se demander si elle est vraie.
 *
 * Décision verrouillée par NEWINSTRUCTION23/24 : une question ne doit jamais
 * être silencieusement convertie en affirmation. « Qui dirige X ? » n'est ni
 * vrai ni faux — c'est une demande de réponse, et elle garde sa nature
 * jusqu'au bout du pipeline.
 *
 * Cinq cas, pas davantage :
 *
 *   · `Affirmation` est seule à pouvoir recevoir un {@see VerdictFactCheck}.
 *   · `Question` produit un claim implicite et une réponse vérifiée — jamais
 *     un verdict sur le contenu soumis (contrat V-8).
 *   · `Opinion` et `Prediction` sont extraites et montrées, mais ne sont
 *     soumises à aucun moteur : une opinion n'est ni vraie ni fausse, une
 *     prédiction ne se vérifie pas avant son échéance.
 *   · `NonFactuel` est un ÉTAT D'INGESTION, pas un verdict (décision V-2) —
 *     « Quelle belle journée ! » ne porte aucune proposition vérifiable.
 */
enum ActeDeLangage: string
{
    case Affirmation = 'affirmation';
    case Question = 'question';
    case Opinion = 'opinion';
    case Prediction = 'prediction';
    case NonFactuel = 'non_factuel';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('analyse.acte_de_langage.'.$this->value);
    }

    /**
     * Ce claim peut-il un jour porter un {@see VerdictFactCheck} ?
     *
     * Seule une affirmation le peut. Une question en dérive un — le claim
     * IMPLICITE qu'elle porte, extrait séparément — mais la question
     * elle-même ne tranche jamais.
     */
    public function peutPorterUnVerdict(): bool
    {
        return $this === self::Affirmation;
    }
}
