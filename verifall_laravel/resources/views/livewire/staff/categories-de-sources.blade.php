{{--
  | « Catégories de sources » (lot 5).
  |
  | Contrairement aux catégories d'outils — liste fermée reprise du prototype —
  | ces familles sont créées par la rédaction. D'où une table, et non une chaîne
  | libre : « Presse » à côté de « presse » casserait le filtre du référentiel
  | sans qu'aucune erreur ne le signale.
  |
  | La suppression est en `nullOnDelete` : elle laisse les sources en place,
  | sans famille. C'est le bon comportement, mais c'est contre-intuitif — la
  | confirmation le dit, et le compteur d'orphelines reste affiché.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('staff.trusted-sources') }}" class="hover:text-[#065F46]">Sources vérifiées</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Catégories</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Catégories de sources</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Les familles du référentiel. Chacune porte une crédibilité par défaut, proposée à la création d'une fiche —
                référencer trente agences de presse ne doit pas demander de taper « 90 » trente fois.
            </p>
        </div>

        @can('create', App\Models\SourceCategory::class)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouvelle famille
            </button>
        @endcan
    </header>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Familles"
               trend="Créées par la rédaction" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sources'], 0, ',', ' ')"
               label="Sources classées"
               trend="Dans tout le référentiel" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sans_famille'], 0, ',', ' ')"
               label="Sans famille"
               trend="À reclasser" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="fam-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="fam-q">Rechercher une famille</label>
        </div>
    </section>

    @if ($familles->isEmpty())
        <x-empty-state variant="staff" icon="category"
                       title="Aucune famille"
                       description="Les sources peuvent exister sans famille : le classement est un confort de lecture, pas une condition. Créez-en une dès que la liste devient longue.">
            <x-slot:action>
                @can('create', App\Models\SourceCategory::class)
                    <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                        Nouvelle famille
                    </button>
                @endcan
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$familles->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Famille</th>
                <th scope="col">Crédibilité par défaut</th>
                <th scope="col">Sources</th>
                <th scope="col">Ordre</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($familles as $famille)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $famille->name }}</p>
                        @if ($famille->description !== null)
                            <p class="text-xs text-[#64748B] mb-0">{{ Str::limit($famille->description, 90) }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $famille->default_credibility }} / 100</td>
                    <td class="whitespace-nowrap">{{ $famille->sources_count }}</td>
                    <td class="whitespace-nowrap text-[#64748B]">{{ $famille->sort_order }}</td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $famille)
                            <button type="button" wire:click="ouvrirEdition('{{ $famille->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                        @endcan
                        @can('delete', $famille)
                            <button type="button" wire:click="demanderLaSuppression('{{ $famille->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $familles->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouvelle famille' : 'Modifier la famille'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="ffield">
                <input id="fam-nom" type="text" wire:model="nom" placeholder=" ">
                <label for="fam-nom">Nom de la famille</label>
                @error('nom') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="fam-desc" wire:model="descriptif" rows="3" placeholder=" "></textarea>
                <label for="fam-desc">Ce que cette famille regroupe</label>
                @error('descriptif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="ffield mb-0">
                    <input id="fam-cred" type="number" min="0" max="100" wire:model="credibiliteParDefaut" placeholder=" ">
                    <label for="fam-cred">Crédibilité par défaut</label>
                    @error('credibiliteParDefaut') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="fam-ordre" type="number" min="0" wire:model="ordre" placeholder=" ">
                    <label for="fam-ordre">Ordre d'affichage</label>
                    @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="text-xs text-[#64748B] mb-0">
                La crédibilité par défaut n'est qu'une proposition : chaque fiche peut s'en écarter.
                Modifier une famille ne touche pas les sources déjà référencées.
            </p>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette famille ?"
                    :description="$familleASupprimer !== null && $familleASupprimer->sources_count > 0
                        ? 'Les '.$familleASupprimer->sources_count.' source(s) de cette famille NE seront PAS supprimées : elles resteront au référentiel, sans famille, et le moteur continuera de les reconnaître.'
                        : 'Cette famille ne contient aucune source. La suppression est définitive.'"
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
