<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

/**
 * Une page RÉCUPÉRÉE parle-t-elle vraiment du claim ? — phase 6.
 *
 * Le collecteur fetch des pages trouvées par des moteurs de recherche en
 * texte libre : rien ne garantit qu'une page retournée pour « le prix du
 * carburant a augmenté » parle de carburant plutôt que d'un sujet voisin qui
 * partage quelques mots. Une page hors sujet dans le paquet de preuves serait
 * pire qu'absente : elle donnerait à un modèle une source à citer qui ne
 * prouve rien du tout.
 *
 * ## Pourquoi ce n'est PAS {@see PertinenceDuFactCheck}
 *
 * Celle-ci exclut une correspondance de POLARITÉ opposée (« X est président »
 * ne concorde pas avec « X n'est plus président ») parce qu'elle décide si un
 * VERDICT se reporte tel quel. Ici, la question est différente : un article
 * qui DÉMENT un claim en parle tout autant qu'un article qui le confirme —
 * c'est au MODÈLE de lire le sens, pas à ce filtre de l'exclure avant qu'il
 * ne la voie. Le recouvrement lexical de mots porteurs, lui, reste le bon
 * signal dans les deux cas : c'est pourquoi il est RÉUTILISÉ tel quel.
 */
final class PertinenceDeLaPreuve
{
    /**
     * En dessous, une page est écartée du paquet — pas notée basse, ABSENTE.
     * Une preuve non pertinente dans le paquet n'est pas un signal faible,
     * c'est une invitation à citer n'importe quoi.
     */
    private const SEUIL_MINIMUM = 0.2;

    public function __construct(
        private readonly PertinenceDuFactCheck $recouvrement = new PertinenceDuFactCheck,
    ) {}

    /**
     * Score de pertinence, de 0 à 100 — titre et extrait comptés ensemble,
     * le titre pesant double : un titre hors sujet est plus révélateur qu'un
     * extrait qui mentionne le sujet en passant.
     */
    public function score(string $claim, string $titre, string $extrait): int
    {
        $titrePese = $this->recouvrement->recouvrement($claim, $titre) * 2;
        $extraitPese = $this->recouvrement->recouvrement($claim, $extrait);

        return (int) round(min(1.0, ($titrePese + $extraitPese) / 3) * 100);
    }

    public function estPertinente(string $claim, string $titre, string $extrait): bool
    {
        return $this->score($claim, $titre, $extrait) >= (int) round(self::SEUIL_MINIMUM * 100);
    }
}
