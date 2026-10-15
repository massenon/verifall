<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Identity\Exceptions\CodeRefuse;
use App\Enums\OtpPurpose;
use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Mot de passe oublié — demande d'un code puis réinitialisation.
 *
 * ÉCART ASSUMÉ AVEC LE PROTOTYPE : celui-ci annonce « un lien de
 * réinitialisation ». SECURITE_RGPD impose le MÊME CONTRAT OTP que
 * l'inscription — code à 6 chiffres, 10 minutes, usage unique. Un lien
 * cliquable transite dans les transferts de courriel et les historiques, là où
 * un code se saisit et se périme.
 *
 * ANTI-ÉNUMÉRATION : `demander()` ne dit JAMAIS si l'adresse correspond à un
 * compte, et la limitation de débit s'applique AVANT la recherche — sinon la
 * présence d'un plafond révélerait elle-même l'existence de l'adresse.
 *
 * POURQUOI CE SERVICE EXISTE. Le point capital d'une réinitialisation est la
 * révocation de TOUT ce qui était ouvert : sessions web, cookies « se souvenir
 * de moi » et jetons d'API. Si l'on change le mot de passe parce qu'un tiers y
 * avait accès, laisser un jeton mobile vivant rend l'opération inutile. Cette
 * liste ne doit exister qu'à UN endroit : le jour où un quatrième support
 * d'authentification apparaît, l'oublier dans la seconde copie serait une
 * porte laissée ouverte que personne ne verrait.
 */
final class ReinitialisationMotDePasse
{
    /** 3 demandes par 10 minutes (SECURITE_RGPD §1). */
    private const DEMANDES = 3;

    private const FENETRE_SECONDES = 600;

    public function __construct(
        private readonly OtpService $otp,
        private readonly SecurityLogService $journal,
    ) {}

    /**
     * Demande un code. Ne révèle rien, quoi qu'il arrive.
     */
    public function demander(string $email, ?string $ip = null): void
    {
        $identite = mb_strtolower(trim($email));
        $cle = 'password-reset:'.$identite;

        if (RateLimiter::tooManyAttempts($cle, self::DEMANDES)) {
            $this->journal->record(SecurityLogService::DEBIT_LIMITE, 'warning', $identite);

            return;
        }

        RateLimiter::hit($cle, self::FENETRE_SECONDES);

        $utilisateur = User::where('email', $identite)->first();

        // Le code n'est émis que si le compte existe — mais l'appelant, lui,
        // affiche exactement la même chose dans les deux cas.
        if ($utilisateur === null) {
            return;
        }

        $code = $this->otp->issue($identite, OtpPurpose::PasswordReset, $ip);
        Mail::to($identite)->queue(new OtpCodeMail($code, OtpPurpose::PasswordReset));
    }

    /**
     * Vérifie le code, change le mot de passe, ferme tout le reste.
     *
     * @throws CodeRefuse
     */
    public function reinitialiser(string $email, string $code, string $motDePasse): User
    {
        $identite = mb_strtolower(trim($email));
        $resultat = $this->otp->verify($identite, OtpPurpose::PasswordReset, $code);

        if (! $resultat->estValide()) {
            $this->journal->record(SecurityLogService::OTP_REJOUE, $resultat->severite(), $identite);

            throw new CodeRefuse($resultat);
        }

        $utilisateur = User::where('email', $identite)->first();

        if ($utilisateur === null) {
            // Code valide sans compte : incohérent, donc suspect. Message
            // générique, incident journalisé.
            $this->journal->record(SecurityLogService::OTP_FORCE, 'critical', $identite);

            throw new CodeRefuse(OtpVerification::Invalide);
        }

        DB::transaction(function () use ($utilisateur, $motDePasse): void {
            $utilisateur->forceFill([
                'password' => $motDePasse,
                // Un compte qui réinitialise son mot de passe prouve la
                // maîtrise de sa boîte : on en profite pour l'activer s'il
                // était resté en attente de vérification.
                'otp_verified_at' => $utilisateur->otp_verified_at ?? now(),
            ])->save();

            // Révoque les cookies « se souvenir de moi » déjà émis.
            $utilisateur->setRememberToken(Str::random(60));
            $utilisateur->save();

            DB::table('sessions')->where('user_id', $utilisateur->id)->delete();

            /*
             | Les jetons d'API partent AUSSI. Un téléphone volé garde son
             | jeton indéfiniment : ne fermer que les sessions du navigateur
             | laisserait ouverte la porte la plus difficile à refermer, celle
             | qu'on ne voit pas.
             */
            $utilisateur->tokens()->delete();
        });

        $this->journal->record('password_reset', 'warning', $identite, $utilisateur);

        return $utilisateur;
    }
}
