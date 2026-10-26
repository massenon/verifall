{{--
  | « Signalements » — la file de modération des contenus signalés.
  |
  | Une seule file pour TOUS les types : sujets, réponses, notes de communauté.
  | Trois écrans séparés auraient produit trois habitudes, et un type qu'on
  | finit par ne plus relever.
  |
  | Les plus ANCIENS en haut, contrairement aux autres écrans : une file de
  | modération se vide par le bas, et trier du plus récent ferait glisser hors
  | de vue ceux qui attendent depuis le plus longtemps.
  |
  | DataTables (contrat `docs/DATATABLES_CONTRAT.md`) : le panneau « signalement
  | ouvert » reste DANS cette racine, comme les modales du pilote Utilisateurs
  | — Livewire ne suit que le premier élément racine du composant.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

<x-premium.entete titre="Signalements" :fil="['Signalements']">
        Ce que les citoyens portent à la modération. La file est triée par PROPAGATION et non par ordre d'arrivée : une rumeur signalée par quinze personnes en deux heures passe devant un hors-sujet signalé hier par une seule. Un signalement se tranche, retenu ou rejeté, et la trace demeure.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($compteurs['total'], 0, ',', ' ')"
               label="Signalements" trend="Tous types de contenu confondus" />

        <x-kpi variant="staff" :value="number_format($compteurs['pending'], 0, ',', ' ')"
               label="En attente" trend="Personne ne les a encore tranchés" />

        <x-kpi variant="staff" :value="number_format($compteurs['upheld'], 0, ',', ' ')"
               label="Retenus" trend="Le signalement était fondé" />

        <x-kpi variant="staff" :value="number_format($compteurs['dismissed'], 0, ',', ' ')"
               label="Rejetés" trend="Le contenu reste en ligne" />
    </x-kpi-grid>

    @if ($signalementOuvert !== null)
        <section class="carte mb-6" id="signalement-ouvert" tabindex="-1" aria-label="Signalement ouvert">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="font-semibold text-encre dark:text-white mb-0">
                        {{ class_basename((string) $signalementOuvert->reportable_type) }}
                        · motif : {{ $signalementOuvert->reason }}
                    </p>
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                        {{-- Un compte supprimé garde son signalement : la trace vaut
                             indépendamment de qui l'a laissée. --}}
                        Signalé par {{ $signalementOuvert->reporter?->name ?? 'Compte supprimé' }}
                        le {{ $signalementOuvert->created_at?->translatedFormat('j F Y à H:i') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-badge-statut variant="staff" :statut="$signalementOuvert->status->cleBadge()" :label="$signalementOuvert->status->libelle()" />
                    <button type="button" wire:click="fermer" class="btn-second">Fermer</button>
                </div>
            </div>

            @if ($signalementOuvert->detail)
                <p class="text-encre dark:text-white mb-4" style="white-space: pre-line;">{{ $signalementOuvert->detail }}</p>
            @endif

            {{-- Le contenu VISÉ, quand il existe encore. Supprimé entre-temps, on
                 le dit — plutôt que d'afficher un cadre vide qui ressemble à une
                 panne. --}}
            <div class="rounded-lg border border-trait dark:border-nuit-trait bg-papier dark:bg-nuit p-4 mb-6">
                @if ($signalementOuvert->reportable === null)
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">Le contenu signalé n'existe plus.</p>
                @else
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-1">Contenu signalé</p>
                    <p class="text-sm text-encre dark:text-white mb-0">
                        {{ Str::limit(strip_tags((string) ($signalementOuvert->reportable->body
                            ?? $signalementOuvert->reportable->title
                            ?? $signalementOuvert->reportable->content
                            ?? '—')), 400) }}
                    </p>
                @endif
            </div>

            @can('update', \App\Models\ContentReport::class)
                <div class="mb-3">
                    <label for="signalement-note" class="etiquette">Note de décision (facultative)</label>
<textarea id="signalement-note" wire:model="note" rows="2" class="champ !h-auto py-2.5"></textarea>
                </div>
                @error('note')<p class="text-sm text-critique mb-3">{{ $message }}</p>@enderror

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="trancher('upheld')" class="btn-primaire">Retenir</button>
                    <button type="button" wire:click="trancher('dismissed')" class="btn-second">Rejeter</button>
                </div>
            @endcan
        </section>
    @endif

    <div wire:ignore
         data-vdt="signalements-contenu"
         data-vdt-source="{{ route('staff.content-reports.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\SignalementsContenu::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <select data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $statut)
                    <option value="{{ $statut->value }}" @selected($filtreStatut === $statut->value)>{{ $statut->libelle() }}</option>
                @endforeach
            </select>

            {{-- Les types proposés sont ceux RÉELLEMENT présents en base : offrir un
                 filtre qui ne ramène jamais rien fait douter de l'écran. --}}
            @if (count($types) > 1)
                <select data-vdt-filtre="type" data-vdt-url="type" data-vdt-livewire="filtreType" class="champ w-auto" aria-label="Filtrer par type de contenu">
                    <option value="">Type : tous</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected($filtreType === $type)>{{ class_basename($type) }}</option>
                    @endforeach
                </select>
            @endif

            <div class="mb-0">
                <label class="etiquette">Reçu depuis le</label>
<input type="text" data-vdt-filtre="recu_du" data-vdt-url="du" data-vdt-date class="champ">
            </div>
            <div class="mb-0">
                <label class="etiquette">jusqu'au</label>
<input type="text" data-vdt-filtre="recu_au" data-vdt-url="au" data-vdt-date class="champ">
            </div>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les signalements.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
        </div>

        <table id="table-signalements-contenu" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Contenu</th>
                    <th scope="col">Propagation</th>
                    <th scope="col">Motif</th>
                    <th scope="col">Signalé par</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Reçu le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.dateTime-1.6.3.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <script src="{{ asset('assets/js/pages/staff-signalements-contenu.js') }}"></script>
@endpush
