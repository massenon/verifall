<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\DataExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Droit à l'effacement — suppression du compte en deux temps.
 *
 * Le prototype demande de taper « SUPPRIMER » et efface. Trois raisons de faire
 * autrement, sans rien retirer à ce geste :
 *
 *   1. Taper un mot ne prouve pas l'identité. Un écran laissé déverrouillé
 *      suffirait à détruire le compte de quelqu'un d'autre. Mot de passe ET
 *      code envoyé par courriel sont donc exigés en plus.
 *   2. Un effacement immédiat ne laisse aucune place au regret. Le compte est
 *      fermé sur-le-champ — plus aucune connexion — mais les données ne
 *      disparaissent qu'au bout de 30 jours, délai pendant lequel un lien
 *      d'annulation reste valable.
 *   3. Tout supprimer casserait ce qui s'y rattache. L'effacement définitif est
 *      une ANONYMISATION : les données personnelles s'en vont, la ligne reste.
 */
final class SuppressionCompteService
{
    /** Délai de rétractation, en jours. */
    public const JOURS_RETRACTATION = 30;

    /**
     * Mot que le prototype demande de saisir. Conservé tel quel.
     *
     * Reste la valeur de référence : c'est elle que documente le contrat d'API
     * et qu'envoie l'application Flutter. La renommer casserait les deux.
     */
    public const MOT_DE_CONFIRMATION = 'SUPPRIMER';

    /**
     * Les mots acceptés, un par langue de l'interface.
     *
     * L'écran anglais affiche « Type DELETE » ; comparer la saisie au seul mot
     * français aurait refusé l'anglophone qui tape exactement ce qu'on lui
     * demande, sur le seul écran où l'on ne peut pas se permettre un refus
     * incompréhensible. On accepte donc les deux mots PARTOUT plutôt que le mot
     * de la langue courante : quelqu'un qui bascule d'anglais en français entre
     * l'affichage et la validation ne doit pas être bloqué non plus.
     *
     * Élargir ce que l'on accepte n'affaiblit rien. Le mot ne prouve que
     * l'intention ; l'identité, elle, reste prouvée par le mot de passe puis
     * par le code envoyé par courriel.
     */
    public const MOTS_DE_CONFIRMATION = [self::MOT_DE_CONFIRMATION, 'DELETE'];

