<?php

declare(strict_types=1);

namespace App\Core\Exams\Exceptions;

use RuntimeException;

/**
 * Le candidat a déjà consommé toutes ses tentatives, rattrapage compris.
 *
 * Le contrôle vit dans le service et non dans l'écran : l'absence de bouton
 * n'est pas une protection, et une requête forgée doit se heurter au même mur.
 */
final class TentativesEpuisees extends RuntimeException {}
