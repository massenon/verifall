<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\SuspensionDeCompte;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Utilisateurs » — créer un compte du personnel, et lui donner un rôle.
 *
 * ## Le trou qu'il bouche
 *
 * Les deux guards partagent la table `users` : un administrateur est un `User`
 * portant le rôle `Admin`. Jusqu'ici, **aucun écran ne permettait d'en
 * créer un**. Le seul chemin était `StaffSeeder`, c'est-à-dire un développeur
 * avec un accès console sur le serveur.
 *
 * Conséquence directe : le porteur du projet ne pouvait pas ouvrir un accès à
 * un nouveau modérateur sans faire appel à quelqu'un d'autre. Sur un produit de
 * modération, où l'équipe change, c'est un point de blocage permanent.
 *
 * ## Ce que cet écran fait, et ce qu'il ne fait PAS
 *
 * Il **crée** un compte, **assigne** ou **retire** un rôle, **liste**, et
 * depuis le 2 septembre 2026 **suspend** ou **rétablit**.
 *
 * Il ne supprime pas, n'exporte pas, ne fait pas de KYC. La matrice déclare bien
 * `user.delete` et `user.export` — mais la suppression d'un compte est un geste
 * RGPD déjà servi par `SuppressionCompteService`, côté citoyen, en deux temps et
 * avec rétractation. La recréer ici en un clic serait un second chemin vers un
 * effet irréversible.
 *
 * ## La suspension, ajoutée le 2 septembre 2026
 *
 * Ce paragraphe disait « la suspension demande une colonne qui n'existe pas ».
 * Elle existe désormais, et avec elle les deux courriels qui l'attendaient au
 * catalogue depuis qu'il a été écrit.
 *
 * L'écran ne fait qu'appeler `SuspensionDeCompte` : c'est le service qui pose
 * l'état, **ferme les sessions ouvertes**, journalise et prévient. Un écran qui
 * se contenterait d'écrire la date laisserait la personne connectée continuer de
 * publier sur un compte officiellement suspendu.
 *
 * Le module utilisateurs complet — citoyens, mentors, KYC — reste au lot 7.
 * Cet écran est la tranche qui débloque : ouvrir un accès.
 *
 * ## Les trois règles qui protègent le back-office
 *
 * 1. **On ne modifie pas ses propres rôles.** Se retirer `role.manage` par
 *    mégarde ferme la porte derrière soi, sans personne pour la rouvrir.
 * 2. **Le dernier administrateur est intouchable.** Retirer le rôle `Admin` au
 *    dernier qui le porte laisse une plateforme que plus personne n'administre.
 *    Aucun test ne verrait ce défaut : la requête réussit.
 * 3. **Tout passe au journal, nominativement.** Un compte du personnel créé
 *    sans trace est une brèche d'audit — c'est l'accès le plus sensible du
 *    produit.
 */
#[Layout('components.layouts.staff', ['title' => "Utilisateurs · Vérif'All"])]
final class Utilisateurs extends Component
{
    use InteractsWithToasts;

    /**
     * Rechargée par `verifall-datatables.js` après chaque écriture réussie :
     * voir `docs/DATATABLES_CONTRAT.md` §6.3. La table elle-même vit côté
     * client, sur `staff.users.data` — `render()` ne la calcule plus.
     */
    public const EVENEMENT_TABLE_A_RECHARGER = 'utilisateurs-modifies';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' ou une valeur de UserRole. */
    #[Url(as: 'role', except: '')]
    public string $filtreRole = '';

    /** '' | 'personnel' | 'citoyens' */
    #[Url(as: 'type', except: '')]
    public string $filtreType = '';

    public bool $formulaireOuvert = false;

    /** UUID du compte dont on change les rôles ; null = création. */
    #[Locked]
    public ?string $enEdition = null;

    // — le formulaire
    public string $prenom = '';

    public string $nom = '';

