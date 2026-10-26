{{--
  | « Répertoire des outils » côté administration (lot 2).
  |
  | L'entrée du menu latéral pointait sur `citizen.tools` : l'administrateur
  | quittait le back-office pour l'espace citoyen, guard compris. Même défaut
  | que les Live Classes, mais celui-là n'avait pas été signalé — il a été
  | trouvé en auditant le gabarit.
  |
  | La catégorie est un SELECT sur liste fermée, jamais un champ libre : les
  | valeurs sont les clés de filtre de l'écran citoyen, et « Images » à côté
  | d'« images » rendrait des fiches invisibles sans lever d'erreur.
--}}
<div>

<x-premium.entete titre="Répertoire des outils" :fil="['Répertoire des outils']">
        Cet annuaire ne calcule rien : sa valeur tient à l'exactitude de ses liens. Un service qui ferme, un domaine racheté, et la fiche envoie quelqu'un au mauvais endroit.
        <x-slot:actions>
            @can('create', App\Models\Tool::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Ajouter un outil
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Fiches"
               :trend="$indicateurs['publies'].' publiées'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Publiées"
               trend="Visibles côté citoyen" />

        <x-kpi variant="staff" :value="number_format($indicateurs['recommandes'], 0, ',', ' ')"
               label="Recommandés"
               trend="Mis en avant dans la liste" />

        <x-kpi variant="staff" :value="number_format($indicateurs['categories'], 0, ',', ' ')"
               label="Catégories"
               trend="Liste fermée, non modifiable ici" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="outils-q" class="etiquette">Rechercher un nom, une description, un mot-clef</label>
<input id="outils-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreCategorie" class="champ w-auto" aria-label="Filtrer par catégorie">
            <option value="">Catégorie : toutes</option>
            @foreach ($categories as $valeur => $suffixe)
                <option value="{{ $valeur }}">{{ $valeur }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publies">Publiés</option>
            <option value="brouillons">Brouillons</option>
            <option value="recommandes">Recommandés</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtreCategorie !== '' || $filtreEtat !== '')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @if ($recherche !== '')
                <span class="pastille p-bon">« {{ $recherche }} »
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtreCategorie !== '')
                <span class="pastille p-bon">{{ $filtreCategorie }}
                    <button type="button" wire:click="$set('filtreCategorie', '')" aria-label="Retirer le filtre de catégorie">×</button>
                </span>
            @endif
            @if ($filtreEtat !== '')
                <span class="pastille p-bon">{{ $filtreEtat }}
                    <button type="button" wire:click="$set('filtreEtat', '')" aria-label="Retirer le filtre d'état">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-brand-700 dark:text-brand font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($outils->isEmpty())
        <x-empty-state variant="staff" icon="handyman"
                       title="Aucun outil"
                       description="Aucune fiche ne correspond à ces critères. Tant que le répertoire est vide, l'écran « Répertoire des outils » citoyen l'est aussi.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$outils->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Outil</th>
                <th scope="col">Catégorie</th>
                <th scope="col">Adresse</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($outils as $outil)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $outil->title }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ Str::limit($outil->description, 90) }}</p>
                    </td>
                    <td class="whitespace-nowrap"><span class="pastille p-info">{{ $outil->category }}</span></td>
                    <td>
                        {{-- `rel` complet : un lien vers un site tiers ouvert depuis le
                             back-office ne doit ni transmettre notre adresse, ni garder
                             la main sur l'onglet d'origine. --}}
                        <a href="{{ $outil->url }}" target="_blank" rel="noopener noreferrer nofollow"
                           class="text-sm text-brand-700 dark:text-brand underline break-all">{{ Str::limit($outil->url, 50) }}</a>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-badge-statut variant="staff"
                                            :statut="$outil->is_published ? 'publie' : 'brouillon'"
                                            :label="$outil->is_published ? 'Publié' : 'Brouillon'" />
                            @if ($outil->is_recommended)
                                <span class="pastille p-bon">Recommandé</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $outil)
                            <button type="button" wire:click="basculerLaPublication('{{ $outil->uuid }}')"
                                    class="btn-icone" title="{{ $outil->is_published ? 'Retirer du répertoire' : 'Publier' }}">
                                <x-lucide :name="$outil->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $outil->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                        @endcan
                        @can('delete', $outil)
                            <button type="button" wire:click="demanderLaSuppression('{{ $outil->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $outils->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Ajouter un outil' : 'Modifier la fiche'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div>
                <label for="outil-titre" class="etiquette">Nom de l'outil</label>
<input id="outil-titre" type="text" wire:model="titre" class="champ">
                @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="outil-desc" class="etiquette">À quoi il sert</label>
<textarea id="outil-desc" wire:model="descriptif" rows="4" class="champ !h-auto py-2.5"></textarea>
                @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="outil-cat" class="block text-sm font-medium text-encre dark:text-white mb-1">Catégorie</label>
                    <select id="outil-cat" wire:model="categorie" class="champ w-full">
                        @foreach ($categories as $valeur => $suffixe)
                            <option value="{{ $valeur }}">{{ $valeur }}</option>
                        @endforeach
                    </select>
                    {{-- Liste fermée : ces valeurs sont les clés de filtre de l'écran
                         citoyen. Une catégorie inédite rendrait la fiche introuvable. --}}
                    @error('categorie') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="outil-tag" class="etiquette">Étiquette (ex. Gratuit)</label>
<input id="outil-tag" type="text" wire:model="etiquette" class="champ">
                    @error('etiquette') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="outil-url" class="etiquette">Adresse de l'outil (https)</label>
<input id="outil-url" type="url" wire:model="lien" class="champ">
                @error('lien') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="mb-0">
                    <label for="outil-lib" class="etiquette">Libellé du bouton</label>
<input id="outil-lib" type="text" wire:model="libelleLien" class="champ">
                    @error('libelleLien') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="outil-icone" class="etiquette">Icône (Material Symbols)</label>
<input id="outil-icone" type="text" wire:model="icone" class="champ">
                    @error('icone') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="outil-kw" class="etiquette">Mots-clefs de recherche</label>
<input id="outil-kw" type="text" wire:model="motsClefs" class="champ">
                @error('motsClefs') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="outil-ordre" class="etiquette">Ordre dans la catégorie</label>
<input id="outil-ordre" type="number" min="0" wire:model="ordre" class="champ">
                @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="recommande" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">Outil recommandé</span>
            </label>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="publie" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">
                    Publier au répertoire
                    <span class="block text-xs text-encre-3 dark:text-nuit-3">
                        Vérifiez le lien avant de publier : c'est tout ce que cette fiche apporte.
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
                    title="Supprimer cette fiche ?"
                    description="La suppression est définitive. Pour retirer temporairement un outil du répertoire, dépubliez-le plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
