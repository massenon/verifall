{{--
  | « Packs d'unités » — la grille tarifaire, modifiable depuis le back-office.
  |
  | Gabarit CRUD du prototype (compte-admin/unites-premium.html) : fil
  | d'Ariane, en-tête avec l'action principale, indicateurs, carte de recherche
  | et filtres, compteur de résultats, table sur grand écran et cartes sur
  | téléphone, tiroir de formulaire, modale de suppression nominative.
--}}
<div>

<x-premium.entete titre="Packs d'unités" :fil="['Packs d\'unités']">
        La grille vendue aux citoyens. Le prix et la quantité sont lus ici, jamais dans la requête d'achat.
        <x-slot:actions>
            @can('create', App\Models\UnitPack::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouveau pack
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="$indicateurs['packs']" label="Packs"
               :trend="$indicateurs['actifs'].' en vente'" />
        <x-kpi variant="staff" :value="number_format($indicateurs['encaisse'], 0, ',', ' ').' F'"
               label="Encaissé (30 j)" trend="paiements aboutis" />
        <x-kpi variant="staff" :value="number_format($indicateurs['achats'], 0, ',', ' ')"
               label="Achats (30 j)" trend="demandes abouties" />
        <x-kpi variant="staff"
               :value="$indicateurs['achats'] > 0 ? number_format($indicateurs['encaisse'] / $indicateurs['achats'], 0, ',', ' ').' F' : '—'"
               label="Panier moyen" trend="sur 30 jours" />
    </x-kpi-grid>

    {{-- Recherche débouncée 350 ms, comme partout ailleurs. --}}
    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="packs-q" class="etiquette">Rechercher un code, un libellé</label>
<input id="packs-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="actifs">En vente</option>
            <option value="retires">Retirés</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtreEtat !== '')
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @if ($recherche !== '')
                <span class="pastille p-bon">{{ $recherche }}
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtreEtat !== '')
                <span class="pastille p-bon">{{ $filtreEtat === 'actifs' ? 'En vente' : 'Retirés' }}
                    <button type="button" wire:click="$set('filtreEtat', '')" aria-label="Retirer le filtre d'état">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-brand-700 dark:text-brand font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($packs->isEmpty())
        <x-empty-state variant="staff" icon="sell"
                       title="Aucun pack"
                       description="Aucun pack ne correspond à ces critères. Sans pack en vente, l'écran d'achat citoyen reste vide.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$packs->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Pack</th>
                <th scope="col">Unités</th>
                <th scope="col">Prix</th>
                <th scope="col">Prix / unité</th>
                <th scope="col">Achats</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($packs as $pack)
                <tr data-row-el wire:key="pack-{{ $pack->id }}">
                    <td>
                        <span class="font-bold text-encre dark:text-white">{{ $pack->label }}</span>
                        <span class="block text-xs text-encre-3 dark:text-nuit-3 font-mono">{{ $pack->code }}</span>
                    </td>
                    <td><span class="font-bold">{{ number_format($pack->units, 0, ',', ' ') }}</span></td>
                    <td>{{ number_format($pack->price_fcfa, 0, ',', ' ') }} F</td>
                    {{-- Le seul chiffre qui dise si un pack est vraiment plus
                         avantageux qu'un autre, et le seul qu'on ne puisse pas
                         lire directement dans le tableau. --}}
                    <td class="text-encre-3 dark:text-nuit-3">{{ number_format($pack->prixParUnite(), 1, ',', ' ') }} F</td>
                    <td>{{ number_format($pack->payments_count, 0, ',', ' ') }}</td>
                    <td>
                        @if ($pack->is_featured)
                            <x-badge-statut variant="staff" statut="info" label="Mis en avant" />
                        @endif
                        <x-badge-statut variant="staff"
                                        :statut="$pack->is_active ? 'success' : 'neutral'"
                                        :label="$pack->is_active ? 'En vente' : 'Retiré'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $pack)
                            <button type="button" wire:click="ouvrirEdition('{{ $pack->uuid }}')"
                                    class="btn-fantome !min-h-[36px]">Modifier</button>
                            <button type="button" wire:click="basculerActivite('{{ $pack->uuid }}')"
                                    class="btn-fantome !min-h-[36px]">{{ $pack->is_active ? 'Retirer' : 'Remettre' }}</button>
                        @endcan
                        @can('delete', $pack)
                            <button type="button" wire:click="demanderSuppression('{{ $pack->uuid }}')"
                                    class="btn-fantome !min-h-[36px] !text-critique">Supprimer</button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-card-list variant="staff">
            @foreach ($packs as $pack)
                <div class="carte p-4" wire:key="pack-carte-{{ $pack->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-encre dark:text-white mb-0">{{ $pack->label }}</p>
                            <p class="text-xs text-encre-3 dark:text-nuit-3 font-mono mb-0">{{ $pack->code }}</p>
                        </div>
                        <x-badge-statut variant="staff"
                                        :statut="$pack->is_active ? 'success' : 'neutral'"
                                        :label="$pack->is_active ? 'En vente' : 'Retiré'" />
                    </div>
                    <p class="mt-2 text-sm text-encre-2 dark:text-nuit-2 mb-0">
                        {{ number_format($pack->units, 0, ',', ' ') }} unités ·
                        {{ number_format($pack->price_fcfa, 0, ',', ' ') }} F ·
                        {{ number_format($pack->prixParUnite(), 1, ',', ' ') }} F l'unité
                    </p>
                    <p class="mt-1 text-xs text-encre-3 dark:text-nuit-3 mb-3">{{ $pack->payments_count }} achat(s)</p>
                    <div class="flex flex-wrap gap-2">
                        @can('update', $pack)
                            <button type="button" wire:click="ouvrirEdition('{{ $pack->uuid }}')"
                                    class="btn-second !min-h-[40px]">Modifier</button>
                            <button type="button" wire:click="basculerActivite('{{ $pack->uuid }}')"
                                    class="btn-fantome !min-h-[40px]">{{ $pack->is_active ? 'Retirer' : 'Remettre' }}</button>
                        @endcan
                    </div>
                </div>
            @endforeach
        </x-card-list>

        <div class="mt-6">{{ $packs->links() }}</div>
    @endif

    {{-- ══════════════════════════════════════════════ tiroir de formulaire --}}
    <x-offcanvas-form variant="staff" id="pack-form"
                      :title="$enEdition !== null ? 'Modifier le pack' : 'Nouveau pack'"
                      :open="$formulaireOuvert"
                      close="fermerFormulaire">
        <p class="text-xs font-bold tracking-[.12em] uppercase text-encre-3 dark:text-nuit-3 mb-4">Informations</p>

        <label class="mb-4">
            <span class="etiquette">Code</span>
            <input type="text" wire:model="code" class="champ" placeholder="p25" maxlength="32">
            {{-- Le code est ce que le client envoie à l'achat : le changer sur
                 un pack déjà en vente casse les liens partagés. --}}
            <span class="block mt-1 text-xs text-encre-3 dark:text-nuit-3">
                Minuscules, chiffres et tirets. C'est l'identifiant envoyé à l'achat.
            </span>
            @error('code') <span class="block mt-1 text-xs text-critique">{{ $message }}</span> @enderror
        </label>

        <label class="mb-4">
            <span class="etiquette">Libellé</span>
            <input type="text" wire:model="label" class="champ" placeholder="Pack Citoyen" maxlength="120">
            @error('label') <span class="block mt-1 text-xs text-critique">{{ $message }}</span> @enderror
        </label>

        <label class="mb-4">
            <span class="etiquette">Nombre d'unités</span>
            <input type="number" wire:model="units" class="champ" placeholder="25" min="1">
            @error('units') <span class="block mt-1 text-xs text-critique">{{ $message }}</span> @enderror
        </label>

        <label class="mb-4">
            <span class="etiquette">Prix en francs CFA</span>
            <input type="number" wire:model="prix" class="champ" placeholder="1000" min="1">
            @error('prix') <span class="block mt-1 text-xs text-critique">{{ $message }}</span> @enderror
        </label>

        @if ($units && $prix)
            <p class="text-sm text-encre-2 dark:text-nuit-2 mb-4">
                Soit <strong class="text-encre dark:text-white">{{ number_format($prix / max(1, $units), 1, ',', ' ') }} F l'unité</strong>.
            </p>
        @endif

        <label class="flex items-start gap-2.5 text-sm cursor-pointer mb-3">
            <input type="checkbox" wire:model="enAvant" class="mt-1 shrink-0">
            <span>
                <strong class="text-encre dark:text-white">Mettre ce pack en avant</strong>
                <span class="block text-encre-3 dark:text-nuit-3">Un seul à la fois : deux « recommandés » côte à côte ne recommandent rien.</span>
            </span>
        </label>

        <label class="flex items-start gap-2.5 text-sm cursor-pointer">
            <input type="checkbox" wire:model="actif" class="mt-1 shrink-0">
            <span>
                <strong class="text-encre dark:text-white">En vente</strong>
                <span class="block text-encre-3 dark:text-nuit-3">Décoché, le pack disparaît de l'écran d'achat sans rien effacer.</span>
            </span>
        </label>

        <x-slot:footer>
            <button type="button" wire:click="fermerFormulaire" class="btn-second">Annuler</button>
            <button type="button" wire:click="enregistrer" class="btn-primaire">Enregistrer</button>
        </x-slot:footer>
    </x-offcanvas-form>

    {{-- ═══════════════════════════════════════════ suppression nominative --}}
    @if ($packASupprimer !== null)
        <x-delete-modal variant="staff" id="pack-delete"
                        :cible="$packASupprimer->label"
                        :cascades="['Le pack disparaît de la grille ; les paiements passés, eux, restent.']"
                        :open="true"
                        confirm="supprimer"
                        cancel="annulerSuppression" />
    @endif
</div>
