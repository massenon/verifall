<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'audit (SECURITE_RGPD §4) — alimente la page « Journal d'Audit ».
 *
 * Trace toute action sensible du back-office : qui, quoi, sur quoi, depuis où,
 * et le diff avant/après. C'est ce diff qui distingue un journal exploitable
 * d'une simple liste d'événements — sans lui, « untel a modifié le cours B »
 * ne dit pas ce qui a changé, et le journal ne sert à rien lors d'un litige.
 *
 * Le nom de l'acteur est DÉNORMALISÉ : après anonymisation RGPD d'un compte,
 * l'historique doit rester lisible même si la personne a disparu.
 */
final class AuditLogService
{
    public const CONNEXION = 'login';

    public const DECONNEXION = 'logout';

    public const CREATION = 'create';

    public const MODIFICATION = 'update';

    public const SUPPRESSION = 'delete';

    public const PUBLICATION = 'publish';

    public const CHANGEMENT_ROLE = 'role_change';

    public const EXPORT_DONNEES = 'data_export';

    public function __construct(private readonly IdentityHasher $hasher) {}

    /**
     * @param  array<string, mixed>|null  $avant
     * @param  array<string, mixed>|null  $apres
     */
    public function record(
        string $action,
        string $module,
        ?User $acteur = null,
        ?Model $cible = null,
        ?array $avant = null,
        ?array $apres = null,
        ?string $cibleLibelle = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $acteur?->id,
            'actor_label' => $acteur?->name,
            'action' => $action,
            'module' => $module,
            'target_type' => $cible !== null ? $cible::class : null,
            'target_uuid' => $cible?->getAttribute('uuid'),
            'target_label' => $cibleLibelle,
            'before' => $this->purger($avant),
            'after' => $this->purger($apres),
            'ip_hash' => ($ip = request()->ip()) !== null ? $this->hasher->hash($ip) : null,
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 512),
        ]);
    }

    /**
     * Connexion au back-office — exigée nommément par LOGIN_DECONNEXION.
     */
    public function connexionStaff(User $acteur): AuditLog
    {
        return $this->record(self::CONNEXION, 'auth', $acteur, cibleLibelle: $acteur->email);
    }

    public function deconnexionStaff(User $acteur): AuditLog
    {
        return $this->record(self::DECONNEXION, 'auth', $acteur, cibleLibelle: $acteur->email);
    }

    /**
     * Écrit un diff en ne conservant que ce qui a RÉELLEMENT changé.
     *
     * @param  array<string, mixed>  $avant
     * @param  array<string, mixed>  $apres
     */
    public function recordDiff(
        string $module,
        User $acteur,
        Model $cible,
        array $avant,
        array $apres,
        ?string $cibleLibelle = null,
    ): AuditLog {
        $modifies = array_keys(array_diff_assoc(
            array_map(static fn ($v): string => is_scalar($v) ? (string) $v : json_encode($v), $apres),
            array_map(static fn ($v): string => is_scalar($v) ? (string) $v : json_encode($v), $avant),
        ));

        // Journaliser l'objet entier noierait le changement réel dans le bruit ;
        // le relecteur doit voir d'un coup d'œil ce qui a bougé.
        return $this->record(
            self::MODIFICATION,
            $module,
            $acteur,
            $cible,
            array_intersect_key($avant, array_flip($modifies)),
            array_intersect_key($apres, array_flip($modifies)),
            $cibleLibelle,
        );
    }

    /**
     * Retire du diff ce qui ne doit JAMAIS être journalisé.
     *
     * Un journal d'audit est consulté par des administrateurs et exporté : y
     * laisser un mot de passe haché ou un secret 2FA en ferait une cible plus
     * intéressante que la table users elle-même.
     *
     * @param  array<string, mixed>|null  $donnees
     * @return array<string, mixed>|null
     */
    private function purger(?array $donnees): ?array
    {
        if ($donnees === null) {
            return null;
        }

        $interdits = [
            'password', 'password_confirmation', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes',
            'code_hash', 'otp_code', 'token',
        ];

        foreach ($interdits as $cle) {
            if (array_key_exists($cle, $donnees)) {
                $donnees[$cle] = '[masqué]';
            }
        }

        return $donnees;
    }
}
