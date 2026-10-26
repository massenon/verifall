{{--
  | « Catégories de cours » (lot 6).
  |
  | `courses.category` était une chaîne libre, et deux semeurs y avaient écrit
  | chacun dans son style : « Fact-Checking », « OSINT », « IA » d'un côté ;
  | « investigation », « securite », « sante », « methode » de l'autre. Le
  | catalogue citoyen bâtit son menu de filtres par `distinct()->pluck()` : il
  | affichait donc les deux conventions dans la même liste déroulante.
  |
  | La migration a ADOPTÉ l'existant plutôt que de le remplacer — aucun cours
  | n'a changé de rayon. C'est depuis cet écran que la rédaction unifie, en un
  | point et pour tous les cours du rayon à la fois.
--}}
<div>

<x-premium.entete titre="Catégories de cours" :fil="['Catégories de cours']">
        Les rayons du catalogue. Renommer ici met à jour tous les cours du rayon d'un seul geste — c'est ce que la chaîne libre interdisait.
        <x-slot:actions>
            @can('create', App\Models\CourseCategory::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle catégorie
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Catégories"
               :trend="$indicateurs['publiees'].' au catalogue'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publiees'], 0, ',', ' ')"
               label="Affichées"
               trend="Visibles côté citoyen" />

        <x-kpi variant="staff" :value="number_format($indicateurs['cours'], 0, ',', ' ')"
               label="Cours"
               trend="Tout le catalogue" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sans_categorie'], 0, ',', ' ')"
               label="Sans catégorie"
               trend="À reclasser" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="catc-q" class="etiquette">Rechercher une catégorie</label>
<input id="catc-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>
    </section>

    @if ($categories->isEmpty())
        <x-empty-state variant="staff" icon="category"
                       title="Aucune catégorie"
                       description="Le catalogue affichera les cours sans rayon. Créez une catégorie pour les regrouper.">
            <x-slot:action>
                @can('create', App\Models\CourseCategory::class)
                    <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                        Nouvelle catégorie
                    </button>
                @endcan
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$categories->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Catégorie</th>
                <th scope="col">Cours</th>
                <th scope="col">Ordre</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($categories as $categorie)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            @if ($categorie->icon !== null)
                                <x-lucide :name="$categorie->icon" class="w-4 h-4 text-encre-3 dark:text-nuit-3" />
                            @endif
                            <div>
                                <p class="font-semibold text-encre dark:text-white mb-0">{{ $categorie->name }}</p>
                                @if ($categorie->description !== null)
                                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ Str::limit($categorie->description, 80) }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap">{{ $categorie->cours_count }}</td>
                    <td class="whitespace-nowrap text-encre-3 dark:text-nuit-3">{{ $categorie->sort_order }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$categorie->is_published ? 'publie' : 'brouillon'"
                                        :label="$categorie->is_published ? 'Au catalogue' : 'Masquée'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $categorie)
                            <button type="button" wire:click="basculerLaPublication('{{ $categorie->uuid }}')"
                                    class="btn-icone" title="{{ $categorie->is_published ? 'Masquer du catalogue' : 'Afficher au catalogue' }}">
                                <x-lucide :name="$categorie->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $categorie->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                        @endcan
                        @can('delete', $categorie)
                            <button type="button" wire:click="demanderLaSuppression('{{ $categorie->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $categories->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouvelle catégorie' : 'Modifier la catégorie'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div>
                <label for="catc-nom" class="etiquette">Nom de la catégorie</label>
<input id="catc-nom" type="text" wire:model="nom" class="champ">
                @if ($enEdition !== null)
                    {{-- L'avertissement compte : le renommage touche des cours
                         déjà publiés, et c'est justement ce qu'on veut. --}}
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                        Le nouveau nom sera reporté sur tous les cours de ce rayon, catalogue citoyen compris.
                    </p>
                @endif
                @error('nom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="catc-desc" class="etiquette">Ce que ce rayon regroupe</label>
<textarea id="catc-desc" wire:model="descriptif" rows="3" class="champ !h-auto py-2.5"></textarea>
                @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="mb-0">
                    <label for="catc-icone" class="etiquette">Icône (Material Symbols)</label>
<input id="catc-icone" type="text" wire:model="icone" class="champ">
                    @error('icone') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="catc-ordre" class="etiquette">Ordre d'affichage</label>
<input id="catc-ordre" type="number" min="0" wire:model="ordre" class="champ">
                    @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="publiee" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">
                    Afficher au catalogue
                    <span class="block text-xs text-encre-3 dark:text-nuit-3">
                        Décochée, la catégorie reste ici mais disparaît des filtres du citoyen.
                    </span>
                </span>
            </label>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette catégorie ?"
                    :description="$categorieASupprimer !== null && $categorieASupprimer->cours_count > 0
                        ? 'Les '.$categorieASupprimer->cours_count.' cours de ce rayon NE seront PAS supprimés : ils resteront au catalogue, sans catégorie. Pour retirer simplement le rayon des filtres, masquez-le plutôt.'
                        : 'Ce rayon ne contient aucun cours. La suppression est définitive.'"
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
