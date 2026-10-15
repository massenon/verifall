<?php

declare(strict_types=1);

namespace App\Core\Marketing\Emails;

use App\Core\Identity\Services\PreferencesService;
use App\Enums\PreferenceNotification;
use App\Mail\CourrielDeGabarit;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Expédie un courriel de gabarit, sous ses DEUX garde-fous.
 *
 * ## Un interrupteur global n'est pas un consentement individuel
 *
 * L'écran d'administration allume ou éteint un envoi pour toute la plateforme.
 * C'est une décision éditoriale. Elle ne dit rien de ce qu'une personne
 * DONNÉE a demandé à recevoir — et `notification_preferences` le dit, depuis le
 * 20 août, avec quatre interrupteurs par compte.
 *
 * Les deux se cumulent, dans cet ordre : le global d'abord (moins cher, une
 * lecture en cache), le personnel ensuite. Confondre les deux ferait qu'un
 * réglage d'administration écrase un choix individuel — ce qui, sur un envoi
 * marketing, est exactement ce que le RGPD interdit.
 *
 * ## Certains envois n'ont AUCUNE préférence correspondante
 *
 * Une prime d'unités ou un badge ne relèvent d'aucun des quatre interrupteurs
 * existants. On passe alors `null`, et seul le garde global s'applique.
 * Inventer une préférence pour l'occasion créerait un cinquième interrupteur
 * qu'aucun écran n'affiche — un réglage que personne ne peut changer est pire
 * qu'un réglage absent.
 *
 * ## Toujours en FILE
 *
 * `queue()` et non `send()`. Ces envois partent depuis une requête : une leçon
 * terminée, un badge décroché, un paiement encaissé. Attendre un SMTP distant
 * ferait tourner la roue du navigateur pendant que quelqu'un vient de réussir
 * quelque chose.
 *
 * **Sur cPanel mutualisé, cela suppose le cron `queue:work`.** Sans lui rien ne
 * part, et rien ne le signale — c'est le même piège que l'inscription qui
 * s'arrête à l'étape 2.
 */
final class ExpediteurDeCourriels
{
    public function __construct(
        private readonly RenduDeGabarit $rendu,
        private readonly PreferencesService $preferences,
    ) {}

    /**
     * @param  array<string, string|int|null>  $variables
     * @param  PreferenceNotification|null  $preference  `null` quand aucune ne correspond
     * @return bool vrai si le courriel a été mis en file
     */
    public function envoyer(
        User $destinataire,
        string $cle,
        array $variables = [],
        ?string $lien = null,
        ?PreferenceNotification $preference = null,
    ): bool {
        if (! $this->rendu->estAllume($cle)) {
            return false;
        }

        if ($preference !== null && ! $this->preferences->estActive($destinataire, $preference)) {
            return false;
        }

        /*
         | Un compte anonymisé n'a plus d'adresse exploitable, et une adresse
         | vide ferait lever le transport au milieu d'un job. La garde est ici
         | plutôt que chez chaque appelant : c'est le genre d'oubli qu'on ne fait
         | qu'une fois, mais dans le service le plus fréquenté.
         */
        if (trim((string) $destinataire->email) === '') {
            return false;
        }

        Mail::to($destinataire->email)->queue(new CourrielDeGabarit($cle, $variables, $lien));

        return true;
    }
}
