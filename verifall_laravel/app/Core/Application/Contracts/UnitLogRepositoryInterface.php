<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\UnitLog;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Journal des mouvements d'unités.
 *
 * Première interface de persistance du projet, conformément à la règle « aucune
 * requête Eloquent hors Repository ». Le code des sprints précédents interroge
 * encore Eloquent directement depuis les services : cette dette est signalée
 * dans docs/ARBITRAGES.md et se rattrape domaine par domaine.
 */
interface UnitLogRepositoryInterface
{
    /** Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL. */
    public const COLONNES_TRIABLES = ['date', 'motif', 'mouvement', 'soldeApres'];

    /** Colonnes que SearchBuilder peut interroger. */
    public const COLONNES_SEARCHBUILDER = ['libelle'];

    /**
     * Le journal global, pour la table DataTables de l'écran d'administration.
     *
     * @return PageDeResultats<UnitLog>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(array $attributs): UnitLog;

    /**
     * Historique paginé, filtrable — le tableau de la page « Mes unités ».
     *
     * @param  'credit'|'debit'|''  $sens
     * @return LengthAwarePaginator<int, UnitLog>
     */
    public function historique(
        User $utilisateur,
        string $recherche = '',
        string $sens = '',
        int $parPage = 10,
    ): LengthAwarePaginator;

    /**
     * Historique paginé PAR CURSEUR — le relevé de l'application mobile.
     *
     * Une seconde méthode plutôt qu'un drapeau sur la première : les deux
     * paginateurs n'ont pas le même contrat de retour, et un `mixed` obligerait
     * chaque appelant à deviner lequel il tient. Le web garde le décalage — sa
     * pagination affiche des numéros de page ; le mobile prend le curseur, qui
     * ne saute ni ne répète quand une ligne s'insère en tête.
     *
     * @return CursorPaginator<int, UnitLog>
     */
    public function historiqueParCurseur(User $utilisateur, int $parPage = 20): CursorPaginator;

    /**
     * Mêmes filtres, sans pagination — pour l'export CSV.
     *
     * @param  'credit'|'debit'|''  $sens
     * @return Collection<int, UnitLog>
     */
    public function pourExport(
        User $utilisateur,
        string $recherche = '',
        string $sens = '',
        int $limite = 5000,
    ): Collection;

    /**
     * Somme des mouvements, à comparer au solde.
     *
     * Le journal et le compteur doivent toujours concorder : l'écart est le
     * signal qu'une écriture a échappé au service.
     */
    public function somme(User $utilisateur): int;

    /**
     * Le journal de TOUS les comptes — l'écran d'administration.
     *
     * ## Une méthode séparée, et surtout pas un `?User $utilisateur = null`
     *
     * Les cinq méthodes ci-dessus bornent la lecture au propriétaire, et le
     * commentaire de l'implémentation dit pourquoi : « c'est ici, et non dans
     * l'appelant, que la fuite se produirait ». Rendre ce paramètre facultatif
     * ferait de l'oubli d'un argument une fuite de données — le journal complet
     * de la plateforme rendu à un citoyen, sans erreur pour le signaler.
     *
     * Une méthode qui s'appelle `journalGlobal` ne s'appelle pas par accident.
     *
     * @param  'credit'|'debit'|''  $sens
     * @param  string  $motif  valeur d'un `MotifMouvementUnite`, ou ''
     * @return LengthAwarePaginator<int, UnitLog>
     */
    public function journalGlobal(
        string $recherche = '',
        string $sens = '',
        string $motif = '',
        int $parPage = 25,
    ): LengthAwarePaginator;

    /**
     * Les totaux du journal complet, pour les indicateurs de l'écran staff.
     *
     * Calculés en base plutôt qu'en PHP : la table croît d'une ligne par
     * vérification, et charger l'historique de la plateforme pour l'additionner
     * cesserait de fonctionner sans prévenir.
     *
     * @return array{mouvements: int, credits: int, debits: int, comptes: int}
     */
    public function totauxGlobaux(): array;
}
