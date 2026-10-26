<?php

declare(strict_types=1);

namespace App\Core\Marketing\Seo;

use RuntimeException;

/** Google n'a pas répondu comme attendu. Le message est lisible par l'équipe, jamais un secret. */
final class GoogleIndisponible extends RuntimeException {}
