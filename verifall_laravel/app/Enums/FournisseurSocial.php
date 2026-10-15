<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les fournisseurs d'identité acceptés.
 *
 * Liste FERMÉE : le nom du fournisseur arrive par l'URL, et sans énumération un
 * `{provider}` forgé irait tenter d'instancier n'importe quel pilote de
 * Socialite — au mieux une erreur 500, au pire une redirection vers un site
 * choisi par l'attaquant.
 */
enum FournisseurSocial: string
{
    case Google = 'google';
    case Facebook = 'facebook';

    public function libelle(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
        };
    }

    /**
     * Ce fournisseur atteste-t-il qu'il a VÉRIFIÉ l'adresse ?
     *
     * La distinction commande tout le reste. Rattacher un compte existant à une
     * identité sociale sur la seule foi d'une adresse revient à donner ce compte
     * à quiconque parvient à faire écrire cette adresse par le fournisseur. Si
     * le fournisseur ne garantit pas qu'il a vérifié l'adresse, la garantie
     * n'existe pas.
     *
     * Google renvoie `email_verified` et le respecte. Facebook ne fournit rien
     * d'équivalent : on ne rattache donc jamais un compte existant via
     * Facebook.
     */
    public function attesteLAdresse(): bool
    {
        return $this === self::Google;
    }

    /**
     * Est-il utilisable ?
     *
     * Sans identifiants dans `.env`, le bouton ne doit pas s'afficher et la
     * route doit refuser. Proposer une connexion qui se solde par une erreur du
     * fournisseur est pire que ne rien proposer.
     */
    public function estConfigure(): bool
    {
        return filled(config('services.'.$this->value.'.client_id'))
            && filled(config('services.'.$this->value.'.client_secret'));
    }

    /**
     * Ceux qui sont réellement utilisables, dans l'ordre d'affichage.
     *
     * @return list<self>
     */
    public static function disponibles(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $fournisseur): bool => $fournisseur->estConfigure(),
        ));
    }
}
