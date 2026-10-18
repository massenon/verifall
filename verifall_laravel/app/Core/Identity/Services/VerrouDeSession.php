<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Http\Middleware\StaffIdleTimeout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Le verrouillage de session de la console.
 *
 * Verrouiller, c'est DÉCONNECTER la garde `staff` tout en gardant en session
 * qui était là. Un simple drapeau ne suffirait pas : les requêtes Livewire
 * (`/livewire/update`) échapperaient à un middleware posé sur `/admin`, et un
 * écran verrouillé continuerait d'accepter des actions. Garde déconnectée, plus
 * rien de la console ne répond tant que le mot de passe n'est pas redonné.
 *
 *  - 5 mots de passe faux ferment la session pour de bon ;
 *  - un verrou plus vieux que l'expiration d'inactivité (30 min) ne se lève
 *    plus par le seul mot de passe : il faut une connexion complète, 2FA comprise.
 */
final class VerrouDeSession
{
    public const CLE = 'staff.verrou';

    public const ESSAIS_MAX = 5;

    public function __construct(private readonly SecurityLogService $journal) {}

    public function verrouiller(Request $requete, string $raison = 'manuel'): void
    {
        $membre = Auth::guard('staff')->user();

        if (! $membre instanceof User) {
            return;
        }

        $retour = url()->previous();

        Auth::guard('staff')->logout();
        session()->regenerate();
        session()->put(self::CLE, [
            'user_id' => $membre->id,
            'depuis' => now()->timestamp,
            'retour' => str_starts_with($retour, url('/admin')) ? $retour : route('staff.dashboard'),
            'raison' => $raison === 'inactivite' ? 'inactivite' : 'manuel',
            'echecs' => 0,
        ]);

        $this->journal->record('session_locked', 'info', $membre->email, $membre, ['raison' => $raison]);
    }

    /**
     * @return array{user_id: int, depuis: int, retour: string, raison: string, echecs: int}|null
     */
    public function etat(Request $requete): ?array
    {
        $etat = session()->get(self::CLE);

        return is_array($etat) && isset($etat['user_id']) ? $etat : null;
    }

    public function membre(Request $requete): ?User
    {
        $etat = $this->etat($requete);

        return $etat === null ? null : User::query()->find($etat['user_id']);
    }

    public function estExpire(Request $requete): bool
    {
        $etat = $this->etat($requete);

        return $etat === null || (now()->timestamp - $etat['depuis']) > StaffIdleTimeout::MINUTES * 60;
    }

    /**
     * @return 'ok'|'refuse'|'ferme'|'expire'
     */
    public function deverrouiller(Request $requete, string $motDePasse): string
    {
        $etat = $this->etat($requete);
        $membre = $this->membre($requete);

        if ($etat === null || $membre === null || $this->estExpire($requete) || $membre->suspended_at !== null) {
            $this->fermer($requete);

            return 'expire';
        }

        if (! Hash::check($motDePasse, (string) $membre->password)) {
            $etat['echecs']++;
            $this->journal->record('unlock_failed', $etat['echecs'] >= 3 ? 'warning' : 'info', $membre->email, $membre, ['echecs' => $etat['echecs']]);

            if ($etat['echecs'] >= self::ESSAIS_MAX) {
                $this->journal->record('session_closed_after_unlock_failures', 'warning', $membre->email, $membre);
                $this->fermer($requete);

                return 'ferme';
            }

            session()->put(self::CLE, $etat);

            return 'refuse';
        }

        // Le journal des connexions note un déverrouillage, pas une connexion.
        $requete->attributes->set('connexion.methode', 'deverrouillage');
        Auth::guard('staff')->login($membre);
        session()->regenerate();
        session()->forget(self::CLE);
        session()->put('staff.last_activity', now()->timestamp);

        return 'ok';
    }

    public function fermer(Request $requete): void
    {
        session()->forget(self::CLE);
        session()->invalidate();
        session()->regenerateToken();
    }
}
