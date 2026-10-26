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

<div>

<x-premium.entete titre="Banque de questions" :fil="['Banque de questions']">
        Le fonds commun des examens. Une question reste en brouillon jusqu'à sa publication — publier, c'est l'exposer à tous les candidats d'une session certifiante.
        <x-slot:actions>
            @can('create', App\Models\QuestionBankItem::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle question
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

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
            <div class="flex-1 mb-0">
                <label for="bq-q" class="etiquette">Rechercher un énoncé</label>
<input id="bq-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" value="{{ $recherche }}" class="champ">
            </div>

            <select data-vdt-filtre="type" data-vdt-url="type" data-vdt-livewire="filtreType" class="champ w-auto" aria-label="Filtrer par type">
                <option value="">Type : tous</option>
                @foreach ($types as $unType)
                    <option value="{{ $unType->value }}" @selected($filtreType === $unType->value)>{{ $unType->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $unStatut)
                    <option value="{{ $unStatut->value }}" @selected($filtreStatut === $unStatut->value)>{{ $unStatut->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="cours" data-vdt-url="cours" data-vdt-livewire="filtreCours" class="champ w-auto" aria-label="Filtrer par cours">
                <option value="">Cours : tous</option>
                @foreach ($cours as $unCours)
                    <option value="{{ $unCours->uuid }}" @selected($filtreCours === $unCours->uuid)>{{ Str::limit($unCours->title, 40) }}</option>
                @endforeach
            </select>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les questions.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
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
            <div>
                <label for="bq-enonce" class="etiquette">Énoncé</label>
<textarea id="bq-enonce" wire:model="enonce" rows="3" class="champ !h-auto py-2.5"></textarea>
                @error('enonce') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="bq-type" class="block text-sm font-medium text-encre dark:text-white mb-1">Type</label>
                    <select id="bq-type" wire:model="type" class="champ w-full">
                        @foreach ($types as $unType)
                            <option value="{{ $unType->value }}">{{ $unType->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="bq-points" class="etiquette">Points</label>
<input id="bq-points" type="number" min="1" max="100" wire:model="points" class="champ">
                    @error('points') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="bq-cours" class="block text-sm font-medium text-encre dark:text-white mb-1">Cours rattaché</label>
                <select id="bq-cours" wire:model="coursUuid" class="champ w-full">
                    <option value="">— Transverse —</option>
                    @foreach ($cours as $unCours)
                        <option value="{{ $unCours->uuid }}">{{ $unCours->title }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                    Sert au tirage : un examen adossé à un cours pioche d'abord dans ses questions.
                </p>
                @error('coursUuid') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <p class="block text-sm font-medium text-encre dark:text-white mb-2">Propositions</p>
                @error('propositions') <p class="text-sm text-critique mb-2">{{ $message }}</p> @enderror

                <div class="space-y-2">
                    @foreach ($propositions as $rang => $proposition)
                        <div class="flex items-start gap-2">
                            <input type="checkbox" wire:model="propositions.{{ $rang }}.correcte"
                                   class="mt-3" aria-label="Proposition correcte">

                            <div class="flex-1 mb-0">
                                <label for="bq-prop-{{ $rang }}" class="etiquette">Proposition {{ $rang + 1 }}</label>
<input id="bq-prop-{{ $rang }}" type="text"
                                       wire:model="propositions.{{ $rang }}.label" class="champ">
                                @error('propositions.'.$rang.'.label')
                                    <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>

                            @if (count($propositions) > 2)
                                <button type="button" wire:click="retirerUneProposition({{ $rang }})"
                                        class="btn-icone text-critique mt-1" title="Retirer">
                                    <x-lucide name="x" class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (count($propositions) < 6)
                    <button type="button" wire:click="ajouterUneProposition"
                            class="text-sm text-brand-700 dark:text-brand font-semibold underline mt-2">
                        + Ajouter une proposition
                    </button>
                @endif
            </div>

            <div>
                <label for="bq-expl" class="etiquette">Explication</label>
<textarea id="bq-expl" wire:model="explication" rows="3" class="champ !h-auto py-2.5"></textarea>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                    Sans elle, le candidat apprend seulement qu'il a eu tort.
                </p>
                @error('explication') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
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
