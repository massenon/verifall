<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use RuntimeException;

/**
 * Le quota journalier de vérifications multimédia est atteint.
 *
 * Levée par {@see QuotaMediaService} quand un compte gratuit ou un invité
 * dépasse sa limite de vérifications d'images, d'audios ou de vidéos.
 *
 * Distinct de {@see ModuleIndisponible} : le module est ouvert, c'est le compte
 * qui a épuisé son enveloppe du jour.
 */
final class QuotaMediaAtteint extends RuntimeException
{
    private function __construct(string $message, private readonly int $limite)
    {
        parent::__construct($message);
    }

    public static function pour(int $limite): self
    {
        return new self(
            sprintf(
                'Vous avez atteint votre limite de %d vérification%s multimédia aujourd\'hui. '
                .'Revenez demain ou passez à un compte premium.',
                $limite,
                $limite > 1 ? 's' : '',
            ),
            $limite,
        );
    }

    public function limite(): int
    {
        return $this->limite;
    }

    public function messageUtilisateur(): string
    {
        return $this->getMessage();
    }
}
