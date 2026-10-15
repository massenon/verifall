<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Application\Contracts\DepotUtilisateurs;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate as PorteContract;
use Illuminate\Support\Facades\Gate;

/**
 * La liste des comptes, telle que DataTables la reçoit.
 *
 * ## Ce que ce service décide, et que le contrôleur ne doit pas décider
 *
 * 1. **Qui voit la liste** : la Policy `viewAny`, rejouée ici même si la route
 *    la garde déjà — la route protège l'adresse, le service protège l'appel.
 * 2. **Qui l'exporte** : `user.export`, permission DISTINCTE de la
 *    consultation. La directive le dit en une ligne : « permission d'accès ≠
 *    permission automatique d'export ». Elle était déclarée dans la matrice
 *    depuis l'origine et employée nulle part ; c'est son premier usage.
 * 3. **Ce qui sort** : une liste FERMÉE de champs. Pas d'`id`, pas de hachage,
 *    pas de secret — la ligne est construite champ par champ, jamais par
 *    `toArray()`, pour qu'une colonne ajoutée demain au modèle ne parte pas
 *    toute seule dans la réponse.
 * 4. **Quelles actions la ligne propose** : chaque drapeau est la réponse de la
 *    Policy pour CE compte. Le navigateur n'en déduit rien ; il affiche un
 *    bouton ou pas, et le composant Livewire ré-autorise au clic.
 * 5. **La trace** : un export de comptes est une extraction de données
 *    personnelles. Il passe au journal d'audit, avec les critères — c'est ce
 *    qui permet de répondre plus tard à « qui a sorti la liste des citoyens de
 *    Parakou, et quand ».
 */
final readonly class TableauDesUtilisateurs
{
    public function __construct(
        private DepotUtilisateurs $depot,
        private AuditLogService $audit,
    ) {}

    /**
     * Les filtres rapides de l'écran, et leurs seules valeurs acceptées.
     *
     * Chacun s'appuie sur une colonne qui EXISTE : `suspended_at`,
     * `otp_verified_at`, `kyc_verified_at` et `kyc_submissions.status`,
     * `last_login_at`, `created_at`. La directive citait aussi « abonnement » :
     * aucune colonne ne le porte, il n'est donc pas proposé.
     *
     * @return array<string, list<string>|'date'>
     */
    public static function filtresAutorises(): array
    {
        return [
            'type' => ['personnel', 'citoyens'],
            'role' => array_column(UserRole::cases(), 'value'),
            'statut' => ['actif', 'non_active', 'suspendu'],
            'kyc' => ['verifiee', 'en_attente', 'non_verifiee'],
            'activite' => ['recente', 'inactive', 'jamais'],
            'inscrit_du' => 'date',
            'inscrit_au' => 'date',
        ];
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(RequeteDataTables $requete, User $acteur): array
    {
        $porte = Gate::forUser($acteur);
        $porte->authorize('viewAny', User::class);

        if ($requete->export && ! $acteur->can('user.export')) {
            throw new AuthorizationException("L'export des comptes demande l'habilitation user.export.");
        }

        $page = $this->depot->pourTableau($requete);

        $identifiants = [];
        foreach ($page->lignes as $compte) {
            $identifiants[] = (int) $compte->id;
        }

        $kycEnAttente = array_flip($this->depot->avecDossierKycEnAttente($identifiants));

        $lignes = [];
        foreach ($page->lignes as $compte) {
            $lignes[] = $this->ligne($compte, $porte, isset($kycEnAttente[$compte->id]));
        }

        if ($requete->export) {
            $this->audit->record(
                action: 'user.export',
                module: 'utilisateurs',
                acteur: $acteur,
                apres: [
                    'lignes' => count($lignes),
                    'tronque' => $page->tronque,
                    'recherche' => $requete->recherche,
                    'filtres' => $requete->filtres,
                    'recherche_avancee' => $requete->searchBuilder !== null,
                ],
                cibleLibelle: 'Liste des utilisateurs',
            );
        }

        return [
            'draw' => $requete->draw,
            'recordsTotal' => $page->total,
            'recordsFiltered' => $page->filtres,
            'data' => $lignes,
            'exportTronque' => $page->tronque,
        ];
    }

    /**
     * Une ligne, champ par champ. Des TEXTES seulement : aucun HTML ne sort
     * d'ici, le navigateur échappe à l'affichage.
     *
     * @return array<string, mixed>
     */
    private function ligne(User $compte, PorteContract $porte, bool $kycEnAttente): array
    {
        $roles = [];
        $personnel = false;

        // `getRoleNames()` lit la relation déjà chargée par le dépôt
        // (`loadMissing`) : aucune requête par ligne.
        foreach ($compte->getRoleNames() as $nom) {
            $roles[] = UserRole::tryFrom($nom)?->label() ?? $nom;
            $personnel = $personnel || $nom !== UserRole::Citoyen->value;
        }

        $statut = match (true) {
            $compte->estSuspendu() => 'suspendu',
            $compte->estActive() => 'actif',
            default => 'non_active',
        };

        $peutSuspendre = $porte->allows('suspend', $compte);

        return [
            'uuid' => (string) $compte->uuid,
            'nom' => (string) $compte->name,
            'email' => (string) $compte->email,
            'profession' => $compte->profession,
            'roles' => $roles,
            'personnel' => $personnel,
            'statut' => $statut,
            'suspension' => $compte->estSuspendu() ? [
                'depuis' => $compte->suspended_at?->translatedFormat('j M Y'),
                'par' => $compte->auteurDeLaSuspension?->name,
                'motif' => $compte->suspension_reason,
            ] : null,
            // Sans objet pour un citoyen : le second facteur n'est exigé qu'au
            // back-office. L'afficher « absent » ferait croire à une faille.
            'deuxFacteurs' => $personnel ? ($compte->two_factor_confirmed_at !== null ? 'active' : 'a_venir') : null,
            'kyc' => $compte->kyc_verified_at !== null ? 'verifiee' : ($kycEnAttente ? 'en_attente' : 'non_verifiee'),
            'unites' => (int) $compte->units,
            'points' => (int) $compte->xp,
            'inscritLe' => $compte->created_at?->toDateString(),
            'inscritLeTexte' => $compte->created_at?->translatedFormat('j M Y'),
            'derniereConnexion' => $compte->last_login_at?->toDateString(),
            'derniereConnexionTexte' => $compte->last_login_at?->translatedFormat('j M Y'),
            'actions' => [
                'modifier' => $porte->allows('update', $compte),
                'suspendre' => $peutSuspendre && ! $compte->estSuspendu(),
                'retablir' => $peutSuspendre && $compte->estSuspendu(),
            ],
        ];
    }
}
