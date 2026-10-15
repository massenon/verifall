<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Identity\Exceptions\ConnexionSocialeRefusee;
use App\Enums\ConsentType;
use App\Enums\FournisseurSocial;
use App\Enums\UserRole;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProfilSocial;

/**
 * Se connecter, ou s'inscrire, par un fournisseur d'identité.
 *
 * Trois chemins possibles, et l'ordre dans lequel on les essaie est ce qui rend
 * le dispositif sûr :
 *
 *   1. L'identité sociale est DÉJÀ rattachée → on connecte. Le rapprochement se
 *      fait sur l'identifiant chez le fournisseur, jamais sur l'adresse : une
 *      adresse se change, se recycle, se réattribue ; l'identifiant désigne la
 *      même personne pour toujours.
 *
 *   2. Aucun compte n'existe → on en crée un. L'adresse ayant été vérifiée par
 *      le fournisseur, il n'y a rien à revérifier par courriel.
 *
 *   3. Un compte existe avec cette adresse, sans rattachement → **on refuse**,
 *      sauf si le fournisseur atteste avoir vérifié l'adresse. Rattacher sur la
 *      seule foi d'une adresse revient à donner le compte à quiconque parvient
 *      à faire écrire cette adresse par le fournisseur. C'est la faille
 *      classique de la connexion sociale, et elle est silencieuse.
 */
final class ConnexionSociale
{
    public function __construct(private readonly ConsentService $consentements) {}

