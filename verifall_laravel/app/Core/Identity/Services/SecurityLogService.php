<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\SecurityLog;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;

/**
 * Journal de sécurité — alimente la page « Alertes Intrusion ».
 *
 * Distinct du journal d'audit : celui-ci trace ce que font les utilisateurs
 * légitimes, celui-là ce qui ressemble à une attaque. Les mélanger noierait
 * les secondes dans les premières.
 *
 * Rien de ce qui est écrit ici n'est en clair : ni l'identité visée, ni l'IP.
 * Un journal de sécurité qui fuiterait ne doit pas devenir lui-même une base
 * d'adresses e-mail exploitable.
 */
final class SecurityLogService
{
    public const LOGIN_ECHOUE = 'login_failed';

    public const LOGIN_VERROUILLE = 'login_locked';

    public const LOGIN_REUSSI = 'login_success';

    public const OTP_REJOUE = 'otp_replayed';

    public const OTP_FORCE = 'otp_bruteforce';

    public const DEBIT_LIMITE = 'rate_limited';

    public const UPLOAD_REFUSE = 'upload_rejected';

    public const DECONNEXION = 'logout';

    public function __construct(
        private readonly IdentityHasher $hasher,
        private readonly JournalDesConnexions $connexions,
    ) {}

    /**
     * @param  array<string, mixed>  $contexte
     */
    public function record(
        string $event,
        string $severity = 'info',
        ?string $identity = null,
        ?User $user = null,
        array $contexte = [],
    ): SecurityLog {
        return SecurityLog::create([
            'event' => $event,
            'severity' => $severity,
            'user_id' => $user?->id,
            'identity_hash' => $identity !== null ? $this->hasher->hash($identity) : null,
            'ip_hash' => ($ip = request()->ip()) !== null ? $this->hasher->hash($ip) : null,
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 512),
            'context' => $contexte === [] ? null : $contexte,
        ]);
    }

    /**
     * Échec de connexion — la sévérité monte avec le nombre de tentatives.
     *
     * Une faute de frappe et une attaque par dictionnaire produisent le même
     * événement ; seul le compteur les distingue, d'où la gradation.
     */
    public function loginEchoue(string $identity, int $echecs): SecurityLog
    {
        $severite = match (true) {
            $echecs >= LoginThrottleService::SEUIL_ALERTE => 'critical',
            $echecs >= 5 => 'warning',
            default => 'info',
        };

        // L'historique du COMPTE visé (s'il existe), pour que sa fiche montre
        // la série d'échecs qui précède une connexion réussie.
        $this->connexions->echec($identity, 'echouee', JournalDesConnexions::espaceDe(request()));

        return $this->record(self::LOGIN_ECHOUE, $severite, $identity, contexte: [
            'echecs_cumules' => $echecs,
        ]);
    }

    public function loginVerrouille(string $identity, int $secondes): SecurityLog
    {
        $this->connexions->echec($identity, 'bloquee', JournalDesConnexions::espaceDe(request()));

        return $this->record(self::LOGIN_VERROUILLE, 'warning', $identity, contexte: [
            'reouverture_dans_secondes' => $secondes,
        ]);
    }

    public function loginReussi(User $user): SecurityLog
    {
        // Les succès sont journalisés aussi : c'est ce qui permet de repérer
        // une connexion aboutie APRÈS une série d'échecs — le signal d'une
        // compromission réussie, invisible si l'on ne trace que les échecs.
        return $this->record(self::LOGIN_REUSSI, 'info', $user->email, $user);
    }
}
