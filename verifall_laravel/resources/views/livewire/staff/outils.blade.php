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
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
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
        <div class="ffield flex-1 mb-0">
            <input id="outils-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="outils-q">Rechercher un nom, une description, un mot-clef</label>
        </div>

        <select wire:model.live="filtreCategorie" class="st-input w-auto" aria-label="Filtrer par catégorie">
            <option value="">Catégorie : toutes</option>
            @foreach ($categories as $valeur => $suffixe)
                <option value="{{ $valeur }}">{{ $valeur }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publies">Publiés</option>
            <option value="brouillons">Brouillons</option>
            <option value="recommandes">Recommandés</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtreCategorie !== '' || $filtreEtat !== '')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @if ($recherche !== '')
                <span class="st-chip">« {{ $recherche }} »
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtreCategorie !== '')
                <span class="st-chip">{{ $filtreCategorie }}
                    <button type="button" wire:click="$set('filtreCategorie', '')" aria-label="Retirer le filtre de catégorie">×</button>
                </span>
            @endif
            @if ($filtreEtat !== '')
                <span class="st-chip">{{ $filtreEtat }}
                    <button type="button" wire:click="$set('filtreEtat', '')" aria-label="Retirer le filtre d'état">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-[#059669] font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($outils->isEmpty())
        <x-empty-state variant="staff" icon="handyman"
                       title="Aucun outil"
                       description="Aucune fiche ne correspond à ces critères. Tant que le répertoire est vide, l'écran « Répertoire des outils » citoyen l'est aussi.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
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
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $outil->title }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ Str::limit($outil->description, 90) }}</p>
                    </td>
                    <td class="whitespace-nowrap"><span class="st-badge st-badge-info">{{ $outil->category }}</span></td>
                    <td>
                        {{-- `rel` complet : un lien vers un site tiers ouvert depuis le
                             back-office ne doit ni transmettre notre adresse, ni garder
                             la main sur l'onglet d'origine. --}}
                        <a href="{{ $outil->url }}" target="_blank" rel="noopener noreferrer nofollow"
                           class="text-sm text-[#059669] underline break-all">{{ Str::limit($outil->url, 50) }}</a>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-badge-statut variant="staff"
                                            :statut="$outil->is_published ? 'publie' : 'brouillon'"
                                            :label="$outil->is_published ? 'Publié' : 'Brouillon'" />
                            @if ($outil->is_recommended)
                                <span class="st-badge st-badge-pub">Recommandé</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $outil)
                            <button type="button" wire:click="basculerLaPublication('{{ $outil->uuid }}')"
                                    class="st-icnbtn" title="{{ $outil->is_published ? 'Retirer du répertoire' : 'Publier' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $outil->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $outil->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                        @endcan
                        @can('delete', $outil)
                            <button type="button" wire:click="demanderLaSuppression('{{ $outil->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
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
            <div class="ffield">
                <input id="outil-titre" type="text" wire:model="titre" placeholder=" ">
                <label for="outil-titre">Nom de l'outil</label>
                @error('titre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="outil-desc" wire:model="descriptif" rows="4" placeholder=" "></textarea>
                <label for="outil-desc">À quoi il sert</label>
                @error('descriptif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="outil-cat" class="block text-sm font-medium text-[#0F172A] mb-1">Catégorie</label>
                    <select id="outil-cat" wire:model="categorie" class="st-input w-full">
                        @foreach ($categories as $valeur => $suffixe)
                            <option value="{{ $valeur }}">{{ $valeur }}</option>
                        @endforeach
                    </select>
                    {{-- Liste fermée : ces valeurs sont les clés de filtre de l'écran
                         citoyen. Une catégorie inédite rendrait la fiche introuvable. --}}
                    @error('categorie') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="outil-tag" type="text" wire:model="etiquette" placeholder=" ">
                    <label for="outil-tag">Étiquette (ex. Gratuit)</label>
                    @error('etiquette') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="ffield">
                <input id="outil-url" type="url" wire:model="lien" placeholder=" ">
                <label for="outil-url">Adresse de l'outil (https)</label>
                @error('lien') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="ffield mb-0">
                    <input id="outil-lib" type="text" wire:model="libelleLien" placeholder=" ">
                    <label for="outil-lib">Libellé du bouton</label>
                    @error('libelleLien') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="outil-icone" type="text" wire:model="icone" placeholder=" ">
                    <label for="outil-icone">Icône (Material Symbols)</label>
                    @error('icone') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="ffield">
                <input id="outil-kw" type="text" wire:model="motsClefs" placeholder=" ">
                <label for="outil-kw">Mots-clefs de recherche</label>
                @error('motsClefs') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <input id="outil-ordre" type="number" min="0" wire:model="ordre" placeholder=" ">
                <label for="outil-ordre">Ordre dans la catégorie</label>
                @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="recommande" class="mt-0.5">
                <span class="text-sm text-[#334155]">Outil recommandé</span>
            </label>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="publie" class="mt-0.5">
                <span class="text-sm text-[#334155]">
                    Publier au répertoire
                    <span class="block text-xs text-[#64748B]">
                        Vérifiez le lien avant de publier : c'est tout ce que cette fiche apporte.
                    </span>
                </span>
            </label>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette fiche ?"
                    description="La suppression est définitive. Pour retirer temporairement un outil du répertoire, dépubliez-le plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
