<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use App\Core\FactChecking\Services\CollecteurDePreuves;

/**
 * Le paquet de preuves transmis à un modèle — contrat V-10, phase 6.
 *
 * Ce qu'il remplace : jusqu'ici, un modèle rendait lui-même ses « sources »,
 * URL comprise — jamais éprouvées, parfois inventées. Désormais, le BACKEND
 * constitue ce paquet depuis ce qu'il a réellement récupéré
 * ({@see CollecteurDePreuves}), et le modèle
 * ne peut plus CITER qu'un identifiant qui y figure déjà. Un identifiant
 * absent du paquet est ignoré — jamais traité comme une URL à deviner.
 */
final class PaquetDePreuves
{
    /**
     * Douze au plus — le plafond posé au §5 du corpus d'origine. Au-delà,
     * le budget de jetons du prompt se dégrade, et le modèle perd le fil de
     * ce qu'il a sous les yeux.
     */
    public const MAX_ELEMENTS = 12;

    /**
     * @param  list<ElementDuPaquet>  $elements
     */
    public function __construct(private readonly array $elements) {}

    /**
     * @param  list<ElementDuPaquet>  $elements
     */
    public static function depuis(array $elements): self
    {
        return new self(array_slice($elements, 0, self::MAX_ELEMENTS));
    }

    public static function vide(): self
    {
        return new self([]);
    }

    /**
     * @return list<ElementDuPaquet>
     */
    public function elements(): array
    {
        return $this->elements;
    }

    public function estVide(): bool
    {
        return $this->elements === [];
    }

    public function compte(): int
    {
        return count($this->elements);
    }

    /**
     * La SEULE résolution id → élément qui existe. Un modèle qui cite
     * `SRC_999` hors de ce paquet reçoit `null` — jamais une URL devinée.
     */
    public function resoudre(string $id): ?ElementDuPaquet
    {
        foreach ($this->elements as $element) {
            if ($element->id === $id) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Le bloc à injecter dans le prompt sous {{PAQUET_DE_PREUVES}}.
     */
    public function pourPrompt(): string
    {
        if ($this->estVide()) {
            return "Aucune preuve n'a pu être récupérée. Tu ne peux citer AUCUN identifiant : si tu ne peux pas trancher sans preuve, le verdict est NON_VERIFIABLE.";
        }

        return implode("\n\n", array_map(
            static fn (ElementDuPaquet $e): string => $e->pourPrompt(),
            $this->elements,
        ));
    }
}
