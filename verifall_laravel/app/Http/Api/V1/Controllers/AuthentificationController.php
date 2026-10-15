<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Identity\Exceptions\CodeRefuse;
use App\Core\Identity\Exceptions\CompteVerrouille;
use App\Core\Identity\Exceptions\IdentifiantsInvalides;
use App\Core\Identity\Services\AuthentificationCitoyen;
use App\Core\Identity\Services\InscriptionCitoyen;
use App\Core\Identity\Services\ReinitialisationMotDePasse;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\JetonPersonnel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

/**
 * Ouvrir, prouver et refermer une identité — côté mobile.
 *
 * Ce contrôleur ne décide RIEN. Il traduit du JSON vers trois services déjà
 * employés par les écrans Livewire — `AuthentificationCitoyen`,
 * `InscriptionCitoyen`, `ReinitialisationMotDePasse` — et retraduit leurs
 * exceptions en enveloppes. Le verrouillage progressif, l'égalisation du temps
 * de réponse, l'anti-énumération et la révocation des jetons vivent là-bas, et
 * là-bas seulement.
 *
 * JETON ET NON COOKIE : une application mobile n'a pas de session de
 * navigateur, et un cookie de session imposerait une politique CSRF, un
 * domaine et un `SameSite` à un client qui n'est pas un navigateur.
 */
