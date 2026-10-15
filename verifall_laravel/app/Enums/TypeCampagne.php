<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Comment une campagne se présente.
 *
 * La distinction n'est pas décorative : une fenêtre INTERROMPT la lecture, un
 * bandeau l'ACCOMPAGNE. Le premier se paie en attention, le second non — et
 * une plateforme dont le métier est la confiance ne peut pas se permettre
 * d'être perçue comme intrusive.
 */
enum TypeCampagne: string
{
    case Fenetre = 'popup';
    case Bandeau = 'banner';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('types.campagne.'.$this->value);
    }
}
