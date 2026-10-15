@push('styles')
  {{-- `hub-pill` et `no-scrollbar` sont declarees EN LIGNE dans la page du
       prototype. Sans cette feuille, les pastilles de categorie sortent en
       texte brut — releve a l'ecran, pas par les tests. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-catalogue.css') }}">
@endpush

{{--
  | « Catalogue de cours » — transposition de compte-user/catalogue.html.
  |
  | Structure, classes et libellés repris du prototype. Ce qui change : les
  | données viennent de la base, et les états de carte des inscriptions réelles.
--}}
<div>

  <header class="mb-6">
    <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_catalogue.fil_d_ariane') }}">
      <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
      <span class="text-line" aria-hidden="true">/</span>
      <span class="text-ink font-medium">{{ __('cit_catalogue.catalogue_de_cours') }}</span>
    </nav>

    <div class="grid lg:grid-cols-3 gap-5 items-stretch">
      <div class="lg:col-span-2 card p-6 sm:p-8 flex flex-col justify-center">
        <h1 class="text-2xl lg:text-3xl font-display font-bold text-ink mb-2">{{ __('cit_catalogue.catalogue_de_cours') }}</h1>
        <p class="text-[15px] text-slate2 max-w-xl mb-4">{{ __('cit_catalogue.developpez_vos_competences_de_verification_et_devenez') }}</p>
        <p class="text-xs text-slate2 mb-5">{{ __('cit_catalogue.evidence_nbsp_nbsp_sources_nbsp_nbsp_context') }}</p>
        <div class="flex flex-wrap gap-2">
          <a href="#cat-grid" class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">{{ __('cit_catalogue.explorer_les_cours') }}</a>
          <a href="{{ route('citizen.stats') }}" class="inline-flex items-center rounded-xl border border-line bg-surface text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">{{ __('cit_catalogue.votre_progression') }}</a>
        </div>
      </div>

      <div class="card p-6 flex flex-col">
        <p class="vf-eyebrow">{{ __('cit_catalogue.votre_progression') }}</p>
        <p class="text-3xl font-display font-black text-ink mb-1.5">{{ $progression['pourcentage'] }} %</p>
        <div class="progress mb-2"><span style="width:{{ $progression['pourcentage'] }}%"></span></div>
        <p class="text-sm text-slate2 mb-4">
          {{ $progression['termines'] }} {{ trans_choice('cit_catalogue.cours_termine_pluriel', $progression['termines']) }}
          · {{ $progression['en_cours'] }} {{ __('cit_catalogue.en_cours') }}
        </p>
        @if($reprise)
          <a href="{{ route('citizen.course', $reprise->course) }}"
             class="mt-auto self-start inline-flex items-center gap-1 text-sm font-semibold text-primary min-h-[44px]">
            {{ __('cit_catalogue.continuer_mon_apprentissage') }}
            <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">arrow_forward</span>
          </a>
        @endif
      </div>
    </div>
  </header>

  {{-- Bandeau « Continuer » : le cours le plus récemment touché. --}}
  @if($reprise)
    <section class="mb-8">
      <div class="rounded-2xl border border-line bg-primary-soft/40 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="min-w-0 flex-1">
          <p class="vf-eyebrow">{{ __('cit_catalogue.continuer') }}</p>
          <h2 class="text-lg font-display font-bold text-ink mb-1.5">{{ $reprise->course->title }}</h2>
          <div class="flex items-center gap-2.5 max-w-md mb-1">
            <span class="progress flex-1"><span style="width:{{ $reprise->progress }}%"></span></span>
            <strong class="text-sm">{{ $reprise->progress }} %</strong>
          </div>
          <p class="text-xs text-slate2 mb-0">{{ $reprise->course->duration_label }} · {{ $reprise->course->level->libelle() }}</p>
        </div>
        <a href="{{ route('citizen.course', $reprise->course) }}"
           class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-3 hover:bg-primary-dark min-h-[48px]">
          {{ $reprise->libelleDeReprise() }}
          <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span>
        </a>
      </div>
    </section>
  @endif

  {{-- Pastilles de catégorie, lues du catalogue et non codées en dur. --}}
  <div class="flex gap-2 overflow-x-auto pb-1 mb-4 no-scrollbar" role="tablist" aria-label="{{ __('cit_catalogue.categories_de_cours') }}">
    <button type="button" wire:click="choisirCategorie('')"
            class="hub-pill {{ $categorie === '' ? 'is-active' : '' }}"
            role="tab" aria-selected="{{ $categorie === '' ? 'true' : 'false' }}">{{ __('cit_catalogue.tous') }}</button>
    @foreach($categories as $nom)
      <button type="button" wire:click="choisirCategorie(@js($nom))" wire:key="pill-{{ $loop->index }}"
              class="hub-pill {{ $categorie === $nom ? 'is-active' : '' }}"
              role="tab" aria-selected="{{ $categorie === $nom ? 'true' : 'false' }}">{{ $nom }}</button>
    @endforeach
  </div>

  <div class="flex flex-col md:flex-row gap-3 mb-8">
    <div class="relative flex-1">
      <label class="sr-only" for="cat-search">{{ __('cit_catalogue.rechercher_un_cours') }}</label>
      <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate2 !text-[19px]" aria-hidden="true">search</span>
      <input id="cat-search" type="search" wire:model.live.debounce.350ms="recherche"
             placeholder="{{ __('cit_catalogue.rechercher_un_cours_une_competence') }}"
             class="w-full min-h-[48px] bg-surface border border-line rounded-xl pl-10 pr-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10">
    </div>
    {{-- Sur téléphone, deux listes déroulantes côte à côte tiennent mal :
         le prototype les remplace par un tiroir qui monte du bas, où les
         choix sont des pastilles assez grandes pour le pouce. --}}
    <button type="button" data-sheet-open
            class="md:hidden inline-flex items-center justify-center gap-1.5 rounded-xl border border-line bg-surface text-sm font-semibold text-ink px-4 min-h-[48px]">{!! __('cit_catalogue.tune_filtres') !!}</button>

    <div class="hidden md:flex flex-wrap gap-3">
      <select wire:model.live="filtreNiveau" aria-label="{{ __('cit_catalogue.filtrer_par_niveau') }}"
              class="bg-surface border border-line rounded-xl px-3 min-h-[48px] text-sm">
        <option value="">{{ __('cit_catalogue.niveau_tous') }}</option>
        @foreach($niveaux as $niveau)
          <option value="{{ $niveau->value }}">{{ $niveau->libelle() }}</option>
        @endforeach
      </select>
      {{-- Le sélecteur n'apparaît qu'à partir de deux langues : un menu dont
           tous les choix donnent le même résultat n'aide personne. --}}
      @if(count($langues) > 1)
        <select wire:model.live="filtreLangue" aria-label="{{ __('cit_catalogue.filtrer_par_langue') }}"
                class="bg-surface border border-line rounded-xl px-3 min-h-[48px] text-sm">
          <option value="">{{ __('cit_catalogue.langue_toutes') }}</option>
          @foreach($langues as $langue)
            <option value="{{ $langue }}">{{ __('cit_catalogue.langue.'.$langue) }}</option>
          @endforeach
        </select>
      @endif

      <select wire:model.live="filtrePrix" aria-label="{{ __('cit_catalogue.filtrer_par_prix') }}"
              class="bg-surface border border-line rounded-xl px-3 min-h-[48px] text-sm">
        <option value="">{{ __('cit_catalogue.prix_tous') }}</option>
        <option value="free">{{ __('cit_catalogue.gratuit') }}</option>
        <option value="premium">{{ __('cit_catalogue.premium') }}</option>
      </select>
    </div>
  </div>

  {{--
    | « Recommandé pour vous ». Le prototype filtre sur un état que sa propre
    | fonction ne renvoie jamais : le bloc y est toujours vide, donc toujours
    | masqué. L'intention est claire — proposer ce qui n'a pas été commencé.
  --}}
  @if($recommandes->isNotEmpty())
    <section class="mb-10">
      <h2 class="text-xl font-display font-bold text-ink mb-0.5">{{ __('cit_catalogue.recommande_pour_vous') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_catalogue.les_mieux_notes_parmi_les_cours_que') }}</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-5">
        @foreach($recommandes as $recommande)
          <x-lms.carte-cours :cours="$recommande" :inscription="null" wire:key="reco-{{ $recommande->id }}" />
        @endforeach
      </div>
    </section>
  @endif

  <section id="cat-grid">
    <h2 class="text-xl font-display font-bold text-ink mb-4">{{ __('cit_catalogue.tous_les_cours') }}</h2>

    @if($cours->isNotEmpty())
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($cours as $unCours)
          <x-lms.carte-cours :cours="$unCours"
                             :inscription="$this->inscription($unCours)"
                             wire:key="cours-{{ $unCours->id }}" />
        @endforeach
      </div>
    @else
      <div class="card p-10 text-center mt-4">
        <span class="text-3xl block mb-2" aria-hidden="true">🔎</span>
        <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_catalogue.aucun_cours_trouve') }}</p>
        <p class="text-sm text-slate2 mb-4">
          @if($this->filtresActifs())
            {{ __('cit_catalogue.essayez_une_autre_recherche_ou_reinitialisez') }}
          @else
            {{ __('cit_catalogue.le_catalogue_s_enrichit_revenez_bientot') }}
          @endif
        </p>
        @if($this->filtresActifs())
          <button type="button" wire:click="reinitialiser"
                  class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors min-h-[44px] items-center">{{ __('cit_catalogue.reinitialiser_les_filtres') }}</button>
        @endif
      </div>
    @endif
  </section>

  {{-- ═══════════════════════════════════════════ tiroir de filtres (mobile) --}}
  <div class="fixed inset-0 z-[70] hidden" data-sheet>
    <div class="absolute inset-0 bg-black/50" data-sheet-close></div>
    <div class="absolute inset-x-0 bottom-0 bg-surface rounded-t-3xl p-6 pb-8" role="dialog" aria-modal="true" aria-label="{{ __('cit_catalogue.filtres_des_cours') }}">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-display font-bold text-ink mb-0">{{ __('cit_catalogue.filtres') }}</h2>
        <button type="button" class="w-10 h-10 inline-flex items-center justify-center rounded-lg hover:bg-canvas" data-sheet-close aria-label="{{ __('cit_catalogue.fermer') }}"><span class="material-symbols-rounded" aria-hidden="true">{{ __('cit_catalogue.close') }}</span></button>
      </div>
      <p class="text-xs font-bold text-slate2 uppercase tracking-wide mb-2">{{ __('cit_catalogue.niveau') }}</p>
      <div class="flex flex-wrap gap-2 mb-5">
        <button type="button" wire:click="$set('filtreNiveau', '')" class="hub-pill {{ $filtreNiveau === '' ? 'is-active' : '' }}">{{ __('cit_catalogue.tous') }}</button>
        @foreach($niveaux as $niveau)
          <button type="button" wire:click="$set('filtreNiveau', '{{ $niveau->value }}')" wire:key="sheet-niv-{{ $niveau->value }}" class="hub-pill {{ $filtreNiveau === $niveau->value ? 'is-active' : '' }}">{{ $niveau->libelle() }}</button>
        @endforeach
      </div>
      <p class="text-xs font-bold text-slate2 uppercase tracking-wide mb-2">{{ __('cit_catalogue.prix') }}</p>
      <div class="flex flex-wrap gap-2 mb-6">
        <button type="button" wire:click="$set('filtrePrix', '')" class="hub-pill {{ $filtrePrix === '' ? 'is-active' : '' }}">{{ __('cit_catalogue.tous') }}</button>
        <button type="button" wire:click="$set('filtrePrix', 'free')" class="hub-pill {{ $filtrePrix === 'free' ? 'is-active' : '' }}">{{ __('cit_catalogue.gratuit') }}</button>
        <button type="button" wire:click="$set('filtrePrix', 'premium')" class="hub-pill {{ $filtrePrix === 'premium' ? 'is-active' : '' }}">{{ __('cit_catalogue.premium') }}</button>
      </div>
      <div class="flex gap-2">
        <button type="button" wire:click="reinitialiser" class="flex-1 rounded-xl border border-line text-sm font-semibold text-ink py-3 min-h-[48px]">{{ __('cit_catalogue.reinitialiser') }}</button>
        <button type="button" data-sheet-close class="flex-1 rounded-xl bg-primary text-white text-sm font-semibold py-3 min-h-[48px]">{{ __('cit_catalogue.appliquer') }}</button>
      </div>
    </div>
  </div>

  @push('scripts')
    <script src="{{ asset('assets/js/pages/tiroir-filtres.js') }}" defer></script>
  @endpush
</div>
