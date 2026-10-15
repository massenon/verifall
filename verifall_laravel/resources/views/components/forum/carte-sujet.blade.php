@props(['sujet', 'aime' => false, 'lien' => true])

@php($avis = $sujet->repartitionDesAvis())
@php($nom = $sujet->author?->name ?? __('composants.sujet_compte_supprime'))

<article class="card p-5">

  <div class="flex items-center gap-2.5 mb-2">
    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-canvas border border-line text-xs font-bold text-ink shrink-0">
      {{ Str::of($nom)->explode(' ')->map(fn ($mot) => Str::substr($mot, 0, 1))->take(2)->implode('') }}
    </span>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-ink mb-0">
        {{ $nom }}
        <span class="text-xs font-normal text-slate2">· {{ $sujet->created_at?->diffForHumans() }}</span>
      </p>
      @if($sujet->category)
        <p class="text-[11px] text-slate2 mb-0"><span class="font-semibold">{{ $sujet->category->name }}</span></p>
      @endif
    </div>
    @if($sujet->is_pinned)
      <span class="material-symbols-rounded text-slate2 !text-[18px]" aria-label="{{ __('composants.sujet_sujet_epingle') }}" title="{{ __('composants.sujet_epingle') }}">push_pin</span>
    @endif
  </div>

  <h3 class="text-base font-display font-bold text-ink leading-snug mb-1">
    @if($lien)
      <a href="{{ route('citizen.thread', $sujet) }}" class="hover:text-primary">{{ $sujet->title }}</a>
    @else
      {{ $sujet->title }}
    @endif
  </h3>

  {{--
    | VERDICT OFFICIEL. Placé au-dessus des barres d'opinion, et visuellement
    | distinct : ce que la plateforme a établi ne se met pas au même niveau que
    | ce que la salle pense.
  --}}
  @if($sujet->porteUnVerdictOfficiel())
    <p class="mb-1.5">
      <span class="{{ $sujet->official_verdict->badge() }}">
        {{ __('composants.sujet_verif_all_confirme') }} {{ mb_strtoupper($sujet->official_verdict->libelle()) }}
      </span>
    </p>
  @endif

  <div class="text-sm text-slate2 mb-0 prose-lesson">{!! clean($sujet->body) !!}</div>

  {{-- Barres d'opinion : affichées seulement si quelqu'un s'est prononcé.
       Trois barres à zéro laisseraient croire à un partage d'avis là où il n'y
       a eu aucun vote. --}}
  @if($sujet->totalDesVotes() > 0)
    <div class="mt-3 space-y-1 text-xs">
      <p class="font-bold text-slate2 uppercase tracking-wide mb-1">{{ __('composants.sujet_avis_de_la_communaute') }}</p>
      @foreach(App\Enums\AvisCommunaute::cases() as $option)
        <div class="flex items-center gap-2">
          <span class="w-16 font-bold" style="color:{{ $option->couleur() }}">{{ $option->libelle() }}</span>
          <div class="progress flex-1"><span style="width:{{ $avis[$option->value] }}%;background:{{ $option->couleur() }}"></span></div>
          <span class="w-9 text-right">{{ $avis[$option->value] }}%</span>
        </div>
      @endforeach
      <p class="text-[11px] text-slate2 mb-0 pt-0.5">{{ $sujet->totalDesVotes() }} {{ $sujet->totalDesVotes() > 1 ? __('composants.sujet_avis_exprimes') : __('composants.sujet_avis_exprime') }}</p>
    </div>
  @endif

  @if($sujet->source_url)
    <p class="text-xs mt-3 mb-0">
      <span class="font-semibold text-vfalse">{{ __('composants.sujet_source_signalee') }}</span>
      {{-- `nofollow noopener` : une plateforme qui combat la désinformation ne
           transmet pas son autorité de référencement à l'arnaque signalée. --}}
      <a href="{{ $sujet->source_url }}" class="text-vinfo underline break-all"
         rel="noopener nofollow ugc" target="_blank">{{ Str::limit($sujet->source_url, 70) }}</a>
    </p>
  @endif

  <div class="flex items-center gap-4 mt-3 pt-3 border-t border-line text-sm">
    <button type="button" wire:click="aimer('{{ $sujet->slug }}')"
            class="inline-flex items-center gap-1 font-semibold {{ $aime ? 'text-primary' : 'text-slate2 hover:text-ink' }}"
            aria-pressed="{{ $aime ? 'true' : 'false' }}">
      <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">thumb_up</span>
      <span>{{ $sujet->likes_count }}</span>
    </button>

    <a href="{{ route('citizen.thread', $sujet) }}"
       class="inline-flex items-center gap-1 text-slate2 font-semibold hover:text-ink">
      <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">chat_bubble</span>
      {{ $sujet->replies_count }}
    </a>

    @if($sujet->is_locked)
      <span class="inline-flex items-center gap-1 text-slate2 text-xs">
        <span class="material-symbols-rounded !text-[17px]" aria-hidden="true">lock</span> {{ __('composants.sujet_fil_clos') }}
      </span>
    @endif
  </div>

</article>
