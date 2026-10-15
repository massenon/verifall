<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use App\Enums\ActeDeLangage;
use App\Enums\PorteeTemporelle;

/**
 * Un claim tel que l'extraction le rend — avant toute persistance.
 *
 * Volontairement pauvre, comme {@see SacDePreuves} : pas de `verification_id`,
 * pas d'identifiant. Juste ce que l'extraction peut établir à partir du
 * contenu soumis.
 */
final readonly class AffirmationExtraite
{
    public function __construct(
        public string $texte,
        public ActeDeLangage $acteDeLangage,
        /** L'extrait d'où le claim est tiré, quand il diffère du texte normalisé. */
        public ?string $texteOriginal = null,
        /**
         * Phase 3 — `null` quand l'extraction n'a pas pu classer ce claim
         * (ce qui ne doit pas arriver pour une Affirmation, et n'a aucun
         * sens pour les autres actes de langage).
         */
        public ?PorteeTemporelle $porteeTemporelle = null,
    ) {}

    /** Une affirmation isolée — ce que l'extraction rend quand elle est désactivée ou en échec. */
    public static function affirmationUnique(string $texte): self
    {
        // Repli PRUDENT, pas optimiste : en l'absence de classification
        // réelle, traiter le claim comme sensible au temps coûte une
        // recherche de plus ; le traiter comme stable risquerait une
        // mémoire périmée rendue avec l'autorité d'une vérification.
        return new self($texte, ActeDeLangage::Affirmation, porteeTemporelle: PorteeTemporelle::Present);
    }
}
