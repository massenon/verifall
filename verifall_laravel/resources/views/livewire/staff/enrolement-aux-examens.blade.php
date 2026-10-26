{{--
  | « Enrôlement aux Examens » (lot 7).
  |
  | `convene` est un verbe à part dans la matrice, et le bouton est gardé
  | séparément : la convocation part chez le candidat, porte un code, et ne se
  | rattrape pas.
  |
  | DataTables (contrat : docs/DATATABLES_CONTRAT.md, migration Blade du
  | 12 octobre 2026). La session, qui conditionnait tout l'écran avant la
  | migration, n'est plus qu'un filtre parmi d'autres — la table sert
  | l'ensemble des inscriptions, toutes sessions confondues.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

<x-premium.entete titre="Enrôlement aux examens" :fil="['Enrôlement aux examens']">
        Les inscrits, toutes sessions confondues. Choisissez une session dans les filtres pour restreindre les indicateurs ci-dessous et inscrire un candidat à sa date.
        <x-slot:actions>
            @if ($peutInscrire && $session !== null)
            <button type="button" wire:click="ouvrirInscription" class="btn-primaire">
                <x-lucide name="user-plus" class="w-4 h-4" />
                Inscrire un candidat
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['inscrits'], 0, ',', ' ')"
               label="Inscrits" :trend="$session !== null ? ($session->seats !== null ? 'sur '.$session->seats.' places' : 'places illimitées') : 'Toutes sessions'" />

        {{-- `null` tant que personne n'est inscrit : « 0 % de présence » sur
             un ensemble vide est un chiffre inventé. --}}
        <x-kpi variant="staff" :value="$indicateurs['presence'] !== null ? $indicateurs['presence'].' %' : '—'"
               label="Présence" trend="Émargements enregistrés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['convocations_a_envoyer'], 0, ',', ' ')"
               label="À convoquer" trend="Convocation pas encore partie" />

        <x-kpi variant="staff" :value="number_format($indicateurs['reports'], 0, ',', ' ')"
               label="Reports demandés" trend="En attente d'arbitrage" />
    </x-kpi-grid>

    <div wire:ignore
         data-vdt="enrolement-aux-examens"
         data-vdt-source="{{ route('staff.exam-enrolment.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\EnrolementAuxExamens::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 mb-0">
                <label for="ee-q" class="etiquette">Rechercher un candidat</label>
<input id="ee-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" value="{{ $recherche }}" class="champ">
            </div>

            <select data-vdt-filtre="session" data-vdt-url="session" data-vdt-livewire="sessionUuid" class="champ w-auto" aria-label="Filtrer par session">
                <option value="">Session : toutes</option>
                @foreach ($sessions as $uneSession)
                    <option value="{{ $uneSession->uuid }}" @selected($session?->uuid === $uneSession->uuid)>
                        {{ $uneSession->exam?->title ?? 'Épreuve' }} —
                        {{ $uneSession->starts_at?->translatedFormat('j M Y · H:i') }}
                        ({{ $uneSession->location ?? 'en ligne' }})
                    </option>
                @endforeach
            </select>

            <select data-vdt-filtre="examen" data-vdt-url="examen" class="champ w-auto" aria-label="Filtrer par épreuve">
                <option value="">Épreuve : toutes</option>
                @foreach ($examens as $uuid => $titre)
                    <option value="{{ $uuid }}">{{ Str::limit($titre, 40) }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $unStatut)
                    <option value="{{ $unStatut->value }}" @selected($filtreStatut === $unStatut->value)>{{ $unStatut->libelle() }}</option>
                @endforeach
            </select>

            <div class="mb-0">
                <label class="etiquette">Session depuis le</label>
<input type="text" data-vdt-filtre="session_du" data-vdt-url="du" data-vdt-date class="champ">
            </div>
            <div class="mb-0">
                <label class="etiquette">jusqu'au</label>
<input type="text" data-vdt-filtre="session_au" data-vdt-url="au" data-vdt-date class="champ">
            </div>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les inscriptions.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
        </div>

        <table id="table-enrolement-aux-examens" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Candidat</th>
                    <th scope="col">Session</th>
                    <th scope="col">Convocation</th>
                    <th scope="col">Présence</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Inscrite</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    @if ($peutInscrire)
        <x-offcanvas-form :open="$formulaireOuvert"
                          title="Inscrire un candidat"
                          close="fermerLeTiroir">
            <form wire:submit="inscrire" class="space-y-4">
                <div>
                    <label for="ee-compte" class="etiquette">Adresse e-mail ou identifiant</label>
<input id="ee-compte" type="text" wire:model.live.debounce.500ms="compte" class="champ">
                    @error('compte') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                @if ($compte !== '' && $compteTrouve !== null)
                    @php($cible = App\Models\User::query()->where('uuid', $compteTrouve)->first())
                    @if ($cible !== null)
                        <div class="rounded-lg border border-trait dark:border-nuit-trait bg-green-100 dark:bg-green-950 p-3">
                            <p class="text-sm font-semibold text-brand-700 dark:text-brand mb-0">{{ $cible->name }}</p>
                            <p class="text-xs text-brand-700 dark:text-brand mb-0">{{ $cible->email }}</p>
                        </div>
                    @endif
                @endif

                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                    L'inscription ne convoque pas : la convocation est un geste séparé, depuis la liste.
                </p>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Inscrire</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>

        <x-offcanvas-form :open="$reportArbitrer !== null"
                          title="Arbitrer un report"
                          close="fermerLArbitrage">
            <form wire:submit="arbitrer" class="space-y-4">
                <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">
                    Un report se tranche vers une <span class="font-semibold">session précise</span>.
                    Il n'existe pas d'« accepter » qui laisserait le candidat sans date.
                </p>

                <div>
                    <label for="ee-report" class="block text-sm font-medium text-encre dark:text-white mb-1">Nouvelle session</label>
                    <select id="ee-report" wire:model="sessionDArrivee" class="champ w-full">
                        <option value="">— Choisir —</option>
                        @foreach ($sessions as $uneSession)
                            <option value="{{ $uneSession->uuid }}">
                                {{ $uneSession->exam?->title ?? 'Épreuve' }} —
                                {{ $uneSession->starts_at?->translatedFormat('j M Y · H:i') }}
                            </option>
                        @endforeach
                    </select>
                    @error('sessionDArrivee') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Reporter</button>
                    <button type="button" wire:click="fermerLArbitrage" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aAnnuler !== null"
                    title="Annuler cette inscription ?"
                    description="La ligne reste au registre — elle passe en « annulée » — et la place se libère. Effacer priverait d'explication le candidat qui reviendrait demander pourquoi il n'est plus inscrit."
                    confirm="annuler"
                    cancel="annulerLAnnulation" />
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
  <script src="{{ asset('assets/js/pages/staff-enrolement-aux-examens.js') }}"></script>
@endpush
