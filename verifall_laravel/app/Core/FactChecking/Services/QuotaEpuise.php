<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use RuntimeException;

/**
 * Le fournisseur a refusé faute de quota (429) ou d'autorisation (403).
 *
 * Distinguée d'un échec ordinaire : un quota épuisé se recharge, et la clé doit
 * être mise au repos plutôt que réessayée en boucle. Un échec réseau, lui, vaut
 * la peine d'être retenté tout de suite avec la même clé.
 */
final class QuotaEpuise extends RuntimeException {}
