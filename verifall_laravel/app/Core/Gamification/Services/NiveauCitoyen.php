<?php

declare(strict_types=1);

namespace App\Core\Gamification\Services;

use App\Models\User;

/**
 * Niveau et progression d'XP — le « Achievement Center » du prototype.
 *
 * Le calcul est délibérément SIMPLE et affiché tel quel : mille XP par palier,
 * une barre, un nom. Une courbe savante donnerait des paliers imprévisibles, et
 * quelqu'un qui ne comprend pas pourquoi il progresse lentement cesse de
 * progresser.
 */
final class NiveauCitoyen
{
    /** Le prototype fixe des paliers réguliers de 1 000 XP. */
    public const XP_PAR_PALIER = 1000;

    /**
     * Noms des paliers, dans l'ordre.
     *
     * Au-delà du dernier, le nom cesse de changer mais le niveau continue de
     * monter : inventer des noms à l'infini produirait des titres absurdes, et
     * bloquer le niveau punirait ceux qui restent.
     *
     * @var list<string>
     */
    private const NOMS = [
        'Novice', 'Observateur', 'Éclaireur', 'Vérificateur',
        'Enquêteur', 'Sentinelle', 'Gardien',
    ];

    public function niveau(User $utilisateur): int
    {
        return intdiv(max(0, $utilisateur->xp), self::XP_PAR_PALIER) + 1;
    }

    public function nom(User $utilisateur): string
    {
        $index = min($this->niveau($utilisateur) - 1, count(self::NOMS) - 1);

        return self::NOMS[$index];
    }

    /** XP acquis DANS le palier courant. */
    public function xpDansLePalier(User $utilisateur): int
    {
        return max(0, $utilisateur->xp) % self::XP_PAR_PALIER;
    }

    public function xpRestant(User $utilisateur): int
    {
        return self::XP_PAR_PALIER - $this->xpDansLePalier($utilisateur);
    }

    /** Avancement dans le palier, en pourcentage. */
    public function pourcentage(User $utilisateur): int
    {
        return (int) round($this->xpDansLePalier($utilisateur) / self::XP_PAR_PALIER * 100);
    }
}
