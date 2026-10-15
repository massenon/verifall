<?php

declare(strict_types=1);

namespace App\Core\Exams\Exceptions;

use RuntimeException;

/**
 * L'examen ou la session visée n'accepte pas ce qu'on lui demande.
 *
 * Exception et non `false` : c'est le cas d'une URL forgée, ou d'un onglet
 * resté ouvert pendant qu'un administrateur fermait la session. Rendre `false`
 * obligerait chaque appelant à se souvenir de le tester.
 */
final class ExamenIndisponible extends RuntimeException {}
