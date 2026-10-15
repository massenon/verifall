{{--
  | « Banque de Questions » (lot 6).
  |
  | Le dépôt (10 méthodes) et le service (7) existaient déjà, complets et
  | testés. Il manquait un écran : le fonds ne pouvait s'alimenter que par la
  | console. Ce composant ne fait que brancher.
  |
  | À ne pas confondre avec les quiz de cours : ceux-là se corrigent seuls et ne
  | valent aucun diplôme. Une question publiée ici est posée à tous les
  | candidats d'un examen certifiant.
  |
  | DataTables (contrat : docs/DATATABLES_CONTRAT.md, migration Blade du
  | 12 octobre 2026). Fonds commun : aucune restriction par mentor (Policy).
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/searchBuilder.dataTables-1.8.4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Banque de questions</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Banque de questions</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Le fonds commun des examens. Une question reste en brouillon jusqu'à sa publication —
                publier, c'est l'exposer à tous les candidats d'une session certifiante.
            </p>
        </div>

        @can('create', App\Models\QuestionBankItem::class)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouvelle question
            </button>
        @endcan
    </header>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Questions" trend="Tout le fonds" />

        <x-kpi variant="staff" :value="number_format($indicateurs['avec_explication'], 0, ',', ' ')"
               label="Avec explication" trend="Le cœur pédagogique" />

        <x-kpi variant="staff" :value="$indicateurs['taux_reussite'] !== null ? $indicateurs['taux_reussite'].' %' : '—'"
               label="Taux de réussite" trend="Sur les copies rendues" />

        <x-kpi variant="staff" :value="number_format($indicateurs['a_reviser'], 0, ',', ' ')"
               label="À réviser" trend="Retirées des tirages" />
    </x-kpi-grid>

    <div wire:ignore
         data-vdt="banque-de-questions"
         data-vdt-source="{{ route('staff.question-bank.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\BanqueDeQuestions::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="ffield flex-1 mb-0">
                <input id="bq-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" value="{{ $recherche }}" placeholder=" ">
                <label for="bq-q">Rechercher un énoncé</label>
            </div>

            <select data-vdt-filtre="type" data-vdt-url="type" data-vdt-livewire="filtreType" class="st-input w-auto" aria-label="Filtrer par type">
                <option value="">Type : tous</option>
                @foreach ($types as $unType)
                    <option value="{{ $unType->value }}" @selected($filtreType === $unType->value)>{{ $unType->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" class="st-input w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $unStatut)
                    <option value="{{ $unStatut->value }}" @selected($filtreStatut === $unStatut->value)>{{ $unStatut->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="cours" data-vdt-url="cours" data-vdt-livewire="filtreCours" class="st-input w-auto" aria-label="Filtrer par cours">
                <option value="">Cours : tous</option>
                @foreach ($cours as $unCours)
                    <option value="{{ $unCours->uuid }}" @selected($filtreCours === $unCours->uuid)>{{ Str::limit($unCours->title, 40) }}</option>
                @endforeach
            </select>

            <button type="button" data-vdt-reinitialiser class="st-btn st-btn-secondary">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-[#FCA5A5] bg-[#FEF2F2] text-[#991B1B] text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les questions.</p>
            <button type="button" data-vdt-reessayer class="st-btn st-btn-secondary">Réessayer</button>
        </div>

        <table id="table-banque-de-questions" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Énoncé</th>
                    <th scope="col">Type</th>
                    <th scope="col">Cours</th>
                    <th scope="col">Points</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Réponses</th>
                    <th scope="col">Créée</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouvelle question' : 'Modifier la question'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="ffield">
                <textarea id="bq-enonce" wire:model="enonce" rows="3" placeholder=" "></textarea>
                <label for="bq-enonce">Énoncé</label>
                @error('enonce') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="bq-type" class="block text-sm font-medium text-[#0F172A] mb-1">Type</label>
                    <select id="bq-type" wire:model="type" class="st-input w-full">
                        @foreach ($types as $unType)
                            <option value="{{ $unType->value }}">{{ $unType->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="bq-points" type="number" min="1" max="100" wire:model="points" placeholder=" ">
                    <label for="bq-points">Points</label>
                    @error('points') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="bq-cours" class="block text-sm font-medium text-[#0F172A] mb-1">Cours rattaché</label>
                <select id="bq-cours" wire:model="coursUuid" class="st-input w-full">
                    <option value="">— Transverse —</option>
                    @foreach ($cours as $unCours)
                        <option value="{{ $unCours->uuid }}">{{ $unCours->title }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Sert au tirage : un examen adossé à un cours pioche d'abord dans ses questions.
                </p>
                @error('coursUuid') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <p class="block text-sm font-medium text-[#0F172A] mb-2">Propositions</p>
                @error('propositions') <p class="text-sm text-[#DC2626] mb-2">{{ $message }}</p> @enderror

                <div class="space-y-2">
                    @foreach ($propositions as $rang => $proposition)
                        <div class="flex items-start gap-2">
                            <input type="checkbox" wire:model="propositions.{{ $rang }}.correcte"
                                   class="mt-3" aria-label="Proposition correcte">

                            <div class="ffield flex-1 mb-0">
                                <input id="bq-prop-{{ $rang }}" type="text"
                                       wire:model="propositions.{{ $rang }}.label" placeholder=" ">
                                <label for="bq-prop-{{ $rang }}">Proposition {{ $rang + 1 }}</label>
                                @error('propositions.'.$rang.'.label')
                                    <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>

                            @if (count($propositions) > 2)
                                <button type="button" wire:click="retirerUneProposition({{ $rang }})"
                                        class="st-icnbtn text-[#DC2626] mt-1" title="Retirer">
                                    <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">close</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (count($propositions) < 6)
                    <button type="button" wire:click="ajouterUneProposition"
                            class="text-sm text-[#059669] font-semibold underline mt-2">
                        + Ajouter une proposition
                    </button>
                @endif
            </div>

            <div class="ffield">
                <textarea id="bq-expl" wire:model="explication" rows="3" placeholder=" "></textarea>
                <label for="bq-expl">Explication</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Sans elle, le candidat apprend seulement qu'il a eu tort.
                </p>
                @error('explication') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Retirer cette question du fonds ?"
                    description="La suppression est définitive. Pour la retirer des tirages sans toucher aux copies déjà rendues, mettez-la plutôt en révision."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.searchBuilder-1.8.4.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <script src="{{ asset('assets/js/pages/staff-banque-de-questions.js') }}"></script>
@endpush
