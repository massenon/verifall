<?php

declare(strict_types=1);

namespace App\Core\Identity\Notifications;

use App\Enums\CategorieNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Un dossier d'identité attend un examen. »
 *
 * Adressée aux personnes qui détiennent `kyc.review`. `database` seulement :
 * comme la contre-expertise, cela s'adresse à une ÉQUIPE, et un courriel envoyé
 * à tout le monde ferait que personne ne se sent concerné.
 *
 * ## Le texte ne porte AUCUNE donnée de la pièce
 *
 * Ni numéro, ni type de document, ni même le nom de la personne. Une
 * notification finit dans une table, parfois dans un journal, parfois dans
 * l'aperçu d'un téléphone verrouillé posé sur une table. Elle dit qu'il y a du
 * travail — l'écran d'examen, lui, exige d'être connecté et habilité pour dire
 * de qui il s'agit.
 */
final class DossierKycDepose extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function texte(): string
    {
        return "Un dossier de vérification d'identité attend un examen.";
    }

    public function icone(): string
    {
        return 'badge';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Communaute;
    }

    public function lien(): string
    {
        return route('staff.kyc');
    }
}
