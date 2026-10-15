{{--
  | Lecteur de leçon — transposition de compte-user/lecon.html.
  |
  | Le chronomètre se met en pause quand l'onglet passe en arrière-plan : sans
  | cela, laisser la page ouverte pendant le déjeuner validerait le cours.
--}}
<div class="grid lg:grid-cols-3 gap-6"
     x-data="{
        secondes: 0,
        requis: {{ $secondesRequises }},
        actif: true,
        tick: null,
        init() {
            document.addEventListener('visibilitychange', () => { this.actif = ! document.hidden });
            this.tick = setInterval(() => {
                if (! this.actif) return;
                this.secondes++;
                $wire.secondesAnnoncees = this.secondes;
            }, 1000);
        },
        destroy() { clearInterval(this.tick) },
        get pret() { return this.secondes >= this.requis },
        get pourcent() { return Math.min(100, Math.round(this.secondes / this.requis * 100)) },
        get horloge() {
            const m = Math.floor(this.secondes / 60), s = this.secondes % 60;
            return m + ':' + String(s).padStart(2, '0');
        },
     }">

  <article class="lg:col-span-2 card p-6 sm:p-8">
    <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_lecon.fil_d_ariane') }}">
      <a href="{{ route('citizen.catalogue') }}" class="hover:text-primary">{{ __('cit_lecon.catalogue') }}</a>
      <span class="text-line" aria-hidden="true">/</span>
      <a href="{{ route('citizen.course', $cours) }}" class="hover:text-primary">{{ Str::limit($cours->title, 34) }}</a>
      <span class="text-line" aria-hidden="true">/</span>
      <span class="text-ink font-medium">{{ __('cit_lecon.lecon') }} {{ $lecon->position }}</span>
    </nav>

    <p class="text-xs font-bold text-primary uppercase tracking-[.1em] mb-1">
      {{ $cours->category }} · {{ __('cit_lecon.lecon_capitales') }} {{ str_pad((string) $lecon->position, 2, '0', STR_PAD_LEFT) }}
    </p>
    <h1 class="text-2xl sm:text-3xl font-display font-bold text-ink mb-5">{{ $lecon->title }}</h1>

    @if($lecon->type === App\Enums\TypeLecon::Video && $lecon->video_url)
      <div class="aspect-video rounded-xl overflow-hidden border border-line mb-6 bg-ink">
        <video controls preload="metadata" class="w-full h-full" src="{{ $lecon->video_url }}">
          {{ __('cit_lecon.votre_navigateur_ne_sait_pas_lire_cette') }}
        </video>
      </div>
    @elseif($lecon->type === App\Enums\TypeLecon::Audio && $lecon->audio_url)
      {{-- Une leçon audio n'affichait RIEN : le gabarit ne connaissait que la
           vidéo, et un enregistrement déposé restait invisible. Trouvé au
           navigateur le 2 septembre 2026. --}}
      <div class="rounded-xl border border-line p-4 mb-6 bg-canvas">
        <audio controls preload="metadata" class="w-full" src="{{ $lecon->audio_url }}">
          {{ __('cit_lecon.votre_navigateur_ne_sait_pas_lire_cette') }}
        </audio>
      </div>
    @endif

    {{-- Contenu purifié à l'écriture ET à l'affichage : cette page rend du HTML
         rédigé par un mentor, c'est-à-dire par un humain faillible. --}}
    <div class="prose-lesson text-[15px] leading-relaxed space-y-4">
      {!! clean($lecon->content ?? '') !!}
    </div>

    {{-- ── DOCUMENTS JOINTS ────────────────────────────────────────────────
         Ils étaient déposables depuis le constructeur, stockés sur le disque
         privé, servis par une route signée — et AUCUN écran ne les montrait.
         Une chaîne complète que personne ne pouvait emprunter. Trouvé au
         navigateur le 2 septembre 2026.

         Chaque lien est signé À CHAQUE RENDU : il prouve qu'il vient de nous,
         et le contrôleur vérifie en plus l'inscription — un lien transmis
         n'ouvre pas le cours de quelqu'un d'autre. --}}
    @if($documents->isNotEmpty())
      <div class="mt-8 rounded-xl border border-line p-4">
        <h2 class="text-sm font-display font-bold text-ink mb-3">{{ __('cit_lecon.documents_de_la_lecon') }}</h2>

        <ul class="list-none p-0 m-0 space-y-2">
          @foreach($documents as $document)
            <li class="flex items-center justify-between gap-3">
              <a href="{{ $this->lienDuDocument($document) }}"
                 class="inline-flex items-center gap-2 text-sm font-semibold text-primary min-w-0">
                <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">{{ $document->icone() }}</span>
                <span class="truncate">{{ $document->original_name }}</span>
              </a>
              <span class="text-xs text-slate2 shrink-0">{{ $document->poidsLisible() }}</span>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="mt-8 pt-5 border-t border-line flex flex-wrap items-center gap-3">
      @if($precedente)
        <a href="{{ route('citizen.lesson', ['course' => $cours, 'lesson' => $precedente]) }}" wire:navigate
           class="inline-flex items-center gap-1 text-sm font-bold text-ink hover:text-primary">← {{ __('cit_lecon.lecon_precedente') }}</a>
      @else
        <span></span>
      @endif

      <div class="flex-1"></div>

      @if($acquise)
        <span class="inline-flex items-center gap-1.5 rounded-xl bg-primary-soft text-primary-dark text-sm font-semibold px-5 py-2.5">
          ✓ {{ __('cit_lecon.lecon_terminee') }}
        </span>
        @if($suivante)
          <a href="{{ route('citizen.lesson', ['course' => $cours, 'lesson' => $suivante]) }}" wire:navigate
             class="inline-flex items-center gap-1 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">
            {{ __('cit_lecon.lecon_suivante') }} →
          </a>
        @else
          <a href="{{ route('citizen.course', $cours) }}" wire:navigate
             class="inline-flex items-center gap-1 rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors">
            {{ __('cit_lecon.terminer_le_cours') }} →
          </a>
        @endif
      @else
        <button type="button" wire:click="terminer" wire:loading.attr="disabled"
                x-bind:disabled="! pret"
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark disabled:opacity-40 disabled:cursor-not-allowed">{{ __('cit_lecon.terminer_la_lecon') }}</button>
      @endif
    </div>

    {{-- Le compteur est VISIBLE : un bouton grisé sans explication passe pour
         un bug. Là, on voit ce qu'il reste à attendre. --}}
    @unless($acquise)
      <div class="mt-4 no-print">
        <p class="text-xs text-slate2 mb-1">
          {{ __('cit_lecon.temps_de_lecture_actif') }} <span x-text="horloge">0:00</span> / {{ intdiv($secondesRequises, 60) }}:{{ str_pad((string) ($secondesRequises % 60), 2, '0', STR_PAD_LEFT) }}
          {{ __('cit_lecon.le_chronometre_se_met_en_pause_si') }}
        </p>
        <div class="progress"><span x-bind:style="'width:' + pourcent + '%'"></span></div>
      </div>
    @endunless
  </article>

  <aside class="space-y-4">
    <div class="card p-5">
      <p class="text-base font-display font-bold text-ink mb-2">{{ $cours->title }}</p>
      <div class="progress mb-1.5"><span style="width:{{ $avancement }}%"></span></div>
      <p class="text-sm text-slate2 mb-0">{{ $avancement }} % {{ __('cit_lecon.termine') }}</p>
    </div>

    <div class="card p-5">
      <p class="text-sm font-display font-bold text-ink mb-3">{{ __('cit_lecon.curriculum') }}</p>
      <ol class="divide-y divide-line">
        @foreach($lecons as $autre)
          @php($faite = $this->leconAcquise($autre))
          @php($ouverte = $this->leconOuverte($autre))
          <li class="flex items-center gap-2.5 py-2.5" wire:key="cur-{{ $autre->id }}">
            @if($faite)
              <span class="material-symbols-rounded text-vtrue !text-[18px]" aria-hidden="true">check_circle</span>
            @elseif($autre->id === $lecon->id)
              <span class="material-symbols-rounded text-primary !text-[18px]" aria-hidden="true">play_circle</span>
            @elseif($ouverte)
              <span class="material-symbols-rounded text-slate2 !text-[18px]" aria-hidden="true">radio_button_unchecked</span>
            @else
              <span class="material-symbols-rounded text-slate2 !text-[18px]" aria-hidden="true">lock</span>
            @endif

            @if($ouverte)
              <a href="{{ route('citizen.lesson', ['course' => $cours, 'lesson' => $autre]) }}" wire:navigate
                 class="flex-1 text-xs {{ $autre->id === $lecon->id ? 'font-bold text-ink' : 'text-slate2 hover:text-primary' }}">{{ $autre->title }}</a>
            @else
              <span class="flex-1 text-xs text-slate2">{{ $autre->title }}</span>
            @endif

            <span class="text-[11px] text-slate2">{{ $autre->minutes }} min</span>
          </li>
        @endforeach
      </ol>
    </div>
  </aside>

</div>
