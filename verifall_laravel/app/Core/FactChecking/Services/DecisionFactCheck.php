<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Enums\StatutVerification;
use App\Enums\VerdictFactCheck;

/**
 * Ce que le scoring a conclu — et à quel titre.
 *
 * Le titre compte autant que le verdict : « tranché », « contradictoire » et
 * « partiel » mènent au même champ mais à trois écrans différents, et à trois
 * traitements différents côté back-office.
 */
final readonly class DecisionFactCheck
{
    /**
     * @param  array<string, float>  $scores
     */
    private function __construct(
        public VerdictFactCheck $verdict,
        public int $confiance,
        public array $scores,
        public StatutVerification $statut,
        public bool $partielle,
        public ?string $reserve,
    ) {}

    /**
     * @param  array<string, float>  $scores
     */
    public static function tranchee(VerdictFactCheck $verdict, int $confiance, array $scores): self
    {
        return new self($verdict, $confiance, $scores, StatutVerification::Terminee, false, null);
    }

    /**
     * Les moteurs se contredisent : le rapport part en arbitrage humain et
     * affiche les DEUX thèses avec leurs sources.
     *
     * @param  array<string, float>  $scores
     */
    public static function contradictoire(VerdictFactCheck $verdict, int $confiance, array $scores): self
    {
        return new self(
            $verdict, $confiance, $scores, StatutVerification::RevueHumaine, false,
            'Les moteurs d\'analyse ne convergent pas : ce rapport est soumis à un arbitrage humain.',
        );
    }

    /**
     * Trop peu de moteurs ont répondu.
     */
    public static function partielle(int $moteursReussis): self
    {
        return new self(
            VerdictFactCheck::NonVerifiable, 0,
            [], StatutVerification::Terminee, true,
            $moteursReussis === 0
                ? 'Aucun moteur d\'analyse n\'a pu répondre. Vos unités vous sont rendues.'
                : 'Analyse partielle : un seul moteur a répondu. Vos unités vous sont rendues.',
        );
    }

    /** Les unités doivent-elles être rendues ? */
    public function ouvreDroitAuRemboursement(): bool
    {
        return $this->partielle;
    }
}
