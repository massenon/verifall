<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Support\Carbon;

/**
 * Codes à usage unique — inscription, réinitialisation, défi de connexion.
 *
 * Cinq garanties, toutes vérifiées par les tests de SECURITE_LOGIN :
 *
 *  1. Le code n'est jamais stocké en clair, ni journalisé.
 *  2. Il expire (voir `TTL_MINUTES`) et n'est utilisable QU'UNE FOIS — un code
 *     rejoué est
 *     refusé même s'il n'a pas expiré.
 *  3. Cinq tentatives de vérification maximum, ensuite le code est détruit :
 *     un code à 6 chiffres se force autrement en quelques minutes.
 *  4. Émettre un nouveau code invalide le précédent : deux codes valides en
 *     parallèle doubleraient la surface d'attaque.
 *  5. La comparaison est en temps constant (hash_equals).
 *
 * L'anti-énumération n'est PAS traitée ici mais dans l'appelant : ce service
 * renvoie toujours la même chose qu'un compte existe ou non, et c'est à
 * l'écran de ne rien laisser filtrer.
 */
final class OtpService
{
    /**
     * Durée de vie, en minutes — comptée depuis l'ÉMISSION, pas la réception.
     *
     * C'est toute la difficulté. Le courriel part par la file (`Mail::queue`),
     * et sur un hébergement mutualisé le worker ne tourne pas en permanence :
     * `docs/CRONS.md` le fait passer toutes les cinq minutes. Le code peut donc
     * dormir cinq minutes avant même de partir, plus le trajet SMTP.
     *
     * Avec dix minutes, il arrivait couramment avec trois ou quatre minutes de
     * validité — parfois aucune. La personne saisissait le code qu'elle venait
     * de recevoir et s'entendait répondre qu'il avait expiré : de son point de
     * vue, la plateforme est cassée, et elle a raison de le penser.
     *
     * Vingt minutes couvrent le cycle de la file, le trajet du courriel et le
     * temps de recopier six chiffres. Ce que cela coûte est mesuré : cinq
     * tentatives au maximum sur un million de valeurs, l'attente n'ouvre aucune
     * fenêtre de force brute.
     *
     * Le vrai remède reste de raccourcir le cycle de la file (un cron à la
     * minute). Cette durée absorbe le cas où il ne l'est pas.
     */
    public const TTL_MINUTES = 20;

    /** Tentatives de vérification avant destruction du code. */
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly IdentityHasher $hasher) {}

    /**
     * Émet un code et renvoie sa valeur EN CLAIR — la seule et unique fois où
     * elle existe. L'appelant l'envoie par courriel puis l'oublie.
     */
    public function issue(string $identity, OtpPurpose $purpose, ?string $ip = null): string
    {
        $identityHash = $this->hasher->hash($identity);

        // Un seul code valide à la fois par (identité, usage).
        OtpCode::query()
            ->where('identity_hash', $identityHash)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();

        OtpCode::create([
            'identity_hash' => $identityHash,
            'purpose' => $purpose->value,
            'code_hash' => $this->hasher->hash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_hash' => $ip !== null ? $this->hasher->hash($ip) : null,
        ]);

        return $code;
    }

    /**
     * Vérifie un code et le consomme si valide.
     *
     * Renvoie un résultat typé plutôt qu'un booléen : l'appelant doit pouvoir
     * distinguer « expiré » de « faux » pour l'affichage, tout en gardant un
     * message générique côté écran public.
     */
    public function verify(string $identity, OtpPurpose $purpose, string $code): OtpVerification
    {
        $otp = OtpCode::query()
            ->where('identity_hash', $this->hasher->hash($identity))
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null) {
            return OtpVerification::Absent;
        }

        if ($otp->expires_at->isPast()) {
            return OtpVerification::Expire;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            // Le code est brûlé : on le consomme pour qu'aucune tentative
            // ultérieure ne puisse aboutir, même avec la bonne valeur.
            $otp->forceFill(['consumed_at' => now()])->save();

            return OtpVerification::TropDeTentatives;
        }

        // Comparaison en temps constant : une comparaison naïve laisserait
        // fuir la longueur du préfixe correct par le temps de réponse.
        if (! hash_equals($otp->code_hash, $this->hasher->hash($code))) {
            $otp->increment('attempts');

            return OtpVerification::Invalide;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return OtpVerification::Valide;
    }

    /**
     * Purge des codes expirés — appelée par la commande planifiée.
     */
    public function purgeExpired(?Carbon $avant = null): int
    {
        return OtpCode::query()
            ->where('expires_at', '<', $avant ?? now()->subDay())
            ->delete();
    }

    /**
     * Six chiffres tirés d'une source cryptographique.
     *
     * random_int et non rand() : un générateur prévisible rendrait le code
     * devinable sans même l'intercepter.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }
}
