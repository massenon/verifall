<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Usage d'un code à usage unique.
 *
 * Cloisonner les usages empêche qu'un code émis pour l'inscription serve à
 * réinitialiser un mot de passe.
 */
enum OtpPurpose: string
{
    case Registration = 'registration';
    case PasswordReset = 'password_reset';
    case LoginChallenge = 'login_challenge';

    /*
     | La suppression de compte a SON usage : un code obtenu pour se connecter
     | ne doit pas pouvoir effacer un compte. C'est tout l'objet du
     | cloisonnement.
     */
    case SuppressionCompte = 'account_deletion';

    public function objetCourriel(): string
    {
        return match ($this) {
            self::Registration => "Votre code de vérification Vérif'All",
            self::PasswordReset => 'Réinitialisation de votre mot de passe',
            self::LoginChallenge => 'Code de connexion',
            self::SuppressionCompte => 'Confirmez la suppression de votre compte',
        };
    }
}
