<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotUtilisateurs;
use App\Enums\StatutDossierKyc;
use App\Enums\UserRole;
use App\Infrastructure\Persistence\DataTables\FiltreSearchBuilder;
use App\Models\KycSubmission;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class DepotUtilisateursEloquent implements DepotUtilisateurs
{
    /**
     * Clé publique → colonne SQL. La seule table de correspondance : tout ce
     * qui n'y figure pas n'atteint jamais `orderBy()` ni `where()`.
     */
    private const COLONNES_SQL = [
        'nom' => 'name',
        'email' => 'email',
        'profession' => 'profession',
        'unites' => 'units',
        'points' => 'xp',
        'inscritLe' => 'created_at',
        'derniereConnexion' => 'last_login_at',
    ];

    private const TYPES_SEARCHBUILDER = [
        'nom' => 'string',
        'email' => 'string',
        'profession' => 'string',
        'unites' => 'num',
        'points' => 'num',
        'inscritLe' => 'date',
        'derniereConnexion' => 'date',
    ];

    /**
     * Les colonnes réellement lues.
     *
     * Pas de `select *` : la ligne `users` porte le secret 2FA chiffré, les
     * codes de secours et le hachage du mot de passe. Aucun n'est affiché, et
     * le plus sûr pour qu'aucun ne fuie un jour dans une réponse JSON est de ne
     * pas les charger.
     */
    private const COLONNES_LUES = [
        'id', 'uuid', 'name', 'email', 'profession', 'units', 'xp',
        'otp_verified_at', 'kyc_verified_at', 'suspended_at', 'suspension_reason', 'suspended_by',
        'two_factor_confirmed_at', 'last_login_at', 'created_at',
    ];

    /** @return PageDeResultats<User> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = User::query()->count();

        $filtree = User::query();
        $this->filtrer($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
        }

        $lignes = $filtree
            ->select(self::COLONNES_LUES)
            ->with(['roles:id,name', 'auteurDeLaSuspension:id,name'])
            // Départage stable : sans lui, deux comptes créés à la même seconde
            // changent de page d'un rechargement à l'autre.
            ->orderByDesc('id')
            ->skip($requete->debut)
            ->take($requete->longueur)
            ->get();

        return new PageDeResultats(
            total: $total,
            filtres: $nombreFiltre,
            lignes: $lignes,
            tronque: $requete->export && $nombreFiltre > $requete->longueur,
        );
    }

    /**
     * @param  list<int>  $identifiants
     * @return list<int>
     */
    public function avecDossierKycEnAttente(array $identifiants): array
    {
        if ($identifiants === []) {
            return [];
        }

        return KycSubmission::query()
            ->whereIn('user_id', $identifiants)
            ->where('status', StatutDossierKyc::EnAttente->value)
            ->distinct()
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Builder<User>  $requete
     */
    private function filtrer(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            // `LIKE` sans `LOWER()` : la collation MySQL est déjà insensible à
            // la casse, et envelopper la colonne interdirait l'index.
            $motif = '%'.$criteres->recherche.'%';

            $requete->where(static fn (Builder $ou) => $ou
                ->where('name', 'like', $motif)
                ->orWhere('email', 'like', $motif)
                ->orWhere('profession', 'like', $motif));
        }

        /*
         | « Personnel » = porte au moins un rôle autre que Citoyen. Même règle
         | que la liste Livewire qu'il remplace : le rôle Citoyen n'étant pas
         | attribué en base à tout le monde, l'absence de rôle vaut citoyen.
         */
        match ($filtres['type'] ?? null) {
            'personnel' => $requete->whereHas('roles', static fn (Builder $r) => $r->whereNot('name', UserRole::Citoyen->value)),
            'citoyens' => $requete->whereDoesntHave('roles', static fn (Builder $r) => $r->whereNot('name', UserRole::Citoyen->value)),
            default => null,
        };

        /*
         | `whereHas` et non le scope `role()` de spatie : ce dernier LÈVE une
         | exception si le rôle n'existe pas en base. La valeur est déjà en
         | liste blanche, mais une base semée à moitié ne doit pas répondre 500.
         */
        if (isset($filtres['role'])) {
            $role = $filtres['role'];
            $requete->whereHas('roles', static fn (Builder $r) => $r->where('name', $role));
        }

        match ($filtres['statut'] ?? null) {
            'suspendu' => $requete->whereNotNull('suspended_at'),
            'actif' => $requete->whereNull('suspended_at')->whereNotNull('otp_verified_at'),
            'non_active' => $requete->whereNull('suspended_at')->whereNull('otp_verified_at'),
            default => null,
        };

        $enAttente = KycSubmission::query()
            ->select('user_id')
            ->where('status', StatutDossierKyc::EnAttente->value);

        match ($filtres['kyc'] ?? null) {
            'verifiee' => $requete->whereNotNull('kyc_verified_at'),
            'en_attente' => $requete->whereNull('kyc_verified_at')->whereIn('id', $enAttente),
            'non_verifiee' => $requete->whereNull('kyc_verified_at')->whereNotIn('id', $enAttente),
            default => null,
        };

        match ($filtres['activite'] ?? null) {
            'recente' => $requete->where('last_login_at', '>=', now()->subDays(30)),
            'inactive' => $requete->where('last_login_at', '<', now()->subDays(90)),
            'jamais' => $requete->whereNull('last_login_at'),
            default => null,
        };

        if (isset($filtres['inscrit_du'])) {
            $requete->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['inscrit_du'])?->startOfDay());
        }

        if (isset($filtres['inscrit_au'])) {
            $requete->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['inscrit_au'])?->endOfDay());
        }

        $colonnes = [];

        foreach (self::TYPES_SEARCHBUILDER as $cle => $type) {
            $colonnes[$cle] = ['colonne' => self::COLONNES_SQL[$cle], 'type' => $type];
        }

        (new FiltreSearchBuilder($colonnes))->appliquer($requete, $criteres->searchBuilder);
    }
}
