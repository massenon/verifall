<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use App\Enums\PorteeTemporelle;
use Illuminate\Support\Carbon;

/**
 * Ce que le système sait du TEMPS d'un claim — phase 3.
 *
 * À ne pas confondre avec {@see SacDePreuves::$contexte} : celui-ci est le
 * texte libre que la personne a saisi dans le formulaire (« vu sur WhatsApp
 * hier »), une donnée non fiable comme le reste du contenu soumis. Ceci est
 * une classification du SYSTÈME, calculée une fois à l'extraction et jamais
 * redevinée par le modèle qui analyse.
 */
final readonly class ContexteDuClaim
{
    public function __construct(
        public PorteeTemporelle $portee,
        /** La date de référence du serveur au moment de l'analyse — jamais celle du modèle. */
        public Carbon $dateReference,
    ) {}

    /**
     * Le bloc à injecter tel quel dans le prompt, sous {{PORTEE_TEMPORELLE}}.
     */
    public function consigne(): string
    {
        return str_replace(
            '{{DATE}}',
            $this->dateReference->locale('fr')->isoFormat('D MMMM YYYY'),
            $this->portee->consigne(),
        );
    }
}
