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
<div>

<x-premium.entete titre="Catégories du blog" :fil="['Rubriques']">
        Les rubriques éditoriales. Une rubrique sans billet ne s'affiche pas côté public.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle rubrique
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Rubriques" trend="Créées" />

        <x-kpi variant="staff" :value="number_format($indicateurs['billets'], 0, ',', ' ')"
               label="Billets" trend="Tout le blog" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sans_rubrique'], 0, ',', ' ')"
               label="Sans rubrique" trend="À reclasser" />
    </x-kpi-grid>

    <section class="mb-4">
        <div class="mb-0">
            <label for="cb-q" class="etiquette">Rechercher une rubrique</label>
<input id="cb-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
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
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $categorie->name }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 font-mono">{{ $categorie->slug }}</p>
                    </td>
                    <td class="whitespace-nowrap">{{ $categorie->posts_count }}</td>
                    <td class="whitespace-nowrap text-encre-3 dark:text-nuit-3">{{ $categorie->position }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="ouvrirEdition('{{ $categorie->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $categorie->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
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
                <div>
                    <label for="cb-nom" class="etiquette">Nom de la rubrique</label>
<input id="cb-nom" type="text" wire:model="nom" class="champ">
                    @if ($enEdition !== null)
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                            L'adresse publique (le slug) ne change pas : un billet se partage et s'indexe.
                        </p>
                    @endif
                    @error('nom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cb-ordre" class="etiquette">Ordre d'affichage</label>
<input id="cb-ordre" type="number" min="0" wire:model="ordre" class="champ">
                    @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
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
