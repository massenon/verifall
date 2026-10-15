<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Application\Services\SyntheseCitoyen;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Mes Statistiques » — transposition de compte-user/statistiques.html.
 *
 * Quatre graphiques et quatre indicateurs. Tous sont calculés ; aucun n'est
 * décoratif. Le prototype affichait « ↗ +3 vs mois dernier » et « Record
 * personnel : 12 jours » en dur — sur une page qui prétend mesurer une
 * pratique, ces deux phrases étaient les seules choses fausses.
 */
#[Layout('components.layouts.citizen', ['title' => "Mes Statistiques · Vérif'All"])]
final class Statistiques extends Component
{
    /** Fenêtre du graphique d'activité, en jours. */
    public int $periode = 30;

    public function render(SyntheseCitoyen $synthese): View
    {
        $utilisateur = $this->utilisateur();

        $verdicts = $synthese->verdicts($utilisateur);
        $total = Verification::where('user_id', $utilisateur->id)->count();

        return view('livewire.citizen.statistiques', [
            'utilisateur' => $utilisateur,
            'nombreDeVerifications' => $total,
            'intoxDetectees' => ($verdicts[VerdictFactCheck::Faux->value] ?? 0)
                + ($verdicts[VerdictFactCheck::Trompeur->value] ?? 0),
            'leconsTerminees' => $synthese->leconsTerminees($utilisateur),
            'evolutionMensuelle' => $synthese->evolutionMensuelle($utilisateur),
            'leconsCetteSemaine' => $synthese->leconsCetteSemaine($utilisateur),
            'verdicts' => $verdicts,
            'types' => $synthese->types($utilisateur),
            'activite' => $synthese->activite($utilisateur, $this->periode),
            'semaine' => $synthese->semaine($utilisateur),
        ]);
    }

    public function changerDePeriode(int $jours): void
    {
        $this->periode = in_array($jours, [7, 30, 90, 365], true) ? $jours : 30;
    }

    public function titreDeLaPeriode(): string
    {
        return match ($this->periode) {
            7 => 'Activité sur 7 jours',
            90 => 'Activité sur 3 mois',
            365 => 'Activité cette année',
            default => 'Activité sur 30 jours',
        };
    }

    private function utilisateur(): User
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        return $utilisateur;
    }
}
