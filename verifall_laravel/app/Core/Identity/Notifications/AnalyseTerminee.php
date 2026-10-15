<?php

declare(strict_types=1);

namespace App\Core\Identity\Notifications;

use App\Enums\CategorieNotification;
use App\Models\Verification;

/**
 * « Votre analyse est prête. »
 *
 * La notification la plus attendue du produit : quelqu'un a soumis un contenu,
 * payé une unité, et attend un verdict. Sans elle, il n'a d'autre choix que de
 * revenir voir — ce que le prototype simulait avec des notifications stockées
 * dans le navigateur, donc invisibles d'un appareil à l'autre.
 *
 * Le texte porte le VERDICT, pas seulement l'achèvement. « Votre analyse est
 * terminée » oblige à ouvrir pour savoir ; « Verdict : FAUX » informe tout de
 * suite. Sur un téléphone, cette différence décide si l'information circule.
 */
final class AnalyseTerminee extends NotificationCitoyenne
{
    public function __construct(private readonly Verification $verification) {}

    public function texte(): string
    {
        /*
         | `Verification` ne porte pas de verdict — c'est son rapport qui en
         | porte un, par la relation `report()`. Lire `$verification->verdict`
         | renvoyait donc TOUJOURS `null` sur un attribut inexistant, qu'un
         | verdict existe ou non : cette notification n'a jamais annoncé le
         | moindre verdict depuis sa création, silencieusement — Eloquent ne
         | lève pas d'erreur sur un attribut absent.
         |
         | Trouvé en traçant les lecteurs de `AnalysisReport::verdict` pour le
         | contrat V-8 ; corrigé ici parce que c'est la même classe de défaut
         | que la décision vient de verrouiller — ne jamais confondre l'objet
         | qui porte le verdict avec celui qui le demande.
         */
        $verdict = $this->verification->report?->verdict;

        if ($verdict === null) {
            // Une analyse achevée sans verdict existe : « non vérifiable ».
            // On ne la présente pas comme un échec — c'en est une réponse.
            return 'Votre analyse est terminée.';
        }

        return sprintf('Verdict : %s — %s', $verdict->libelle(), $this->titreCourt());
    }

    public function icone(): string
    {
        return 'fact_check';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Verification;
    }

    public function lien(): ?string
    {
        return route('citizen.verification', $this->verification);
    }

    /** Le titre soumis, ramené à ce qui tient dans une ligne de notification. */
    private function titreCourt(): string
    {
        $titre = trim((string) ($this->verification->title ?? ''));

        if ($titre === '') {
            return 'votre vérification';
        }

        return mb_strlen($titre) > 60 ? mb_substr($titre, 0, 57).'…' : $titre;
    }
}
