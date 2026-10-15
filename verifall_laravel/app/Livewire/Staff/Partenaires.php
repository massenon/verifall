<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Partner;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Nos Partenaires » — le carrousel de l'accueil, enfin administrable.
 *
 * ## Ce que cet écran remplace
 *
 * Un carrousel de logos écrit EN DUR dans `public/accueil.blade.php` : noms,
 * URL, chemins d'images, tout figé. Ajouter un partenaire — le geste le plus
 * ordinaire d'un projet financé, et souvent une obligation contractuelle —
 * exigeait un développeur et un déploiement.
 *
 * ## Le logo est un CHEMIN, pas un téléversement
 *
 * Les logos existants sont déjà déposés dans `public/assets/images/partners/`.
 * Ouvrir une chaîne de téléversement — validation du type par le contenu,
 * disque privé, route signée, comme l'exige `CLAUDE.md` — pour six images qui
 * bougent une fois par an serait disproportionné.
 *
 * Le champ attend donc un chemin relatif à `public/`. La validation refuse
 * toute remontée d'arborescence : un chemin `../../.env` rendu dans un `src`
 * ne fuiterait rien par lui-même, mais un chemin arbitraire dans une page
 * publique n'a aucune raison d'être accepté.
 *
 * ## `sort_order` compte plus qu'il n'en a l'air
 *
 * Sur un carrousel de bailleurs, l'ordre d'apparition se négocie. Ce n'est pas
 * un détail d'affichage : c'est parfois écrit dans une convention.
 */
#[Layout('components.layouts.staff', ['title' => "Nos partenaires · Vérif'All"])]
final class Partenaires extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $nom = '';

    public string $logo = '';

    public string $lien = '';

    public string $descriptif = '';

    public string $ordre = '0';

    public bool $publie = true;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('partner.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.partenaires', [
            'partenaires' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
        ]);
    }

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'nom', 'logo', 'lien', 'descriptif');
        $this->ordre = (string) ((int) Partner::query()->max('sort_order') + 10);
        $this->publie = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $partenaire = $this->partenaire($uuid);

        $this->enEdition = $uuid;
        $this->nom = (string) $partenaire->name;
        $this->logo = (string) $partenaire->logo_path;
        $this->lien = (string) $partenaire->url;
        $this->descriptif = (string) $partenaire->description;
        $this->ordre = (string) $partenaire->sort_order;
        $this->publie = (bool) $partenaire->is_published;

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

        $partenaire = $this->enEdition !== null ? $this->partenaire($this->enEdition) : new Partner;

        $suivies = ['name', 'logo_path', 'url', 'sort_order', 'is_published'];
        $avant = $partenaire->exists ? $partenaire->only($suivies) : null;

        $partenaire->forceFill([
            'uuid' => $partenaire->uuid ?? (string) Str::uuid(),
            'name' => $donnees['nom'],
            'logo_path' => $donnees['logo'] !== '' ? ltrim($donnees['logo'], '/') : null,
            'url' => $donnees['lien'] !== '' ? $donnees['lien'] : null,
            'description' => $donnees['descriptif'] !== '' ? $donnees['descriptif'] : null,
            'sort_order' => (int) $donnees['ordre'],
            'is_published' => $donnees['publie'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'partner.create' : 'partner.update',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $partenaire,
            avant: $avant,
            apres: $partenaire->only($suivies),
            cibleLibelle: $partenaire->name,
        );

        $this->oublierLeCache();

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Partenaire ajouté.' : 'Partenaire mis à jour.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $partenaire = $this->partenaire($uuid);
        $publie = ! $partenaire->is_published;

        $partenaire->forceFill(['is_published' => $publie])->save();

        $audit->record(
            action: $publie ? 'partner.publish' : 'partner.unpublish',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $partenaire,
            avant: ['is_published' => ! $publie],
            apres: ['is_published' => $publie],
            cibleLibelle: $partenaire->name,
        );

        $this->oublierLeCache();

        $this->toastSuccess($publie ? 'Partenaire affiché sur l\'accueil.' : 'Partenaire retiré de l\'accueil.');
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

        $partenaire = $this->partenaire($this->aSupprimer);

        $audit->record(
            action: 'partner.delete',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $partenaire,
            avant: $partenaire->only(['name', 'url', 'is_published']),
            cibleLibelle: $partenaire->name,
        );

        $partenaire->delete();
        $this->oublierLeCache();

        $this->aSupprimer = null;
        $this->toastSuccess('Partenaire supprimé.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:160'],
            /*
             | Un chemin relatif à `public/`, sans remontée d'arborescence.
             | `regex` plutôt que `starts_with` : il faut refuser `..` PARTOUT
             | dans la chaîne, pas seulement en tête.
             */
            'logo' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-\/.]+$/', 'not_regex:/\.\./'],
            'lien' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'descriptif' => ['nullable', 'string', 'max:500'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
            'publie' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom du partenaire',
            'logo' => 'chemin du logo',
            'lien' => 'site du partenaire',
            'descriptif' => 'description',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    /**
     * La clé posée par le composeur de l'accueil.
     *
     * Sans cet oubli, retirer un partenaire le laisserait au carrousel une
     * heure de plus. Une heure, c'est long quand on retire un partenaire parce
     * que la convention a pris fin.
     */
    private function oublierLeCache(): void
    {
        Cache::forget('partenaires-accueil');
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('partner.manage') ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, Partner>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Partner::query()
            ->when($this->recherche !== '',
                fn (Builder $q) => $q->where('name', 'like', '%'.$this->recherche.'%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @return array{total: int, publies: int, sans_logo: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => Partner::query()->count(),
            'publies' => Partner::query()->where('is_published', true)->count(),
            /*
             | Un partenaire publié SANS logo laisse un trou dans le carrousel.
             | Personne d'autre ne le signale : le compteur est le seul endroit
             | où ça se voit sans ouvrir l'accueil.
             */
            'sans_logo' => Partner::query()
                ->where('is_published', true)
                ->where(fn (Builder $q) => $q->whereNull('logo_path')->orWhere('logo_path', ''))
                ->count(),
        ];
    }

    private function partenaire(string $uuid): Partner
    {
        return Partner::query()->where('uuid', $uuid)->firstOrFail();
    }
}
