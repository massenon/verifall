{{--
  | « Mots surveillés » — la liste que consulte le filtre de modération.
  |
  | Même gabarit que les autres écrans : fil d'Ariane, en-tête, indicateurs,
  | filtres, table. Le formulaire s'ouvre en ligne plutôt qu'en fenêtre modale :
  | on ajoute souvent plusieurs mots d'affilée, et refermer entre chaque
  | rendrait le geste pénible.
--}}
<div>

<x-premium.entete titre="Mots surveillés" :fil="['Mots surveillés']">
        Deux actions seulement. « Bloquer » refuse la publication, et se réserve à ce qui n'a aucune raison d'être. « Publier et signaler » laisse le message paraître et prévient la modération : sur un site de fact-checking, les mots d'une rumeur sont précisément ceux qu'on vient signaler.
        <x-slot:actions>
            @can('update', \App\Models\ModerationKeyword::class)
            <button type="button" wire:click="ouvrirFormulaire" class="st-btn st-btn-primary shrink-0">
                Ajouter un mot
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($compteurs['total'], 0, ',', ' ')"
               label="Mots déclarés" trend="Actifs et désactivés confondus" />

        <x-kpi variant="staff" :value="number_format($compteurs['actifs'], 0, ',', ' ')"
               label="Actifs" trend="Réellement appliqués à chaque publication" />

        <x-kpi variant="staff" :value="number_format($compteurs['bloquants'], 0, ',', ' ')"
               label="Bloquants" trend="Refusent la publication" />

        <x-kpi variant="staff" :value="number_format($compteurs['declenchements'], 0, ',', ' ')"
               label="Déclenchements" trend="Depuis la mise en place" />
    </x-kpi-grid>

    @if ($formulaireOuvert)
        <section class="st-card mb-6" aria-label="Formulaire de mot surveillé">
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div class="ffield mb-0">
                    <input id="mot-terme" type="text" wire:model="terme" placeholder=" ">
                    <label for="mot-terme">Mot ou expression</label>
                    @error('terme')<p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p>@enderror
                </div>

                <div>
                    <select wire:model="action" class="st-input" aria-label="Action">
                        @foreach ($actions as $possible)
                            <option value="{{ $possible->value }}">{{ $possible->libelle() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">
                        {{ \App\Enums\ActionMotClef::from($action)->explication() }}
                    </p>
                </div>
            </div>

            {{-- La raison est OBLIGATOIRE. Sans elle, personne n'ose retirer
                 une entrée six mois plus tard, faute de savoir ce qu'elle
                 visait. --}}
            <div class="ffield mb-3">
                <textarea id="mot-note" wire:model="note" rows="2" placeholder=" "></textarea>
                <label for="mot-note">Pourquoi ce mot est surveillé</label>
            </div>
            @error('note')<p class="text-sm text-[#DC2626] mb-3">{{ $message }}</p>@enderror

            <label class="flex items-center gap-2 text-sm text-[#0F172A] mb-4">
                <input type="checkbox" wire:model="actif">
                Actif
            </label>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="enregistrer" class="st-btn st-btn-primary">Enregistrer</button>
                <button type="button" wire:click="fermerFormulaire" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </section>
    @endif

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="mots-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="mots-q">Rechercher un mot</label>
        </div>

        <select wire:model.live="filtreAction" class="st-input w-auto" aria-label="Filtrer par action">
            <option value="">Action : toutes</option>
            @foreach ($actions as $possible)
                <option value="{{ $possible->value }}">{{ $possible->libelle() }}</option>
            @endforeach
        </select>
    </section>

    @if ($mots->isEmpty())
        <x-empty-state variant="staff" icon="shield"
                       title="Aucun mot surveillé"
                       description="La liste est vide : aucune publication n'est filtrée. Ajoutez un mot quand une rumeur ou une forme d'injure revient.">
            @can('update', \App\Models\ModerationKeyword::class)
                <x-slot:action>
                    <button type="button" wire:click="ouvrirFormulaire" class="st-btn st-btn-primary">Ajouter un mot</button>
                </x-slot:action>
            @endcan
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$mots->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Mot</th>
                <th scope="col">Action</th>
                <th scope="col">Raison</th>
                <th scope="col">Déclenchements</th>
                <th scope="col">État</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @foreach ($mots as $mot)
                <tr>
                    <td><p class="font-semibold text-[#0F172A] mb-0">{{ $mot->term }}</p></td>
                    <td>
                        <x-badge-statut variant="staff" :statut="$mot->action->cleBadge()" :label="$mot->action->libelle()" />
                    </td>
                    <td><p class="text-sm text-[#0F172A] mb-0">{{ Str::limit((string) $mot->note, 90) }}</p></td>
                    <td class="tabular-nums">
                        {{ number_format($mot->hits, 0, ',', ' ') }}
                        @if ($mot->last_hit_at)
                            <span class="block text-xs text-[#64748B]">{{ $mot->last_hit_at->translatedFormat('j M Y') }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($mot->is_active)
                            <x-badge-statut variant="staff" statut="publie" label="Actif" />
                        @else
                            <x-badge-statut variant="staff" statut="archive" label="Désactivé" />
                        @endif
                    </td>
                    <td class="text-right">
                        @can('update', \App\Models\ModerationKeyword::class)
                            <button type="button" wire:click="modifier('{{ $mot->uuid }}')" class="st-btn st-btn-secondary">Modifier</button>
                            {{-- On DÉSACTIVE, on ne supprime pas : la ligne garde
                                 sa raison et son compteur. --}}
                            <button type="button" wire:click="basculer('{{ $mot->uuid }}')" class="st-btn st-btn-secondary">
                                {{ $mot->is_active ? 'Désactiver' : 'Réactiver' }}
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-6">{{ $mots->links() }}</div>
    @endif
</div>
