{{--
  | Rediffusion d'une Live Class (lot 1, B-06).
  |
  | L'écran qui manquait derrière « Voir le replay » — un bouton qui, faute de
  | toute colonne d'enregistrement, ramenait à la liste des tutoriels.
  |
  | Le lecteur est un <iframe> et non un <video> : les enregistrements vivent
  | chez un hébergeur vidéo, pas sur notre disque. Un <video> exigerait de
  | servir le fichier nous-mêmes — bande passante, transcodage, reprise de
  | lecture — pour un module qui n'en est pas là.
--}}
<div class="max-w-3xl mx-auto">

  <nav class="text-xs text-slate2 mb-3" aria-label="{{ __('cit_live_classes.fil_d_ariane') }}">
    <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
    <span class="text-line" aria-hidden="true">/</span>
    <a href="{{ route('citizen.live-classes') }}" class="hover:text-primary">{{ __('cit_live_classes.live_classes') }}</a>
    <span class="text-line" aria-hidden="true">/</span>
    <span class="text-ink font-medium">{{ __('cit_live_classes.rediffusion') }}</span>
  </nav>

  {{-- ════════════════════════════════ la séance n'a pas de rediffusion --}}
  @if($blocage === 'sans-rediffusion')
    <div class="card p-8 text-center">
      <span class="material-symbols-rounded text-slate2 !text-[52px] mb-3" aria-hidden="true">videocam_off</span>
      <h1 class="text-2xl font-display font-bold text-ink mb-2">{{ __('cit_live_classes.rediffusion_indisponible') }}</h1>
      <p class="text-sm text-slate2 mb-5">{{ __('cit_live_classes.rediffusion_pas_toutes_enregistrees') }}</p>
      <div class="flex gap-2 justify-center flex-wrap">
        <a href="{{ route('citizen.live-classes') }}"
           class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
          {{ __('cit_live_classes.rediffusion_voir_les_seances') }}
        </a>
        @if($seance->course)
          <a href="{{ route('citizen.course', $seance->course) }}"
             class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">
            {{ __('cit_live_classes.rediffusion_retour_au_cours') }}
          </a>
        @endif
      </div>
    </div>

  {{-- ═══════════════════════════════════════════ pas inscrit au cours --}}
  @elseif($blocage === 'non-inscrit')
    <div class="card p-8 text-center">
      <span class="material-symbols-rounded text-vdoubt !text-[52px] mb-3" aria-hidden="true">school</span>
      <h1 class="text-2xl font-display font-bold text-ink mb-2">{{ __('cit_live_classes.rediffusion_reservee') }}</h1>
      <p class="text-sm text-slate2 mb-5">{{ __('cit_live_classes.rediffusion_inscrivez_vous') }}</p>
      @if($seance->course)
        <a href="{{ route('citizen.course', $seance->course) }}"
           class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
          {{ __('cit_live_classes.rediffusion_voir_le_cours') }}
        </a>
      @endif
    </div>

  {{-- ══════════════════════════════════════════════════════ le lecteur --}}
  @else
    <div class="card overflow-hidden mb-5">
      <div class="bg-ink" style="position:relative;padding-top:56.25%">
        {{-- 56,25 % = 9/16. La hauteur suit la largeur : sans ce calage, le
             lecteur se replie sur un rectangle de quelques pixels sur mobile. --}}
        <iframe src="{{ $seance->replay_url }}"
                title="{{ $seance->title }}"
                loading="lazy"
                allow="accelerometer; encrypted-media; picture-in-picture; fullscreen"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
                style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
      </div>

      <div class="p-5 sm:p-6">
        <span class="vbadge vbadge-true mb-2">{{ __('cit_live_classes.rediffusion') }}</span>
        <h1 class="text-xl sm:text-2xl font-display font-bold text-ink leading-snug mb-1">{{ $seance->title }}</h1>
        <p class="text-xs text-slate2 mb-0">
          {{ $seance->course?->title ?? __('composants.live_seance_ouverte') }}
          {{ __('composants.live_animee_par') }} {{ $seance->host?->name ?? __('composants.live_l_equipe_verif_all') }}
          · {{ $seance->starts_at->translatedFormat('j F Y') }}
          · {{ $minutes }} min
        </p>
      </div>
    </div>

    @if($seance->description)
      <div class="card p-5 sm:p-6 mb-5">
        <h2 class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_live_classes.rediffusion_au_programme') }}</h2>
        <p class="text-sm text-slate2 mb-0">{{ $seance->description }}</p>
      </div>
    @endif

    <div class="flex flex-wrap gap-2">
      <a href="{{ route('citizen.live-classes') }}"
         class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">
        {{ __('cit_live_classes.rediffusion_voir_les_seances') }}
      </a>
      @if($seance->course)
        <a href="{{ route('citizen.course', $seance->course) }}"
           class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">
          {{ __('cit_live_classes.rediffusion_retour_au_cours') }}
        </a>
      @endif
    </div>
  @endif

</div>
