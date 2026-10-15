<?php

declare(strict_types=1);

namespace App\Core\Exams\Exceptions;

use RuntimeException;

/**
 * Personne ne compose sans être inscrit à la session.
 *
 * C'est la différence de fond avec un quiz : un quiz s'ouvre depuis le cours,
 * un examen se passe sur convocation. Sans ce contrôle, l'enrôlement ne servait
 * qu'à décorer un écran.
 */
final class CandidatNonInscrit extends RuntimeException {}
