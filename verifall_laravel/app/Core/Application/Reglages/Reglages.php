<?php

declare(strict_types=1);

namespace App\Core\Application\Reglages;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Lit et écrit les réglages de la plateforme.
 *
 * ## Les trois garanties de ce service
 *
 * **1. Un secret est chiffré au repos.** Un mot de passe SMTP en clair dans une
 * table, c'est un mot de passe SMTP dans chaque sauvegarde, chaque export, et
 * chaque copie de base envoyée à un prestataire. Le chiffrement suit la clé de
 * l'application, qui elle ne vit pas en base.
 *
 * **2. Un secret ne ressort JAMAIS vers l'affichage.** `pourAffichage()` rend
 * une chaîne vide à la place. Un formulaire qui pré-remplit le mot de passe
 * SMTP l'expédie au navigateur à chaque affichage de page — le champ est masqué
 * à l'écran, la valeur est dans la source.
 *
 * **3. Une valeur vide sur un secret CONSERVE l'ancienne.** Conséquence directe
 * du point 2 : puisque le formulaire arrive vide, l'enregistrer effacerait la
 * clé à chaque passage. Le champ vide veut dire « je n'y touche pas ».
 *
 * ## Sur le cache
 *
 * Les réglages sont lus à chaque requête — nom du site, logo, drapeaux. Un
 * aller en base par lecture serait payé partout. Le cache est purgé à
 * l'écriture, jamais expiré par un délai : un mot de passe SMTP corrigé doit
 * prendre effet tout de suite, pas dans cinq minutes.
 */
final class Reglages
{
    private const CLE_CACHE = 'reglages.toutes';

    /**
     * La valeur brute d'un réglage, déchiffrée si nécessaire.
     *
     * C'est la méthode qu'emploie le CODE — l'envoi d'un courriel, l'appel à un
     * moteur. Elle rend le secret en clair, parce que c'est ce dont un client
     * SMTP a besoin.
     */
    public function get(string $cle, ?string $defaut = null): ?string
    {
        $valeurs = $this->toutes();

        if (! array_key_exists($cle, $valeurs)) {
            return $defaut ?? CatalogueDesReglages::trouver($cle)?->defaut;
        }

        $valeur = $valeurs[$cle];

        if ($valeur === null || $valeur === '') {
            return $defaut ?? CatalogueDesReglages::trouver($cle)?->defaut;
        }

        return $valeur;
    }

    /** Un réglage booléen. */
    public function actif(string $cle): bool
    {
        return in_array($this->get($cle), ['1', 'true', 'on', 'yes'], true);
    }

    /** Un réglage numérique. */
    public function nombre(string $cle, int $defaut = 0): int
    {
        $valeur = $this->get($cle);

        return $valeur === null || ! is_numeric($valeur) ? $defaut : (int) $valeur;
    }

    /**
     * La valeur telle qu'on peut la MONTRER — donc vide si elle est secrète.
     *
     * C'est la méthode qu'emploie le FORMULAIRE. La distinction avec `get()`
     * n'est pas cosmétique : c'est elle qui empêche une clé Stripe de partir
     * dans le HTML à chaque affichage de l'écran des paramètres.
     */
    public function pourAffichage(string $cle): string
    {
        $definition = CatalogueDesReglages::trouver($cle);

        if ($definition?->secret === true) {
            return '';
        }

        return (string) ($this->get($cle) ?? '');
    }

    /** Un secret a-t-il déjà une valeur enregistrée ? */
    public function secretRenseigne(string $cle): bool
    {
        $valeurs = $this->toutes();

        return isset($valeurs[$cle]) && $valeurs[$cle] !== null && $valeurs[$cle] !== '';
    }

    /**
     * Écrit un réglage.
     *
     * Renvoie `false` quand rien n'a été écrit — cas d'un secret laissé vide,
     * qui conserve sa valeur. L'appelant s'en sert pour ne pas journaliser une
     * modification qui n'a pas eu lieu.
     */
    public function set(string $cle, ?string $valeur, ?User $acteur = null): bool
    {
        $definition = CatalogueDesReglages::trouver($cle);

        if ($definition === null) {
            /*
             | Une clé hors catalogue est refusée. Sans ce garde-fou, un champ
             | forgé dans le navigateur écrirait n'importe quoi dans la table —
             | et les réglages sont exactement l'endroit où « n'importe quoi »
             | devient une clé d'API ou un drapeau de sécurité.
             */
            Log::warning('Réglage inconnu refusé : '.$cle);

            return false;
        }

        /*
         | Un secret laissé vide CONSERVE l'ancienne valeur.
         |
         | Conséquence directe du non-renvoi au navigateur : le formulaire
         | arrive toujours vide sur ces champs. L'enregistrer tel quel
         | effacerait la clé Stripe à chaque passage dans l'écran.
         */
        if ($definition->secret && ($valeur === null || $valeur === '')) {
            return false;
        }

        $aEcrire = $definition->secret && $valeur !== null && $valeur !== ''
            ? Crypt::encryptString($valeur)
            : $valeur;

        Setting::updateOrCreate(
            ['key' => $cle],
            [
                'value' => $aEcrire,
                'group' => $definition->groupe,
                'is_secret' => $definition->secret,
                'updated_by' => $acteur?->id,
            ],
        );

        $this->oublier();

        return true;
    }

    /** Purge le cache — appelée à chaque écriture. */
    public function oublier(): void
    {
        Cache::forget(self::CLE_CACHE);
    }

    /**
     * Toutes les valeurs, déchiffrées, en une seule lecture.
     *
     * @return array<string, string|null>
     */
    private function toutes(): array
    {
        /** @var array<string, string|null> $valeurs */
        $valeurs = Cache::rememberForever(self::CLE_CACHE, function (): array {
            $lignes = [];

            foreach (Setting::all() as $reglage) {
                $lignes[$reglage->key] = $this->dechiffrer($reglage);
            }

            return $lignes;
        });

        return $valeurs;
    }

    /**
     * Déchiffre une valeur secrète, sans jamais lever.
     *
     * Une clé d'application régénérée rend tous les secrets indéchiffrables.
     * Laisser filer l'exception mettrait le site entier à terre pour une clé
     * d'API — alors que la bonne conduite est de traiter le réglage comme
     * absent, ce que chaque moteur sait déjà gérer.
     */
    private function dechiffrer(Setting $reglage): ?string
    {
        if (! $reglage->is_secret || $reglage->value === null || $reglage->value === '') {
            return $reglage->value;
        }

        try {
            return Crypt::decryptString($reglage->value);
        } catch (DecryptException) {
            Log::warning(
                'Réglage secret indéchiffrable — la clé de l’application a-t-elle changé ? : '.$reglage->key
            );

            return null;
        }
    }
}
