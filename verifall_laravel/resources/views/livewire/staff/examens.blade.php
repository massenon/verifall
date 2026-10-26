{{--
  | « Liste des Examens » (lot 7).
  |
  | Le dépôt (18 méthodes) et le service (11) existaient, complets. Il manquait
  | l'écran : les examens ne pouvaient se créer que par la console.
  |
  | Les sessions s'ouvrent EN PLACE sous la ligne de leur épreuve. Une épreuve
  | n'a d'existence pratique qu'à travers ses sessions — c'est la session qui a
  | une date, des places et des candidats.
  |
  | DataTables (contrat : docs/DATATABLES_CONTRAT.md, migration Blade du
  | 12 octobre 2026). Les sessions dépliées restent rendues par Livewire, SOUS
  | la zone [data-vdt] : `staff-examens.js` ne les décrit pas.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

<x-premium.entete titre="Examens" :fil="['Examens']">
        Les épreuves certifiantes et leurs sessions. Une épreuve sans question ne se publie pas : elle serait réussie par tout le monde.
        <x-slot:actions>
            @can('create', App\Models\Exam::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvel examen
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['actifs'], 0, ',', ' ')"
               label="Examens publiés" trend="Disponibles à la planification" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sessions_ouvertes'], 0, ',', ' ')"
               label="Sessions ouvertes" trend="Inscriptions possibles" />

        <x-kpi variant="staff" :value="number_format($indicateurs['candidats_30j'], 0, ',', ' ')"
               label="Candidats (30 j)" trend="Copies rendues" />

        {{-- `null` et non zéro tant qu'aucune copie n'a été corrigée : « 0 % de
             réussite » sur une plateforme qui n'a corrigé personne est un
             chiffre inventé, et c'est celui qu'on retient. --}}
        <x-kpi variant="staff" :value="$indicateurs['taux_reussite'] !== null ? $indicateurs['taux_reussite'].' %' : '—'"
               label="Taux de réussite" trend="Sur les copies corrigées" />
    </x-kpi-grid>

    <div wire:ignore
         data-vdt="examens"
         data-vdt-source="{{ route('staff.exams.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\Examens::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 mb-0">
                <label for="ex-q" class="etiquette">Rechercher une épreuve</label>
<input id="ex-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" value="{{ $recherche }}" class="champ">
            </div>

            <select data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                @foreach ($statuts as $unStatut)
                    <option value="{{ $unStatut->value }}" @selected($filtreStatut === $unStatut->value)>{{ $unStatut->libelle() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="cours" data-vdt-url="cours" class="champ w-auto" aria-label="Filtrer par cours">
                <option value="">Cours : tous</option>
                @foreach ($cours as $unCours)
                    <option value="{{ $unCours->uuid }}">{{ Str::limit($unCours->title, 40) }}</option>
                @endforeach
            </select>

            <div class="mb-0">
                <label class="etiquette">Créé depuis le</label>
<input type="text" data-vdt-filtre="cree_du" data-vdt-url="du" data-vdt-date class="champ">
            </div>
            <div class="mb-0">
                <label class="etiquette">jusqu'au</label>
<input type="text" data-vdt-filtre="cree_au" data-vdt-url="au" data-vdt-date class="champ">
            </div>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les examens.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
        </div>

        <table id="table-examens" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Épreuve</th>
                    <th scope="col">Cours</th>
                    <th scope="col">Barème</th>
                    <th scope="col">Candidats</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Créé</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    @if ($examenCourant !== null)
        <section class="mt-8">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-lg font-titre font-bold text-encre dark:text-white mb-0">
                        Sessions — {{ $examenCourant->title }}
                    </h2>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                        La session porte la date, le lieu et les places. C'est elle qu'on ouvre aux inscriptions.
                    </p>
                </div>

                @can('update', $examenCourant)
                    @if ($examenCourant->status?->value === 'published')
                        <button type="button" wire:click="ouvrirCreationSession" class="btn-second">
                            <x-lucide name="plus" class="w-4 h-4" />
                            Planifier une session
                        </button>
                    @else
                        {{-- Le service refuse : « on ne convoque personne à un examen
                             qui n'est pas publié ». On le dit AVANT le clic. --}}
                        <p class="text-xs text-critique mb-0">Publiez l'épreuve pour pouvoir planifier une session.</p>
                    @endif
                @endcan
            </div>

            @if ($sessions === null || $sessions->isEmpty())
                <x-empty-state variant="staff" icon="event_busy"
                               title="Aucune session"
                               description="Tant qu'aucune session n'est planifiée, personne ne peut s'inscrire à cette épreuve." />
            @else
                <x-data-table variant="staff" :count="$sessions->total()" :selectable="false">
                    <x-slot:head>
                        <th scope="col">Date</th>
                        <th scope="col">Lieu</th>
                        <th scope="col">Places</th>
                        <th scope="col">État</th>
                        <th class="text-right">Actions</th>
                    </x-slot:head>

                    @foreach ($sessions as $session)
                        <tr>
                            <td class="whitespace-nowrap">
                                <p class="font-semibold text-encre dark:text-white mb-0">
                                    {{ $session->starts_at?->translatedFormat('j M Y · H:i') }}
                                </p>
                                @if ($session->is_proctored)
                                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">Surveillée</p>
                                @endif
                            </td>
                            <td class="text-sm text-encre-2 dark:text-nuit-2">{{ $session->location ?? 'En ligne' }}</td>
                            <td class="whitespace-nowrap text-sm">{{ $session->seats ?? 'illimitées' }}</td>
                            <td>
                                <span class="pastille p-info">{{ $session->status?->libelle() ?? $session->status }}</span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @can('update', $examenCourant)
                                    <button type="button" wire:click="changerLEtatDeLaSession('{{ $session->uuid }}', 'ouvrir')"
                                            class="btn-icone" title="Ouvrir aux inscriptions">
                                        <x-lucide name="lock-open" class="w-4 h-4" />
                                    </button>
                                    <button type="button" wire:click="changerLEtatDeLaSession('{{ $session->uuid }}', 'fermer')"
                                            class="btn-icone" title="Fermer les inscriptions">
                                        <x-lucide name="lock" class="w-4 h-4" />
                                    </button>
                                    <button type="button" wire:click="changerLEtatDeLaSession('{{ $session->uuid }}', 'annuler')"
                                            class="btn-icone text-critique" title="Annuler la session">
                                        <x-lucide name="calendar-x" class="w-4 h-4" />
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </section>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouvel examen' : 'Modifier l\'épreuve'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div>
                <label for="ex-titre" class="etiquette">Titre de l'épreuve</label>
<input id="ex-titre" type="text" wire:model="titre" class="champ">
                @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ex-desc" class="etiquette">Ce que cette épreuve évalue</label>
<textarea id="ex-desc" wire:model="descriptif" rows="3" class="champ !h-auto py-2.5"></textarea>
                @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ex-cours" class="block text-sm font-medium text-encre dark:text-white mb-1">Cours rattaché</label>
                <select id="ex-cours" wire:model="coursUuid" class="champ w-full">
                    <option value="">— Transverse —</option>
                    @foreach ($cours as $unCours)
                        <option value="{{ $unCours->uuid }}">{{ $unCours->title }}</option>
                    @endforeach
                </select>
                @error('coursUuid') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div class="mb-0">
                    <label for="ex-duree" class="etiquette">Durée (min)</label>
<input id="ex-duree" type="number" min="5" max="480" wire:model="duree" class="champ">
                    @error('duree') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="ex-seuil" class="etiquette">Seuil (%)</label>
<input id="ex-seuil" type="number" min="50" max="100" wire:model="seuil" class="champ">
                    @error('seuil') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="ex-tent" class="etiquette">Tentatives</label>
<input id="ex-tent" type="number" min="1" max="5" wire:model="tentatives" class="champ">
                    @error('tentatives') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                L'épreuve naît en brouillon. Attachez-lui des questions depuis la banque avant de publier :
                un examen sans question serait réussi par tout le monde.
            </p>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-offcanvas-form :open="$formulaireSession"
                      title="Planifier une session"
                      close="fermerLeTiroirSession">
        <form wire:submit="enregistrerLaSession" class="space-y-4">
            <div>
                <label for="se-debut" class="etiquette">Date et heure</label>
<input id="se-debut" type="datetime-local" wire:model="sessionDebut" class="champ">
                @error('sessionDebut') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="mb-0">
                    <label for="se-places" class="etiquette">Places</label>
<input id="se-places" type="number" min="1" max="1000" wire:model="sessionPlaces" class="champ">
                    @error('sessionPlaces') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="se-lieu" class="etiquette">Lieu (vide = en ligne)</label>
<input id="se-lieu" type="text" wire:model="sessionLieu" class="champ">
                    @error('sessionLieu') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="sessionSurveillee" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">Session surveillée</span>
            </label>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Planifier</button>
                <button type="button" wire:click="fermerLeTiroirSession" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>
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
  <script src="{{ asset('assets/js/pages/staff-examens.js') }}"></script>
@endpush
