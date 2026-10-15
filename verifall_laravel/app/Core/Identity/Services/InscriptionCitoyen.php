<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Application\Reglages\Reglages;
use App\Core\Economy\Services\EconomyService;
use App\Core\Marketing\Services\Parrainage;
use App\Enums\ConsentType;
use App\Enums\MotifMouvementUnite;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Création d'un compte citoyen, de bout en bout.
 *
 * POURQUOI CE SERVICE EXISTE. Créer un compte, ce n'est pas insérer une ligne :
 * c'est aussi attribuer un rôle, horodater trois consentements, rattacher un
 * parrainage et laisser le compte INACTIF jusqu'à preuve de maîtrise de la
 * boîte mail. Cette suite vivait dans le composant Livewire ; l'inscription
 * mobile en a besoin à l'identique. Deux copies auraient fini par diverger sur
 * le maillon le plus discret — le consentement — et un compte sans trace de
 * consentement est un compte non conforme.
 *
 * Le code de vérification est émis SÉPARÉMENT (`envoyerLeCode`) : le renvoi
 * d'un code est une action à part, avec sa propre limitation de débit, et la
 * fusionner ici aurait obligé l'appelant à recréer un compte pour renvoyer
 * un code.
 */
final class InscriptionCitoyen
{
    /**
     * Le bonus de bienvenue DE REPLI, si les réglages sont illisibles.
     *
     * Même conduite que `Parrainage` : la valeur vit aux réglages
     * (`default_user_units`, onglet Économie), cette constante n'est que le
     * dernier filet — et elle nomme ce que le code attend.
     */
    public const BONUS_BIENVENUE_PAR_DEFAUT = 5;

    public function __construct(
        private readonly ConsentService $consentements,
        private readonly OtpService $otp,
        private readonly Parrainage $parrainage,
        private readonly EconomyService $economie,
        private readonly Reglages $reglages,
    ) {}

    /**
     * @param  array{prenom:string,nom:string,telephone:string,profession:string,email:string,motDePasse:string,newsletter:bool}  $donnees
     * @param  string|null  $codeParrainage  code déposé par le lien d'invitation, s'il y en avait un
     */
    public function creer(
        array $donnees,
        ?string $codeParrainage = null,
        ?string $ip = null,
        ?string $agent = null,
    ): User {
        // Création et consentements forment un tout : un compte sans trace de
        // consentement serait un compte non conforme.
        return DB::transaction(function () use ($donnees, $codeParrainage, $ip, $agent): User {
            $utilisateur = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => trim($donnees['prenom'].' '.$donnees['nom']),
                'first_name' => $donnees['prenom'],
                'last_name' => $donnees['nom'],
                'email' => mb_strtolower(trim($donnees['email'])),
                'phone' => $donnees['telephone'],
                'profession' => $donnees['profession'],
                'password' => $donnees['motDePasse'],
            ]);

            $utilisateur->assignRole(UserRole::Citoyen->value);

            $this->consentements->recordMany($utilisateur, [
                ConsentType::Cgu->value => true,
                ConsentType::Privacy->value => true,
                // Le refus est tracé au même titre que l'acceptation.
                ConsentType::Newsletter->value => $donnees['newsletter'],
            ], $ip, $agent);

            /*
             | Rattaché DANS la transaction : un compte créé sans son
             | parrainage ne serait rattrapable qu'à la main.
             */
            if ($codeParrainage !== null && $codeParrainage !== '') {
                $this->parrainage->rattacher($utilisateur, $codeParrainage);
            }

            $this->offrirLeBonusDeBienvenue($utilisateur);

            return $utilisateur;
        });
    }

    /**
     * Les unités offertes à la création du compte.
     *
     * ## Ce qui manquait, et pourquoi l'inscription semblait inachevée
     *
     * Rien ne créditait ce bonus. `users.units` vaut 0 par défaut : on
     * terminait les quatre étapes, on arrivait au tableau de bord, et l'on ne
     * pouvait vérifier **aucune** information — la première action du produit
     * était fermée à qui venait de s'inscrire pour elle.
     *
     * Le réglage `default_user_units` existait pourtant, à 5, avec pour aide
     * « Unités créditées à l'inscription, une seule fois ». Personne ne le
     * lisait : troisième cas de l'écart É-02 relevé au lot 0, après le barème
     * de parrainage et le gabarit de certificat.
     *
     * ## Pourquoi il ne peut pas faire échouer une inscription
     *
     * Le crédit est enveloppé : un solde à zéro est regrettable, un compte
     * qu'on ne peut pas créer l'est beaucoup plus. La transaction porte
     * l'essentiel — compte, rôle, consentements, parrainage ; le bonus est un
     * agrément, et il se rattrape à la main.
     *
     * Un bonus réglé à zéro n'appelle aucun mouvement : `EconomyService`
     * refuserait un crédit nul, et une ligne de journal à zéro unité ne
     * raconterait rien.
     */
    private function offrirLeBonusDeBienvenue(User $utilisateur): void
    {
        try {
            $unites = $this->reglages->nombre('default_user_units', self::BONUS_BIENVENUE_PAR_DEFAUT);

            if ($unites <= 0) {
                return;
            }

            $this->economie->crediter(
                $utilisateur,
                $unites,
                MotifMouvementUnite::Bienvenue,
                'Bienvenue sur Vérif\'All',
            );
        } catch (Throwable $erreur) {
            /*
             | Journalisé, pas silencieux : un compte sans son bonus est un
             | ticket de support, et l'équipe doit pouvoir le retrouver sans
             | attendre que la personne se plaigne.
             */
            Log::warning('Bonus de bienvenue non crédité.', [
                'user_uuid' => $utilisateur->uuid,
                'erreur' => $erreur->getMessage(),
            ]);
        }
    }

    /**
     * Émet un code à six chiffres et le met en file d'envoi.
     *
     * Mise en file plutôt qu'envoi direct : ni l'écran ni l'application mobile
     * ne doivent attendre le serveur SMTP pour afficher l'étape suivante.
     */
    public function envoyerLeCode(User $utilisateur, ?string $ip = null): void
    {
        $code = $this->otp->issue($utilisateur->email, OtpPurpose::Registration, $ip);

        Mail::to($utilisateur->email)->queue(new OtpCodeMail($code, OtpPurpose::Registration));
    }

    /**
     * Active le compte si le code est bon.
     *
     * Rend le résultat de la vérification plutôt qu'un booléen : l'appelant
     * doit pouvoir dire POURQUOI le code est refusé — expiré, déjà utilisé,
     * faux — et non se contenter d'un « non ».
     */
    public function activer(User $utilisateur, string $code): OtpVerification
    {
        $resultat = $this->otp->verify($utilisateur->email, OtpPurpose::Registration, $code);

        if ($resultat->estValide()) {
            $utilisateur->forceFill(['otp_verified_at' => now()])->save();
        }

        return $resultat;
    }
}
