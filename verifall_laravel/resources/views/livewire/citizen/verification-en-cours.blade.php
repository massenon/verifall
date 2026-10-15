{{--
  | « Analyse en cours » — Progression dynamique fluide et temps réel.
--}}
<div wire:poll.{{ $reprise ? '30s' : '2s' }}="suivre"
     x-data="{
        avancementVisuel: {{ max(10, (int) $avancement) }},
        timer: null,
        init() {
            this.timer = setInterval(() => {
                if (this.avancementVisuel < 92) {
                    let increment = (95 - this.avancementVisuel) / 20;
                    this.avancementVisuel = Math.min(92, Math.round(this.avancementVisuel + Math.max(1, increment)));
                }
            }, 800);
        }
     }">

  <div class="max-w-2xl mx-auto py-4">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
      <div>
        <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_verification_en_cours.fil_d_ariane') }}">
          <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
          <span class="text-line mx-1" aria-hidden="true">/</span>
          <a href="{{ route('citizen.verify') }}" class="hover:text-primary">{{ __('cit_verification_en_cours.verifier') }}</a>
          <span class="text-line mx-1" aria-hidden="true">/</span>
          <span class="text-ink font-medium">{{ __('cit_verification_en_cours.analyse') }}</span>
        </nav>
        <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_verification_en_cours.analyse_en_cours') }}</h1>
        <p class="text-slate2 mt-1 mb-0">
          {{ $this->messageDeStatut() }}
          @if(! $reprise)
            {{ __('cit_verification_en_cours.ne_fermez_pas_cette_page_le_rapport') }}
          @else
            {{ __('cit_verification_en_cours.vous_pouvez_fermer_cette_page_sans_rien') }}
          @endif
        </p>
      </div>
    </div>

    <div class="card p-6 sm:p-8 shadow-sm border border-line bg-surface">

      {{-- En-tête : Référence & Pourcentage dynamique --}}
      <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate2 mb-0">
          {{ __('cit_verification_en_cours.reference') }} <strong class="text-ink font-mono">{{ $verification->reference() }}</strong>
        </p>
        <p class="text-2xl font-display font-black text-primary mb-0" x-text="avancementVisuel + '%'"></p>
      </div>

      {{-- BARRE DE PROGRESSION ACTIVE & ANIMÉE --}}
      <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden border border-line shadow-inner mb-6">
        <div class="bg-primary h-full rounded-full transition-all duration-500 ease-out relative"
             :style="'width: ' + avancementVisuel + '%'">
          <div class="absolute inset-0 bg-white/25 w-full h-full animate-[pulse_1.5s_infinite]"></div>
        </div>
      </div>

      {{-- Indicateur contextuel dynamique --}}
      <div class="text-center py-2 px-3 mb-6 bg-canvas rounded-xl border border-line">
        <div class="inline-flex items-center gap-2 text-xs font-semibold text-primary">
          <span class="material-symbols-rounded !text-[16px] animate-spin">progress_activity</span>
          <span x-show="avancementVisuel < 20">Réception du contenu et calcul des empreintes...</span>
          <span x-show="avancementVisuel >= 20 && avancementVisuel < 40" x-cloak>Recherche dans les bases de fact-checking et sources officielles...</span>
          <span x-show="avancementVisuel >= 40 && avancementVisuel < 70" x-cloak>Analyse croisée multi-modèles...</span>
          <span x-show="avancementVisuel >= 70 && avancementVisuel < 88" x-cloak>Consolidation des preuves et évaluation de la rhétorique...</span>
          <span x-show="avancementVisuel >= 88" x-cloak>Calcul de l'indice de fiabilité et finalisation du rapport...</span>
        </div>
      </div>

      {{-- TIMELINE DES ÉTAPES : Synchronisée avec avancementVisuel --}}
      @php
        $seuils = [
          0 => 15,  // Réception
          1 => 35,  // Préparation
          2 => 55,  // Sources
          3 => 75,  // Analyse IA
          4 => 88,  // Consolidation
          5 => 98   // Verdict
        ];
      @endphp

      <ol class="vtimeline">
        @foreach($etapes as $index => $etape)
          @php($seuil = $seuils[$index] ?? (($index + 1) * 16))
          <li class="vt-step"
              :class="{
                'is-done': avancementVisuel >= {{ $seuil }},
                'is-active': avancementVisuel < {{ $seuil }} && avancementVisuel >= {{ $seuil - 20 }}
              }"
              wire:key="etape-{{ $etape->value }}">
            <span class="vt-dot">
              <span class="material-symbols-rounded !text-[14px]" aria-hidden="true">check</span>
            </span>
            <p class="vt-title text-sm font-medium text-ink mb-0.5">
              {{ $etape->libelle() }}
            </p>
            <p class="text-xs text-slate2 mb-0">{{ $etape->description() }}</p>
          </li>
        @endforeach
      </ol>

      {{-- En cas de reprise / attente --}}
      @if($reprise)
        <div class="mt-6 pt-5 border-t border-line">
          <div class="flex items-start gap-3">
            <span class="material-symbols-rounded text-vinvest !text-[22px] shrink-0" aria-hidden="true">schedule</span>
            <div class="min-w-0">
              <p class="text-sm font-bold text-ink mb-1">{{ __('cit_verification_en_cours.analyse_mise_en_attente') }}</p>
              <p class="text-sm text-slate2 mb-2">
                {{ $verification->retry_reason }}
                {{ __('cit_verification_en_cours.plutot_que_de_vous_rendre_un_verdict') }}
                @if($relancable)
                  {{ __('cit_verification_en_cours.des_maintenant') }}
                @else
                  {{ trans_choice('cit_verification_en_cours.dans_n_minutes', $minutes, ['nombre' => $minutes]) }}
                @endif
              </p>

              {{--
                | CE QUE COÛTE L'ATTENTE — dit en clair.
                |
                | Cette phrase avait disparu de l'écran lors de la refonte de la
                | barre de progression, pendant que ses clés de langue et le test
                | qui l'exige restaient en place. L'écran annonçait donc un
                | report sans jamais dire ce qu'il advenait des unités déjà
                | prélevées, ce qui est la seule question que se pose quelqu'un
                | qui a payé. La directive du parcours citoyen l'impose
                | explicitement : « Vos unités restent protégées ».
              --}}
              <p class="text-sm text-slate2 mb-2">
                {{ __('cit_verification_en_cours.rien_n_est_perdu_la_reprise_est') }}
                @if($verification->cost_units > 1)
                  {{ __('cit_verification_en_cours.vos') }}
                  <strong class="text-ink">{{ $verification->cost_units }} {{ __('cit_verification_en_cours.unites') }}</strong>
                  {{ __('cit_verification_en_cours.vous_seront_rendues') }}
                @else
                  {{ __('cit_verification_en_cours.votre') }}
                  <strong class="text-ink">{{ __('cit_verification_en_cours.unite') }}</strong>
                  {{ __('cit_verification_en_cours.vous_sera_rendue') }}
                @endif
              </p>
              <div class="flex flex-wrap items-center gap-3 mt-3">
                @if($relancable)
                  <button type="button" wire:click="relancer" wire:loading.attr="disabled"
                          class="inline-flex items-center gap-2 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2 hover:bg-primary-dark">
                    {!! __('cit_verification_en_cours.refresh_relancer_maintenant_relance') !!}
                  </button>
                @endif
                <a href="{{ route('citizen.verifications') }}" class="text-sm font-semibold text-primary">
                  {{ __('cit_verification_en_cours.voir_mes_verifications') }}
                </a>
              </div>
            </div>
          </div>
        </div>
      @endif

      {{-- Alerte seulement si dépassement réel prolongé --}}
      @if($bloquee)
        <div class="mt-6 pt-5 border-t border-line">
          <p class="text-sm text-vfalse font-bold mb-1">{{ __('cit_verification_en_cours.l_analyse_prend_plus_de_temps_que') }}</p>
          <p class="text-sm text-slate2 mb-3">{{ __('cit_verification_en_cours.un_moteur_d_analyse_ne_repond_peut') }}</p>
          <a href="{{ route('citizen.verifications') }}" class="text-sm font-semibold text-primary">
            {{ __('cit_verification_en_cours.voir_mes_verifications') }}
          </a>
        </div>
      @endif

    </div>

    <p class="text-xs text-slate2 text-center mt-4 mb-0">
      @if(! $reprise)
        {{ __('cit_verification_en_cours.cette_page_se_met_a_jour_toute') }}
      @else
        {{ __('cit_verification_en_cours.nous_reprenons_l_analyse_sans_que_vous') }}
      @endif
    </p>
  </div>
</div>