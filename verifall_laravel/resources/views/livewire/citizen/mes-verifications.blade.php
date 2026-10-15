@push('styles')
  {{-- `.vchip`, `.icon-act` et `.no-scrollbar` sont déclarées EN LIGNE
       dans la page du prototype. Sans cette feuille, les pastilles de
       filtre sortent en texte brut — sans fond, sans bordure, sans état
       actif. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-mes-verifications.css') }}">
@endpush

{{--
  | « Mes Vérifications » — transposition de compte-user/mes-verifications.html.
  |
  | Structure, classes et ordre des blocs repris du prototype : en-tête avec le
  | compte, accordéon d'indicateurs, filtres, chips, tableau, états vides.
--}}
<div>
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div>
      <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_mes_verifications.fil_d_ariane') }}">
        <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
        <span class="text-line" aria-hidden="true">/</span>
        <span class="text-ink font-medium">{{ __('cit_mes_verifications.mes_verifications') }}</span>
      </nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_mes_verifications.mes_verifications') }}</h1>
      <p class="text-slate2 mt-1 mb-0">
        {{ __('cit_mes_verifications.votre_laboratoire_personnel_de_verification') }} {{ trans_choice('cit_mes_verifications.n_contenus_analyses', $this->indicateurs['total']) }}.
        @if($this->indicateurs['derniere'])
          <span class="block text-xs text-slate2 mt-1">{{ __('cit_mes_verifications.derniere_verification') }} {{ $this->indicateurs['derniere'] }}.</span>
        @endif
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('citizen.verify') }}"
         class="inline-flex items-center gap-1.5 rounded-xl bg-brandred text-white text-sm font-semibold px-4 py-2.5 hover:opacity-90 min-h-[44px]">
        <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
        <span class="hidden sm:inline">{{ __('cit_mes_verifications.verifier_un_contenu') }}</span><span class="sm:hidden">{{ __('cit_mes_verifications.verifier') }}</span>
      </a>
    </div>
  </div>

  {{-- indicateurs : sur TOUT l'historique, pas sur la page filtrée --}}
  <section data-kpi-accordion class="mb-6">
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{!! __('cit_mes_verifications.votre_activite_de_verification_expand_more') !!}</button>
    <div class="kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="vf-kpi">
        <p class="vf-kpi-value">{{ $this->indicateurs['total'] }}</p>
        <p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_mes_verifications.contenus_soumis') }}</p>
      </div>
      <div class="vf-kpi">
        <p class="vf-kpi-value text-vinfo">{{ $this->indicateurs['en_cours'] }}</p>
        <p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_mes_verifications.en_cours') }}</p>
      </div>
      <div class="vf-kpi">
        {{-- « Intox » réunit faux et trompeur : du point de vue de quelqu'un
             qui a failli partager, les deux sont des pièges. --}}
        <p class="vf-kpi-value text-vfalse">{{ $this->indicateurs['intox'] }}</p>
        <p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_mes_verifications.intox_detectees') }}</p>
      </div>
      <div class="vf-kpi">
        <p class="vf-kpi-value text-vtrue">{{ $this->indicateurs['fiables'] }}</p>
        <p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_mes_verifications.infos_fiables') }}</p>
      </div>
    </div>
  </section>

  {{-- filtres --}}
  <div class="card p-4 mb-4 flex flex-col md:flex-row gap-3">
    <div class="relative flex-1">
      <label class="sr-only" for="f-search">{{ __('cit_mes_verifications.rechercher') }}</label>
      <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate2 !text-[19px]" aria-hidden="true">search</span>
      <input id="f-search" type="search" placeholder="{{ __('cit_mes_verifications.rechercher_dans_mes_verifications') }}"
             wire:model.live.debounce.350ms="recherche"
             class="w-full bg-canvas border border-line rounded-xl pl-10 pr-3 py-2.5 text-sm outline-none focus:border-primary">
    </div>

    <select wire:model.live="filtreType" aria-label="{{ __('cit_mes_verifications.filtrer_par_type') }}"
            class="bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm">
      <option value="">{{ __('cit_mes_verifications.tous_les_types') }}</option>
      @foreach($types as $type)
        <option value="{{ $type->value }}">{{ $type->libelle() }}</option>
      @endforeach
    </select>

    <select wire:model.live="filtreVerdict" aria-label="{{ __('cit_mes_verifications.filtrer_par_verdict') }}"
            class="bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm">
      <option value="">{{ __('cit_mes_verifications.tous_les_verdicts') }}</option>
      @foreach($verdicts as $verdict)
        <option value="{{ $verdict->value }}">{{ mb_strtoupper($verdict->libelle()) }}</option>
      @endforeach
      <option value="en_cours">{{ __('cit_mes_verifications.en_cours') }}</option>
    </select>
  </div>

  {{-- chips mobiles --}}
  <div class="flex md:hidden gap-2 overflow-x-auto pb-1 mb-3 no-scrollbar" aria-label="{{ __('cit_mes_verifications.filtrer_par_verdict') }}">
    <button type="button" wire:click="$set('filtreVerdict', '')"
            class="vchip {{ $filtreVerdict === '' ? 'is-active' : '' }}">{{ __('cit_mes_verifications.tous') }}</button>
    <button type="button" wire:click="filtrerParVerdict('true')"
            class="vchip {{ $filtreVerdict === 'true' ? 'is-active' : '' }}">✓ {{ __('cit_mes_verifications.vrai') }}</button>
    <button type="button" wire:click="filtrerParVerdict('false')"
            class="vchip {{ $filtreVerdict === 'false' ? 'is-active' : '' }}">✕ {{ __('cit_mes_verifications.faux') }}</button>
    <button type="button" wire:click="filtrerParVerdict('misleading')"
            class="vchip {{ $filtreVerdict === 'misleading' ? 'is-active' : '' }}">⚠ {{ __('cit_mes_verifications.trompeur') }}</button>
    <button type="button" wire:click="filtrerParVerdict('en_cours')"
            class="vchip {{ $filtreVerdict === 'en_cours' ? 'is-active' : '' }}">◌ {{ __('cit_mes_verifications.en_cours') }}</button>
  </div>

  <p class="text-sm font-semibold text-ink mb-3">{{ trans_choice('cit_mes_verifications.n_verifications', $verifications->total()) }}</p>

  @if($verifications->total() > 0)
    {{-- `overflow-x-auto` et non `overflow-hidden` : à 1024 px la colonne
         Action était rognée de 73 px sans aucun moyen de l'atteindre, et
         plus rien ne menait au rapport. --}}
    <div class="table-wrap card overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">{{ __('cit_mes_verifications.contenu') }}</th>
            <th scope="col">{{ __('cit_mes_verifications.type') }}</th>
            <th scope="col">{{ __('cit_mes_verifications.verdict') }}</th>
            <th scope="col">{{ __('cit_mes_verifications.confiance') }}</th>
            <th scope="col">{{ __('cit_mes_verifications.date') }}</th>
            <th scope="col"><span class="sr-only">{{ __('cit_mes_verifications.action') }}</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($verifications as $verification)
            <tr wire:key="verif-{{ $verification->id }}" data-row-el>
              <td class="max-w-[320px]">
                <span class="block truncate">{{ $verification->apercu(70) }}</span>
                <span class="block text-xs text-slate2 font-mono">{{ $verification->reference() }}</span>
              </td>
              <td class="text-slate2">{{ $verification->type->libelle() }}</td>
              <td>
                {{-- `verdict` est NULLABLE dès qu'un rapport est MIXTE OU
                     INSUFFISANT (contrat V-8) : `estMixte()` seul laissait
                     passer le second cas vers `verdict->badge()` sur `null`.
                     Se brancher sur `verdict !== null` couvre les deux à la
                     fois, et tout état futur qui porterait aussi un verdict
                     nul. --}}
                @if($verification->report && $verification->report->verdict !== null)
                  <span class="{{ $verification->report->verdict->badge() }}">
                    {{ $verification->report->verdict->libelle() }}
                  </span>
                @elseif($verification->report)
                  <span class="{{ $verification->report->composition_status->badge() }}">
                    {{ $verification->report->composition_status->libelle() }}
                  </span>
                @else
                  {{-- Une analyse en cours n'a pas de verdict : afficher son
                       ÉTAT vaut mieux qu'un tiret, qui laisserait croire à un
                       échec. --}}
                  <span class="{{ $verification->status->badge() }}">
                    {{ $verification->status->libelle() }}
                  </span>
                @endif
              </td>
              <td class="text-slate2">{{ $verification->report ? $verification->report->confidence.' %' : '—' }}</td>
              <td class="text-slate2">{{ $verification->created_at?->translatedFormat('d M Y') }}</td>
              <td>
                {{-- Le lien mène à l'écran qui convient : le rapport s'il
                     existe, le suivi sinon. --}}
                @if($verification->report)
                  <a href="{{ route('citizen.report', ['verification' => $verification->uuid]) }}"
                     class="text-sm font-bold text-primary">{{ __('cit_mes_verifications.voir_le_rapport') }}</a>
                @elseif(! $verification->estTerminee())
                  <a href="{{ route('citizen.verification', ['verification' => $verification->uuid]) }}"
                     class="text-sm font-bold text-primary">{{ __('cit_mes_verifications.suivre') }}</a>
                @else
                  <span class="text-sm text-slate2">{{ __('cit_mes_verifications.indisponible') }}</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pendant MOBILE du tableau. `citizen.css` masque `.table-wrap` sous
         768 px et attend `.cards-wrap` : sans lui, le compteur annonce des
         résultats et l'écran n'en montre aucun. --}}
    <div class="cards-wrap space-y-3">
      @foreach($verifications as $verification)
        <article class="card p-4" wire:key="carte-{{ $verification->id }}">
          <p class="text-sm font-bold text-ink mb-0.5">{{ $verification->apercu(70) }}</p>
          <p class="text-xs text-slate2 font-mono mb-2">{{ $verification->reference() }}</p>

          <p class="mb-2">
            @if($verification->report && $verification->report->verdict !== null)
              <span class="{{ $verification->report->verdict->badge() }}">
                {{ $verification->report->verdict->libelle() }}
              </span>
            @elseif($verification->report)
              <span class="{{ $verification->report->composition_status->badge() }}">
                {{ $verification->report->composition_status->libelle() }}
              </span>
            @else
              <span class="{{ $verification->status->badge() }}">
                {{ $verification->status->libelle() }}
              </span>
            @endif
          </p>

          <p class="text-xs text-slate2 mb-3">
            {{ $verification->type->libelle() }}
            @if($verification->report) · {{ $verification->report->confidence }} % {{ __('cit_mes_verifications.de_fiabilite') }} @endif
            · {{ $verification->created_at?->translatedFormat('d M Y') }}
          </p>

          @if($verification->report)
            <a href="{{ route('citizen.report', ['verification' => $verification->uuid]) }}"
               class="text-sm font-bold text-primary min-h-[44px] inline-flex items-center">{{ __('cit_mes_verifications.voir_le_rapport') }} →</a>
          @elseif(! $verification->estTerminee())
            <a href="{{ route('citizen.verification', ['verification' => $verification->uuid]) }}"
               class="text-sm font-bold text-primary min-h-[44px] inline-flex items-center">{{ __('cit_mes_verifications.suivre') }} →</a>
          @else
            <span class="text-sm text-slate2">{{ __('cit_mes_verifications.indisponible') }}</span>
          @endif
        </article>
      @endforeach
    </div>


    <div class="mt-4">{{ $verifications->links() }}</div>

  @elseif($this->filtresActifs())
    {{-- Deux vides distincts : « rien ne correspond » et « vous n'avez encore
         rien fait » n'appellent pas la même action. --}}
    <div class="card p-10 text-center">
      <span class="text-3xl block mb-2" aria-hidden="true">🔎</span>
      <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_mes_verifications.aucun_resultat') }}</p>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_mes_verifications.aucune_verification_ne_correspond_a_vos_criteres') }}</p>
      <button type="button" wire:click="reinitialiser"
              class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors min-h-[44px] items-center">{{ __('cit_mes_verifications.reinitialiser_les_filtres') }}</button>
    </div>

  @else
    <div class="card p-10 text-center">
      <span class="text-3xl block mb-2" aria-hidden="true">🧪</span>
      <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_mes_verifications.votre_laboratoire_est_encore_vide') }}</p>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_mes_verifications.commencez_votre_premiere_verification_pour_construire_votre') }}</p>
      <a href="{{ route('citizen.verify') }}"
         class="inline-flex rounded-xl bg-brandred text-white text-sm font-semibold px-5 py-2.5 min-h-[44px] items-center">
        {{ __('cit_mes_verifications.verifier_un_contenu') }}
      </a>
    </div>
  @endif
</div>
