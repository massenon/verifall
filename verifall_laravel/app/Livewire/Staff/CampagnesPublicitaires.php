<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\MarketingCampaign;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Campagnes Publicitaires » — les bandeaux et modales du site public.
 *
 * ## Le cache de cinq minutes, et pourquoi l'écran doit le connaître
 *
 * `SeoServiceProvider` met les campagnes diffusables en cache sous
 * `campagnes-diffusables`, cinq minutes. Sans invalidation à l'écriture,
 * arrêter une campagne la laisserait s'afficher jusqu'à cinq minutes de plus.
 *
 * Cinq minutes paraît peu. Ça ne l'est pas quand on arrête une campagne
 * PARCE QU'ELLE POSE PROBLÈME — un lien mort, une formulation malheureuse, une
 * information devenue fausse. L'écran oublie donc le cache à chaque écriture.
 *
 * ## Une campagne a une FENÊTRE, pas seulement un interrupteur
 *
 * `starts_at` et `ends_at` sont nullables des deux côtés, et le scope
 * `diffusable()` traite `null` comme « pas de borne ». C'est ce qui permet de
 * préparer une campagne à l'avance sans qu'elle paraisse, et de la laisser
 * s'éteindre seule — plutôt que de compter sur quelqu'un pour décocher une
 * case un dimanche soir.
 *
 * L'écran affiche donc l'état RÉEL (« diffusée maintenant ») et non le seul
 * drapeau : une campagne active dont la fenêtre est passée ne s'affiche nulle
 * part, et la ligne doit le dire.
 */
