<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\AuditLog;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journal d'audit — page « Logs d'Audit » du back-office.
 *
 * Consultation seule, et c'est délibéré : un journal d'audit modifiable ne
 * prouve rien. Aucune action de suppression ni d'édition n'est exposée, y
 * compris à l'administrateur.
 */
#[Layout('components.layouts.staff', ['title' => "Logs d'Audit — Vérif'All"])]
final class JournalAudit extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** Recherche débouncée 350 ms côté vue (DESIGN_SYSTEM). */
    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'module', except: '')]
    public string $filtreModule = '';

    #[Url(as: 'action', except: '')]
    public string $filtreAction = '';

    public function render(): View
    {
        return view('livewire.staff.journal-audit', [
            'entrees' => $this->entrees(),
            'modules' => $this->modules(),
            'actions' => $this->actions(),
        ]);
    }

    /**
     * Tout changement de filtre ramène en page 1 : sans cela, filtrer depuis
     * la page 7 affiche un tableau vide et donne l'impression d'un bug.
     */
    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreModule', 'filtreAction'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'filtreModule', 'filtreAction']);
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    private function entrees(): LengthAwarePaginator
    {
        // with('actor') : sans lui, chaque ligne déclencherait une requête —
        // et preventLazyLoading lèverait, ce qui est le comportement voulu.
        return $this->requete()
            ->with('actor')
            ->latest('id')
            ->paginate(25);
    }

    /**
     * @return Builder<AuditLog>
     */
    private function requete(): Builder
    {
        return AuditLog::query()
            ->when($this->filtreModule !== '', fn (Builder $q) => $q->where('module', $this->filtreModule))
            ->when($this->filtreAction !== '', fn (Builder $q) => $q->where('action', $this->filtreAction))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(function (Builder $sous) use ($terme): void {
                    $sous->where('actor_label', 'like', $terme)
                        ->orWhere('target_label', 'like', $terme)
                        ->orWhere('module', 'like', $terme);
                });
            });
    }

    /**
     * KPI de l'en-tête.
     *
     * @return array{evenements: int, destructives: int, acteurs: int}
     */
    #[Computed]
    public function indicateurs(): array
    {
        $depuis = now()->subDays(7);

        return [
            'evenements' => AuditLog::where('created_at', '>=', $depuis)->count(),
            // Les actions destructives sont mises en avant : ce sont elles
            // qu'on cherche en priorité lors d'un incident.
            'destructives' => AuditLog::where('created_at', '>=', $depuis)
                ->whereIn('action', [AuditLogService::SUPPRESSION, AuditLogService::CHANGEMENT_ROLE])
                ->count(),
            'acteurs' => AuditLog::where('created_at', '>=', $depuis)
                ->distinct()->count('actor_id'),
        ];
    }

    /**
     * Libellé français d'une action.
     *
     * Les valeurs stockées sont des codes techniques stables — les traduire à
     * l'affichage évite de figer du français dans la base, où un changement de
     * formulation obligerait à réécrire l'historique. Le dictionnaire de
     * copywriting proscrit par ailleurs les libellés bruts du type
     * « Role_change » à l'écran.
     */
    public function libelleAction(string $action): string
    {
        return match ($action) {
            AuditLogService::CONNEXION => 'Connexion',
            AuditLogService::DECONNEXION => 'Déconnexion',
            AuditLogService::CREATION => 'Création',
            AuditLogService::MODIFICATION => 'Modification',
            AuditLogService::SUPPRESSION => 'Suppression',
            AuditLogService::PUBLICATION => 'Publication',
            AuditLogService::CHANGEMENT_ROLE => 'Changement de rôle',
            AuditLogService::EXPORT_DONNEES => 'Export de données',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    /**
     * Libellé français d'un module.
     */
    public function libelleModule(string $module): string
    {
        return match ($module) {
            'auth' => 'Authentification',
            'course' => 'Cours',
            'user' => 'Utilisateurs',
            'signalement' => 'Signalements',
            'audit' => 'Audit',
            'role' => 'Rôles',
            default => ucfirst($module),
        };
    }

    /**
     * L'action a-t-elle un caractère destructif ?
     *
     * Centralisé ici plutôt que répété dans la vue : ajouter une action
     * destructive ne doit pas obliger à retrouver toutes les conditions
     * disséminées dans le Blade.
     */
    public function estDestructive(string $action): bool
    {
        return in_array($action, [
            AuditLogService::SUPPRESSION,
            AuditLogService::CHANGEMENT_ROLE,
        ], true);
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        return AuditLog::query()->distinct()->orderBy('module')->pluck('module')->all();
    }

    /**
     * @return list<string>
     */
    private function actions(): array
    {
        return AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    /**
     * Export CSV du périmètre filtré.
     *
     * L'export lui-même est journalisé : extraire des données d'audit est une
     * action sensible, et savoir qui l'a fait fait partie de l'audit.
     */
    public function exporterCsv(AuditLogService $audit): StreamedResponse
    {
        $acteur = Auth::guard('staff')->user();

        if ($acteur !== null) {
            $audit->record(AuditLogService::EXPORT_DONNEES, 'audit', $acteur,
                cibleLibelle: 'Export du journal d\'audit');
        }

        $lignes = $this->requete()->with('actor')->latest('id')->limit(5000)->get();

        return response()->streamDownload(function () use ($lignes): void {
            $sortie = fopen('php://output', 'wb');

            // BOM UTF-8 : sans lui, Excel affiche « Ã© » à la place des accents.
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Horodatage', 'Acteur', 'Action', 'Module', 'Cible'], ';');

            foreach ($lignes as $ligne) {
                fputcsv($sortie, [
                    $ligne->created_at?->format('d/m/Y H:i:s'),
                    $ligne->actor_label ?? '—',
                    $ligne->action,
                    $ligne->module,
                    $ligne->target_label ?? '—',
                ], ';');
            }

            fclose($sortie);
        }, 'journal-audit-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
