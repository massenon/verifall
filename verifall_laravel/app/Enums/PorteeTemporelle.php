<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ce qu'un claim dit du TEMPS — phase 3 du plan en 13 phases.
 *
 * Objectif de la phase, au mot près : « la temporalité devient une donnée du
 * domaine, pas une consigne de prompt ». Avant cette phase, `analyse.md`
 * demandait au MODÈLE de repérer lui-même des marqueurs (« actuellement »,
 * « en exercice »…) dans le texte qu'il lisait. Ça marche, et le prompt le
 * garde — mais ça dépend de la vigilance du modèle à chaque appel.
 *
 * Désormais, l'extraction (phase 2) classe CHAQUE claim une fois pour
 * toutes, le système en déduit une consigne précise et une fenêtre de
 * fraîcheur de recherche, et les deux sont injectées — jamais redevinées.
 */
enum PorteeTemporelle: string
{
    /**
     * Un état PRÉSENT, sensible au temps : qui occupe une fonction, un prix
     * en vigueur, une loi applicable. La mémoire d'un modèle est dangereuse
     * ici précisément parce qu'elle semble à jour sans l'être.
     */
    case Present = 'present';

    /**
     * Un fait HISTORIQUE, réputé stable : un événement daté et clos, une
     * constante. La mémoire d'un modèle y est admise — rien ne s'y périme.
     */
    case Historique = 'historique';

    /**
     * Le claim porte sa propre date ou période (« en 2020 », « le 3 mars »).
     * C'est cette date, et non l'instant de la vérification, qui ancre ce
     * qu'il faut chercher — une fraîcheur de recherche serait hors sujet.
     */
    case DateExplicite = 'date_explicite';

    /**
     * Le claim se date par rapport au MOMENT DE LA SOUMISSION (« hier »,
     * « cette semaine », « cette année ») : le système doit résoudre la date
     * réelle depuis {{DATE}}, le modèle ne le peut pas de lui-même.
     */
    case Relatif = 'relatif';

    public function libelle(): string
    {
        return (string) __('analyse.portee_temporelle.libelle.'.$this->value);
    }

    /**
     * La fenêtre de fraîcheur de recherche adaptée à CETTE portée.
     *
     * `null` : aucun filtre de fraîcheur n'a de sens — une date explicite
     * ancre déjà la recherche mieux qu'une fenêtre glissante ne le ferait.
     *
     * Réglable sans déploiement : {@see config('factcheck.runtime.fraicheur_par_portee')}
     * surclasse ce défaut, exactement comme `fraicheur_recherche` le faisait
     * déjà de façon globale avant cette phase.
     */
    public function fraicheurRecherche(): ?string
    {
        /** @var array<string, string|null> $carte */
        $carte = config('factcheck.runtime.fraicheur_par_portee', []);

        if (array_key_exists($this->value, $carte)) {
            return $carte[$this->value];
        }

        return match ($this) {
            self::Present => 'month',
            self::Relatif => 'week',
            self::DateExplicite => null,
            self::Historique => 'year',
        };
    }

    /**
     * Le fragment de consigne injecté dans le prompt — {{PORTEE_TEMPORELLE}}.
     *
     * Dit en clair ce que le modèle devait auparavant déduire seul : si la
     * mémoire est admise, et ce qu'il doit chercher à la place sinon.
     */
    public function consigne(): string
    {
        return (string) __('analyse.portee_temporelle.consigne.'.$this->value);
    }

    /** Cette portée autorise-t-elle un verdict rendu de mémoire, sans recherche ? */
    public function memoireAutorisee(): bool
    {
        return $this === self::Historique;
    }
}
