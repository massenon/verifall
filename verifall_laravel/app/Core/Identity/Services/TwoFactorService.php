<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Double authentification TOTP (RFC 6238) — SECURITE_LOGIN.
 *
 * Exigee du personnel dont le role figure dans
 * `config('verifall.securite.deux_facteurs.roles')`, facultative pour le
 * citoyen. Voir `exigePour()`.
 *
 * Trois précautions structurent l'implémentation :
 *
 *  1. Le secret est chiffré au repos (cast `encrypted` sur le modèle). Une
 *     fuite de la base ne doit pas permettre de générer les codes.
 *  2. Les codes de récupération sont HACHÉS, comme des mots de passe : ils
 *     ouvrent le compte à eux seuls, les stocker en clair reviendrait à
 *     ranger le double des clés sous le paillasson.
 *  3. Une fenêtre de tolérance d'UN intervalle seulement. Élargir accepterait
 *     des codes vieux de plusieurs minutes et multiplierait d'autant les
 *     valeurs valides à un instant donné.
 */
final class TwoFactorService
{
    /** Nombre de codes de récupération émis. */
    public const NB_CODES_RECUPERATION = 8;

    /**
     * Tolérance en intervalles de 30 s, de part et d'autre.
     *
     * 1 couvre l'horloge légèrement désynchronisée d'un téléphone sans ouvrir
     * une fenêtre exploitable.
     */
    private const FENETRE = 1;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Prépare l'activation : secret + codes de récupération.
     *
     * Le secret n'est PAS confirmé à ce stade — la 2FA ne devient effective
     * qu'après vérification d'un premier code, sinon une erreur de scan
     * enfermerait l'utilisateur dehors.
     *
     * @return array{secret: string, codes: list<string>}
     */
    public function preparer(User $utilisateur): array
    {
        $secret = $this->google2fa->generateSecretKey();
        $codes = $this->genererCodesRecuperation();

        /*
         | ON PURGE AVANT D'ÉCRIRE, ET CE N'EST PAS UNE PRÉCAUTION DE STYLE.
         |
         | `save()` appelle `isDirty()`, qui compare l'état courant à l'état
         | d'origine — et cette comparaison DÉCHIFFRE l'ancien
         | `two_factor_secret` pour savoir s'il a changé. Après une rotation
         | d'`APP_KEY`, ce déchiffrement lève « The MAC is invalid » avant même
         | que la nouvelle valeur ait la moindre chance d'être écrite.
         |
         | Le défaut était vicieux : `estActive()` détecte correctement un
         | secret illisible et renvoie la personne vers l'ENRÔLEMENT, seul
         | chemin qui remette le compte en état. Sauf que l'enrôlement passe
         | ici, et plantait. Le garde-fou conduisait droit dans le mur qu'il
         | était censé contourner.
         |
         | `reinitialiser()` écrit par le constructeur de requêtes pour cette
         | raison exacte. On s'en sert : une fois les colonnes vidées, plus
         | rien d'illisible ne subsiste et `save()` peut faire son travail.
         */
        $this->reinitialiser($utilisateur);

        $utilisateur->forceFill([
            'two_factor_secret' => $secret,
            // Hachés un à un : le tableau en clair n'existe que dans cette
            // réponse, et l'utilisateur est invité à le conserver.
            'two_factor_recovery_codes' => array_map(
                static fn (string $code): string => Hash::make($code),
                $codes,
            ),
            'two_factor_confirmed_at' => null,
        ])->save();

        return ['secret' => $secret, 'codes' => $codes];
    }

