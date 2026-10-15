<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une copie vient d'être notée au-dessus du seuil.
 *
 * Le domaine Examens ne connaît ni les badges, ni les certificats, ni l'XP : il
 * annonce le fait, à charge pour l'aval d'y réagir. Sans cet événement, publier
 * un examen aurait fini par dépendre de la gamification, et chaque nouveauté
 * marketing serait venue modifier la correction.
 *
 * Émis à la FINALISATION de la correction, jamais à la soumission : une copie
 * contenant une réponse libre n'est réussie qu'une fois lue par un mentor.
 */
final class ExamenReussi
{
    use Dispatchable;

    public function __construct(public readonly ExamAttempt $copie) {}
}
