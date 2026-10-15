<?php

declare(strict_types=1);

namespace App\Support\Hashing;

/**
 * Pseudonymisation des données identifiantes (RGPD).
 *
 * Employé pour les adresses IP, les e-mails servant de clé de recherche et les
 * codes à usage unique. Un HMAC salé par APP_KEY, et non un SHA-256 nu : sans
 * sel, un dictionnaire d'adresses e-mail courantes suffirait à inverser les
 * empreintes en quelques secondes.
 *
 * Conséquence assumée : une rotation d'APP_KEY invalide les correspondances.
 * C'est acceptable pour des données de corrélation, jamais pour des données
 * qu'il faut relire — celles-là passent par un chiffrement réversible.
 */
final class IdentityHasher
{
    public function hash(string $valeur): string
    {
        return hash_hmac('sha256', $this->normalise($valeur), $this->cle());
    }

    /**
     * Comparaison en temps constant.
     */
    public function equals(string $empreinte, string $valeur): bool
    {
        return hash_equals($empreinte, $this->hash($valeur));
    }

    /**
     * Casse et espaces neutralisés : « Rene@Verifall.com » et
     * « rene@verifall.com  » désignent le même compte et doivent donc produire
     * la même empreinte, sans quoi le verrouillage se contournerait par une
     * simple majuscule.
     */
    private function normalise(string $valeur): string
    {
        return mb_strtolower(trim($valeur));
    }

    private function cle(): string
    {
        return (string) config('app.key');
    }
}