    /**
     * La saisie est-elle le mot de confirmation, dans l'une des langues ?
     *
     * La comparaison vit ici et non dans le composant : l'écran citoyen et
     * l'API posaient la même question chacun de son côté, et deux copies d'une
     * règle finissent toujours par diverger.
     *
     * LA CASSE RESTE EXIGÉE. On accepte un mot de plus, pas une saisie plus
     * approximative : « supprimer » en minuscules est toujours refusé. Taper
     * des capitales demande un geste de plus, et c'est précisément ce geste
     * que le prototype réclame avant d'effacer un compte. Normaliser la casse
     * aurait allégé le seul écran où l'on souhaite que l'utilisateur ralentisse.
     */
    public function confirmationValide(string $saisie): bool
    {
        return in_array(trim($saisie), self::MOTS_DE_CONFIRMATION, true);
    }

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly SecurityLogService $securite,
    ) {}

    /**
     * Le mot de passe fourni est-il celui du compte ?
     */
    public function motDePasseCorrect(User $utilisateur, string $motDePasse): bool
    {
        $correct = Hash::check($motDePasse, $utilisateur->password);

        if (! $correct) {
            // Un mot de passe raté ICI mérite plus d'attention qu'ailleurs :
            // c'est une tentative de destruction, pas une tentative d'accès.
            $this->securite->record(
                SecurityLogService::LOGIN_ECHOUE, 'warning', $utilisateur->email, $utilisateur,
                ['contexte' => 'suppression de compte'],
            );
        }

        return $correct;
    }

    /**
     * Ferme le compte et arme le compte à rebours.
     *
     * L'appelant doit avoir vérifié le mot de passe ET le code à usage unique :
     * ce service ne reconstitue pas ces preuves, il agit une fois qu'elles sont
     * faites.
     */
    public function programmer(User $utilisateur): User
    {
        return DB::transaction(function () use ($utilisateur): User {
            /*
             | Les archives d'export partent tout de suite, sans attendre les
             | 30 jours : ce sont des copies complètes des données de la
             | personne, et il n'y a aucune raison qu'elles survivent à sa
             | demande d'effacement.
             */
            foreach ($utilisateur->dataExports()->get() as $archive) {
                $archive->detruire();
            }

            $utilisateur->forceFill([
                'deletion_requested_at' => now(),
            ])->save();

            // Journalisé avant la fermeture : après, le compte n'est plus
            // interrogeable par les requêtes ordinaires.
            $this->audit->record(
                AuditLogService::SUPPRESSION, 'profile', $utilisateur,
                cible: $utilisateur,
                cibleLibelle: 'Suppression de compte demandée',
            );

            $utilisateur->delete();

            return $utilisateur;
        });
    }

    /**
     * Annule une demande, tant que le délai court.
     *
     * Passé l'échéance, l'anonymisation a eu lieu : il n'y a plus rien à
     * restaurer, et le dire franchement vaut mieux que de rendre un compte vide.
     */
    public function annuler(User $utilisateur): bool
    {
        if ($utilisateur->anonymized_at !== null) {
            return false;
        }

        $utilisateur->restore();

        $utilisateur->forceFill(['deletion_requested_at' => null])->save();

        $this->audit->record(
            AuditLogService::MODIFICATION, 'profile', $utilisateur,
            cible: $utilisateur,
            cibleLibelle: 'Suppression de compte annulée',
        );

        return true;
    }

    /**
     * L'échéance est-elle passée ?
     */
    public function echeanceAtteinte(User $utilisateur): bool
    {
        return $utilisateur->deletion_requested_at !== null
            && $utilisateur->deletion_requested_at
                ->addDays(self::JOURS_RETRACTATION)
                ->isPast();
    }

    /**
     * Effacement définitif — par anonymisation.
     *
     * Ce qui disparaît : nom, adresse, téléphone, profession, ville, bio,
     * secrets d'authentification, préférences, jetons.
     *
     * Ce qui reste : la LIGNE, avec son identifiant, ses compteurs agrégés et
     * la trace de son anonymisation. Détruire la ligne romprait tout ce qui s'y
     * rattache — les journaux d'audit, les certificats émis, les contributions
     * au forum — et transformerait une demande d'effacement en corruption de
     * l'historique d'autres personnes.
     *
     * L'adresse est remplacée par une valeur unique et non par NULL : la
     * colonne est unique, et deux comptes anonymisés se heurteraient.
     */
    public function anonymiser(User $utilisateur): User
    {
        if ($utilisateur->anonymized_at !== null) {
            // Rejouer l'opération écraserait le marqueur lui-même et ferait
            // perdre la date réelle de l'effacement.
            return $utilisateur;
        }

        return DB::transaction(function () use ($utilisateur): User {
            $utilisateur->forceFill([
                'name' => 'Citoyen supprimé',
                'first_name' => 'Citoyen',
                'last_name' => 'supprimé',
                'email' => 'supprime-'.Str::uuid()->toString().'@invalide.local',
                'email_verified_at' => null,
                'phone' => null,
                'profession' => null,
                'city' => null,
                'bio' => null,

                // Un mot de passe aléatoire, jamais communiqué : la colonne
                // n'accepte pas NULL, et laisser l'ancienne empreinte
                // conserverait un élément déduit de la personne.
                'password' => Str::random(64),
                'remember_token' => null,

                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,

                'notification_preferences' => null,
                'otp_verified_at' => null,
                'kyc_verified_at' => null,
                'last_login_at' => null,
                'last_login_ip_hash' => null,

                'anonymized_at' => now(),
            ])->save();

            // Les rôles partent aussi : un compte anonymisé ne doit pouvoir
            // porter aucune autorisation résiduelle.
            $utilisateur->syncRoles([]);

            // Les jetons d'API survivraient sinon à l'effacement et
            // continueraient d'ouvrir l'application mobile.
            $utilisateur->tokens()->delete();

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $utilisateur->id)->delete();

            DataExport::where('user_id', $utilisateur->id)->delete();

            $this->audit->record(
                AuditLogService::SUPPRESSION, 'profile', null,
                cible: $utilisateur,
                cibleLibelle: 'Compte anonymisé (échéance atteinte)',
            );

            return $utilisateur;
        });
    }
}
