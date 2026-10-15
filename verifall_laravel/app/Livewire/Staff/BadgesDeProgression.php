<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Badges de progression » — la récompense, et ce qu'elle coûte.
 *
 * ## Le slug est FIGÉ, et c'est plus grave qu'ailleurs
 *
 * `AttributionDesBadges` reconnaît les badges PAR LEUR SLUG pour décider qui
 * les mérite. Laisser le slug suivre un nom corrigé ne casserait pas un lien :
 * ça **arrêterait l'attribution** — silencieusement, et seulement pour les
 * citoyens à venir. Ceux qui l'ont déjà le gardent, les suivants ne l'auront
 * jamais, et rien ne le signalera.
 *
 * ## Les récompenses ne se rejouent PAS
 *
 * `xp_reward` et `units_reward` sont versés AU MOMENT du déblocage. Augmenter
 * la récompense d'un badge ne crédite personne rétroactivement — et la
 * diminuer ne reprend rien.
 *
 * L'écran le dit à l'enregistrement, parce que c'est exactement ce qu'on croit
 * en modifiant le chiffre. Le compteur « déjà débloqué » sur chaque ligne donne
 * l'ordre de grandeur de ce qui a déjà été versé.
 *
 * ## Désactiver plutôt que supprimer
 *
 * Supprimer un badge laisse des `user_badges` orphelins : des citoyens qui
 * l'ont gagné, et dont le tableau de bord afficherait une case vide. La
 * désactivation le retire des attributions à venir sans toucher au passé —
 * c'est le geste ordinaire, et la suppression annonce le nombre de porteurs.
 */
