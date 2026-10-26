{{--
  | « Historiques des Unités IA » (lot 5).
  |
  | `MotifMouvementUnite::AjustementAdmin` existait depuis l'origine et RIEN ne
  | l'émettait : corriger le solde d'un compte demandait `tinker` en production.
  | Les CGU §6 s'y engagent pourtant par écrit — « la plateforme procède au
  | crédit ou au remboursement ». La clause existait, le moyen de la tenir non.
  |
  | Aucune ligne ne se modifie ni ne se supprime ici : un mouvement se corrige
  | par un mouvement inverse, comme en comptabilité. C'est `UnitLog` lui-même
  | qui le pose dans son en-tête.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/searchBuilder.dataTables-1.8.4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

<x-premium.entete titre="Historiques des Unités IA" :fil="['Historiques des Unités IA']">
        Tous les mouvements d'unités, tous comptes confondus. Le journal ne se réécrit pas : une erreur se corrige par un mouvement inverse, qui laisse les deux lignes visibles.
        <x-slot:actions>
            @if ($peutAjuster)
            <button type="button" wire:click="ouvrirAjustement" class="btn-primaire">
                <x-lucide name="sliders-horizontal" class="w-4 h-4" />
                Ajuster un compte
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['mouvements'], 0, ',', ' ')"
               label="Mouvements"
               trend="Depuis l'origine" />

        <x-kpi variant="staff" :value="number_format($indicateurs['credits'], 0, ',', ' ')"
               label="Unités créditées"
               trend="Dotations, parrainages, achats" />

        <x-kpi variant="staff" :value="number_format($indicateurs['debits'], 0, ',', ' ')"
               label="Unités dépensées"
               trend="Vérifications, cours premium" />

        <x-kpi variant="staff" :value="number_format($indicateurs['comptes'], 0, ',', ' ')"
               label="Comptes concernés"
               trend="Ayant au moins un mouvement" />
    </x-kpi-grid>

    <div wire:ignore
         data-vdt="journal-des-unites"
         data-vdt-source="{{ route('staff.unit-journal.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\JournalDesUnites::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 mb-0">
                <label for="ju-q" class="etiquette">Rechercher un compte, un libellé, une référence</label>
<input id="ju-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" class="champ">
            </div>

            <select data-vdt-filtre="sens" data-vdt-url="sens" data-vdt-livewire="filtreSens" class="champ w-auto" aria-label="Filtrer par sens">
                <option value="">Sens : tous</option>
                <option value="credit">Crédits</option>
                <option value="debit">Débits</option>
            </select>

            <select data-vdt-filtre="motif" data-vdt-url="motif" data-vdt-livewire="filtreMotif" class="champ w-auto" aria-label="Filtrer par motif">
                <option value="">Motif : tous</option>
                @foreach ($motifs as $motif)
                    <option value="{{ $motif->value }}">{{ $motif->libelle() }}</option>
                @endforeach
            </select>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger le journal.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
        </div>

        <table id="table-journal-des-unites" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Compte</th>
                    <th scope="col">Motif</th>
                    <th scope="col">Libellé</th>
                    <th scope="col" class="text-right">Mouvement</th>
                    <th scope="col" class="text-right">Solde après</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    @if ($peutAjuster)
        <x-offcanvas-form :open="$formulaireOuvert"
                          title="Ajuster un compte"
                          close="fermerLeTiroir">
            <form wire:submit="ajuster" class="space-y-4">

                <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">
                    L'ajustement pose un mouvement de plus au journal, au motif
                    « Ajustement administrateur ». Il ne réécrit rien.
                </p>

                <div>
                    <label for="ju-compte" class="etiquette">Adresse e-mail ou identifiant du compte</label>
<input id="ju-compte" type="text" wire:model.live.debounce.500ms="compte" class="champ">
                    @error('compte') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                {{-- Le compte est nommé AVANT le geste : « paul@ » et « paule@ »
                     se ressemblent, et l'erreur ne se voit qu'après coup. --}}
                @if ($compte !== '')
                    @if ($compteTrouve !== null)
                        @php($cible = App\Models\User::query()->where('uuid', $compteTrouve)->first())
                        @if ($cible !== null)
                            <div class="rounded-lg border border-trait dark:border-nuit-trait bg-green-100 dark:bg-green-950 p-3">
                                <p class="text-sm font-semibold text-brand-700 dark:text-brand mb-0">{{ $cible->name }}</p>
                                <p class="text-xs text-brand-700 dark:text-brand mb-0">
                                    {{ $cible->email }} · solde actuel {{ number_format($cible->units, 0, ',', ' ') }} unité(s)
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="rounded-lg border border-trait dark:border-nuit-trait bg-red-100 dark:bg-red-950 p-3">
                            <p class="text-sm text-critique mb-0">Aucun compte ne correspond à cette saisie.</p>
                        </div>
                    @endif
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="ju-sens" class="block text-sm font-medium text-encre dark:text-white mb-1">Sens</label>
                        <select id="ju-sens" wire:model="sensAjustement" class="champ w-full">
                            <option value="credit">Créditer</option>
                            <option value="debit">Débiter</option>
                        </select>
                        @error('sensAjustement') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="ju-unites" class="etiquette">Nombre d'unités</label>
<input id="ju-unites" type="number" min="1" max="10000" wire:model="unites" class="champ">
                        @error('unites') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="ju-just" class="etiquette">Justification</label>
<textarea id="ju-just" wire:model="justification" rows="3" class="champ !h-auto py-2.5"></textarea>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                        Obligatoire. C'est la seule pièce qui expliquera, dans six mois, pourquoi ce compte a été ajusté —
                        et la première qu'on cherchera si le geste est contesté.
                    </p>
                    @error('justification') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer l'ajustement</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif
</div>

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.dateTime-1.6.3.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.searchBuilder-1.8.4.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <script src="{{ asset('assets/js/pages/staff-journal-des-unites.js') }}"></script>
@endpush