final class AuthentificationController
{
    /**
     * `POST /auth/login`
     *
     * Un compte non activé se voit refuser AVEC LE MÊME MESSAGE qu'un mot de
     * passe faux : distinguer les deux permettrait d'énumérer les inscrits.
     */
    public function connexion(Request $requete, AuthentificationCitoyen $authentification): JsonResponse
    {
        $donnees = $requete->validate([
            'email' => ['required', 'string', 'email', 'max:180'],
            'mot_de_passe' => ['required', 'string'],
            // Nommer l'appareil rend la liste des sessions lisible : « iPhone
            // de Rita » se révoque en connaissance de cause, « token 47 » non.
            'appareil' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $utilisateur = $authentification->authentifier(
                $donnees['email'],
                $donnees['mot_de_passe'],
                (string) ($requete->ip() ?? '0.0.0.0'),
            );
        } catch (CompteVerrouille $verrou) {
            return Enveloppe::erreur(
                CodeErreur::DebitDepasse,
                'Trop de tentatives. Réessayez dans '.max(1, (int) ceil($verrou->secondes / 60)).' minute(s).',
                meta: ['retry_after' => $verrou->secondes],
            )->header('Retry-After', (string) $verrou->secondes);
        } catch (IdentifiantsInvalides) {
            return Enveloppe::erreur(CodeErreur::NonAuthentifie, 'Identifiants invalides.');
        }

        return JetonPersonnel::reponse($utilisateur, $donnees['appareil'] ?? null);
    }

    /**
     * `POST /auth/logout`
     *
     * Révoque le SEUL jeton présenté. Fermer tous les appareils d'un coup
     * parce qu'on se déconnecte de l'un serait une surprise désagréable — et
     * cela existe déjà, sous un autre nom : la réinitialisation du mot de
     * passe.
     */
    public function deconnexion(Request $requete): JsonResponse
    {
        $jeton = $requete->user()?->currentAccessToken();

        if ($jeton !== null && method_exists($jeton, 'delete')) {
            $jeton->delete();
        }

        return Enveloppe::fait();
    }

    /**
     * `POST /auth/register`
     *
     * Ne renvoie PAS de jeton : le compte existe mais n'est pas activé. C'est
     * `POST /auth/otp/verify` qui l'ouvre, une fois la maîtrise de la boîte
     * mail prouvée.
     */
    public function inscription(Request $requete, InscriptionCitoyen $inscriptions): JsonResponse
    {
        $donnees = $requete->validate([
            'prenom' => ['required', 'string', 'min:2', 'max:60'],
            'nom' => ['required', 'string', 'min:2', 'max:60'],
            'telephone' => ['required', 'string', 'min:8', 'max:20'],
            'profession' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc,dns', 'max:180', 'unique:users,email'],
            // Politique définie une seule fois, dans AppServiceProvider.
            'mot_de_passe' => ['required', 'string', Password::defaults()],
            // Consentements RGPD : jamais pré-cochés côté client, exigés ici.
            // Le refus de la lettre d'information est tracé comme l'accord.
            'cgu' => ['accepted'],
            'confidentialite' => ['accepted'],
            'newsletter' => ['boolean'],
            'code_parrainage' => ['nullable', 'string', 'max:24'],
        ]);

        $utilisateur = $inscriptions->creer([
            'prenom' => $donnees['prenom'],
            'nom' => $donnees['nom'],
            'telephone' => $donnees['telephone'],
            'profession' => $donnees['profession'],
            'email' => $donnees['email'],
            'motDePasse' => $donnees['mot_de_passe'],
            'newsletter' => (bool) ($donnees['newsletter'] ?? false),
        ], $donnees['code_parrainage'] ?? null, $requete->ip(), $requete->userAgent());

        $inscriptions->envoyerLeCode($utilisateur, $requete->ip());

        return Enveloppe::donnee([
            'uuid' => $utilisateur->uuid,
            'email' => $utilisateur->email,
            'etape' => 'code_envoye',
        ], statut: 201);
    }

    /**
     * `POST /auth/otp/request` et `POST /auth/otp/resend`
     *
     * Deux chemins pour un seul geste : demander un code d'activation. Le
     * simulateur les nomme séparément parce que l'écran de saisie du code a un
     * bouton « renvoyer » ; le serveur, lui, fait la même chose.
     *
     * Réponse INDIFFÉRENCIÉE : ni l'existence du compte ni son état
     * d'activation ne transparaissent. Sans cela, ce point d'entrée devient un
     * annuaire des inscrits.
     */
    public function demanderLeCode(Request $requete, InscriptionCitoyen $inscriptions): JsonResponse
    {
        $donnees = $requete->validate([
            'email' => ['required', 'string', 'email', 'max:180'],
        ]);

        $identite = mb_strtolower(trim($donnees['email']));
        $cle = 'api-otp:'.$identite;

        /*
         | 3 envois par 10 minutes (SECURITE_RGPD §1). Le plafond s'applique
         | AVANT de chercher le compte : sinon la présence d'une limitation
         | révélerait elle-même l'existence de l'adresse.
         */
        if (! RateLimiter::tooManyAttempts($cle, 3)) {
            RateLimiter::hit($cle, 600);

            $utilisateur = User::where('email', $identite)->first();

            // Un compte DÉJÀ activé ne reçoit rien : le code d'inscription
            // n'aurait plus d'objet, et l'envoyer offrirait un moyen d'inonder
            // la boîte de quelqu'un d'autre.
            if ($utilisateur !== null && ! $utilisateur->estActive()) {
                $inscriptions->envoyerLeCode($utilisateur, $requete->ip());
            }
        }

        return Enveloppe::donnee([
            'message' => 'Si ce compte attend une vérification, un code à 6 chiffres vient d\'être envoyé.',
        ]);
    }

    /**
     * `POST /auth/otp/verify`
     *
     * C'est ICI que le compte s'ouvre, et donc ici que le premier jeton est
     * émis. Émettre le jeton à l'inscription aurait donné un accès complet à
     * quelqu'un qui n'a jamais prouvé tenir l'adresse déclarée.
     */
    public function verifierLeCode(Request $requete, InscriptionCitoyen $inscriptions): JsonResponse
    {
        $donnees = $requete->validate([
            'email' => ['required', 'string', 'email', 'max:180'],
            'code' => ['required', 'digits:6'],
            'appareil' => ['nullable', 'string', 'max:60'],
        ]);

        $utilisateur = User::where('email', mb_strtolower(trim($donnees['email'])))->first();

        if ($utilisateur === null) {
            // Même réponse que pour un code faux : un compte inexistant ne se
            // distingue pas d'une erreur de saisie.
            return Enveloppe::erreur(CodeErreur::Validation, 'Code incorrect. Vérifiez votre saisie.', [
                'code' => ['Code incorrect. Vérifiez votre saisie.'],
            ]);
        }

        $resultat = $inscriptions->activer($utilisateur, $donnees['code']);

        if (! $resultat->estValide()) {
            return Enveloppe::erreur(CodeErreur::Validation, $resultat->message(), [
                'code' => [$resultat->message()],
            ]);
        }

        return JetonPersonnel::reponse($utilisateur, $donnees['appareil'] ?? null);
    }

    /**
     * `POST /auth/password/forgot`
     *
     * Réponse toujours identique, y compris quand le débit est saturé — c'est
     * le service qui le garantit. Un 429 visible ici dirait « cette adresse
     * intéresse quelqu'un ».
     */
    public function motDePasseOublie(Request $requete, ReinitialisationMotDePasse $reinitialisations): JsonResponse
    {
        $donnees = $requete->validate([
            'email' => ['required', 'string', 'email', 'max:180'],
        ]);

        $reinitialisations->demander($donnees['email'], $requete->ip());

        return Enveloppe::donnee([
            'message' => 'Si un compte existe pour cette adresse, un code à 6 chiffres vient d\'être envoyé.',
        ]);
    }

    /**
     * `POST /auth/password/reset`
     *
     * Le service révoque TOUT — sessions web, cookies de mémorisation et
     * jetons d'API, y compris celui qui aurait servi à appeler. Le nouveau
     * jeton renvoyé ici est donc le seul vivant : le client repart d'une
     * connexion propre plutôt que de recycler l'ancien.
     */
    public function reinitialiserLeMotDePasse(
        Request $requete,
        ReinitialisationMotDePasse $reinitialisations,
    ): JsonResponse {
        $donnees = $requete->validate([
            'email' => ['required', 'string', 'email', 'max:180'],
            'code' => ['required', 'digits:6'],
            'mot_de_passe' => ['required', 'string', Password::defaults()],
            'appareil' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $utilisateur = $reinitialisations->reinitialiser(
                $donnees['email'],
                $donnees['code'],
                $donnees['mot_de_passe'],
            );
        } catch (CodeRefuse $refus) {
            return Enveloppe::erreur(CodeErreur::Validation, $refus->resultat->message(), [
                'code' => [$refus->resultat->message()],
            ]);
        }

        return JetonPersonnel::reponse($utilisateur, $donnees['appareil'] ?? null);
    }
}
