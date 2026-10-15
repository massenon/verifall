<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sessions actives d'un compte — bloc « Sessions actives » du prototype.
 *
 * Le pilote de session est la base de données : la liste est donc RÉELLE, pas
 * une maquette. C'est ce qui donne son sens au bouton « Déconnecter » — sans
 * quoi il rassurerait sans rien fermer.
 *
 * Aucune adresse IP n'est affichée. Le prototype montre « Android · Cotonou »,
 * une géolocalisation dont nous ne disposons pas ; l'inventer serait mentir, et
 * afficher l'IP brute ferait de la page un annuaire d'adresses à la portée de
 * quiconque emprunte l'écran quelques secondes.
 */
final class SessionsService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @return list<array{id: string, appareil: string, navigateur: string, derniere: string, courante: bool}>
     */
    public function pour(User $utilisateur, ?string $sessionCourante = null): array
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $utilisateur->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $ligne): array => [
                'id' => (string) $ligne->id,
                'appareil' => $this->appareil((string) $ligne->user_agent),
                'navigateur' => $this->navigateur((string) $ligne->user_agent),
                'derniere' => Carbon::createFromTimestamp((int) $ligne->last_activity)->diffForHumans(),
                'courante' => $sessionCourante !== null && $sessionCourante === (string) $ligne->id,
            ])
            ->all();
    }

    /**
     * Ferme une session précise.
     *
     * L'identifiant est vérifié contre le PROPRIÉTAIRE avant suppression :
     * sans cette clause, un identifiant deviné fermerait la session de
     * n'importe qui — un déni de service à un clic.
     */
    public function revoquer(User $utilisateur, string $sessionId): bool
    {
        $supprimees = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $utilisateur->id)
            ->where('id', $sessionId)
            ->delete();

        if ($supprimees === 0) {
            return false;
        }

        $this->audit->record(
            AuditLogService::DECONNEXION, 'session', $utilisateur,
            cibleLibelle: 'Session distante fermée',
        );

        return true;
    }

    /**
     * Ferme toutes les sessions SAUF celle d'où part la demande.
     *
     * Utilisé après un changement de mot de passe : un mot de passe changé
     * parce qu'on le croit compromis ne sert à rien si les sessions ouvertes
     * avec l'ancien restent valides.
     */
    public function revoquerLesAutres(User $utilisateur, string $sessionCourante): int
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $utilisateur->id)
            ->where('id', '!=', $sessionCourante)
            ->delete();
    }

    /**
     * Système d'exploitation, déduit de l'agent utilisateur.
     *
     * Approximatif par nature — l'agent utilisateur est déclaratif. Il ne sert
     * qu'à aider quelqu'un à reconnaître SES appareils, jamais à décider quoi
     * que ce soit.
     */
    private function appareil(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Appareil inconnu',
        };
    }

    private function navigateur(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Navigateur inconnu',
        };
    }
}
