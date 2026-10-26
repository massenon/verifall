<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * L'état d'un candidat vis-à-vis d'une session.
 *
 * La PRÉSENCE n'est pas un état ici, et c'est délibéré : elle se lit sur
 * `attended_at`. Deux sources pour le même fait finissent par se contredire, et
 * c'est le taux de présence affiché qui ment en premier.
 *
 * L'envoi de la convocation non plus : « Envoyée » / « À envoyer » se déduit de
 * `convocation_sent_at`. Un statut « convoqué » se serait désynchronisé le jour
 * du premier envoi échoué.
 */
enum StatutInscriptionExamen: string
{
    case Inscrit = 'registered';
    case Confirme = 'confirmed';
    case ReportDemande = 'deferral_requested';
    case Annule = 'cancelled';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.inscription_examen.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Inscrit => 'pastille p-info',
            self::Confirme => 'pastille p-bon',
            self::ReportDemande => 'pastille p-alerte',
            self::Annule => 'pastille p-neutre',
        };
    }

    /**
     * Ce candidat a-t-il le droit de composer ?
     *
     * « Report demandé » ne le prive de rien tant que la demande n'est pas
     * arbitrée : quelqu'un qui demande un report puis se présente quand même
     * doit pouvoir passer l'épreuve.
     */
    public function autoriseLaComposition(): bool
    {
        return $this !== self::Annule;
    }
}
