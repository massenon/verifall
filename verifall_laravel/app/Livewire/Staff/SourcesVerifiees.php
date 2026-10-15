<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\Identity\Services\AuditLogService;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Sources vérifiées » — le référentiel de la rédaction.
 *
 * ## Ce n'est pas un annuaire de plus
 *
 * `AnalyseTechniqueAdapter` consulte cette table à chaque analyse de lien. Une
 * fiche active y **désarme** la règle d'usurpation institutionnelle et ajoute
 * un signal au raisonnement affiché au citoyen. Écrire ici, c'est modifier le
 * jugement de la machine — d'où la journalisation de chaque geste et
 * l'invalidation immédiate du cache du dépôt.
 *
 * ## L'écran affiche ce que la source CHANGE, pas seulement ce qu'elle est
 *
 * Un référentiel dont on ne voit pas l'effet devient un cimetière de fiches :
 * on n'ose plus rien retirer faute de savoir ce que ça casserait. La colonne
 * « effet » dit, pour chaque ligne, ce que le moteur en fait — et une fiche
 * inactive le dit aussi, plutôt que de disparaître discrètement.
 */
#[Layout('components.layouts.staff', ['title' => "Sources vérifiées · Vérif'All"])]
final class SourcesVerifiees extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'sources-verifiees-modifiees';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'famille', except: '')]
    public string $filtreCategorie = '';

    /** '' | 'actives' | 'inactives' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $nom = '';

    public string $domaine = '';

    public string $categorieUuid = '';

    public string $lien = '';

    public string $credibilite = '70';

    public bool $active = true;

    public string $note = '';

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCategorie', 'filtreEtat');
    }

    public function mount(): void
    {
        $this->authorize('viewAny', TrustedSource::class);
    }

    public function render(DepotSourcesFiables $depot): View
    {
        $toutesLesFamilles = $this->familles();
        $uuidsEnUsage = $depot->uuidsDesFamilles();

        return view('livewire.staff.sources-verifiees', [
            'indicateurs' => $this->indicateurs(),
            'familles' => $toutesLesFamilles,
            // Le filtre de la table n'offre que ce que `filtresAutorises()`
            // accepte réellement côté serveur : une famille sans aucune
            // source filtrerait sur une valeur toujours vide.
            'famillesEnUsage' => $toutesLesFamilles->filter(
                static fn (SourceCategory $f): bool => in_array((string) $f->uuid, $uuidsEnUsage, true)
            ),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', TrustedSource::class);

        $this->reset('enEdition', 'nom', 'domaine', 'lien', 'note');
        $this->categorieUuid = '';
        $this->credibilite = '70';
        $this->active = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $source = $this->source($uuid);
        $this->authorize('update', $source);

        $this->enEdition = $uuid;
        $this->nom = (string) $source->name;
        $this->domaine = (string) $source->domain;
        $this->categorieUuid = (string) ($source->categorie?->uuid ?? '');
        $this->lien = (string) $source->url;
        $this->credibilite = (string) $source->credibility;
        $this->active = (bool) $source->is_active;
        $this->note = (string) $source->notes;

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    /**
     * La famille choisie propose sa crédibilité par défaut.
     *
     * Seulement en CRÉATION, et seulement si le champ n'a pas déjà été touché :
     * écraser une valeur saisie à la main parce qu'on a corrigé la famille
     * serait exactement le genre de geste qui fait perdre confiance en un
     * formulaire.
     */
    public function updatedCategorieUuid(string $valeur): void
    {
        if ($this->enEdition !== null || $valeur === '') {
            return;
        }

        $famille = SourceCategory::query()->where('uuid', $valeur)->first();

        if ($famille !== null) {
            $this->credibilite = (string) $famille->default_credibility;
        }
    }

    public function enregistrer(AuditLogService $audit, DepotSourcesFiables $depot): void
    {
        $donnees = $this->validate();

        $source = $this->enEdition !== null ? $this->source($this->enEdition) : new TrustedSource;

        $this->authorize($source->exists ? 'update' : 'create', $source->exists ? $source : TrustedSource::class);

        $suivies = ['name', 'domain', 'url', 'credibility', 'is_active', 'source_category_id'];
        $avant = $source->exists ? $source->only($suivies) : null;

        $source->forceFill([
            'uuid' => $source->uuid ?? (string) Str::uuid(),
            'source_category_id' => $this->familleId($donnees['categorieUuid'] ?? ''),
            'name' => $donnees['nom'],
            'domain' => TrustedSource::normaliserLeDomaine($donnees['domaine']),
            'url' => $donnees['lien'] !== '' ? $donnees['lien'] : null,
            'credibility' => (int) $donnees['credibilite'],
            'is_active' => $donnees['active'],
            'notes' => $donnees['note'] !== '' ? $donnees['note'] : null,
        ])->save();

        $audit->record(
            action: $avant === null ? 'trusted_source.create' : 'trusted_source.update',
            module: 'fact-checking',
            acteur: Auth::guard('staff')->user(),
            cible: $source,
            avant: $avant,
            apres: $source->only($suivies),
            cibleLibelle: $source->name.' ('.$source->domain.')',
        );

        /*
         | Le cache est oublié AVANT le toast. Sans cela, la personne lit
         | « source ajoutée » et la vérification lancée dans la minute ne la
         | voit toujours pas — pendant un quart d'heure, sans explication.
         */
        $depot->oublier();

        $this->fermerLeTiroir();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess($avant === null ? 'Source référencée.' : 'Fiche mise à jour.');
    }

    public function basculerLActivation(string $uuid, AuditLogService $audit, DepotSourcesFiables $depot): void
    {
        $source = $this->source($uuid);
        $this->authorize('update', $source);

        $active = ! $source->is_active;

        $source->forceFill(['is_active' => $active])->save();

        $audit->record(
            action: $active ? 'trusted_source.activate' : 'trusted_source.deactivate',
            module: 'fact-checking',
            acteur: Auth::guard('staff')->user(),
            cible: $source,
            avant: ['is_active' => ! $active],
            apres: ['is_active' => $active],
            cibleLibelle: $source->name.' ('.$source->domain.')',
        );

        $depot->oublier();

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess($active
            ? 'Source réactivée : le moteur en tient compte.'
            : 'Source désactivée : le moteur ne la reconnaît plus.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->source($uuid));
        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $audit, DepotSourcesFiables $depot): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        $source = $this->source($this->aSupprimer);
        $this->authorize('delete', $source);

        $audit->record(
            action: 'trusted_source.delete',
            module: 'fact-checking',
            acteur: Auth::guard('staff')->user(),
            cible: $source,
            avant: $source->only(['name', 'domain', 'credibility', 'is_active']),
            cibleLibelle: $source->name.' ('.$source->domain.')',
        );

        $source->delete();
        $depot->oublier();

        $this->aSupprimer = null;
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Source retirée du référentiel.');
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
             | Pas de règle `url` : on attend un DOMAINE, pas une adresse. Mais
             | la saisie humaine arrive presque toujours collée depuis la barre
             | du navigateur, et `normaliserLeDomaine()` sait la ramener. La
             | règle vérifie donc la forme APRÈS normalisation — sinon on
             | refuserait le geste le plus naturel.
             |
             | L'unicité porte sur la valeur normalisée pour la même raison :
             | `https://www.lemonde.fr/` et `lemonde.fr` sont la même fiche, et
             | seul le premier passerait un `unique` naïf.
             */
            'domaine' => [
                'required', 'string', 'max:190',
                function (string $attribut, mixed $valeur, callable $echoue): void {
                    $normalise = TrustedSource::normaliserLeDomaine((string) $valeur);

                    if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $normalise)) {
                        $echoue('Indiquez un domaine valide, par exemple « lemonde.fr ».');

                        return;
                    }

                    $existe = TrustedSource::query()
                        ->where('domain', $normalise)
                        ->when($this->enEdition !== null,
                            fn (Builder $q) => $q->where('uuid', '!=', $this->enEdition))
                        ->exists();

                    if ($existe) {
                        $echoue('Ce domaine est déjà référencé.');
                    }
                },
            ],
            'categorieUuid' => ['nullable', 'string', Rule::in($this->familles()->pluck('uuid')->all())],
            'lien' => ['nullable', 'url', 'max:500'],
            /*
             | Bornée à 100 parce que la colonne est un `tinyInteger` non signé :
             | 256 y entrerait comme 0, et une source « très fiable » deviendrait
             | la moins fiable de la table sans qu'aucune erreur ne le dise.
             */
            'credibilite' => ['required', 'integer', 'min:0', 'max:100'],
            'active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom de la source',
            'domaine' => 'domaine',
            'categorieUuid' => 'famille',
            'lien' => 'adresse du site',
            'credibilite' => 'crédibilité',
            'note' => 'note éditoriale',
        ];
    }

    /**
     * @return Collection<int, SourceCategory>
     */
    private function familles(): Collection
    {
        return SourceCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    private function familleId(string $uuid): ?int
    {
        if ($uuid === '') {
            return null;
        }

        return SourceCategory::query()->where('uuid', $uuid)->value('id');
    }

    /**
     * @return array{total: int, actives: int, familles: int, credibilite: int}
     */
    private function indicateurs(): array
    {
        $actives = TrustedSource::query()->where('is_active', true);

        return [
            'total' => TrustedSource::query()->count(),
            'actives' => (clone $actives)->count(),
            'familles' => SourceCategory::query()->count(),
            // Sur les seules sources actives : la moyenne des fiches retirées
            // ne décrit rien de ce que le moteur voit aujourd'hui.
            'credibilite' => (int) round((float) (clone $actives)->avg('credibility')),
        ];
    }

    private function source(string $uuid): TrustedSource
    {
        return TrustedSource::query()->where('uuid', $uuid)->firstOrFail();
    }
}
