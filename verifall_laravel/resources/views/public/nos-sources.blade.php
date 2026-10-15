@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/transparence.css') }}">
@endpush

{{--
  | Les sources sur lesquelles s'appuie Vérif'All.
  |
  | Rien n'est écrit en dur ici : la liste vient du dépôt, qui rend le MÊME
  | référentiel en cache que celui que le moteur consulte à chaque analyse. Une
  | source amendée depuis le back-office change cette page — c'est la seule
  | façon qu'elle reste vraie.
  |
  | Les classes sont celles du build Tailwind figé et de `transparence.css`,
  | comme la page Méthodologie dont celle-ci est le pendant. Le gabarit public
  | ouvre déjà un <main id="contenu"> : racine <div>.
--}}
<div>
  <section class="bg-[#f8f9fa] pt-36 pb-14 lg:pb-16">
    <div class="max-w-3xl mx-auto px-4 text-center">
      <nav class="flex items-center justify-center gap-2 text-sm text-body/70 mb-4" aria-label="{{ __('nos_sources.fil_d_ariane') }}">
        <a href="{{ route('public.home') }}" class="hover:text-primary">{{ __('nos_sources.accueil') }}</a>
        <span aria-hidden="true">›</span>
        <span class="text-heading font-medium">{{ __('nos_sources.nos_sources') }}</span>
      </nav>

      <h1 class="text-4xl md:text-5xl font-display font-extrabold text-heading mb-3">{{ __('nos_sources.nos_sources') }}</h1>
      <p class="text-lg text-body/80 mb-4">{{ __('nos_sources.chapeau', ['total' => $total]) }}</p>
      <p class="text-body/70 mb-0">{{ __('nos_sources.rappel_methodologie') }}
        <a href="{{ route('public.methodology') }}" class="text-primary hover:underline">{{ __('nos_sources.notre_methodologie') }}</a>.
      </p>
    </div>
  </section>

  {{-- ============ CE QUE CETTE LISTE VEUT DIRE ============ --}}
  <section class="pt-14">
    <div class="max-w-3xl mx-auto px-4">
      <div class="bg-white rounded-xl shadow-card border border-line p-6">
        <h2 class="text-lg font-display font-bold text-heading mb-2">{{ __('nos_sources.ce_que_cette_liste_veut_dire') }}</h2>
        <p class="text-body/85 leading-relaxed mb-3">{{ __('nos_sources.explication_referencement') }}</p>
        {{-- Le point le plus important de la page, et le plus facile à taire. --}}
        <p class="text-body/85 leading-relaxed mb-0">{{ __('nos_sources.explication_pas_de_note') }}</p>
      </div>
    </div>
  </section>

  {{-- ============ FILTRES ============ --}}
  <section class="pt-10">
    <div class="max-w-3xl mx-auto px-4">
      <label for="recherche-source" class="sr-only">{{ __('nos_sources.rechercher_une_source') }}</label>
      <input id="recherche-source" type="search"
             wire:model.live.debounce.350ms="recherche"
             placeholder="{{ __('nos_sources.rechercher_une_source') }}"
             class="w-full rounded-xl border border-line px-4 py-3 text-body focus:border-primary">

      <div class="flex flex-wrap gap-2 mt-4">
        @foreach ($familles as $nom)
          <button type="button" wire:click="choisirLaFamille('{{ $nom }}')"
                  aria-pressed="{{ $famille === $nom ? 'true' : 'false' }}"
                  class="rounded-full border border-line px-4 py-2 text-sm {{ $famille === $nom ? 'bg-primary text-white' : 'bg-white text-body hover:text-primary' }}">
            {{ $nom }} <span class="text-xs">({{ $comptes[$nom] }})</span>
          </button>
        @endforeach

        @if ($famille !== '' || $recherche !== '')
          <button type="button" wire:click="reinitialiser"
                  class="rounded-full px-4 py-2 text-sm text-body/70 hover:text-primary">
            {{ __('nos_sources.tout_afficher') }}
          </button>
        @endif
      </div>
    </div>
  </section>

  {{-- ============ LA LISTE ============ --}}
  <section class="py-14 lg:py-16">
    <div class="max-w-3xl mx-auto px-4">
      @forelse ($resultats as $nomDeFamille => $sources)
        <div class="mb-10">
          <div class="flex items-center flex-wrap gap-2 mb-4">
            <h2 class="text-2xl font-display font-bold text-heading mb-0">{{ $nomDeFamille }}</h2>
            <span class="text-sm text-body/70">{{ trans_choice('nos_sources.n_sources', $sources->count(), ['count' => $sources->count()]) }}</span>
          </div>

          <div class="bg-white rounded-xl shadow-card border border-line p-6">
            <ul class="list-none space-y-4 mb-0">
              @foreach ($sources as $source)
                <li class="flex items-baseline flex-wrap gap-2">
                  <span class="font-semibold text-heading">{{ $source->name }}</span>
                  {{-- `rel="nofollow noopener"` : on référence une source, on ne
                       lui transmet pas notre autorité de recherche, et on
                       n'ouvre pas notre onglet à la page visée. --}}
                  <a href="https://{{ $source->domain }}" target="_blank" rel="nofollow noopener"
                     class="text-sm text-primary hover:underline">{{ $source->domain }}</a>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @empty
        <div class="bg-white rounded-xl shadow-card border border-line p-6 text-center">
          <p class="text-body/85 mb-0">{{ __('nos_sources.aucune_source_ne_correspond') }}</p>
        </div>
      @endforelse
    </div>
  </section>

  {{-- ============ SIGNALER UN OUBLI ============ --}}
  <section class="pb-20">
    <div class="max-w-3xl mx-auto px-4">
      <div class="bg-white rounded-xl shadow-card border border-line p-6">
        <h2 class="text-lg font-display font-bold text-heading mb-2">{{ __('nos_sources.un_oubli_une_erreur') }}</h2>
        <p class="text-body/85 leading-relaxed mb-4">{{ __('nos_sources.explication_signalement') }}</p>
        <a href="{{ route('public.contact') }}" class="inline-flex rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:opacity-90">
          {{ __('nos_sources.nous_ecrire') }}
        </a>
      </div>
    </div>
  </section>
</div>
