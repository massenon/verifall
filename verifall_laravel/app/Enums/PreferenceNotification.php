<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Préférences de notification du citoyen — bloc « 🔔 Notifications » du
 * prototype.
 *
 * Un enum et non des chaînes libres : ces clés sont écrites en base, lues par
 * les notificateurs et affichées dans l'interface. Une faute de frappe sur
 * l'une d'elles désactiverait silencieusement une notification.
 *
 * La lettre d'information n'en fait pas partie : c'est un consentement
 * marketing, tracé dans `consents` (voir ConsentType::Newsletter).
 */
enum PreferenceNotification: string
{
    case ResultatsVerification = 'verification_results';
    case RappelsApprentissage = 'learning_reminders';
    case ActiviteForum = 'forum_activity';
    case AlertesConnexion = 'login_alerts';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('notifications.preference.libelle.'.$this->value);
    }

    public function description(): string
    {
        return (string) __('notifications.preference.description.'.$this->value);
    }

    /**
     * Valeur par défaut, reprise des interrupteurs du prototype.
     *
     * Toutes activées : ce sont des notifications de SERVICE, attendues par
     * qui a demandé une vérification ou suivi un cours. Le seul réglage
     * facultatif — la lettre d'information — est un consentement, et il est
     * décoché par défaut.
     */
    public function defaut(): bool
    {
        return true;
    }

    /** Clé du bouton dans le prototype, conservée pour la conformité du DOM. */
    public function idBouton(): string
    {
        return match ($this) {
            self::ResultatsVerification => 'tg-nverif',
            self::RappelsApprentissage => 'tg-nlms',
            self::ActiviteForum => 'tg-nforum',
            self::AlertesConnexion => 'tg-alerts',
        };
    }
}