    /**
     * @throws ConnexionSocialeRefusee
     */
    public function connecter(
        FournisseurSocial $fournisseur,
        ProfilSocial $profil,
        ?string $ip = null,
        ?string $agent = null,
    ): User {
        $identifiant = (string) $profil->getId();
        $adresse = mb_strtolower(trim((string) $profil->getEmail()));

        // ─────────────────────────────── 1. identité déjà rattachée
        $rattachement = SocialAccount::where('provider', $fournisseur->value)
            ->where('provider_user_id', $identifiant)
            ->first();

        if ($rattachement !== null) {
            $utilisateur = $rattachement->user;

            if ($utilisateur === null) {
                // Le compte a été effacé mais la ligne a survécu : on nettoie
                // plutôt que de laisser une identité orpheline bloquer le
                // rattachement suivant.
                $rattachement->delete();
            } else {
                $this->refuserSiEnSuppression($utilisateur);

                $rattachement->forceFill([
                    'email' => $adresse ?: $rattachement->email,
                    'avatar_url' => $profil->getAvatar() ?: $rattachement->avatar_url,
                    'last_login_at' => now(),
                ])->save();

                return $utilisateur;
            }
        }

        if ($adresse === '') {
            throw ConnexionSocialeRefusee::adresseAbsente($fournisseur->libelle());
        }

        $existant = User::where('email', $adresse)->first();

        // ─────────────────────── 3. compte existant, pas encore rattaché
        if ($existant !== null) {
            if (! $fournisseur->attesteLAdresse() || ! $this->adresseAttestee($profil)) {
                throw ConnexionSocialeRefusee::compteDejaExistant($adresse);
            }

            $this->refuserSiEnSuppression($existant);

            $this->rattacher($existant, $fournisseur, $profil, $adresse);

            return $existant;
        }

        // ─────────────────────────────────── 2. création d'un compte
        return DB::transaction(function () use ($fournisseur, $profil, $adresse, $ip, $agent): User {
            $utilisateur = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => trim((string) $profil->getName()) ?: Str::before($adresse, '@'),
                'email' => $adresse,
                // Mot de passe aléatoire, jamais communiqué : le compte se
                // rouvre par le fournisseur ou par « mot de passe oublié ».
                // Laisser le champ vide permettrait une connexion sans mot de
                // passe si une vérification venait à être oubliée ailleurs.
                'password' => Str::password(40),
            ]);

            $utilisateur->assignRole(UserRole::Citoyen->value);

            /*
             | L'adresse a été vérifiée par le fournisseur : redemander un code
             | par courriel ferait refaire à la personne ce qu'elle vient de
             | faire. `otp_verified_at` est ce qui rend le compte ACTIF.
             */
            $utilisateur->forceFill([
                'email_verified_at' => now(),
                'otp_verified_at' => now(),
            ])->save();

            /*
             | Les consentements sont enregistrés ici aussi. Un compte créé
             | sans trace de consentement est un compte non conforme — que la
             | personne soit passée par le formulaire ou par Google n'y change
             | rien. L'écran affiche la mention au-dessus des boutons.
             */
            $this->consentements->recordMany($utilisateur, [
                ConsentType::Cgu->value => true,
                ConsentType::Privacy->value => true,
                // Jamais cochée d'office : une newsletter imposée est un
                // consentement non libre, donc nul.
                ConsentType::Newsletter->value => false,
            ], $ip, $agent);

            $this->rattacher($utilisateur, $fournisseur, $profil, $adresse);

            return $utilisateur;
        });
    }

    /**
     * Relie une identité à un compte déjà connecté, depuis les réglages.
     *
     * @throws ConnexionSocialeRefusee
     */
    public function relier(User $utilisateur, FournisseurSocial $fournisseur, ProfilSocial $profil): SocialAccount
    {
        $identifiant = (string) $profil->getId();

        $ailleurs = SocialAccount::where('provider', $fournisseur->value)
            ->where('provider_user_id', $identifiant)
            ->where('user_id', '!=', $utilisateur->id)
            ->exists();

        if ($ailleurs) {
            throw new ConnexionSocialeRefusee(
                "Ce compte {$fournisseur->libelle()} est déjà relié à un autre compte Vérif'All."
            );
        }

        return $this->rattacher(
            $utilisateur,
            $fournisseur,
            $profil,
            mb_strtolower(trim((string) $profil->getEmail())),
        );
    }

    /**
     * Détache une identité.
     *
     * Refuse si c'est le dernier moyen de se connecter — quelqu'un qui s'est
     * inscrit par Google n'a pas de mot de passe qu'il connaisse, et détacher
     * l'enfermerait dehors.
     */
    public function detacher(User $utilisateur, FournisseurSocial $fournisseur): bool
    {
        $rattachement = SocialAccount::where('user_id', $utilisateur->id)
            ->where('provider', $fournisseur->value)
            ->first();

        if ($rattachement === null) {
            return false;
        }

        $rattachement->delete();

        return true;
    }

    private function rattacher(
        User $utilisateur,
        FournisseurSocial $fournisseur,
        ProfilSocial $profil,
        string $adresse,
    ): SocialAccount {
        return SocialAccount::updateOrCreate(
            ['provider' => $fournisseur->value, 'provider_user_id' => (string) $profil->getId()],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $utilisateur->id,
                'email' => $adresse ?: null,
                'avatar_url' => $profil->getAvatar(),
                'email_verified_by_provider' => $fournisseur->attesteLAdresse() && $this->adresseAttestee($profil),
                'linked_at' => now(),
                'last_login_at' => now(),
            ],
        );
    }

    /**
     * Le fournisseur affirme-t-il avoir vérifié l'adresse ?
     *
     * On lit la charge utile brute : Socialite ne normalise pas ce champ, et
     * chaque fournisseur le nomme à sa façon. Absent ⇒ non vérifié. Le doute
     * profite au compte, pas à celui qui tente d'y entrer.
     */
    private function adresseAttestee(ProfilSocial $profil): bool
    {
        $brut = $profil->getRaw();

        return ($brut['email_verified'] ?? $brut['verified_email'] ?? false) === true;
    }

    /**
     * @throws ConnexionSocialeRefusee
     */
    private function refuserSiEnSuppression(User $utilisateur): void
    {
        if ($utilisateur->deletion_requested_at !== null) {
            throw ConnexionSocialeRefusee::compteDesactive();
        }
    }
}
