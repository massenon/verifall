<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les trois onglets de la cloche, tels que le prototype les nomme.
 *
 * « Tout » n'en est pas une : c'est l'absence de filtre. L'ajouter comme
 * quatrième cas permettrait d'enregistrer une notification catégorisée
 * « tout », qui n'apparaîtrait alors dans aucun onglet thématique.
 */
enum CategorieNotification: string
{
    case Verification = 'verif';
    case Apprentissage = 'lms';
    /*
     | AJOUT au prototype, consigné. La cloche y porte trois onglets — Tout,
     | Vérifications, Apprentissage — et rien n'y accueillait une mention du
     | forum.
     |
     | La ranger sous « Vérifications » l'aurait noyée parmi les rapports
     | d'analyse ; la laisser sans catégorie l'aurait fait disparaître de tous
     | les onglets thématiques, ce que la classe de base existe précisément
     | pour empêcher.
     */
    case Communaute = 'community';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('notifications.categorie.'.$this->value);
    }
}
