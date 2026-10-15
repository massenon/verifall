{{--
  | « Catégories du Blog » (lot 8).
  |
  | Le plus simple des écrans du lot, et il n'y a pas de honte à ça : quatre
  | colonnes, aucun effet de bord. Ce qui compte tient en une phrase — le slug
  | est FIGÉ à la création, parce qu'il est l'adresse publique de la rubrique.
  |
  | Pas de drapeau de publication, contrairement aux catégories de cours : une
  | rubrique de blog vide ne s'affiche simplement pas côté public. C'est le
  | nombre de billets qui décide, pas un état de plus à tenir.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('staff.posts') }}" class="hover:text-[#065F46]">Articles du blog</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Rubriques</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Catégories du blog</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Les rubriques éditoriales. Une rubrique sans billet ne s'affiche pas côté public.
            </p>
        </div>

        @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouvelle rubrique
            </button>
        @endif
    </header>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Rubriques" trend="Créées" />

        <x-kpi variant="staff" :value="number_format($indicateurs['billets'], 0, ',', ' ')"
               label="Billets" trend="Tout le blog" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sans_rubrique'], 0, ',', ' ')"
               label="Sans rubrique" trend="À reclasser" />
    </x-kpi-grid>

    <section class="mb-4">
        <div class="ffield mb-0">
            <input id="cb-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="cb-q">Rechercher une rubrique</label>
        </div>
    </section>

    @if ($categories->isEmpty())
        <x-empty-state variant="staff" icon="category"
                       title="Aucune rubrique"
                       description="Les billets peuvent exister sans rubrique. Créez-en une dès que la liste devient longue." />
    @else
        <x-data-table variant="staff" :count="$categories->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Rubrique</th>
                <th scope="col">Billets</th>
                <th scope="col">Ordre</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($categories as $categorie)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $categorie->name }}</p>
                        <p class="text-xs text-[#64748B] mb-0 font-mono">{{ $categorie->slug }}</p>
                    </td>
                    <td class="whitespace-nowrap">{{ $categorie->posts_count }}</td>
                    <td class="whitespace-nowrap text-[#64748B]">{{ $categorie->position }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="ouvrirEdition('{{ $categorie->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $categorie->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $categories->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition === null ? 'Nouvelle rubrique' : 'Modifier la rubrique'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div class="ffield">
                    <input id="cb-nom" type="text" wire:model="nom" placeholder=" ">
                    <label for="cb-nom">Nom de la rubrique</label>
                    @if ($enEdition !== null)
                        <p class="text-xs text-[#64748B] mt-1 mb-0">
                            L'adresse publique (le slug) ne change pas : un billet se partage et s'indexe.
                        </p>
                    @endif
                    @error('nom') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="cb-ordre" type="number" min="0" wire:model="ordre" placeholder=" ">
                    <label for="cb-ordre">Ordre d'affichage</label>
                    @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette rubrique ?"
                    :description="$categorieVisee !== null && $categorieVisee->posts_count > 0
                        ? 'Les '.$categorieVisee->posts_count.' billet(s) de cette rubrique NE seront PAS supprimés : ils resteront au blog, sans rubrique.'
                        : 'Cette rubrique ne contient aucun billet. La suppression est définitive.'"
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
