<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\SecurityLogService;
use App\Models\SecurityLog;
use App\Support\Trait\InteractsWithToasts;
use App\Support\Trait\ListeDeLaConsole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Alertes Intrusion — page de surveillance du back-office.
 *
 * Distincte du journal d'audit, et pas seulement par le contenu : ici on
 * cherche des SCHÉMAS (une même empreinte qui revient, une salve d'échecs),
 * là on cherche un acte précis. D'où le regroupement par empreinte plutôt que
 * la liste brute.
 *
 * Aucune identité n'est affichée en clair : la page montre des empreintes
 * tronquées, suffisantes pour corréler deux lignes sans exposer d'adresse.
 */
#[Layout('components.layouts.staff', ['title' => 'Alertes Intrusion — Vérif\'All'])]
final class AlertesIntrusion extends Component
{
    use InteractsWithToasts;
    use ListeDeLaConsole;

    #[Url(as: 'type', except: '')]
    public string $filtreEvenement = '';

    #[Url(as: 'niveau', except: '')]
    public string $filtreSeverite = '';

    public function render(): View
    {
        return view('livewire.staff.alertes-intrusion', [
            'alertes' => $this->alertes(),
            'evenements' => $this->evenements(),
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['filtreEvenement', 'filtreSeverite'], true)) {
            $this->resetPage();
        }
    }

    protected function reinitialiserLesFiltres(): void
    {
        $this->reset(['filtreEvenement', 'filtreSeverite']);
    }

    /**
     * @return LengthAwarePaginator<int, SecurityLog>
     */
    private function alertes(): LengthAwarePaginator
    {
        return $this->requete()->with('user')->latest('id')->paginate($this->lignesParPage());
    }

    /**
     * @return Builder<SecurityLog>
     */
    private function requete(): Builder
    {
        return SecurityLog::query()
            ->when($this->filtreEvenement !== '',
                fn (Builder $q) => $q->where('event', $this->filtreEvenement))
            ->when($this->filtreSeverite !== '',
                fn (Builder $q) => $q->where('severity', $this->filtreSeverite))
            ->when(trim($this->recherche) !== '', function (Builder $q): void {
                $motif = '%'.addcslashes(trim($this->recherche), '%_\\').'%';
                // L'événement, ou le compte visé : jamais l'empreinte, qui n'est pas lisible.
                $q->where(fn (Builder $ou) => $ou->where('event', 'like', $motif)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $motif)->orWhere('email', 'like', $motif)));
            });
    }

    /**
     * @return array{bloquees: int, critiques: int, empreintes: int}
     */
    #[Computed]
    public function indicateurs(): array
    {
        $depuis = now()->subDays(7);

        return [
            'bloquees' => SecurityLog::where('created_at', '>=', $depuis)
                ->whereIn('event', [
                    SecurityLogService::LOGIN_ECHOUE,
                    SecurityLogService::LOGIN_VERROUILLE,
                    SecurityLogService::DEBIT_LIMITE,
                ])->count(),
            'critiques' => SecurityLog::where('created_at', '>=', $depuis)
                ->where('severity', 'critical')->count(),
            // Le nombre d'empreintes distinctes distingue une salve venue d'une
            // seule source d'une campagne distribuée : ce ne sont pas les mêmes
            // contre-mesures.
            'empreintes' => SecurityLog::where('created_at', '>=', $depuis)
                ->whereNotNull('ip_hash')->distinct()->count('ip_hash'),
        ];
    }

    /**
     * @return list<string>
     */
    private function evenements(): array
    {
        return SecurityLog::query()->distinct()->orderBy('event')->pluck('event')->all();
    }

    /**
     * Libellé lisible d'un événement technique.
     */
    public function libelle(string $evenement): string
    {
        return match ($evenement) {
            SecurityLogService::LOGIN_ECHOUE => 'Connexion refusée',
            SecurityLogService::LOGIN_VERROUILLE => 'Compte verrouillé',
            SecurityLogService::LOGIN_REUSSI => 'Connexion réussie',
            SecurityLogService::OTP_REJOUE => 'Code rejoué ou expiré',
            SecurityLogService::OTP_FORCE => 'Forçage de code',
            SecurityLogService::DEBIT_LIMITE => 'Débit limité',
            SecurityLogService::UPLOAD_REFUSE => 'Envoi de fichier refusé',
            SecurityLogService::DECONNEXION => 'Déconnexion',
            default => ucfirst(str_replace('_', ' ', $evenement)),
        };
    }

    public function statutBadge(string $severite): string
    {
        return match ($severite) {
            'critical' => 'danger',
            'warning' => 'trompeur',
            default => 'info',
        };
    }
}
