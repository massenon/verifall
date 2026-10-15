<?php

declare(strict_types=1);

namespace App\Core\Exams\Exceptions;

use RuntimeException;

/**
 * On ne corrige pas une copie déjà notée, ni une copie encore en composition.
 *
 * Rouvrir une note rendue changerait un fait daté ; noter une copie non rendue
 * évaluerait un travail que son auteur croit encore pouvoir modifier.
 */
final class CopieNonCorrigeable extends RuntimeException {}
