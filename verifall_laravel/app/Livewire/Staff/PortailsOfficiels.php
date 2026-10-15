<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Reglages\Reglages;
use App\Core\Identity\Services\AuditLogService;
use App\Models\HubPortal;
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
 * « Hub — Portails Officiels » : le dernier écran du prototype sans route.
 *
 * ## Une table qu'on ÉCRIT et que personne ne LIT
 *
 * `hub_portals` existe depuis les premiers lots, avec cinq lignes semées —
 * Institutions, Démarches, Télécoms, Santé, Médias. **Aucun écran ne s'en
 * servait**, ni côté administration ni côté citoyen : ni CRUD, ni affichage,
 * ni API. Le chantier a déjà refusé deux fois de livrer une table sans
 * lecteur ; celle-ci existait avant la règle.
 *
 * ## Ce que l'écran citoyen fait de particulier
 *
 * Le porteur du projet a demandé le CRUD **maintenant** et l'exposition
 * citoyenne **plus tard** : l'entrée figure dans le menu du compte, rendue
 * DÉSACTIVÉE, et un réglage l'ouvre le jour venu. Cet écran porte donc la
 * bascule — {@see Reglages}, clé `hub_portals_actif` — pour que l'ouverture
 * soit un geste d'administration et non un déploiement.
 *
 * Tant que le réglage est fermé, la route citoyenne rend 404 : un lien
 * désactivé qui répondrait quand même serait un lien désactivé pour la forme.
 */
