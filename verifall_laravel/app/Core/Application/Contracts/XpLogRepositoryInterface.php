<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\User;
use App\Models\XpLog;

/**
 * Journal des points d'expérience.
 *
 * Critère 3 de la règle 2 : le volume n'est pas borné — une ligne par quiz
 * réussi, par badge, par cours terminé, pour chaque compte.
 *
 * ## Trois méthodes, et pas les sept du journal des unités
 *
 * `UnitLogRepositoryInterface` en porte sept parce que sept écrans les
 * appellent : la page « Mes unités », son export CSV, le relevé mobile, le
 * journal global de l'administration, ses totaux. **L'XP n'a qu'un lecteur**,
 * l'écran Récompenses. Déclarer ici un `journalGlobal()` ou un `pourExport()`
 * que rien n'appelle recréerait exactement le défaut que ce chantier a corrigé
 * quatre fois en deux lots : du code juste qu'aucun écran n'atteint. Ils
 * s'ajouteront le jour où un écran les demandera.
 *
 * Cette interface en a d'ailleurs porté une quatrième pendant une heure,
 * `derniers()`, destinée à l'activité récente de l'écran Récompenses — où elle
 * aurait doublé les lignes « Badge débloqué » et « Certificat obtenu » qui s'y
 * trouvent déjà. Retirée avant livraison : la règle ci-dessus vaut aussi pour
 * qui l'écrit.
 */
interface XpLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(array $attributs): XpLog;

    /**
     * D'où viennent les points de ce compte : somme par motif.
     *
     * Groupé EN BASE et non en PHP. La table croît d'une ligne par récompense ;
     * charger l'historique complet pour l'additionner marcherait un an puis
     * cesserait sans prévenir.
     *
     * @return array<string, int> valeur de MotifGainXp => total, motifs à zéro absents
     */
    public function repartition(User $utilisateur): array;

    /**
     * Somme du journal, à comparer au compteur `users.xp`.
     *
     * Les deux doivent concorder : l'écart est le signal qu'une écriture a
     * échappé au service — précisément ce qui était vrai partout avant que ce
     * journal existe.
     */
    public function somme(User $utilisateur): int;
}
