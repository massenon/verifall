{{--
  | Carte d'une Live Class — reprise du gabarit `card()` du prototype.
  |
  | Les trois états n'offrent pas les mêmes gestes : en direct on rejoint,
  | à venir on met dans son agenda, terminée on regarde le replay. Un bouton
  | « Rejoindre » sur une séance passée serait une porte qui ne s'ouvre pas.
--}}
@props(['seance', 'statut'])

@php
  $quand = match ($statut) {
      'live' => __('composants.live_en_ce_moment'),
      default => ucfirst($seance->starts_at->diffForHumans(['parts' => 1]))
          .' · '.$seance->starts_at->format('H:i'),
  };
@endphp

<article class="card p-5">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
    @if($statut === 'live')
      <span class="vbadge vbadge-false"><span class="inline-block w-2 h-2 rounded-full bg-vfalse animate-pulse" aria-hidden="true"></span> {{ __('composants.live_en_direct') }}</span>
    @elseif($statut === 'upcoming')
      <span class="vbadge vbadge-progress">{{ __('composants.live_planifiee') }}</span>
    @else
      <span class="vbadge vbadge-true">{{ __('composants.live_terminee') }}</span>
    @endif
    <span class="text-xs text-slate2">{{ $quand }} · {{ $seance->minutes }} min</span>
  </div>
  <h2 class="text-base font-display font-bold text-ink leading-snug mb-1">{{ $seance->title }}</h2>
  <p class="text-xs text-slate2 mb-4">{{ $seance->course?->title ?? __('composants.live_seance_ouverte') }} {{ __('composants.live_animee_par') }} {{ $seance->host?->name ?? __('composants.live_l_equipe_verif_all') }}</p>
  <div class="flex flex-wrap gap-2">
    @if($statut === 'live')
      <button type="button" class="rounded-xl bg-vfalse text-white text-sm font-semibold px-4 py-2.5 hover:opacity-90"
              data-join data-join-titre="{{ $seance->title }}" data-join-lien="{{ $seance->join_url }}">{{ __('composants.live_rejoindre_maintenant') }}</button>
    @elseif($statut === 'upcoming')
      {{-- Le fichier calendrier est fabriqué par le serveur : le navigateur
           n'a pas à recomposer une date qu'il ne connaît qu'à l'affichage. --}}
      <a href="{{ route('citizen.live-class.ics', $seance) }}" class="rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas">{{ __('composants.live_ajouter_au_calendrier') }}</a>
      @if($seance->lienDisponible())
        <button type="button" class="rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark"
                data-join data-join-titre="{{ $seance->title }}" data-join-lien="{{ $seance->join_url }}">{{ __('composants.live_rejoindre') }}</button>
      @else
        <button type="button" class="rounded-xl bg-canvas text-sm font-semibold text-slate2 px-4 py-2.5 cursor-not-allowed" disabled title="{{ __('composants.live_le_lien_s_active_15_minutes_avant') }}">{{ __('composants.live_rejoindre_bientot') }}</button>
      @endif
    @else
      {{-- B-06. Ce bouton pointait sur `citizen.tutorials` — on cliquait pour
           revoir une séance et on atterrissait sur un catalogue. Le lien
           n'était pas le coupable : aucune colonne d'enregistrement n'existait,
           il n'y avait rien vers quoi pointer.

           TROIS états, et le troisième vient du passage au navigateur : la
           première version n'interrogeait que `rediffusionDisponible()`, un
           fait sur la SÉANCE. Elle offrait donc un bouton vert et engageant à
           quelqu'un de non inscrit au cours, qui tombait ensuite sur un
           refus — exactement l'impasse que le lot 1 s'était donné pour but de
           supprimer, reproduite deux écrans plus loin.

           `rediffusionVisiblePar()` répond, lui, sur la PERSONNE. --}}
      @if($seance->rediffusionVisiblePar(auth()->user()))
        <a href="{{ route('citizen.live-class.replay', $seance) }}" class="rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark inline-flex items-center gap-1.5"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">play_circle</span> {{ __('composants.live_voir_le_replay') }}</a>
      @elseif($seance->rediffusionDisponible() && $seance->course)
        {{-- Une rediffusion EXISTE, mais pas pour cette personne. On ne la
             cache pas : on dit ce qui l'ouvrirait, et on y mène. --}}
        <a href="{{ route('citizen.course', $seance->course) }}" class="rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas inline-flex items-center gap-1.5"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">school</span> {{ __('composants.live_rediffusion_reservee') }}</a>
      @else
        <span class="rounded-xl bg-canvas text-sm font-semibold text-slate2 px-4 py-2.5 inline-flex items-center gap-1.5"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">videocam_off</span> {{ __('composants.live_rediffusion_a_venir') }}</span>
      @endif
    @endif
  </div>
</article>
