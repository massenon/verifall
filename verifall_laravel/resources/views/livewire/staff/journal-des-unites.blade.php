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
            <button type="button" wire:click="ouvrirAjustement" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">tune</span>
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
            <div class="ffield flex-1 mb-0">
                <input id="ju-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" placeholder=" ">
                <label for="ju-q">Rechercher un compte, un libellé, une référence</label>
            </div>

            <select data-vdt-filtre="sens" data-vdt-url="sens" data-vdt-livewire="filtreSens" class="st-input w-auto" aria-label="Filtrer par sens">
                <option value="">Sens : tous</option>
                <option value="credit">Crédits</option>
                <option value="debit">Débits</option>
            </select>

            <select data-vdt-filtre="motif" data-vdt-url="motif" data-vdt-livewire="filtreMotif" class="st-input w-auto" aria-label="Filtrer par motif">
                <option value="">Motif : tous</option>
                @foreach ($motifs as $motif)
                    <option value="{{ $motif->value }}">{{ $motif->libelle() }}</option>
                @endforeach
            </select>

            <button type="button" data-vdt-reinitialiser class="st-btn st-btn-secondary">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-[#FCA5A5] bg-[#FEF2F2] text-[#991B1B] text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger le journal.</p>
            <button type="button" data-vdt-reessayer class="st-btn st-btn-secondary">Réessayer</button>
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

                <p class="text-sm text-[#334155] mb-0">
                    L'ajustement pose un mouvement de plus au journal, au motif
                    « Ajustement administrateur ». Il ne réécrit rien.
                </p>

                <div class="ffield">
                    <input id="ju-compte" type="text" wire:model.live.debounce.500ms="compte" placeholder=" ">
                    <label for="ju-compte">Adresse e-mail ou identifiant du compte</label>
                    @error('compte') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                {{-- Le compte est nommé AVANT le geste : « paul@ » et « paule@ »
                     se ressemblent, et l'erreur ne se voit qu'après coup. --}}
                @if ($compte !== '')
                    @if ($compteTrouve !== null)
                        @php($cible = App\Models\User::query()->where('uuid', $compteTrouve)->first())
                        @if ($cible !== null)
                            <div class="rounded-lg border border-[#E2E8F0] bg-[#D1FAE5] p-3">
                                <p class="text-sm font-semibold text-[#065F46] mb-0">{{ $cible->name }}</p>
                                <p class="text-xs text-[#065F46] mb-0">
                                    {{ $cible->email }} · solde actuel {{ number_format($cible->units, 0, ',', ' ') }} unité(s)
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="rounded-lg border border-[#E2E8F0] bg-[#FEE2E2] p-3">
                            <p class="text-sm text-[#DC2626] mb-0">Aucun compte ne correspond à cette saisie.</p>
                        </div>
                    @endif
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="ju-sens" class="block text-sm font-medium text-[#0F172A] mb-1">Sens</label>
                        <select id="ju-sens" wire:model="sensAjustement" class="st-input w-full">
                            <option value="credit">Créditer</option>
                            <option value="debit">Débiter</option>
                        </select>
                        @error('sensAjustement') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <input id="ju-unites" type="number" min="1" max="10000" wire:model="unites" placeholder=" ">
                        <label for="ju-unites">Nombre d'unités</label>
                        @error('unites') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="ffield">
                    <textarea id="ju-just" wire:model="justification" rows="3" placeholder=" "></textarea>
                    <label for="ju-just">Justification</label>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">
                        Obligatoire. C'est la seule pièce qui expliquera, dans six mois, pourquoi ce compte a été ajusté —
                        et la première qu'on cherchera si le geste est contesté.
                    </p>
                    @error('justification') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer l'ajustement</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
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
