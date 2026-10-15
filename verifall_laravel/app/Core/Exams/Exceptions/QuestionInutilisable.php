<?php

declare(strict_types=1);

namespace App\Core\Exams\Exceptions;

use RuntimeException;

/**
 * La question ne peut pas être posée en l'état.
 *
 * Un QCM sans bonne réponse est corrigé zéro pour tout le monde, et personne ne
 * comprend pourquoi le taux de réussite de l'examen s'est effondré. Le refus se
 * fait à la publication, pas à la correction — trop tard.
 */
final class QuestionInutilisable extends RuntimeException {}
