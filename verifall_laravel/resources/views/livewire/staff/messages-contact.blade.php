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

<div>

<x-premium.entete titre="Messages" :fil="['Messages']">
        Ce qui arrive par le formulaire de contact public. Un message se traite, il ne s'efface pas : celui qui écrit signale parfois une intox.
    </x-premium.entete>

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
        <section id="message-ouvert" tabindex="-1" class="carte mb-6" aria-label="Message ouvert">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="font-semibold text-encre dark:text-white mb-0">{{ $messageOuvert->name }}</p>
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                        <a href="mailto:{{ $messageOuvert->email }}" class="hover:text-brand-700">{{ $messageOuvert->email }}</a>
                        @if ($messageOuvert->organisation) · {{ $messageOuvert->organisation }} @endif
                        @if ($messageOuvert->phone) · {{ $messageOuvert->phone }} @endif
                    </p>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                        Reçu le {{ $messageOuvert->created_at?->translatedFormat('j F Y à H:i') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-badge-statut variant="staff" :statut="$messageOuvert->status->cleBadge()" :label="$messageOuvert->status->libelle()" />
                    <button type="button" wire:click="fermer" class="btn-second">Fermer</button>
                </div>
            </div>

            @if ($messageOuvert->kind === \App\Enums\NatureMessageContact::Don)
                {{-- Le soutien proposé, mis en évidence : c'est l'information
                     pour laquelle on ouvre ce message. Le montant est
                     DÉCLARATIF, rien n'a été encaissé. --}}
                <div class="rounded-lg border border-trait dark:border-nuit-trait bg-papier dark:bg-nuit p-4 mb-4">
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-1">Soutien proposé (déclaratif, aucun paiement reçu)</p>
                    <p class="text-sm font-semibold text-encre dark:text-white mb-0">
                        {{ $messageOuvert->donation_amount ?: 'Montant non précisé' }}
                        @if ($messageOuvert->donation_recurrence)
                            · {{ $messageOuvert->donation_recurrence }}
                        @endif
                    </p>
                </div>
            @endif

            <p class="text-encre dark:text-white mb-6" style="white-space: pre-line;">{{ $messageOuvert->message }}</p>

            @can('update', \App\Models\ContactMessage::class)
                <div class="mb-3">
                    <label for="contact-note" class="etiquette">Note de traitement (facultative)</label>
<textarea id="contact-note" wire:model="note" rows="2" class="champ !h-auto py-2.5"></textarea>
                </div>
                @error('note')<p class="text-sm text-critique mb-3">{{ $message }}</p>@enderror

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="marquer('in_progress')" class="btn-second">Prendre en charge</button>
                    <button type="button" wire:click="marquer('handled')" class="btn-primaire">Marquer traité</button>
                    {{-- « Indésirable » sort le message de la file SANS l'effacer :
                         un faux positif se rattrape, une suppression non. --}}
                    <button type="button" wire:click="marquer('spam')" class="btn-second">Indésirable</button>
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
            <div class="flex-1 mb-0">
                <label for="contact-q" class="etiquette">Rechercher un nom, une adresse ou un mot du message</label>
<input id="contact-q" type="search" data-vdt-recherche data-vdt-url="q" value="{{ $recherche }}" class="champ">
            </div>

            <select data-vdt-filtre="statut" data-vdt-url="statut" class="champ w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $statut)
                    <option value="{{ $statut->value }}" @selected($filtreStatut === $statut->value)>{{ $statut->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="nature" data-vdt-url="nature" class="champ w-auto" aria-label="Filtrer par nature">
                <option value="">Nature : toutes</option>
                @foreach ($natures as $nature)
                    <option value="{{ $nature->value }}" @selected($filtreNature === $nature->value)>{{ $nature->libelle() }}</option>
                @endforeach
            </select>

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
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les messages.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
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
