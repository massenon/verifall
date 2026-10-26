<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

/**
 * Les agrégats des écrans Analytique. Volume non borné et plusieurs agrégats :
 * un dépôt, et un cache de dix minutes. Rien n'est simulé — un chiffre qu'on
 * ne peut pas mesurer revient à zéro ou vide, jamais inventé.
 */
interface DepotAnalytique
{
    /**
     * @return array{
     *   entonnoir: array{inscrits: int, en_cours: int, termines: int, certificats: int},
     *   cours: list<array{titre: string, inscrits: int, termines: int, completion: int, progression: int}>,
     *   inactifs: int,
     *   actifs_7j: int
     * }
     */
    public function academie(): array;

    /**
     * @return list<array{moteur: string, analyses: int, echecs: int, taux_echec: int, latence_ms: int, confiance: int}>
     */
    public function moteurs(int $jours = 30): array;

    /**
     * @return array{
     *   mois: list<array{mois: string, fcfa: int, paiements: int}>,
     *   total_fcfa: int,
     *   reussis: int,
     *   echoues: int,
     *   credites: int,
     *   consommes: int,
     *   motifs: list<array{motif: string, unites: int}>
     * }
     */
    public function revenus(int $mois = 6): array;
}
