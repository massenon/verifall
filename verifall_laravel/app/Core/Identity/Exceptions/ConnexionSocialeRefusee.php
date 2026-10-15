<?php

declare(strict_types=1);

namespace App\Core\Identity\Exceptions;

use RuntimeException;

/**
 * La connexion par un fournisseur d'identité n'a pas abouti.
 *
 * Le message est destiné à être MONTRÉ. Un « une erreur est survenue » laisse
 * quelqu'un devant un écran de connexion sans savoir s'il doit réessayer,
 * changer de méthode, ou renoncer — et il renonce.
 */
final class ConnexionSocialeRefusee extends RuntimeException
{
    public static function adresseAbsente(string $fournisseur): self
    {
        return new self(
            "{$fournisseur} ne nous a pas transmis votre adresse e-mail. "
            .'Créez un compte avec votre adresse, vous pourrez relier '
            ."{$fournisseur} ensuite depuis vos réglages."
        );
    }

    /**
     * Un compte existe déjà avec cette adresse, et le fournisseur ne garantit
     * pas l'avoir vérifiée.
     *
     * Rattacher malgré tout donnerait ce compte à quiconque parvient à faire
     * écrire cette adresse par le fournisseur.
     */
    public static function compteDejaExistant(string $adresse): self
    {
        return new self(
            "Un compte utilise déjà l'adresse {$adresse}. Connectez-vous avec "
            .'votre mot de passe, puis reliez ce service depuis vos réglages : '
            .'nous ne rattachons jamais un compte existant sans cette étape.'
        );
    }

    public static function compteDesactive(): self
    {
        return new self(
            'Ce compte est en cours de suppression. Annulez la suppression '
            .'depuis le lien reçu par courriel pour le réactiver.'
        );
    }

    public static function fournisseurIndisponible(string $fournisseur): self
    {
        return new self("La connexion via {$fournisseur} n'est pas disponible pour le moment.");
    }
}
