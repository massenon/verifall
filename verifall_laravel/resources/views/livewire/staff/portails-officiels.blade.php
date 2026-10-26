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
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Ajouter un portail
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    {{-- ── LA BASCULE D'OUVERTURE ────────────────────────────────────────────
         Un geste d'administration, pas un déploiement. Tant qu'elle est fermée,
         l'entrée du menu citoyen reste visible mais désactivée, et l'adresse
         répond 404. --}}
    <div class="st-card mb-5">
        <div class="st-card-b flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-display font-bold text-[#0F172A] mb-0">
                    Rubrique citoyenne :
                    <span class="{{ $accesCitoyenOuvert ? 'st-badge st-badge-pub' : 'st-badge st-badge-warn' }}">
                        {{ $accesCitoyenOuvert ? 'ouverte' : 'fermée' }}
                    </span>
                </p>
                <p class="text-xs text-[#64748B] mb-0 mt-1">
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
                        class="st-btn {{ $accesCitoyenOuvert ? 'st-btn-secondary' : 'st-btn-primary' }}">
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
               class="st-input" style="max-width: 320px;"
               placeholder="Nom ou adresse…" aria-label="Rechercher un portail">

        <select wire:model.live="filtreCategorie" class="st-select" style="max-width: 220px;" aria-label="Filtrer par rubrique">
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
                        <p class="font-semibold text-[#0F172A] mb-0">
                            {{ $portail->name }}
                            @if ($portail->is_official)
                                <span class="st-badge st-badge-info">Officiel</span>
                            @endif
                        </p>
                        <p class="text-xs text-[#64748B] mb-0 truncate" style="max-width: 380px;">{{ $portail->url }}</p>
                    </td>
                    <td class="text-sm text-[#64748B]">{{ $portail->category }}</td>
                    <td class="text-sm text-[#64748B]">{{ $portail->position }}</td>
                    <td>
                        <span class="{{ $portail->is_published ? 'st-badge st-badge-pub' : 'st-badge st-badge-draft' }}">
                            {{ $portail->is_published ? 'Publié' : 'Brouillon' }}
                        </span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLaPublication('{{ $portail->uuid }}')"
                                    class="st-icnbtn" title="{{ $portail->is_published ? 'Retirer' : 'Publier' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $portail->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $portail->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $portail->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
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
                <div class="ffield">
                    <input id="po-nom" type="text" wire:model="nom" placeholder=" ">
                    <label for="po-nom">Nom du portail</label>
                    @error('nom') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="po-lien" type="url" wire:model="lien" placeholder=" ">
                    <label for="po-lien">Adresse (https://)</label>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">
                        `https://` est exigé : un portail officiel que le navigateur signale comme
                        non sécurisé serait le pire exemple sur une plateforme qui enseigne à se méfier.
                    </p>
                    @error('lien') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="po-rubrique" type="text" wire:model="categorie" placeholder=" " list="po-rubriques">
                    <label for="po-rubrique">Rubrique</label>
                    <datalist id="po-rubriques">
                        @foreach ($categories as $rubrique)
                            <option value="{{ $rubrique }}"></option>
                        @endforeach
                    </datalist>
                    @error('categorie') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <textarea id="po-desc" wire:model="descriptif" rows="3" placeholder=" "></textarea>
                    <label for="po-desc">Descriptif</label>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">Ce que ce site permet de faire, en une phrase.</p>
                    @error('descriptif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="po-logo" type="text" wire:model="logo" placeholder=" ">
                    <label for="po-logo">Chemin du logo (facultatif)</label>
                </div>

                <div class="ffield">
                    <input id="po-ordre" type="number" min="0" step="10" wire:model="ordre" placeholder=" ">
                    <label for="po-ordre">Ordre d'affichage</label>
                    @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-[#0F172A]">
                    <input type="checkbox" wire:model="officiel">
                    Marquer comme site officiel
                </label>

                <label class="flex items-center gap-2 text-sm text-[#0F172A]">
                    <input type="checkbox" wire:model="publie">
                    Publié
                </label>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
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