    /**
     * Confirme l'activation avec un premier code valide.
     */
    public function confirmer(User $utilisateur, string $code): bool
    {
        if (! $this->verifierCode($utilisateur, $code)) {
            return false;
        }

        $utilisateur->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    /**
     * Vérifie un code TOTP.
     */
    public function verifierCode(User $utilisateur, string $code): bool
    {
        $secret = $this->secretLisible($utilisateur);

        if ($secret === null || $secret === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, trim($code), self::FENETRE);
    }

    /**
     * Consomme un code de récupération.
     *
     * Chaque code ne sert QU'UNE FOIS : il est retiré de la liste dès qu'il a
     * fonctionné. Sans cela, un code intercepté resterait valable indéfiniment.
     */
    public function consommerCodeRecuperation(User $utilisateur, string $code): bool
    {
        try {
            $codes = $utilisateur->two_factor_recovery_codes ?? [];
        } catch (DecryptException) {
            // Même cause que pour le secret : une clé d'application changée.
            // Voir `secretLisible()`.
            return false;
        }

        $propre = trim($code);

        foreach ($codes as $index => $hache) {
            if (Hash::check($propre, $hache)) {
                unset($codes[$index]);

                $utilisateur->forceFill([
                    'two_factor_recovery_codes' => array_values($codes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie un code TOTP OU un code de récupération.
     */
    public function verifier(User $utilisateur, string $code): bool
    {
        return $this->verifierCode($utilisateur, $code)
            || $this->consommerCodeRecuperation($utilisateur, $code);
    }

    /**
     * Ce compte doit-il presenter un second facteur pour entrer ?
     *
     * La reponse vient de la CONFIGURATION, jamais de l'etat du compte. Se fier
     * a `estActive()` pour trancher reviendrait a laisser chacun decider s'il
     * veut etre protege : il suffirait de n'avoir jamais scanne le code QR pour
     * que l'obligation disparaisse.
     *
     * Un compte qui cumule plusieurs roles est traite par le PLUS EXIGEANT : un
     * seul role listE suffit a reclamer le second facteur. Un administrateur a
     * qui l'on ajoute un role de moderation ne doit pas voir sa protection
     * baisser parce qu'on lui a confie une tache de plus.
     */
    public function exigePour(User $utilisateur): bool
    {
        /** @var list<string> $roles */
        $roles = (array) config('verifall.securite.deux_facteurs.roles', []);

        foreach ($roles as $role) {
            if ($utilisateur->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function estActive(User $utilisateur): bool
    {
        /*
         | « Confirmée » NE SUFFIT PAS : encore faut-il que le secret soit
         | lisible. Un secret chiffré avec une clé d'application qui n'est plus
         | celle en service ne protège plus personne — il enferme seulement son
         | propriétaire dehors.
         |
         | En répondant faux ici, on renvoie la personne vers l'ENRÔLEMENT,
         | c'est-à-dire vers le seul chemin qui la remette en état. Le mot de
         | passe a déjà été vérifié à ce stade : il n'y a aucune perte de
         | sécurité à reconstituer un facteur qui n'en était plus un.
         */
        return $utilisateur->two_factor_confirmed_at !== null
            && $this->secretLisible($utilisateur) !== null;
    }

    /**
     * Purge un facteur devenu illisible, pour permettre un nouvel enrôlement.
     *
     * L'écriture passe par le CONSTRUCTEUR DE REQUÊTES, et non par le modèle.
     * C'est le point délicat : `save()` compare l'état courant à l'état
     * d'origine pour savoir ce qui a changé, et cette comparaison déchiffre les
     * anciennes valeurs — donc lève, alors même qu'on cherchait à s'en
     * débarrasser. On ne peut pas effacer ces colonnes par le modèle qui bute
     * dessus.
     */
    public function reinitialiser(User $utilisateur): void
    {
        DB::table('users')->where('id', $utilisateur->id)->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $utilisateur->setRawAttributes(array_merge($utilisateur->getRawOriginal(), [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]), true);
    }

    /**
     * Le secret, ou `null` s'il est illisible.
     *
     | Le cas se produit après une rotation d'`APP_KEY` : le déchiffrement lève
     * `DecryptException` (« The MAC is invalid »). Sans ce filet, l'exception
     * remontait jusqu'à l'écran de connexion — **tout le personnel enfermé
     * dehors sur une erreur 500**, et aucun moyen de s'en sortir par
     * l'interface.
     *
     * L'incident est journalisé : c'est une anomalie d'exploitation, pas un
     * événement ordinaire, et personne ne doit la découvrir par hasard.
     */
    private function secretLisible(User $utilisateur): ?string
    {
        try {
            $secret = $utilisateur->two_factor_secret;
        } catch (DecryptException) {
            Log::warning("Secret de double authentification illisible : clé d'application changée ?", [
                'user_id' => $utilisateur->id,
            ]);

            return null;
        }

        return $secret === null || $secret === '' ? null : (string) $secret;
    }

    /**
     * Désactivation — exige mot de passe ET code valide (SECURITE_LOGIN).
     *
     * Sans le second facteur, un attaquant disposant du mot de passe pourrait
     * simplement retirer la protection qu'il vient de rencontrer.
     */
    public function desactiver(User $utilisateur, string $motDePasse, string $code): bool
    {
        if (! Hash::check($motDePasse, $utilisateur->password)) {
            return false;
        }

        if (! $this->verifier($utilisateur, $code)) {
            return false;
        }

        $utilisateur->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return true;
    }

    /**
     * URI otpauth:// à encoder en QR pour l'application d'authentification.
     */
    public function uriProvisioning(User $utilisateur): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            $utilisateur->email,
            (string) $utilisateur->two_factor_secret,
        );
    }

    /**
     * QR code en SVG inline.
     *
     * SVG et non image distante : le secret ne doit jamais transiter par un
     * générateur de QR tiers, ce que ferait une URL d'API externe.
     */
    public function qrCodeSvg(User $utilisateur): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd));

        return $writer->writeString($this->uriProvisioning($utilisateur));
    }

    /**
     * @return list<string>
     */
    private function genererCodesRecuperation(): array
    {
        return array_map(
            static fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)),
            range(1, self::NB_CODES_RECUPERATION),
        );
    }
}
