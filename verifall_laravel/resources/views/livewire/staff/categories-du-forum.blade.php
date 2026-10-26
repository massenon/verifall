{{--
  | « Catégories du Forum » (lot 8).
  |
  | Le slug est FIGÉ à la création : c'est la clé de filtre de l'écran citoyen
  | ET de l'API mobile (`ForumController` cherche par slug). Le laisser suivre
  | un nom corrigé casserait les deux d'un coup, sans erreur pour le signaler.
  |
  | La désactivation retire la rubrique des choix du citoyen SANS toucher aux
  | sujets qu'elle contient. C'est le geste ordinaire quand une rubrique ne
  | prend pas ; la suppression, elle, laisse les sujets sans rubrique.
--}}
<div>

<x-premium.entete titre="Catégories du forum" :fil="['Rubriques']">
        Les rubriques du débat citoyen. La couleur sert la pastille du forum : deux rubriques de la même teinte rendent le repérage inopérant.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle rubrique
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Rubriques" trend="Créées" />

        <x-kpi variant="staff" :value="number_format($indicateurs['actives'], 0, ',', ' ')"
               label="Ouvertes" trend="Le citoyen peut y publier" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sujets'], 0, ',', ' ')"
               label="Sujets" trend="Tout le forum" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sans_rubrique'], 0, ',', ' ')"
               label="Sans rubrique" trend="À reclasser" />
    </x-kpi-grid>

    <section class="mb-4">
        <div class="mb-0">
            <label for="cf-q" class="etiquette">Rechercher une rubrique</label>
<input id="cf-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>
    </section>

    @if ($categories->isEmpty())
        <x-empty-state variant="staff" icon="category"
                       title="Aucune rubrique"
                       description="Sans rubrique, les citoyens publient dans un forum sans classement — et personne ne retrouve rien." />
    @else
        <x-data-table variant="staff" :count="$categories->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Rubrique</th>
                <th scope="col">Couleur</th>
                <th scope="col">Sujets</th>
                <th scope="col">Ordre</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($categories as $categorie)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $categorie->name }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 font-mono">{{ $categorie->slug }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        {{-- La pastille est rendue par un attribut `style` et non une
                             classe : la couleur est une donnée, et le build Tailwind
                             est figé — aucune classe arbitraire ne s'y ajoute. --}}
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-4 h-4 rounded-full"
                                  style="background: {{ $categorie->color ?? '#059669' }}"
                                  aria-hidden="true"></span>
                            <span class="text-xs text-encre-3 dark:text-nuit-3 font-mono">{{ $categorie->color }}</span>
                        </span>
                    </td>
                    <td class="whitespace-nowrap">{{ $categorie->threads_count }}</td>
                    <td class="whitespace-nowrap text-encre-3 dark:text-nuit-3">{{ $categorie->position }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$categorie->is_active ? 'publie' : 'brouillon'"
                                        :label="$categorie->is_active ? 'Ouverte' : 'Fermée'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLActivation('{{ $categorie->uuid }}')"
                                    class="btn-icone" title="{{ $categorie->is_active ? 'Fermer la rubrique' : 'Rouvrir' }}">
                                <x-lucide :name="$categorie->is_active ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
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
                    <label for="cf-nom" class="etiquette">Nom de la rubrique</label>
<input id="cf-nom" type="text" wire:model="nom" class="champ">
                    @if ($enEdition !== null)
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                            L'adresse (le slug) ne change pas : elle sert le filtre citoyen et l'API mobile.
                        </p>
                    @endif
                    @error('nom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="cf-couleur" class="block text-sm font-medium text-encre dark:text-white mb-1">Couleur</label>
                        <input id="cf-couleur" type="color" wire:model="couleur" class="champ w-full">
                        @error('couleur') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="cf-ordre" class="etiquette">Ordre d'affichage</label>
<input id="cf-ordre" type="number" min="0" wire:model="ordre" class="champ">
                        @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="active" class="mt-0.5">
                    <span class="text-sm text-encre-2 dark:text-nuit-2">
                        Rubrique ouverte
                        <span class="block text-xs text-encre-3 dark:text-nuit-3">
                            Décochée, elle disparaît des choix du citoyen — les sujets existants restent lisibles.
                        </span>
                    </span>
                </label>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette rubrique ?"
                    :description="$categorieVisee !== null && $categorieVisee->threads_count > 0
                        ? 'Les '.$categorieVisee->threads_count.' sujet(s) de cette rubrique NE seront PAS supprimés : ils resteront au forum, sans rubrique. Pour la retirer simplement des choix du citoyen, FERMEZ-la plutôt.'
                        : 'Cette rubrique ne contient aucun sujet. La suppression est définitive.'"
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
