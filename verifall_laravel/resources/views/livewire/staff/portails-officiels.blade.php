{{--
  | Hub — Portails Officiels : le dernier écran du prototype sans route.
  |
  | `hub_portals` existait avec cinq lignes semées et RIEN ne les lisait — ni
  | CRUD, ni affichage, ni API. Une table qu'on écrit et que personne ne lit.
  |
  | L'écran porte aussi la bascule d'ouverture de la rubrique citoyenne : le
  | porteur du projet a demandé le CRUD maintenant et l'exposition plus tard.
--}}
<div>

    <x-premium.entete titre="Portails officiels">
        L'annuaire des sites de l'administration. C'est la rubrique qui répond à « ce site est-il bien celui du ministère, ou un site qui lui ressemble ? »
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Ajouter un portail
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    {{-- ── LA BASCULE D'OUVERTURE ────────────────────────────────────────────
         Un geste d'administration, pas un déploiement. Tant qu'elle est fermée,
         l'entrée du menu citoyen reste visible mais désactivée, et l'adresse
         répond 404. --}}
    <div class="carte mb-5">
        <div class="carte-corps flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-titre font-bold text-encre dark:text-white mb-0">
                    Rubrique citoyenne :
                    <span class="{{ $accesCitoyenOuvert ? 'pastille p-bon' : 'pastille p-alerte' }}">
                        {{ $accesCitoyenOuvert ? 'ouverte' : 'fermée' }}
                    </span>
                </p>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 mt-1">
                    @if ($accesCitoyenOuvert)
                        L'entrée « Portails officiels » est cliquable dans le menu du compte.
                    @else
                        L'entrée figure dans le menu du compte, désactivée, et l'adresse répond 404.
                        Ouvrez la rubrique quand le contenu vous paraît prêt.
                    @endif
                </p>
            </div>

            @if ($peutGerer)
                <button type="button" wire:click="basculerLAccesCitoyen"
                        class="{{ $accesCitoyenOuvert ? 'btn-second' : 'btn-primaire' }}">
                    {{ $accesCitoyenOuvert ? 'Refermer la rubrique' : 'Ouvrir la rubrique' }}
                </button>
            @endif
        </div>
    </div>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')" label="Portails" />
        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')" label="Publiés" />
        <x-kpi variant="staff" :value="number_format($indicateurs['officiels'], 0, ',', ' ')" label="Marqués officiels" />
    </x-kpi-grid>

    <div class="flex flex-wrap items-center gap-3 my-5">
        <input type="search" wire:model.live.debounce.350ms="recherche"
               class="champ" style="max-width: 320px;"
               placeholder="Nom ou adresse…" aria-label="Rechercher un portail">

        <select wire:model.live="filtreCategorie" class="champ" style="max-width: 220px;" aria-label="Filtrer par rubrique">
            <option value="">Toutes les rubriques</option>
            @foreach ($categories as $rubrique)
                <option value="{{ $rubrique }}">{{ $rubrique }}</option>
            @endforeach
        </select>
    </div>

    @if ($portails->total() === 0)
        <x-empty-state variant="staff" icon="account_balance"
                       title="Aucun portail"
                       description="Ajoutez les sites officiels que les citoyens doivent pouvoir reconnaître." />
    @else
        <x-data-table variant="staff" :count="$portails->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Portail</th>
                <th scope="col">Rubrique</th>
                <th scope="col">Ordre</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($portails as $portail)
                <tr wire:key="portail-{{ $portail->uuid }}">
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">
                            {{ $portail->name }}
                            @if ($portail->is_official)
                                <span class="pastille p-info">Officiel</span>
                            @endif
                        </p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 truncate" style="max-width: 380px;">{{ $portail->url }}</p>
                    </td>
                    <td class="text-sm text-encre-3 dark:text-nuit-3">{{ $portail->category }}</td>
                    <td class="text-sm text-encre-3 dark:text-nuit-3">{{ $portail->position }}</td>
                    <td>
                        <span class="{{ $portail->is_published ? 'pastille p-bon' : 'pastille p-neutre' }}">
                            {{ $portail->is_published ? 'Publié' : 'Brouillon' }}
                        </span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLaPublication('{{ $portail->uuid }}')"
                                    class="btn-icone" title="{{ $portail->is_published ? 'Retirer' : 'Publier' }}">
                                <x-lucide :name="$portail->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $portail->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $portail->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $portails->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition !== null ? 'Modifier le portail' : 'Ajouter un portail'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div>
                    <label for="po-nom" class="etiquette">Nom du portail</label>
<input id="po-nom" type="text" wire:model="nom" class="champ">
                    @error('nom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="po-lien" class="etiquette">Adresse (https://)</label>
<input id="po-lien" type="url" wire:model="lien" class="champ">
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                        `https://` est exigé : un portail officiel que le navigateur signale comme
                        non sécurisé serait le pire exemple sur une plateforme qui enseigne à se méfier.
                    </p>
                    @error('lien') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="po-rubrique" class="etiquette">Rubrique</label>
<input id="po-rubrique" type="text" wire:model="categorie" list="po-rubriques" class="champ">
                    <datalist id="po-rubriques">
                        @foreach ($categories as $rubrique)
                            <option value="{{ $rubrique }}"></option>
                        @endforeach
                    </datalist>
                    @error('categorie') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="po-desc" class="etiquette">Descriptif</label>
<textarea id="po-desc" wire:model="descriptif" rows="3" class="champ !h-auto py-2.5"></textarea>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">Ce que ce site permet de faire, en une phrase.</p>
                    @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="po-logo" class="etiquette">Chemin du logo (facultatif)</label>
<input id="po-logo" type="text" wire:model="logo" class="champ">
                </div>

                <div>
                    <label for="po-ordre" class="etiquette">Ordre d'affichage</label>
<input id="po-ordre" type="number" min="0" step="10" wire:model="ordre" class="champ">
                    @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-encre dark:text-white">
                    <input type="checkbox" wire:model="officiel">
                    Marquer comme site officiel
                </label>

                <label class="flex items-center gap-2 text-sm text-encre dark:text-white">
                    <input type="checkbox" wire:model="publie">
                    Publié
                </label>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>

        <x-delete-modal :open="$aSupprimer !== null"
                        title="Supprimer ce portail ?"
                        description="Il disparaîtra de l'annuaire citoyen. Pour le retirer sans perdre sa fiche, dépubliez-le plutôt."
                        confirm="supprimer"
                        cancel="annulerLaSuppression" />
    @endif
</div>
