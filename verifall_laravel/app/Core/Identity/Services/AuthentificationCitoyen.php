<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Identity\Exceptions\CompteSuspendu;
use App\Core\Identity\Exceptions\CompteVerrouille;
use App\Core\Identity\Exceptions\IdentifiantsInvalides;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Support\Facades\Hash;

/**
 * Vérifie un couple adresse / mot de passe, et rien d'autre.
 *
 * POURQUOI CE SERVICE EXISTE. La connexion vivait entièrement dans le
 * composant Livewire. L'API mobile en a besoin à l'identique — verrouillage
 * progressif, égalisation du temps de réponse, refus des comptes non activés,
 * journal de sécurité. Recopier cette suite dans un contrôleur aurait produit
 * deux chemins qui se ressemblent aujourd'hui et divergent demain ; et c'est
 * toujours le moins testé des deux qui reste faux.
 *
 * Ce service ne DÉMARRE PAS de session et n'émet PAS de jeton : il constate
 * une identité. Le web enchaîne sur `Auth::login()` + régénération de session,
 * le mobile sur `createToken()`. Mélanger les deux ici aurait obligé chaque
 * appelant à défaire ce que l'autre voulait.
 */
final class AuthentificationCitoyen
{
    public function __construct(
        private readonly LoginThrottleService $throttle,
        private readonly SecurityLogService $journal,
        private readonly IdentityHasher $hasher,
    ) {}

    /**
     * @throws CompteVerrouille trop de tentatives pour cette identité
     * @throws IdentifiantsInvalides adresse inconnue, mot de passe faux, ou compte non activé
     * @throws CompteSuspendu mot de passe BON, mais compte suspendu
     */
    public function authentifier(string $email, string $motDePasse, string $ip): User
    {
        $identite = mb_strtolower(trim($email));

        if ($this->throttle->estVerrouille($identite, $ip)) {
            $secondes = $this->throttle->secondesRestantes($identite, $ip);
            $this->journal->loginVerrouille($identite, $secondes);

            throw new CompteVerrouille($secondes);
        }

        $utilisateur = User::where('email', $identite)->first();

        /*
         | Le mot de passe est TOUJOURS vérifié, même sans compte trouvé.
         |
         | Sans ce hachage à vide, l'absence de compte répondrait en une
         | microseconde là où un compte existant coûte le temps d'un Argon2id :
         | la différence suffit à énumérer les inscrits sans jamais deviner un
         | seul mot de passe.
         */
        $motDePasseValide = $utilisateur !== null
            ? Hash::check($motDePasse, $utilisateur->password)
            : Hash::check($motDePasse, $this->hachageLeurre());

        if ($utilisateur === null || ! $motDePasseValide) {
            $echecs = $this->throttle->enregistrerEchec($identite, $ip);
            $this->journal->loginEchoue($identite, $echecs);

            throw new IdentifiantsInvalides;
        }

        // Compte créé mais OTP jamais validé : on ne connecte pas, et on ne
        // révèle pas non plus que le compte existe — même exception.
        if (! $utilisateur->estActive()) {
            $this->throttle->enregistrerEchec($identite, $ip);

            throw new IdentifiantsInvalides;
        }

        /*
         | La suspension est vérifiée APRÈS le mot de passe, et rend une
         | exception DISTINCTE. Voir le bloc de documentation de
         | `CompteSuspendu` : la placer avant, ou la fondre dans
         | `IdentifiantsInvalides`, ferait l'un ou l'autre de deux défauts —
         | un annuaire des inscrits, ou une sanction que la personne prend pour
         | une panne.
         |
         | Le compteur d'échecs n'est PAS incrémenté : ce n'est pas une
         | tentative ratée, c'est un refus assumé. L'incrémenter finirait par
         | verrouiller un compte déjà suspendu, et brouillerait la lecture des
         | tentatives d'intrusion.
         */
        if ($utilisateur->estSuspendu()) {
            throw new CompteSuspendu($utilisateur->suspension_reason);
        }

        $this->throttle->reinitialiser($identite, $ip);

        $utilisateur->forceFill([
            'last_login_at' => now(),
            'last_login_ip_hash' => $this->hasher->hash($ip),
        ])->save();

        $this->journal->loginReussi($utilisateur);

        return $utilisateur;
    }

    /**
     * Hachage factice servant à égaliser le temps de réponse.
     *
     * Généré une fois et mémorisé : le recalculer à chaque appel coûterait
     * plus cher qu'une vérification réelle et créerait l'écart inverse.
     */
    private function hachageLeurre(): string
    {
        static $leurre = null;

        return $leurre ??= Hash::make('mot-de-passe-inexistant-'.config('app.key'));
    }
}