    public string $email = '';

    public string $profession = '';

    public string $motDePasse = '';

    /** @var list<string> */
    public array $roles = [];

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreRole', 'filtreType');
    }

    /** Compte visé par la modale de suspension, par uuid. */
    #[Locked]
    public ?string $aSuspendre = null;

    /** Compte visé par la confirmation de rétablissement. */
    #[Locked]
    public ?string $aRetablir = null;

    public string $motifDeSuspension = '';

    /** Ouvre le tiroir d'édition depuis la fiche d'un compte (`?modifier=<uuid>`). */
    #[Url(as: 'modifier', except: '')]
    public string $aModifier = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);

        // Sans le droit de modifier, ou pour un uuid inconnu : la liste seule,
        // sans erreur — un lien de fiche ne doit pas révéler ce qui existe.
        if ($this->aModifier !== '' && auth('staff')->user()?->can('user.update')) {
            try {
                $this->ouvrirEdition($this->aModifier);
            } catch (\Throwable) {
                $this->formulaireOuvert = false;
                $this->enEdition = null;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.staff.utilisateurs', [
            'indicateurs' => $this->indicateurs(),
            'rolesDisponibles' => UserRole::cases(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', User::class);

        $this->reset('enEdition', 'prenom', 'nom', 'email', 'profession', 'motDePasse', 'roles');
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $compte = $this->compte($uuid);
        $this->authorize('update', $compte);

        $this->enEdition = $uuid;
        $this->prenom = (string) $compte->first_name;
        $this->nom = (string) $compte->last_name;
        $this->email = (string) $compte->email;
        $this->profession = (string) $compte->profession;
        $this->motDePasse = '';
        $this->roles = $compte->getRoleNames()->all();

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    public function enregistrer(AuditLogService $audit): void
    {
        $donnees = $this->validate();

        $creation = $this->enEdition === null;
        $compte = $creation ? new User : $this->compte($this->enEdition);

        $this->authorize($creation ? 'create' : 'update', $creation ? User::class : $compte);

        $membre = Auth::guard('staff')->user();

        /*
         | RÈGLE 1 — on ne touche pas à ses propres rôles.
         |
         | Se retirer `role.manage` d'un clic maladroit ferme la porte derrière
         | soi : plus aucun écran ne permet de se la rouvrir, et il faut
         | retourner à la console. Le champ est d'ailleurs désactivé dans la
         | vue ; ce test-ci existe parce qu'un champ désactivé se réactive dans
         | un navigateur.
         */
        $rolesDemandes = $creation || $membre?->id !== $compte->id
            ? $donnees['roles']
            : $compte->getRoleNames()->all();

        if (! $creation && $membre?->id === $compte->id && $donnees['roles'] !== $compte->getRoleNames()->all()) {
            $this->toastWarning('Vous ne pouvez pas modifier vos propres rôles.');

            return;
        }

        // RÈGLE 2 — le dernier administrateur ne perd pas son rôle.
        if (! $creation && $this->retireraitLeDernierAdmin($compte, $rolesDemandes)) {
            $this->toastError('Impossible : ce compte est le dernier administrateur.');

            return;
        }

        $rolesAvant = $creation ? [] : $compte->getRoleNames()->all();

        $compte->forceFill([
            'uuid' => $compte->uuid ?? (string) Str::uuid(),
            'name' => trim($donnees['prenom'].' '.$donnees['nom']),
            'first_name' => $donnees['prenom'],
            'last_name' => $donnees['nom'],
            'email' => $donnees['email'],
            'profession' => $donnees['profession'],
        ]);

        if ($creation) {
            $compte->forceFill([
                'password' => $donnees['motDePasse'],
                /*
                 | Le compte est ACTIF d'emblée : c'est un accès ouvert
                 | délibérément par un administrateur, pas une inscription
                 | publique à vérifier par courriel. Lui imposer un code OTP
                 | envoyé à une adresse professionnelle qui peut ne pas être
                 | relevée bloquerait l'accès qu'on vient d'accorder.
                 */
                'otp_verified_at' => now(),
                'email_verified_at' => now(),
            ]);
        } elseif ($donnees['motDePasse'] !== '') {
            $compte->forceFill(['password' => $donnees['motDePasse']]);
        }

        /*
         | La 2FA n'est PAS pré-activée, et surtout pas pré-remplie ici.
         |
         | Le guard `staff` l'exige : `StaffLogin` conduit à l'enrôlement à la
         | première connexion, et la personne scanne son propre secret. Le poser
         | à sa place supposerait de le lui transmettre — par un canal qui
         | annulerait tout l'intérêt du second facteur.
         */
        $compte->save();

        $compte->syncRoles($rolesDemandes);

        /*
         | Deux chemins de journalisation, et la distinction est utile au
         | relecteur : une CRÉATION de compte est un fait entier — nouvel accès
         | ouvert, à telle personne, avec tels rôles. Une MODIFICATION n'est
         | intéressante que par ce qui a bougé, et `recordDiff` ne retient que
         | cela : voir « rôles : Mentor → Mentor, Modérateur » vaut mieux que
         | relire l'objet complet à chaque ligne.
         */
        if ($creation) {
            $audit->record(
                action: 'user.create',
                module: 'utilisateurs',
                acteur: $membre,
                cible: $compte,
                apres: ['email' => $compte->email, 'roles' => $rolesDemandes],
                cibleLibelle: $compte->name,
            );
        } elseif ($membre !== null) {
            $audit->recordDiff(
                'utilisateurs',
                $membre,
                $compte,
                ['roles' => $rolesAvant, 'email' => $donnees['email']],
                ['roles' => $rolesDemandes, 'email' => $compte->email],
                cibleLibelle: $compte->name,
            );
        }

        $this->fermerLeTiroir();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess($creation
            ? 'Compte créé. La double authentification sera demandée à la première connexion.'
            : 'Compte mis à jour.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        $existant = $this->enEdition !== null ? $this->compte($this->enEdition) : null;

        /*
         | LE CONTRÔLE DNS NE S'APPLIQUE QU'À UNE ADRESSE QUI CHANGE.
         |
         | `email:rfc,dns` est la règle de la maison, et elle est justifiée à la
         | saisie : une adresse dont le domaine n'existe pas rend le compte
         | irrécupérable — plus de réinitialisation de mot de passe possible.
         |
         | Mais elle s'appliquait aussi à la valeur PRÉ-REMPLIE d'un compte
         | existant. Conséquence : un compte dont le domaine a cessé de résoudre
         | — une organisation qui change de nom, un domaine expiré — devenait
         | totalement inéditable. On ne pouvait plus même lui retirer ses rôles,
         | ce qui est exactement le geste qu'on veut faire dans ce cas-là.
         |
         | Trouvé par un test qui échouait sur une adresse `@example.org` : le
         | défaut aurait mis des mois à se manifester en production, et se
         | serait présenté comme « le formulaire refuse mon enregistrement sans
         | rien dire d'utile ».
         */
        $adresseInchangee = $existant !== null && $existant->email === $this->email;

        return [
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                $adresseInchangee ? 'email:rfc' : 'email:rfc,dns',
                'max:180',
                Rule::unique('users', 'email')->ignore($existant?->id),
            ],
            'profession' => ['nullable', 'string', 'max:120'],
            /*
             | Obligatoire à la création, facultatif ensuite — vide signifiant
             | « je n'y touche pas », comme aux réglages. Les règles de Laravel
             | plutôt qu'un `min:8` maison : elles interdisent aussi les mots de
             | passe connus des fuites publiques, ce qui compte davantage que la
             | longueur sur un accès d'administration.
             */
            'motDePasse' => $this->enEdition === null
                ? ['required', Password::defaults()]
                : ['nullable', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => [Rule::in(array_column(UserRole::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'prenom' => 'prénom', 'nom' => 'nom', 'email' => 'adresse e-mail',
            'profession' => 'fonction', 'motDePasse' => 'mot de passe', 'roles' => 'rôles',
        ];
    }

    /**
     * Ouvre la modale — la suspension ne part pas d'ici.
     *
     * Deux écrans plutôt qu'un bouton : le motif est OBLIGATOIRE, et le
     * demander dans la boîte qui annonce la sanction est le seul moment où on
     * est sûr qu'il sera écrit pour cette personne-là.
     */
    public function ouvrirLaSuspension(string $uuid): void
    {
        $cible = $this->compte($uuid);
        $this->authorize('suspend', $cible);

        $this->aSuspendre = $uuid;
        $this->motifDeSuspension = '';
        $this->resetValidation();
    }

    public function annulerLaSuspension(): void
    {
        $this->aSuspendre = null;
        $this->aRetablir = null;
        $this->motifDeSuspension = '';
        $this->resetValidation();
    }

    public function suspendre(SuspensionDeCompte $suspension): void
    {
        if ($this->aSuspendre === null) {
            return;
        }

        $cible = $this->compte($this->aSuspendre);
        $this->authorize('suspend', $cible);

        $this->validate([
            'motifDeSuspension' => ['required', 'string', 'min:10', 'max:'.SuspensionDeCompte::MOTIF_MAXIMUM],
        ], attributes: ['motifDeSuspension' => 'motif']);

        $suspension->suspendre($cible, $this->acteur(), $this->motifDeSuspension);

        $this->annulerLaSuspension();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Compte suspendu. Les sessions ouvertes ont été fermées et la personne est prévenue.');
    }

    public function ouvrirLeRetablissement(string $uuid): void
    {
        $cible = $this->compte($uuid);
        $this->authorize('suspend', $cible);

        $this->aRetablir = $uuid;
    }

    public function retablir(SuspensionDeCompte $suspension): void
    {
        if ($this->aRetablir === null) {
            return;
        }

        $cible = $this->compte($this->aRetablir);
        $this->authorize('suspend', $cible);

        $suspension->retablir($cible, $this->acteur());

        $this->annulerLaSuspension();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Compte rétabli. La personne retrouve ses accès et en est informée.');
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }

    /**
     * Retirer ces rôles laisserait-il la plateforme sans administrateur ?
     *
     * @param  list<string>  $rolesDemandes
     */
    private function retireraitLeDernierAdmin(User $compte, array $rolesDemandes): bool
    {
        $perdSonRole = $compte->hasRole(UserRole::Admin->value)
            && ! in_array(UserRole::Admin->value, $rolesDemandes, true);

        if (! $perdSonRole) {
            return false;
        }

        return User::query()->role(UserRole::Admin->value)->count() <= 1;
    }

    /**
     * @return array{total: int, personnel: int, administrateurs: int, sans2fa: int}
     */
    private function indicateurs(): array
    {
        $personnel = User::query()
            ->whereHas('roles', fn (Builder $r) => $r->whereNot('name', UserRole::Citoyen->value));

        return [
            'total' => User::query()->count(),
            'personnel' => (clone $personnel)->count(),
            'administrateurs' => User::query()->role(UserRole::Admin->value)->count(),
            /*
             | Le chiffre qui appelle une action : un compte du personnel sans
             | second facteur confirmé n'a pas encore franchi l'enrôlement. Il
             | ne peut donc pas entrer — c'est utile à voir avant qu'on le
             | signale comme une panne.
             */
            'sans2fa' => (clone $personnel)->whereNull('two_factor_confirmed_at')->count(),
        ];
    }

    private function compte(string $uuid): User
    {
        return User::query()->where('uuid', $uuid)->firstOrFail();
    }
}
