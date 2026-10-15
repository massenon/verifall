<?php

declare(strict_types=1);

namespace App\Enums;

/** D'où vient un message de la boîte de réception. */
enum NatureMessageContact: string
{
    case Contact = 'contact';
    case Don = 'don';

    public function libelle(): string
    {
        return match ($this) {
            self::Contact => 'Message',
            self::Don => 'Soutien',
        };
    }

    public function cleBadge(): string
    {
        return match ($this) {
            self::Contact => 'info',
            // Une demande de soutien se distingue au premier coup d'œil dans la
            // file : c'est le message dont le délai de réponse coûte le plus.
            self::Don => 'publie',
        };
    }
}
