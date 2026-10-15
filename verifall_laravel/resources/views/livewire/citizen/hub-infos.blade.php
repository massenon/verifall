@push('styles')
  {{-- `hub-card`, `hub-badge-*` et `hub-pill` vivent en ligne dans la page du
       prototype : sans cette feuille, les cartes et les bandeaux de verdict
       sortent sans style. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-hub-infos.css') }}">
@endpush

{{--
  | « Hub opportunités » — transposition de compte-user/hub-infos.html.
  |
  | La structure est celle du prototype : en-tête avec recherche à droite, « à
  | la une » sur douze colonnes (une grande carte + deux petites), puis les
  | listes en grille.
  |
  | Deux écarts, tous deux demandés :
  |   · les PORTAILS OFFICIELS ne sont plus affichés — les données restent en
  |     base, c'est une mise en retrait, pas un abandon ;
  |   · la section « Jobs vérifiés » se dédouble en OPPORTUNITÉS BÉNIN et
  |     OPPORTUNITÉS INTERNATIONALES, et les annonces sont classées par nature.
--}}
<div>

  <header class="mb-6">
    <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_hub_infos.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_hub_infos.hub_opportunites') }}</span></nav>
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div class="max-w-xl">
        <h1 class="text-3xl lg:text-4xl font-display font-bold text-ink mb-2">{{ __('cit_hub_infos.hub_opportunites') }}</h1>
        <p class="text-[15px] text-slate2 mb-0">{{ __('cit_hub_infos.l_actualite_passee_au_crible_et_les') }}</p>
      </div>
      <div class="relative w-full lg:max-w-[640px]">
        <label class="sr-only" for="hub-search">{{ __('cit_hub_infos.rechercher_une_actualite_une_opportunite') }}</label>
        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-slate2 !text-[20px]" aria-hidden="true">search</span>
        <input id="hub-search" type="search" wire:model.live.debounce.350ms="recherche"
               placeholder="{{ __('cit_hub_infos.rechercher_une_actualite_une_opportunite_indice') }}"
               class="w-full h-[50px] bg-surface border border-line rounded-xl pl-11 pr-4 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10">
      </div>
    </div>
  </header>

  <nav class="flex gap-2 overflow-x-auto pb-1 mb-10 no-scrollbar" aria-label="{{ __('cit_hub_infos.sections_du_hub') }}">
    <a href="#hub-actualites" class="hub-pill">{{ __('cit_hub_infos.actualites') }}</a>
    <a href="#opportunites-benin" class="hub-pill">{{ __('cit_hub_infos.opportunites_benin') }}</a>
    <a href="#opportunites-internationales" class="hub-pill">{{ __('cit_hub_infos.opportunites_internationales') }}</a>
  </nav>

  {{-- ═══════════════════════════════════════════════════════ actualités --}}
  <section class="mb-16" id="hub-actualites">
    <div class="flex items-end justify-between gap-3 mb-5">
      <div>
        <p class="text-[11px] font-bold tracking-[.16em] text-primary uppercase mb-1">{{ __('cit_hub_infos.a_la_une') }}</p>
        <h2 class="text-xl lg:text-2xl font-display font-bold text-ink mb-0">{{ __('cit_hub_infos.l_analyse_du_jour') }}</h2>
      </div>
    </div>

    @if($aLaUne)
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <article class="lg:col-span-8 hub-card flex flex-col overflow-hidden">
          @if($aLaUne->cover_path)
            <a href="{{ route('citizen.hub.article', $aLaUne) }}" class="block"><img src="{{ asset($aLaUne->cover_path) }}" alt="" width="640" height="360" loading="lazy" class="w-full h-auto object-cover" style="aspect-ratio:16/9" aria-hidden="true"></a>
          @endif
          <div class="p-5 lg:p-6 flex flex-col flex-1">
            <div class="mb-3"><span class="{{ $aLaUne->classeDuBandeau() }}">{{ $aLaUne->libelleDuBandeau() }}</span></div>
            <h3 class="text-xl lg:text-2xl font-display font-bold text-ink leading-snug mb-2"><a href="{{ route('citizen.hub.article', $aLaUne) }}" class="hover:text-primary transition-colors">{{ $aLaUne->title }}</a></h3>
            <p class="text-[15px] text-slate2 mb-3">{{ $aLaUne->excerpt }}</p>
            <p class="text-xs text-slate2 mb-4">@if($aLaUne->source_name){{ $aLaUne->source_name }} · @endif{{ $aLaUne->published_at?->translatedFormat('j F Y') }}</p>
            <div class="mt-auto flex items-center gap-2">
              <a href="{{ route('citizen.hub.article', $aLaUne) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">{{ __('cit_hub_infos.lire_l_analyse') }} <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span></a>
            </div>
          </div>
        </article>
        <div class="lg:col-span-4 grid gap-5 content-start">
          @foreach($breves->take(2) as $breve)
            <article class="hub-card flex flex-col overflow-hidden" wire:key="une-{{ $breve->id }}">
              @if($breve->cover_path)
                <a href="{{ route('citizen.hub.article', $breve) }}" class="block"><img src="{{ asset($breve->cover_path) }}" alt="" width="640" height="360" loading="lazy" class="w-full h-auto object-cover" style="aspect-ratio:16/9" aria-hidden="true"></a>
              @endif
              <div class="p-5 flex flex-col flex-1">
                <div class="mb-2"><span class="{{ $breve->classeDuBandeau() }}">{{ $breve->libelleDuBandeau() }}</span></div>
                <h3 class="text-base font-display font-bold text-ink leading-snug mb-2"><a href="{{ route('citizen.hub.article', $breve) }}" class="hover:text-primary transition-colors">{{ $breve->title }}</a></h3>
                <p class="text-xs text-slate2 mb-3">@if($breve->source_name){{ $breve->source_name }} · @endif{{ $breve->published_at?->translatedFormat('j M Y') }}</p>
                <a href="{{ route('citizen.hub.article', $breve) }}" class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-primary">{{ __('cit_hub_infos.lire_l_article') }} <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">arrow_forward</span></a>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    @endif

    @if($categories !== [])
      <div class="flex flex-wrap gap-3 mb-5 mt-10" role="tablist" aria-label="{{ __('cit_hub_infos.categories_d_actualites') }}">
        <button type="button" wire:click="choisirCategorie('')" class="hub-pill {{ $categorie === '' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $categorie === '' ? 'true' : 'false' }}">{{ __('cit_hub_infos.tout') }}</button>
        @foreach($categories as $nom)
          <button type="button" wire:click="choisirCategorie(@js($nom))" wire:key="hub-cat-{{ $loop->index }}" class="hub-pill {{ $categorie === $nom ? 'is-active' : '' }}" role="tab" aria-selected="{{ $categorie === $nom ? 'true' : 'false' }}">{{ $nom }}</button>
        @endforeach
      </div>
    @endif

    @if($breves->isNotEmpty())
      <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach($breves as $breve)
          <article class="hub-card flex flex-col overflow-hidden" wire:key="breve-{{ $breve->id }}">
            <div class="p-5 flex flex-col flex-1">
              <div class="mb-2"><span class="{{ $breve->classeDuBandeau() }}">{{ $breve->libelleDuBandeau() }}</span></div>
              <p class="text-[10px] font-bold tracking-[.08em] text-primary uppercase mb-1.5">{{ $breve->category }}</p>
              <h3 class="text-base font-display font-bold text-ink leading-snug mb-1.5"><a href="{{ route('citizen.hub.article', $breve) }}" class="hover:text-primary transition-colors">{{ $breve->title }}</a></h3>
              <p class="text-sm text-slate2 mb-3">{{ Str::limit($breve->excerpt, 130) }}</p>
              <p class="text-xs text-slate2 mb-3">@if($breve->source_name){{ $breve->source_name }} · @endif{{ $breve->published_at?->translatedFormat('j M Y') }}</p>
              <a href="{{ route('citizen.hub.article', $breve) }}" class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-primary">{{ __('cit_hub_infos.lire_l_article') }} <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">arrow_forward</span></a>
            </div>
          </article>
        @endforeach
      </div>

      <div class="mt-6">{{ $breves->links() }}</div>
    @else
      <div class="hub-card p-8 text-center mt-5">
        <p class="text-sm font-bold text-ink mb-1">{{ __('cit_hub_infos.aucune_actualite_trouvee') }}</p>
        <p class="text-sm text-slate2 mb-3">{{ $this->filtresActifs() ? __('cit_hub_infos.essayez_une_autre_recherche_ou_modifiez_vos') : __('cit_hub_infos.le_hub_s_enrichit_chaque_semaine') }}</p>
        @if($this->filtresActifs())
          <button type="button" wire:click="reinitialiser" class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-4 py-2.5 hover:bg-primary hover:text-white transition-colors">{{ __('cit_hub_infos.reinitialiser_les_filtres') }}</button>
        @endif
      </div>
    @endif
  </section>

  {{-- Filtre de NATURE, commun aux deux listes : quelqu'un qui cherche une
       bourse la cherche des deux côtés à la fois. --}}
  @if($types !== [])
    <div class="flex gap-2 overflow-x-auto pb-1 mb-6 no-scrollbar" role="tablist" aria-label="{{ __('cit_hub_infos.nature_des_opportunites') }}">
      <button type="button" wire:click="choisirType('')" class="hub-pill {{ $filtreType === '' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $filtreType === '' ? 'true' : 'false' }}">{{ __('cit_hub_infos.toutes') }}</button>
      @foreach($types as $type)
        <button type="button" wire:click="choisirType('{{ $type->value }}')" wire:key="type-{{ $type->value }}" class="hub-pill {{ $filtreType === $type->value ? 'is-active' : '' }}" role="tab" aria-selected="{{ $filtreType === $type->value ? 'true' : 'false' }}">{{ $type->libelle() }}</button>
      @endforeach
    </div>
  @endif

  {{-- ═════════════════════════════════════════════ opportunités au Bénin --}}
  <section class="mb-16" id="opportunites-benin">
    <div class="mb-5">
      <p class="text-[11px] font-bold tracking-[.16em] text-primary uppercase mb-1">{{ __('cit_hub_infos.opportunites_verifiees') }}</p>
      <h2 class="text-xl lg:text-2xl font-display font-bold text-ink mb-1">{{ __('cit_hub_infos.opportunites_benin') }}</h2>
      <p class="text-sm text-slate2 mb-0">{{ __('cit_hub_infos.organisation_confirmee_canal_officiel_coordonnees_verifiees_') }}</p>
    </div>

    @if($opportunitesBenin->isNotEmpty())
      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        @foreach($opportunitesBenin as $offre)
          <x-hub.carte-opportunite :offre="$offre" wire:key="benin-{{ $offre->id }}" />
        @endforeach
      </div>
    @else
      <x-hub.aucune-opportunite :filtres="$this->filtresOpportunitesActifs()"
                                titre="Aucune opportunité vérifiée au Bénin" />
    @endif
  </section>

  {{-- ═════════════════════════════════ opportunités internationales --}}
  <section class="mb-16" id="opportunites-internationales">
    <div class="mb-5">
      <p class="text-[11px] font-bold tracking-[.16em] text-primary uppercase mb-1">{{ __('cit_hub_infos.hors_du_benin') }}</p>
      <h2 class="text-xl lg:text-2xl font-display font-bold text-ink mb-1">{{ __('cit_hub_infos.opportunites_internationales') }}</h2>
      <p class="text-sm text-slate2 mb-0">{{ __('cit_hub_infos.bourses_conferences_financements_et_postes_hors_du') }}</p>
    </div>

    @if($opportunitesInternationales->isNotEmpty())
      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        @foreach($opportunitesInternationales as $offre)
          <x-hub.carte-opportunite :offre="$offre" wire:key="inter-{{ $offre->id }}" />
        @endforeach
      </div>
    @else
      <x-hub.aucune-opportunite :filtres="$this->filtresOpportunitesActifs()"
                                titre="Aucune opportunité internationale vérifiée" />
    @endif
  </section>

  <section class="mb-16">
    <div class="rounded-3xl bg-forest text-white p-8 lg:p-12 text-center">
      <h2 class="text-xl lg:text-2xl font-display font-bold mb-2">{{ __('cit_hub_infos.une_information_vous_semble_douteuse') }}</h2>
      <p class="text-[15px] text-white/70 max-w-xl mx-auto mb-6">{{ __('cit_hub_infos.ne_la_partagez_pas_avant_de_l') }}</p>
      <a href="{{ route('citizen.verify') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-6 py-3 hover:bg-primary-dark min-h-[48px]">{{ __('cit_hub_infos.verifier_une_information') }} <span class="material-symbols-rounded !text-[17px]" aria-hidden="true">arrow_forward</span></a>
    </div>
  </section>

  <section class="hub-card p-6 lg:p-8">
    <h2 class="text-base font-display font-bold text-ink mb-2">{{ __('cit_hub_infos.pourquoi_faire_confiance_a_ces_informations') }}</h2>
    <p class="text-sm text-slate2 mb-4">{{ __('cit_hub_infos.les_contenus_presentes_dans_ce_hub_sont') }}</p>
    <a href="{{ route('public.how') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary">{{ __('cit_hub_infos.comprendre_notre_methode') }} <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">arrow_forward</span></a>
  </section>
</div>
