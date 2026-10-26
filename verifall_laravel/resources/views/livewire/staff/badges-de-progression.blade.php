{{--
  | « Badges de progression » (lot 9).
  |
  | Le SLUG est figé, et c'est plus grave qu'ailleurs : `AttributionDesBadges`
  | reconnaît les badges par leur slug. Le laisser suivre un nom corrigé ne
  | casserait pas un lien — ça ARRÊTERAIT l'attribution, silencieusement, et
  | seulement pour les citoyens à venir.
  |
  | Les récompenses ne se rejouent pas : XP et unités sont versés au moment du
  | déblocage. L'écran le dit à l'enregistrement, parce que c'est exactement ce
  | qu'on croit en modifiant le chiffre.
--}}
<div>

<x-premium.entete titre="Badges de progression" :fil="['Badges de progression']">
        Ce que les citoyens débloquent, et ce que ça leur rapporte. La condition affichée doit correspondre à la règle réelle — sinon elle promet ce qui n'arrivera pas.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouveau badge
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Badges" trend="Définis" />

        <x-kpi variant="staff" :value="number_format($indicateurs['actifs'], 0, ',', ' ')"
               label="Actifs" trend="Peuvent être débloqués" />

        <x-kpi variant="staff" :value="number_format($indicateurs['debloques'], 0, ',', ' ')"
               label="Déblocages" trend="Tous badges confondus" />

        <x-kpi variant="staff" :value="number_format($indicateurs['unites_versees'], 0, ',', ' ')"
               label="Unités versées" trend="Estimation au tarif actuel" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="bd-q" class="etiquette">Rechercher un badge</label>
<input id="bd-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="actifs">Actifs</option>
            <option value="inactifs">Inactifs</option>
        </select>
    </section>

    @if ($badges->isEmpty())
        <x-empty-state variant="staff" icon="military_tech"
                       title="Aucun badge"
                       description="Sans badge, l'écran des récompenses du citoyen reste vide et la progression n'est jamais célébrée." />
    @else
        <x-data-table variant="staff" :count="$badges->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Badge</th>
                <th scope="col">Condition affichée</th>
                <th scope="col">Récompense</th>
                <th scope="col">Débloqué</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($badges as $badge)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            {{-- Un EMOJI, pas une ligature Material Symbols :
                                 `badges.icon` est un `string(16)`, et la police
                                 d'icônes tenterait de lire l'emoji comme un nom
                                 de glyphe. --}}
                            <span class="text-lg" aria-hidden="true">{{ $badge->icon }}</span>
                            <div>
                                <p class="font-semibold text-encre dark:text-white mb-0">{{ $badge->name }}</p>
                                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 font-mono">{{ $badge->slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm text-encre-2 dark:text-nuit-2">{{ Str::limit($badge->requirement_label, 60) }}</td>
                    <td class="whitespace-nowrap text-sm">
                        @if ($badge->xp_reward > 0)
                            <span class="text-encre-2 dark:text-nuit-2">{{ $badge->xp_reward }} XP</span>
                        @endif
                        @if ($badge->units_reward > 0)
                            <span class="block text-brand-700 dark:text-brand">{{ $badge->units_reward }} unité(s)</span>
                        @endif
                        @if ($badge->xp_reward === 0 && $badge->units_reward === 0)
                            <span class="text-xs text-encre-3 dark:text-nuit-3">Honorifique</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $badge->users_count }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$badge->is_active ? 'publie' : 'brouillon'"
                                        :label="$badge->is_active ? 'Actif' : 'Inactif'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLActivation('{{ $badge->uuid }}')"
                                    class="btn-icone" title="{{ $badge->is_active ? 'Désactiver' : 'Réactiver' }}">
                                <x-lucide :name="$badge->is_active ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $badge->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $badge->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $badges->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition === null ? 'Nouveau badge' : 'Modifier le badge'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div>
                    <label for="bd-nom" class="etiquette">Nom du badge</label>
<input id="bd-nom" type="text" wire:model="nom" class="champ">
                    @if ($enEdition !== null)
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                            L'identifiant technique ne change pas : c'est lui qui déclenche l'attribution.
                        </p>
                    @endif
                    @error('nom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="bd-desc" class="etiquette">Description</label>
<textarea id="bd-desc" wire:model="descriptif" rows="3" class="champ !h-auto py-2.5"></textarea>
                    @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="bd-cond" class="etiquette">Condition affichée au citoyen</label>
<input id="bd-cond" type="text" wire:model="condition" class="champ">
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                        Un libellé, pas une règle : c'est le code qui décide de l'attribution.
                        S'il ne correspond pas à la règle réelle, il promet ce qui n'arrivera pas.
                    </p>
                    @error('condition') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="mb-0">
                        <label for="bd-icone" class="etiquette">Icône (emoji)</label>
<input id="bd-icone" type="text" wire:model="icone" class="champ">
                        @error('icone') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="bd-xp" class="etiquette">Points XP</label>
<input id="bd-xp" type="number" min="0" max="10000" wire:model="xp" class="champ">
                        @error('xp') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="bd-unites" class="etiquette">Unités</label>
<input id="bd-unites" type="number" min="0" max="100" wire:model="unites" class="champ">
                        @error('unites') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="bd-ordre" class="etiquette">Ordre d'affichage</label>
<input id="bd-ordre" type="number" min="0" wire:model="ordre" class="champ">
                    @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="actif" class="mt-0.5">
                    <span class="text-sm text-encre-2 dark:text-nuit-2">
                        Badge actif
                        <span class="block text-xs text-encre-3 dark:text-nuit-3">
                            Désactivé, il n'est plus attribué — ceux qui l'ont déjà le gardent.
                        </span>
                    </span>
                </label>

                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                    Les récompenses sont versées au moment du déblocage.
                    Les modifier ne crédite ni ne reprend rien à ceux qui ont déjà le badge.
                </p>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer ce badge ?"
                    :description="$badgeVise !== null && $badgeVise->users_count > 0
                        ? $badgeVise->users_count.' citoyen(s) ont débloqué ce badge : leur tableau de bord affichera une case vide. Pour l\'arrêter sans toucher au passé, DÉSACTIVEZ-le plutôt.'
                        : 'Aucun citoyen n\'a débloqué ce badge. La suppression est définitive.'"
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
