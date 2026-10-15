@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-hub-infos.css') }}">
@endpush

{{-- Une brève du Hub — transposition de compte-user/hub-article.html. --}}
<div class="max-w-3xl mx-auto">

  <nav class="text-xs text-slate2 mb-3" aria-label="{{ __('cit_article_hub.fil_d_ariane') }}">
    <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
    <span class="text-line" aria-hidden="true">/</span>
    <a href="{{ route('citizen.hub') }}" class="hover:text-primary">{{ __('cit_article_hub.hub_opportunites') }}</a>
    <span class="text-line" aria-hidden="true">/</span>
    <span class="text-ink font-medium">{{ Str::limit($breve->title, 40) }}</span>
  </nav>

  <article class="card p-6 sm:p-8">
    <div class="mb-3"><span class="{{ $breve->classeDuBandeau() }}">{{ $breve->libelleDuBandeau() }}</span></div>

    <p class="text-[10px] font-bold tracking-[.08em] text-primary uppercase mb-1.5">{{ $breve->category }}</p>
    <h1 class="text-2xl sm:text-3xl font-display font-bold text-ink leading-tight mb-3">{{ $breve->title }}</h1>

    <p class="text-sm text-slate2 mb-6">
      {{ $breve->published_at?->translatedFormat('j F Y') }}
      @if($breve->source_name) · {{ __('cit_article_hub.source') }} <strong class="text-ink">{{ $breve->source_name }}</strong> @endif
    </p>

    <p class="text-base text-ink font-medium mb-5">{{ $breve->excerpt }}</p>

    {{-- Purifié à l'affichage aussi : cette page rend du HTML rédigé par un
         humain, et une seule vue qui l'oublierait suffirait. --}}
    <div class="prose-lesson text-[15px] leading-relaxed space-y-4">
      {!! clean($breve->body ?? '') !!}
    </div>

    @if($breve->source_url)
      <p class="text-sm mt-6 mb-0">
        {{-- `nofollow` : on ne transmet pas notre autorité de référencement à ce
             qu'on est en train de démentir. --}}
        <a href="{{ route('public.fact.source', $breve) }}" target="_blank" rel="noopener nofollow"
           class="inline-flex items-center gap-1.5 font-semibold text-primary">
          {{ __('cit_article_hub.consulter_la_source') }}
          <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">open_in_new</span>
        </a>
      </p>
    @endif

    @if($breve->verifiedFact)
      <div class="mt-6 pt-5 border-t border-line">
        <p class="text-sm font-bold text-ink mb-1">{{ __('cit_article_hub.fonde_sur_une_verification_de_notre_equipe') }}</p>
        <p class="text-sm text-slate2 mb-0">{{ Str::limit(strip_tags($breve->verifiedFact->summary), 260) }}</p>
      </div>
    @endif
  </article>

  @if($voisines->isNotEmpty())
    <section class="mt-8">
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_article_hub.dans_la_meme_rubrique') }}</h2>
      <div class="space-y-3">
        @foreach($voisines as $voisine)
          <a href="{{ route('citizen.hub.article', $voisine) }}" wire:key="voisine-{{ $voisine->id }}"
             class="card p-4 flex items-start gap-3 hover:border-primary transition-colors">
            <span class="{{ $voisine->classeDuBandeau() }} shrink-0">{{ $voisine->libelleDuBandeau() }}</span>
            <span class="min-w-0">
              <span class="block text-sm font-bold text-ink">{{ $voisine->title }}</span>
              <span class="block text-xs text-slate2">{{ $voisine->published_at?->translatedFormat('j M Y') }}</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endif

</div>
