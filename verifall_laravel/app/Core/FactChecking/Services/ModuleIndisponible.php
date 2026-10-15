<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Enums\TypeContenu;
use RuntimeException;

/**
 * Ce type de contenu ne peut pas être analysé aujourd'hui.
 *
 * ## Pourquoi cette exception existe
 *
 * Vidéo et audio exigent une transcription — sans texte, il n'y a rien à
 * confronter à des sources. Aucun fournisseur de transcription n'est souscrit,
 * et `MoteurAnalyse::allumeParDefaut()` documente cette décision
 * d'exploitation depuis le 1er septembre 2026.
 *
 * L'écran de soumission le savait déjà : `choisirType()` refusait le clic et
 * les deux onglets s'affichaient grisés. Mais la règle vivait DANS L'ÉCRAN, et
 * deux chemins la contournaient :
 *
 *   · `Verifier::$type` n'était pas `#[Locked]` et `lancer()` ne revérifiait
 *     rien : une requête Livewire forgée posait `type = video` directement ;
 *   · `POST /api/v1/verifications` acceptait toute valeur de `TypeContenu`,
 *     sans aucun contrôle de disponibilité.
 *
 * Dans les deux cas les unités étaient débitées et l'analyse lancée sur un
 * fichier que nul moteur ne pouvait transcrire. La promesse « zéro débit, zéro
 * déception » n'était donc pas tenue côté serveur.
 *
 * La règle est désormais dans le SERVICE, seul point par lequel passent le web
 * et l'API — c'est la règle 1 du CLAUDE.md : la logique métier ne vit pas dans
 * un composant Livewire.
 */
final class ModuleIndisponible extends RuntimeException
{
    public static function pour(TypeContenu $type): self
    {
        return new self(sprintf(
            'L’analyse %s est en cours d’intégration. Aucune unité n’a été débitée.',
            mb_strtolower($type->libelle()),
        ));
    }
}