#[Layout('components.layouts.staff', ['title' => "Portails officiels · Vérif'All"])]
final class PortailsOfficiels extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** La clé qui ouvre l'accès citoyen. Déclarée au catalogue des réglages. */
    public const CLE_ACTIVATION = 'hub_portals_actif';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'rubrique', except: '')]
    public string $filtreCategorie = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $nom = '';

    public string $lien = '';

    public string $categorie = '';

    public string $descriptif = '';

    public string $logo = '';

    public bool $officiel = true;

    public string $ordre = '0';

    public bool $publie = true;

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('hub_portal.view') ?? false, 403);
    }

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCategorie(): void
    {
        $this->resetPage();
    }

    public function render(Reglages $reglages): View
    {
        return view('livewire.staff.portails-officiels', [
            'portails' => $this->lignes(),
            'categories' => HubPortal::query()->distinct()->orderBy('category')->pluck('category'),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
            'accesCitoyenOuvert' => $reglages->actif(self::CLE_ACTIVATION),
        ]);
    }

    /**
     * Ouvre ou ferme l'accès citoyen.
     *
     * C'est un geste d'ADMINISTRATION, pas un déploiement : le jour où la
     * rubrique est prête, quelqu'un coche une case. Journalisé, parce qu'ouvrir
     * une rubrique publique est une décision éditoriale.
     */
    public function basculerLAccesCitoyen(Reglages $reglages, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $membre = Auth::guard('staff')->user();
        $ouvert = $reglages->actif(self::CLE_ACTIVATION);

        $reglages->set(self::CLE_ACTIVATION, $ouvert ? '0' : '1', $membre);

        $audit->record(
            action: $ouvert ? 'hub_portal.acces_ferme' : 'hub_portal.acces_ouvert',
            module: 'hub',
            acteur: $membre,
            apres: ['actif' => ! $ouvert],
        );

        $this->toastSuccess($ouvert
            ? 'Rubrique refermée : l’entrée redevient désactivée dans le menu du compte.'
            : 'Rubrique ouverte : l’entrée devient cliquable dans le menu du compte.');
    }

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'nom', 'lien', 'categorie', 'descriptif', 'logo');
        $this->ordre = (string) ((int) HubPortal::query()->max('position') + 10);
        $this->officiel = true;
        $this->publie = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $portail = $this->portail($uuid);

        $this->enEdition = $uuid;
        $this->nom = (string) $portail->name;
        $this->lien = (string) $portail->url;
        $this->categorie = (string) $portail->category;
        $this->descriptif = (string) $portail->description;
        $this->logo = (string) $portail->logo_path;
        $this->officiel = (bool) $portail->is_official;
        $this->ordre = (string) $portail->position;
        $this->publie = (bool) $portail->is_published;

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

        $valide = $this->validate([
            'nom' => ['required', 'string', 'max:120'],
            /*
             | `url` ET le schéma imposé. Un portail officiel dont l'adresse
             | part en `http://` renverrait les gens vers une page que leur
             | navigateur signale comme non sécurisée — sur une plateforme qui
             | enseigne à se méfier, ce serait le pire des exemples.
             */
            'lien' => ['required', 'url', 'starts_with:https://', 'max:255'],
            'categorie' => ['required', 'string', 'max:60'],
            'descriptif' => ['required', 'string', 'max:400'],
            'logo' => ['nullable', 'string', 'max:255'],
            'ordre' => ['required', 'integer', 'min:0'],
        ], attributes: [
            'nom' => 'nom', 'lien' => 'adresse', 'categorie' => 'rubrique',
            'descriptif' => 'descriptif', 'ordre' => 'ordre',
        ]);

        $attributs = [
            'name' => trim($valide['nom']),
            'url' => trim($valide['lien']),
            'category' => trim($valide['categorie']),
            'description' => trim($valide['descriptif']),
            'logo_path' => trim($this->logo) !== '' ? trim($this->logo) : null,
            'is_official' => $this->officiel,
            'position' => (int) $valide['ordre'],
            'is_published' => $this->publie,
        ];

        if ($this->enEdition !== null) {
            $portail = $this->portail($this->enEdition);
            $avant = $portail->only(array_keys($attributs));
            $portail->forceFill($attributs)->save();

            $audit->record(
                action: 'hub_portal.update',
                module: 'hub',
                acteur: Auth::guard('staff')->user(),
                cible: $portail,
                avant: $avant,
                apres: $attributs,
            );

            $this->toastSuccess('Portail mis à jour.');
        } else {
            $portail = HubPortal::create([...$attributs, 'uuid' => (string) Str::uuid()]);

            $audit->record(
                action: 'hub_portal.create',
                module: 'hub',
                acteur: Auth::guard('staff')->user(),
                cible: $portail,
                apres: $attributs,
            );

            $this->toastSuccess('Portail ajouté.');
        }

        $this->fermerLeTiroir();
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $portail = $this->portail($uuid);
        $portail->forceFill(['is_published' => ! $portail->is_published])->save();

        $audit->record(
            action: $portail->is_published ? 'hub_portal.publish' : 'hub_portal.unpublish',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $portail,
            apres: ['is_published' => $portail->is_published],
        );

        $this->toastSuccess($portail->is_published ? 'Portail publié.' : 'Portail retiré.');
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
        abort_unless($this->peutGerer(), 403);

        if ($this->aSupprimer === null) {
            return;
        }

        $portail = $this->portail($this->aSupprimer);
        $avant = $portail->only(['name', 'url', 'category']);
        $portail->delete();

        $audit->record(
            action: 'hub_portal.delete',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            avant: $avant,
            cibleLibelle: (string) $avant['name'],
        );

        $this->aSupprimer = null;
        $this->toastSuccess('Portail supprimé.');
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('hub_portal.manage') ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, HubPortal>
     */
    private function lignes(): LengthAwarePaginator
    {
        return HubPortal::query()
            ->when($this->filtreCategorie !== '', fn (Builder $q) => $q->where('category', $this->filtreCategorie))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where('name', 'like', $terme)->orWhere('url', 'like', $terme);
            })
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return array{total: int, publies: int, officiels: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => HubPortal::query()->count(),
            'publies' => HubPortal::query()->where('is_published', true)->count(),
            'officiels' => HubPortal::query()->where('is_official', true)->count(),
        ];
    }

    private function portail(string $uuid): HubPortal
    {
        return HubPortal::query()->where('uuid', $uuid)->firstOrFail();
    }
}
