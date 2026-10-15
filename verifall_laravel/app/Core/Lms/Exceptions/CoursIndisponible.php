<?php

declare(strict_types=1);

namespace App\Core\Lms\Exceptions;

use RuntimeException;

/**
 * Le cours visé n'est pas ouvert aux inscriptions.
 *
 * Exception et non `false` : c'est le cas d'une URL forgée ou d'une page restée
 * ouverte pendant qu'un mentor dépubliait son cours. Rendre `false` obligerait
 * chaque appelant à se souvenir de le tester.
 */
final class CoursIndisponible extends RuntimeException {}