#[Layout('components.layouts.staff', ['title' => "Campagnes publicitaires · Vérif'All"])]
final class CampagnesPublicitaires extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'diffusees' | 'programmees' | 'terminees' | 'inactives' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $titre = '';

    public string $texte = '';

    public string $bouton = '';

    public string $lien = '';

    public string $type = 'banner';

    public string $couleur = '#059669';

    public string $delai = '0';

    public string $debut = '';

    public string $fin = '';

    public string $ordre = '0';

    public bool $active = false;

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
        abort_unless(Auth::guard('staff')->user()?->can('campaign.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.campagnes-publicitaires', [
            'campagnes' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
        ]);
    }

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'titre', 'texte', 'bouton', 'lien', 'debut', 'fin');
        $this->type = 'banner';
        $this->couleur = '#059669';
        $this->delai = '0';
        $this->ordre = (string) ((int) MarketingCampaign::query()->max('position') + 10);
        // Inactive à la création : une campagne se relit avant de paraître sur
        // toutes les pages du site public.
        $this->active = false;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $campagne = $this->campagne($uuid);

        $this->enEdition = $uuid;
        $this->titre = (string) $campagne->title;
        $this->texte = (string) $campagne->text;
        $this->bouton = (string) $campagne->cta;
        $this->lien = (string) $campagne->url;
        $this->type = (string) $campagne->type;
        $this->couleur = (string) ($campagne->color ?? '#059669');
        $this->delai = (string) $campagne->delay_seconds;
        $this->debut = $campagne->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->fin = $campagne->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->ordre = (string) $campagne->position;
        $this->active = (bool) $campagne->is_active;

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

        $campagne = $this->enEdition !== null ? $this->campagne($this->enEdition) : new MarketingCampaign;

        $suivies = ['title', 'type', 'url', 'is_active', 'starts_at', 'ends_at'];
        $avant = $campagne->exists ? $campagne->only($suivies) : null;

        $campagne->forceFill([
            'uuid' => $campagne->uuid ?? (string) Str::uuid(),
            'slug' => $campagne->slug ?? Str::slug($donnees['titre']).'-'.Str::lower(Str::random(4)),
            'title' => $donnees['titre'],
            'text' => $donnees['texte'],
            'cta' => $donnees['bouton'],
            'url' => $donnees['lien'],
            'type' => $donnees['type'],
            'color' => $donnees['couleur'],
            'delay_seconds' => (int) $donnees['delai'],
            'starts_at' => $donnees['debut'] !== '' ? $donnees['debut'] : null,
            'ends_at' => $donnees['fin'] !== '' ? $donnees['fin'] : null,
            'position' => (int) $donnees['ordre'],
            'is_active' => $donnees['active'],
        ])->save();

        $this->oublierLeCache();

        $audit->record(
            action: $avant === null ? 'campaign.create' : 'campaign.update',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $campagne,
            avant: $avant,
            apres: $campagne->only($suivies),
            cibleLibelle: $campagne->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null
            ? 'Campagne créée, inactive. Activez-la pour qu\'elle paraisse.'
            : 'Campagne mise à jour.');
    }

    public function basculerLActivation(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $campagne = $this->campagne($uuid);
        $active = ! $campagne->is_active;

        $campagne->forceFill(['is_active' => $active])->save();

        /*
         | AVANT le toast, comme partout : arrêter une campagne parce qu'elle
         | pose problème et la voir s'afficher encore cinq minutes est
         | exactement ce qu'il ne faut pas.
         */
        $this->oublierLeCache();

        $audit->record(
            action: $active ? 'campaign.activate' : 'campaign.deactivate',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $campagne,
            avant: ['is_active' => ! $active],
            apres: ['is_active' => $active],
            cibleLibelle: $campagne->title,
        );

        $this->toastSuccess($active
            ? 'Campagne activée : elle paraît dès maintenant, dans sa fenêtre de diffusion.'
            : 'Campagne arrêtée. Elle disparaît immédiatement du site.');
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

        $campagne = $this->campagne($this->aSupprimer);

        $audit->record(
            action: 'campaign.delete',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $campagne,
            avant: $campagne->only(['title', 'type', 'is_active']),
            cibleLibelle: $campagne->title,
        );

        $campagne->delete();
        $this->oublierLeCache();

        $this->aSupprimer = null;
        $this->toastSuccess('Campagne supprimée.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:3', 'max:120'],
            'texte' => ['required', 'string', 'min:10', 'max:500'],
            /*
             | OBLIGATOIRES tous les deux, parce que le schéma les veut ainsi —
             | et le schéma a raison : une campagne sans bouton ni destination
             | n'a rien à proposer. Elle occuperait le haut de chaque page pour
             | dire quelque chose sans permettre d'y donner suite.
             */
            'bouton' => ['required', 'string', 'max:60'],
            /*
             | Pas `url` : le scope du modèle accepte les ancres (`#infolettre`)
             | et les chemins (`/verifier-une-information`), et documente qu'il
             | distingue l'interne du tiers. Exiger une URL absolue interdirait
             | de renvoyer vers une page du site.
             */
            'lien' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['banner', 'overlay'])],
            'couleur' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            /*
             | Le délai n'a de sens que pour une modale. Plafonné à 120 s : une
             | modale qui surgit après deux minutes tombe sur quelqu'un qui lit,
             | et se fait fermer sans être lue.
             */
            'delai' => ['required', 'integer', 'min:0', 'max:120'],
            'debut' => ['nullable', 'date'],
            // La fin après le début, sinon la campagne ne paraîtrait JAMAIS —
            // et le scope ne dirait pas pourquoi.
            'fin' => ['nullable', 'date', 'after:debut'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre',
            'texte' => 'message',
            'bouton' => 'libellé du bouton',
            'lien' => 'destination',
            'type' => 'type',
            'couleur' => 'couleur',
            'delai' => 'délai d\'apparition',
            'debut' => 'début de diffusion',
            'fin' => 'fin de diffusion',
            'ordre' => 'ordre',
        ];
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('campaign.manage') ?? false;
    }

    /**
     * La clé posée par `SeoServiceProvider`. Recopiée, faute d'être exposée —
     * et un test la tient pour que la divergence se voie.
     */
    private function oublierLeCache(): void
    {
        Cache::forget('campagnes-diffusables');
    }

    /**
     * @return LengthAwarePaginator<int, MarketingCampaign>
     */
    private function lignes(): LengthAwarePaginator
    {
        return MarketingCampaign::query()
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('text', 'like', $terme));
            })
            ->when($this->filtreEtat === 'diffusees', fn (Builder $q) => $q->diffusable())
            ->when($this->filtreEtat === 'programmees', fn (Builder $q) => $q
                ->where('is_active', true)->where('starts_at', '>', now()))
            ->when($this->filtreEtat === 'terminees', fn (Builder $q) => $q
                ->whereNotNull('ends_at')->where('ends_at', '<', now()))
            ->when($this->filtreEtat === 'inactives', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('position')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * @return array{total: int, diffusees: int, programmees: int, terminees: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => MarketingCampaign::query()->count(),
            // L'état RÉEL, par le scope du modèle : c'est ce qui s'affiche,
            // pas ce qui est coché.
            'diffusees' => MarketingCampaign::query()->diffusable()->count(),
            'programmees' => MarketingCampaign::query()
                ->where('is_active', true)->where('starts_at', '>', now())->count(),
            'terminees' => MarketingCampaign::query()
                ->whereNotNull('ends_at')->where('ends_at', '<', now())->count(),
        ];
    }

    private function campagne(string $uuid): MarketingCampaign
    {
        return MarketingCampaign::query()->where('uuid', $uuid)->firstOrFail();
    }
}
