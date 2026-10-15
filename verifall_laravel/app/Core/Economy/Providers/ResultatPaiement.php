<?php

declare(strict_types=1);

namespace App\Core\Economy\Providers;

use App\Enums\StatutPaiement;

/**
 * Réponse d'un opérateur sur l'état d'une demande.
 */
final readonly class ResultatPaiement
{
    private function __construct(
        public StatutPaiement $statut,
        public ?string $reference = null,
        public ?string $motif = null,
    ) {}

    public static function reussi(string $reference): self
    {
        return new self(StatutPaiement::Reussi, $reference);
    }

    public static function echoue(string $motif, ?string $reference = null): self
    {
        return new self(StatutPaiement::Echoue, $reference, $motif);
    }

    /**
     * L'opérateur n'a pas encore tranché.
     *
     * Distinct d'un échec, et c'est essentiel : traiter une absence de réponse
     * comme un refus annulerait des paiements qui aboutissent une minute plus
     * tard, après que la personne a validé sur son téléphone.
     */
    public static function enAttente(): self
    {
        return new self(StatutPaiement::EnAttente);
    }
}
