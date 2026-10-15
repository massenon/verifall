<div class="p-6 lg:p-8">

    {{-- En-tête : titre + description + action unique (PAGE_SECTION_ORDER). --}}
    <header class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-display font-bold text-[#0F172A]">Logs d'Audit</h1>
            <p class="mt-1 text-sm text-[#475569]">
                Traçabilité des actions du back-office. Consultation seule :
                un journal modifiable ne prouverait rien.
            </p>
        </div>

        <button type="button" wire:click="exporterCsv" class="st-btn st-btn-secondary">
            <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">download</span>
            Exporter en CSV
        </button>
    </header>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($this->indicateurs['evenements'], 0, ',', ' ')"
               label="Événements (7 j)" trend="toutes actions" />
        <x-kpi variant="staff" :value="number_format($this->indicateurs['destructives'], 0, ',', ' ')"
               label="Actions destructives" trend="suppressions et changements de rôle" />
        <x-kpi variant="staff" :value="number_format($this->indicateurs['acteurs'], 0, ',', ' ')"
               label="Acteurs distincts" trend="sur 7 jours" />
    </x-kpi-grid>

    {{-- Recherche débouncée 350 ms + filtres + chips supprimables. --}}
    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="audit-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="audit-q">Rechercher un acteur, une cible, un module</label>
        </div>

        <select wire:model.live="filtreModule" class="st-input w-auto" aria-label="Filtrer par module">
            <option value="">Module : tous</option>
            @foreach ($modules as $module)
                <option value="{{ $module }}">{{ $this->libelleModule($module) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreAction" class="st-input w-auto" aria-label="Filtrer par action">
            <option value="">Action : toutes</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}">{{ $this->libelleAction($action) }}</option>
            @endforeach
        </select>
    </section>

    @if ($recherche !== '' || $filtreModule !== '' || $filtreAction !== '')
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @foreach ([['recherche', $recherche], ['filtreModule', $filtreModule], ['filtreAction', $filtreAction]] as [$champ, $valeur])
                @if ($valeur !== '')
                    <span class="st-chip">
                        {{ $valeur }}
                        <button type="button" wire:click="$set('{{ $champ }}', '')"
                                aria-label="Retirer le filtre {{ $valeur }}">×</button>
                    </span>
                @endif
            @endforeach

            <button type="button" wire:click="reinitialiser" class="text-sm text-[#059669] font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($entrees->isEmpty())
        <x-empty-state variant="staff" icon="search_off"
                       title="Aucun événement"
                       description="Aucune action ne correspond à ces critères. Essayez d'élargir la recherche.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$entrees->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Événement</th>
                <th scope="col">Acteur</th>
                <th scope="col">Module</th>
                <th scope="col">Cible</th>
                <th scope="col">Horodatage</th>
            </x-slot:head>

            @foreach ($entrees as $entree)
                <tr data-row-el wire:key="audit-{{ $entree->id }}">
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$this->estDestructive($entree->action) ? 'danger' : 'info'"
                                        :label="$this->libelleAction($entree->action)" />
                    </td>
                    {{-- Libellé dénormalisé : reste lisible après anonymisation RGPD. --}}
                    <td>{{ $entree->actor_label ?? '—' }}</td>
                    <td>{{ $this->libelleModule($entree->module) }}</td>
                    <td>{{ $entree->target_label ?? '—' }}</td>
                    <td>{{ $entree->created_at?->translatedFormat('j M Y H:i') }}</td>
                </tr>
            @endforeach
        </x-data-table>

        <x-card-list variant="staff">
            @foreach ($entrees as $entree)
                <div class="st-card p-4" wire:key="audit-carte-{{ $entree->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-[#0F172A]">{{ $entree->actor_label ?? '—' }}</p>
                        <x-badge-statut variant="staff"
                                        :statut="$this->estDestructive($entree->action) ? 'danger' : 'info'"
                                        :label="$this->libelleAction($entree->action)" />
                    </div>
                    <p class="mt-1 text-sm text-[#475569]">
                        {{ $this->libelleModule($entree->module) }}@if ($entree->target_label) · {{ $entree->target_label }} @endif
                    </p>
                    <p class="mt-1 text-xs text-[#64748B]">
                        {{ $entree->created_at?->translatedFormat('j M Y H:i') }}
                    </p>
                </div>
            @endforeach
        </x-card-list>

        <div class="mt-4">{{ $entrees->links() }}</div>
    @endif
</div>