#[Layout('components.layouts.staff', ['title' => "Badges de progression · Vérif'All"])]
final class BadgesDeProgression extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'actifs' | 'inactifs' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $nom = '';

    public string $descriptif = '';

    public string $icone = '';

    public string $condition = '';

    public string $xp = '0';

    public string $unites = '0';

    public string $ordre = '0';

    public bool $actif = true;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('system.badge') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.badges-de-progression', [
            'badges' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
            'badgeVise' => $this->aSupprimer !== null ? $this->badge($this->aSupprimer) : null,
        ]);
    }

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'nom', 'descriptif', 'condition');
        // Une valeur par défaut plutôt qu'un champ vide : la colonne est
        // obligatoire, et un médaillon convient à la plupart des badges.
        $this->icone = '🏅';
        $this->xp = '0';
        $this->unites = '0';
        $this->ordre = (string) ((int) Badge::query()->max('position') + 10);
        $this->actif = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $badge = $this->badge($uuid);

        $this->enEdition = $uuid;
        $this->nom = (string) $badge->name;
        $this->descriptif = (string) $badge->description;
        $this->icone = (string) $badge->icon;
        $this->condition = (string) $badge->requirement_label;
        $this->xp = (string) $badge->xp_reward;
        $this->unites = (string) $badge->units_reward;
        $this->ordre = (string) $badge->position;
        $this->actif = (bool) $badge->is_active;

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    public function enregistrer(AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $donnees = $this->validate();

        $badge = $this->enEdition !== null ? $this->badge($this->enEdition) : new Badge;

        $suivies = ['name', 'icon', 'requirement_label', 'xp_reward', 'units_reward', 'position', 'is_active'];
        $avant = $badge->exists ? $badge->only($suivies) : null;

        $badge->forceFill([
            'uuid' => $badge->uuid ?? (string) Str::uuid(),
            /*
             | FIGÉ. `AttributionDesBadges` reconnaît les badges par leur slug :
             | le laisser suivre un nom corrigé arrêterait l'attribution, sans
             | erreur, et seulement pour les citoyens à venir.
             */
            'slug' => $badge->slug ?? Str::slug($donnees['nom']).'-'.Str::lower(Str::random(4)),
            'name' => $donnees['nom'],
            'description' => $donnees['descriptif'],
            'icon' => $donnees['icone'],
            'requirement_label' => $donnees['condition'],
            'xp_reward' => (int) $donnees['xp'],
            'units_reward' => (int) $donnees['unites'],
            'position' => (int) $donnees['ordre'],
            'is_active' => $donnees['actif'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'system.badge' : 'system.badge',
            module: 'configuration',
            acteur: Auth::guard('staff')->user(),
            cible: $badge,
            avant: $avant,
            apres: $badge->only($suivies),
            cibleLibelle: $badge->name,
        );

        $modifiait = $avant !== null;
        $recompenseChangee = $modifiait
            && (($avant['xp_reward'] ?? 0) !== (int) $donnees['xp']
                || ($avant['units_reward'] ?? 0) !== (int) $donnees['unites']);

        $this->fermerLeTiroir();

        /*
         | Le message dit ce qui NE SE PASSE PAS. Modifier une récompense, on
         | croit toujours que ça rattrape le passé — or les XP et les unités
         | sont versés au moment du déblocage, une fois.
         */
        $this->toastSuccess(match (true) {
            ! $modifiait => 'Badge créé.',
            $recompenseChangee => 'Badge mis à jour. La nouvelle récompense ne vaut que pour les prochains déblocages.',
            default => 'Badge mis à jour.',
        });
    }

    public function basculerLActivation(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $badge = $this->badge($uuid);
        $actif = ! $badge->is_active;

        $badge->forceFill(['is_active' => $actif])->save();

        $audit->record(
            action: 'system.badge',
            module: 'configuration',
            acteur: Auth::guard('staff')->user(),
            cible: $badge,
            avant: ['is_active' => ! $actif],
            apres: ['is_active' => $actif],
            cibleLibelle: $badge->name,
        );

        $this->toastSuccess($actif
            ? 'Badge réactivé : il peut de nouveau être débloqué.'
            : 'Badge désactivé. Ceux qui l\'ont déjà le gardent.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $audit): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        abort_unless($this->peutGerer(), 403);

        $badge = $this->badge($this->aSupprimer);
        $porteurs = UserBadge::query()->where('badge_id', $badge->id)->count();

        $audit->record(
            action: 'system.badge',
            module: 'configuration',
            acteur: Auth::guard('staff')->user(),
            cible: $badge,
            avant: $badge->only(['name', 'slug', 'is_active']) + ['porteurs' => $porteurs],
            cibleLibelle: $badge->name,
        );

        $badge->delete();

        $this->aSupprimer = null;
        $this->toastSuccess($porteurs === 0
            ? 'Badge supprimé.'
            : 'Badge supprimé — '.$porteurs.' citoyen(s) l\'avaient débloqué.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:120'],
            'descriptif' => ['required', 'string', 'min:10', 'max:500'],
            /*
             | Obligatoire et courte : `badges.icon` est un `string(16)` NOT
             | NULL. Seize caractères, c'est un emoji ou un code bref — pas un
             | nom d'icône Material Symbols, qui dépasse souvent. Le schéma le
             | dit ; le formulaire s'y tient plutôt que d'échouer à
             | l'enregistrement.
             */
            'icone' => ['required', 'string', 'max:16'],
            /*
             | La CONDITION est un libellé, pas une règle exécutable : c'est
             | `AttributionDesBadges` qui décide, en code, par slug. Ce texte
             | explique au citoyen ce qu'il doit faire — s'il ne correspond pas
             | à la règle réelle, il promet ce qui n'arrivera pas.
             */
            'condition' => ['required', 'string', 'min:5', 'max:200'],
            'xp' => ['required', 'integer', 'min:0', 'max:10000'],
            /*
             | Les unités sont plafonnées bas : un badge à 500 unités vaudrait
             | plus que tous les packs vendus, et se débloquerait sans
             | encaissement.
             */
            'unites' => ['required', 'integer', 'min:0', 'max:100'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
            'actif' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom du badge',
            'descriptif' => 'description',
            'icone' => 'icône',
            'condition' => 'condition affichée',
            'xp' => 'points d\'expérience',
            'unites' => 'unités offertes',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('system.badge') ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, Badge>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Badge::query()
            ->withCount('users')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('name', 'like', $terme)
                    ->orWhere('description', 'like', $terme));
            })
            ->when($this->filtreEtat === 'actifs', fn (Builder $q) => $q->where('is_active', true))
            ->when($this->filtreEtat === 'inactifs', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @return array{total: int, actifs: int, debloques: int, unites_versees: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => Badge::query()->count(),
            'actifs' => Badge::query()->where('is_active', true)->count(),
            'debloques' => UserBadge::query()->count(),
            /*
             | Ce que les badges ont RÉELLEMENT coûté en unités. Calculé sur les
             | déblocages réels et le tarif ACTUEL du badge — approximation
             | assumée, faute d'un montant figé au déblocage. Le journal des
             | unités, lui, porte le chiffre exact.
             */
            'unites_versees' => (int) UserBadge::query()
                ->join('badges', 'badges.id', '=', 'user_badges.badge_id')
                ->sum('badges.units_reward'),
        ];
    }

    private function badge(string $uuid): Badge
    {
        return Badge::query()->withCount('users')->where('uuid', $uuid)->firstOrFail();
    }
}
