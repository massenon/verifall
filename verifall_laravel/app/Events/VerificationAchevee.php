<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Verification;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une vérification vient d'aboutir.
 *
 * Premier événement du projet, et il est là pour une raison précise : la
 * gamification et le parrainage doivent réagir à une analyse terminée, mais le
 * fact-checking n'a aucune raison de connaître les badges. Sans événement,
 * l'orchestrateur — le cœur du produit — dépendrait des récompenses, et chaque
 * nouveauté marketing viendrait le modifier.
 *
 * Émis seulement pour les analyses d'un COMPTE : une vérification d'invité n'a
 * personne à récompenser.
 */
final class VerificationAchevee
{
    use Dispatchable;

    public function __construct(public readonly Verification $verification) {}
}
