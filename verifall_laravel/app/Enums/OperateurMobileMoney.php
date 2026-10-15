<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Opérateurs Mobile Money proposés (prototype : « MTN MoMo, Moov Money,
 * Celtiis Cash »).
 *
 * Le préfixe sert à DEVINER l'opérateur, pas à le vérifier : les plages de
 * numéros béninoises évoluent et la portabilité les brouille. Un mauvais choix
 * doit donc rester corrigible à la main plutôt que bloquer un paiement.
 */
enum OperateurMobileMoney: string
{
    case Mtn = 'mtn';
    case Moov = 'moov';
    case Celtiis = 'celtiis';

    public function libelle(): string
    {
        return match ($this) {
            self::Mtn => 'MTN MoMo',
            self::Moov => 'Moov Money',
            self::Celtiis => 'Celtiis Cash',
        };
    }

    /**
     * Code USSD affiché pendant l'attente — c'est ce que la personne doit
     * composer si la notification n'arrive pas sur son téléphone.
     */
    public function ussd(): string
    {
        return match ($this) {
            self::Mtn => '*880#',
            self::Moov => '*855#',
            self::Celtiis => '*800#',
        };
    }

    /**
     * @return list<string>
     */
    public function prefixes(): array
    {
        return match ($this) {
            self::Mtn => ['96', '97', '61', '62', '66', '67', '69'],
            self::Moov => ['94', '95', '98', '99', '60', '63', '64', '65', '68'],
            self::Celtiis => ['41', '42', '43', '44', '45', '46'],
        };
    }

    /**
     * Devine l'opérateur d'après le numéro national à 8 chiffres.
     */
    public static function depuisNumero(string $numero): ?self
    {
        $chiffres = preg_replace('/\D+/', '', $numero) ?? '';

        // Un numéro saisi avec l'indicatif +229 : on retient les 8 derniers.
        $national = mb_substr($chiffres, -8);
        $prefixe = mb_substr($national, 0, 2);

        foreach (self::cases() as $operateur) {
            if (in_array($prefixe, $operateur->prefixes(), true)) {
                return $operateur;
            }
        }

        return null;
    }
}
