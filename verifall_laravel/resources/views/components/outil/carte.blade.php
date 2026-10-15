{{--
  | Carte d'un outil externe — reprise à l'identique du prototype.
  |
  | `rel="noopener noreferrer"` n'est pas décoratif : sans `noopener`, la page
  | ouverte garde une poignée sur la nôtre et peut la rediriger.
--}}
@props(['outil'])

<article class="card p-5 flex flex-col">
  <div class="flex items-start justify-between gap-2 mb-3">
    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-soft"><span class="material-symbols-rounded text-primary" aria-hidden="true">{{ $outil->icon }}</span></span>
    @if($outil->tag)
      <span class="text-[10px] font-bold tracking-[.05em] uppercase text-slate2">{{ $outil->tag }}</span>
    @endif
  </div>
  <h3 class="text-base font-display font-bold text-ink leading-snug mb-1">{{ $outil->title }}</h3>
  <p class="text-sm text-slate2 mb-3">{{ $outil->description }}</p>
  @if(!empty($outil->use_cases))
    <p class="text-[11px] font-bold text-slate2 uppercase tracking-wide mb-1.5">{{ __('composants.outil_ideal_pour') }}</p>
    <div class="flex flex-wrap gap-1.5 mb-4">@foreach($outil->use_cases as $usage)<span class="rounded-full bg-canvas border border-line text-[11px] font-semibold text-slate2 px-2.5 py-1">{{ $usage }}</span>@endforeach</div>
  @endif
  <div class="mt-auto pt-3 border-t border-line">
    <a href="{{ $outil->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark min-h-[44px]">{{ $outil->link_label ?: __('composants.outil_ouvrir_l_outil') }} <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">open_in_new</span></a>
  </div>
</article>
