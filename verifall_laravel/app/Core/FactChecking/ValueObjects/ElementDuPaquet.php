<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use Illuminate\Support\Carbon;

/**
 * Une preuve RÉELLEMENT récupérée, identifiée par un jeton opaque — contrat V-10.
 *
 * `id` (`SRC_001`, `SRC_002`…) est ce qu'un modèle peut citer. Jamais l'URL :
 * c'est tout le sens du contrat « SOURCE_ID → URL, jamais LLM → URL ». La
 * résolution id → URL appartient au backend seul, via
 * {@see PaquetDePreuves::resoudre()}.
 */
final readonly class ElementDuPaquet
{
    public function __construct(
        public string $id,
        public string $urlFinale,
        public ?string $urlCanonique,
        public ?string $editeur,
        public string $titre,
        public string $extrait,
        public ?Carbon $publieeLe,
        public int $fiabilite,
    ) {}

    /**
     * Le fragment à injecter dans le prompt pour CET élément — jamais l'URL.
     */
    public function pourPrompt(): string
    {
        return sprintf(
            "[%s] %s%s\n%s",
            $this->id,
            $this->editeur ?? 'Source non identifiée',
            $this->publieeLe !== null ? ' — '.$this->publieeLe->toDateString() : '',
            mb_substr($this->extrait, 0, 300),
        );
    }
}
