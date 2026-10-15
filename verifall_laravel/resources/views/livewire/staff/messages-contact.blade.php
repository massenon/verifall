{{--
  | « Messages » — la boîte de réception du formulaire de contact.
  |
  | Même gabarit que les autres écrans du back-office : fil d'Ariane, en-tête,
  | indicateurs, filtres, jetons de filtres actifs, table sur grand écran.
  |
  | Le panneau de lecture s'ouvre SOUS les filtres, et non dans une fenêtre
  | modale : on lit un message en gardant la file sous les yeux, alors qu'une
  | modale obligerait à fermer pour passer au suivant.
  |
  | DataTables : contrat complet dans `docs/DATATABLES_CONTRAT.md`.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Messages</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Messages</h1>
        <p class="text-[#64748B] text-sm mb-0">
            Ce qui arrive par le formulaire de contact public. Un message se traite,
            il ne s'efface pas : celui qui écrit signale parfois une intox.
        </p>
    </header>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($compteurs['total'], 0, ',', ' ')"
               label="Messages reçus" trend="Depuis l'ouverture du formulaire" />

        <x-kpi variant="staff" :value="number_format($compteurs['new'], 0, ',', ' ')"
               label="Nouveaux" trend="Jamais ouverts" />

        <x-kpi variant="staff" :value="number_format($compteurs['in_progress'], 0, ',', ' ')"
               label="En cours" trend="Pris en charge, réponse à venir" />

        <x-kpi variant="staff" :value="number_format($compteurs['handled'], 0, ',', ' ')"
               label="Traités"
               :trend="$compteurs['spam'] > 0 ? $compteurs['spam'].' marqué(s) indésirable(s)' : 'Aucun indésirable'" />
    </x-kpi-grid>

    {{-- Le panneau de lecture.
         Le message est rendu avec {{ }}, jamais {!! !!} : c'est du texte libre
         écrit par un inconnu, donc le cas type d'injection. --}}
    @if ($messageOuvert !== null)
        <section id="message-ouvert" tabindex="-1" class="st-card mb-6" aria-label="Message ouvert">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="font-semibold text-[#0F172A] mb-0">{{ $messageOuvert->name }}</p>
                    <p class="text-sm text-[#64748B] mb-0">
                        <a href="mailto:{{ $messageOuvert->email }}" class="hover:text-[#065F46]">{{ $messageOuvert->email }}</a>
                        @if ($messageOuvert->organisation) · {{ $messageOuvert->organisation }} @endif
                        @if ($messageOuvert->phone) · {{ $messageOuvert->phone }} @endif
                    </p>
                    <p class="text-xs text-[#64748B] mb-0">
                        Reçu le {{ $messageOuvert->created_at?->translatedFormat('j F Y à H:i') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-badge-statut variant="staff" :statut="$messageOuvert->status->cleBadge()" :label="$messageOuvert->status->libelle()" />
                    <button type="button" wire:click="fermer" class="st-btn st-btn-secondary">Fermer</button>
                </div>
            </div>

            @if ($messageOuvert->kind === \App\Enums\NatureMessageContact::Don)
                {{-- Le soutien proposé, mis en évidence : c'est l'information
                     pour laquelle on ouvre ce message. Le montant est
                     DÉCLARATIF, rien n'a été encaissé. --}}
                <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-4 mb-4">
                    <p class="text-xs text-[#64748B] mb-1">Soutien proposé (déclaratif, aucun paiement reçu)</p>
                    <p class="text-sm font-semibold text-[#0F172A] mb-0">
                        {{ $messageOuvert->donation_amount ?: 'Montant non précisé' }}
                        @if ($messageOuvert->donation_recurrence)
                            · {{ $messageOuvert->donation_recurrence }}
                        @endif
                    </p>
                </div>
            @endif

            <p class="text-[#0F172A] mb-6" style="white-space: pre-line;">{{ $messageOuvert->message }}</p>

            @can('update', \App\Models\ContactMessage::class)
                <div class="ffield mb-3">
                    <textarea id="contact-note" wire:model="note" rows="2" placeholder=" "></textarea>
                    <label for="contact-note">Note de traitement (facultative)</label>
                </div>
                @error('note')<p class="text-sm text-[#DC2626] mb-3">{{ $message }}</p>@enderror

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="marquer('in_progress')" class="st-btn st-btn-secondary">Prendre en charge</button>
                    <button type="button" wire:click="marquer('handled')" class="st-btn st-btn-primary">Marquer traité</button>
                    {{-- « Indésirable » sort le message de la file SANS l'effacer :
                         un faux positif se rattrape, une suppression non. --}}
                    <button type="button" wire:click="marquer('spam')" class="st-btn st-btn-secondary">Indésirable</button>
                </div>
            @endcan
        </section>
    @endif

    <div wire:ignore
         data-vdt="messages-contact"
         data-vdt-source="{{ route('staff.contact-messages.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\MessagesContact::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="ffield flex-1 mb-0">
                <input id="contact-q" type="search" data-vdt-recherche data-vdt-url="q" value="{{ $recherche }}" placeholder=" ">
                <label for="contact-q">Rechercher un nom, une adresse ou un mot du message</label>
            </div>

            <select data-vdt-filtre="statut" data-vdt-url="statut" class="st-input w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $statut)
                    <option value="{{ $statut->value }}" @selected($filtreStatut === $statut->value)>{{ $statut->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="nature" data-vdt-url="nature" class="st-input w-auto" aria-label="Filtrer par nature">
                <option value="">Nature : toutes</option>
                @foreach ($natures as $nature)
                    <option value="{{ $nature->value }}" @selected($filtreNature === $nature->value)>{{ $nature->libelle() }}</option>
                @endforeach
            </select>

            <div class="ffield mb-0">
                <input type="text" data-vdt-filtre="recu_du" data-vdt-url="du" data-vdt-date placeholder=" ">
                <label>Reçu depuis le</label>
            </div>
            <div class="ffield mb-0">
                <input type="text" data-vdt-filtre="recu_au" data-vdt-url="au" data-vdt-date placeholder=" ">
                <label>jusqu'au</label>
            </div>

            <button type="button" data-vdt-reinitialiser class="st-btn st-btn-secondary">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-[#FCA5A5] bg-[#FEF2F2] text-[#991B1B] text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les messages.</p>
            <button type="button" data-vdt-reessayer class="st-btn st-btn-secondary">Réessayer</button>
        </div>

        <table id="table-messages-contact" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Nature</th>
                    <th scope="col">Expéditeur</th>
                    <th scope="col">Message</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Reçu le</th>
                    <th scope="col" class="text-right">Actions</th>
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
  <script src="{{ asset('assets/js/pages/staff-messages-contact.js') }}"></script>
@endpush
