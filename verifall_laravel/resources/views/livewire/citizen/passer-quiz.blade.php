{{--
  | Quiz chronométré — transposition de compte-user/quiz.html.
  |
  | Une question à la fois, un compte à rebours, une correction commentée.
  | L'explication est le cœur pédagogique : un quiz qui note sans expliquer ne
  | fait que sanctionner.
--}}
<div class="max-w-2xl mx-auto">

  {{-- ═══════════════════════════ le quiz ne s'ouvre pas, et on dit pourquoi

       Remplace deux `abort(403)` nus. La règle qu'ils appliquaient est juste —
       un quiz vérifie ce qui a été enseigné — mais une page d'erreur sans
       phrase ne disait ni ce qui manquait, ni où aller. Le serveur connaît la
       leçon qui bloque : il n'y a aucune raison de la garder pour lui.        --}}
  @if($blocage === 'lecons-restantes')
    <nav class="text-xs text-slate2 mb-3" aria-label="{{ __('cit_passer_quiz.fil_d_ariane') }}">
      <a href="{{ route('citizen.course', $cours) }}" class="hover:text-primary">{{ $cours->title }}</a>
      <span class="text-line" aria-hidden="true">/</span>
      <span class="text-ink font-medium">{{ $quiz->title }}</span>
    </nav>

    <div class="card p-8 text-center">
      <span class="material-symbols-rounded text-vdoubt !text-[52px] mb-3" aria-hidden="true">lock</span>
      <h1 class="text-2xl font-display font-bold text-ink mb-2">{{ __('cit_passer_quiz.quiz_pas_encore_ouvert') }}</h1>
      <p class="text-sm text-slate2 mb-5">{{ __('cit_passer_quiz.quiz_lecons_a_terminer') }}</p>

      @if($leconManquante)
        <p class="text-sm text-slate2 mb-1">{{ __('cit_passer_quiz.quiz_prochaine_lecon') }}</p>
        <p class="font-display font-bold text-ink mb-5">{{ $leconManquante->title }}</p>
      @endif

      <div class="flex gap-2 justify-center flex-wrap">
        @if($leconManquante)
          <a href="{{ route('citizen.lesson', ['course' => $cours, 'lesson' => $leconManquante]) }}"
             class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
            <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">play_circle</span>
            {{ __('cit_passer_quiz.quiz_reprendre_la_lecon') }}
          </a>
        @endif
        <a href="{{ route('citizen.course', $cours) }}"
           class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">
          {{ __('cit_passer_quiz.retour_au_cours') }}
        </a>
      </div>
    </div>

  {{-- ══════════════════════════════ pas inscrit : on propose de s'inscrire --}}
  @elseif($blocage === 'non-inscrit')
    <div class="card p-8 text-center">
      <span class="material-symbols-rounded text-vdoubt !text-[52px] mb-3" aria-hidden="true">school</span>
      <h1 class="text-2xl font-display font-bold text-ink mb-2">{{ __('cit_passer_quiz.quiz_reserve_aux_inscrits') }}</h1>
      <p class="text-sm text-slate2 mb-5">{{ __('cit_passer_quiz.quiz_inscrivez_vous_au_cours') }}</p>
      <a href="{{ route('citizen.course', $cours) }}"
         class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
        {{ __('cit_passer_quiz.quiz_voir_le_cours') }}
      </a>
    </div>

  {{-- ══════════════════════════════════ déjà réussi : on ne rejoue pas --}}
  @elseif($dejaReussi && ! $resultat)
    <div class="card p-8 text-center">
      <span class="material-symbols-rounded text-vtrue !text-[52px] mb-3" aria-hidden="true">verified</span>
      <h1 class="text-2xl font-display font-bold text-ink mb-2">{{ __('cit_passer_quiz.quiz_deja_reussi') }}</h1>
      <p class="text-sm text-slate2 mb-5">
        {{ __('cit_passer_quiz.vous_avez_valide_une_reussite_n_est', ['titre' => $quiz->title]) }}
      </p>
      <div class="flex gap-2 justify-center flex-wrap">
        <a href="{{ route('citizen.course', $cours) }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">
          {{ __('cit_passer_quiz.retour_au_cours') }}
        </a>
        <button type="button" wire:click="reessayer"
                class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas">{{ __('cit_passer_quiz.refaire_pour_reviser') }}</button>
      </div>
    </div>

  {{-- ═════════════════════════════════════════════════════════ résultat --}}
  @elseif($resultat)
    <div class="card p-8 text-center">
      @if($resultat->passed)
        <span class="material-symbols-rounded text-vtrue !text-[56px] mb-2" aria-hidden="true">celebration</span>
        <h1 class="text-2xl font-display font-black text-ink mb-1">{{ __('cit_passer_quiz.quiz_reussi') }}</h1>
      @else
        <span class="material-symbols-rounded text-vdoubt !text-[56px] mb-2" aria-hidden="true">refresh</span>
        <h1 class="text-2xl font-display font-black text-ink mb-1">{{ __('cit_passer_quiz.pas_encore') }}</h1>
      @endif

      <p class="text-4xl font-display font-black {{ $resultat->passed ? 'text-vtrue' : 'text-ink' }} mb-1">{{ $resultat->score }} %</p>
      <p class="text-sm text-slate2 mb-1">{{ __('cit_passer_quiz.seuil_de_reussite') }} {{ $quiz->pass_score }} %</p>

      @if($resultat->passed && $quiz->xp_reward > 0)
        <p class="text-sm font-bold text-xp mb-6">+{{ $quiz->xp_reward }} XP</p>
      @else
        <p class="mb-6"></p>
      @endif

      <div class="flex gap-2 justify-center flex-wrap mb-8">
        @if($certificat)
          <a href="{{ route('citizen.certificate', $certificat) }}"
             class="rounded-xl bg-ink text-white text-sm font-semibold px-5 py-2.5">🎓 {{ __('cit_passer_quiz.voir_mon_certificat') }}</a>
        @endif
        @unless($resultat->passed)
          <button type="button" wire:click="reessayer"
                  class="rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">{{ __('cit_passer_quiz.reessayer') }}</button>
        @endunless
        <a href="{{ route('citizen.course', $cours) }}"
           class="rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas">{{ __('cit_passer_quiz.retour_au_cours') }}</a>
      </div>

      {{-- La correction commentée : c'est elle qui transforme une erreur en
           leçon. Sans elle, un score n'apprend rien. --}}
      <div class="text-left border-t border-line pt-6">
        <h2 class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_passer_quiz.correction') }}</h2>

        @foreach($questions as $numero => $uneQuestion)
          @php($donnee = $resultat->given_answers[$uneQuestion->id] ?? null)
          @php($juste = (bool) ($donnee['correct'] ?? false))
          @php($choisie = $uneQuestion->answers->firstWhere('id', $donnee['answer_id'] ?? null))
          @php($bonne = $uneQuestion->answers->firstWhere('is_correct', true))

          <div class="py-4 border-t border-line first:border-0 first:pt-0" wire:key="corr-{{ $uneQuestion->id }}">
            <p class="text-sm font-bold text-ink mb-2">
              <span class="text-slate2">{{ $numero + 1 }}.</span> {{ $uneQuestion->prompt }}
            </p>

            <p class="text-sm mb-1 flex items-start gap-2">
              @if($juste)
                <span class="material-symbols-rounded text-vtrue !text-[18px]" aria-hidden="true">check_circle</span>
                <span class="text-vtrue">{{ $choisie?->label }}</span>
              @else
                <span class="material-symbols-rounded text-vfalse !text-[18px]" aria-hidden="true">cancel</span>
                <span class="text-vfalse">{{ $choisie?->label ?? __('cit_passer_quiz.sans_reponse') }}</span>
              @endif
            </p>

            @unless($juste)
              <p class="text-sm text-slate2 mb-1 pl-6">{{ __('cit_passer_quiz.bonne_reponse') }} <strong class="text-ink">{{ $bonne?->label }}</strong></p>
            @endunless

            @if($uneQuestion->explanation)
              <p class="text-sm text-slate2 mb-0 pl-6 italic">{{ $uneQuestion->explanation }}</p>
            @endif
          </div>
        @endforeach
      </div>
    </div>

  {{-- ═══════════════════════════════════════════════ le quiz en cours --}}
  @elseif($question)
    <div x-data="{
            restant: {{ $secondes }},
            tick: null,
            init() {
                this.tick = setInterval(() => {
                    if (this.restant <= 0) { clearInterval(this.tick); $wire.soumettre(); return }
                    this.restant--;
                }, 1000);
            },
            destroy() { clearInterval(this.tick) },
            get horloge() {
                const m = Math.floor(this.restant / 60), s = this.restant % 60;
                return m + ':' + String(s).padStart(2, '0');
            },
            get urgent() { return this.restant <= 60 },
         }">

      <div class="card p-4 mb-4 flex items-center justify-between gap-3">
        <div class="flex-1 min-w-0">
          <p class="text-xs font-bold text-primary uppercase tracking-wide mb-0.5">{{ $quiz->title }}</p>
          <p class="text-sm text-ink font-medium mb-1">{{ __('cit_passer_quiz.question') }} {{ $index + 1 }} / {{ $total }}</p>
          <div class="progress" role="progressbar" aria-valuenow="{{ (int) round($index / max(1, $total) * 100) }}" aria-valuemin="0" aria-valuemax="100">
            <span style="width:{{ (int) round($index / max(1, $total) * 100) }}%"></span>
          </div>
        </div>
        <span class="shrink-0 inline-flex items-center gap-1.5 rounded-xl px-3 py-2 font-mono font-bold text-sm"
              x-bind:class="urgent ? 'bg-vfalse/10 text-vfalse' : 'bg-canvas text-ink'"
              aria-live="polite">
          <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">timer</span>
          <span x-text="horloge">—</span>
        </span>
      </div>

      <div class="card p-6">
        <h2 class="text-lg font-display font-bold text-ink mb-4">{{ $question->prompt }}</h2>

        <div class="space-y-2 mb-6" role="radiogroup" aria-label="{{ __('cit_passer_quiz.reponses_proposees') }}">
          @foreach($question->answers as $reponse)
            @php($choisie = ($reponses[$question->id] ?? null) === $reponse->id)
            <button type="button" wire:click="choisir({{ $question->id }}, {{ $reponse->id }})"
                    wire:key="rep-{{ $reponse->id }}"
                    role="radio" aria-checked="{{ $choisie ? 'true' : 'false' }}"
                    class="w-full flex items-start gap-2.5 p-3.5 rounded-xl border text-left transition-colors {{ $choisie ? 'border-primary bg-primary-soft' : 'border-line hover:border-primary' }}">
              <span class="mt-0.5 shrink-0 w-4 h-4 rounded-full border-2 {{ $choisie ? 'border-primary bg-primary' : 'border-line' }}" aria-hidden="true"></span>
              <span class="text-sm">{{ $reponse->label }}</span>
            </button>
          @endforeach
        </div>

        <div class="flex items-center justify-between">
          @if($index > 0)
            <button type="button" wire:click="precedent"
                    class="rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas">{{ __('cit_passer_quiz.precedent') }}</button>
          @else
            <span></span>
          @endif

          <button type="button" wire:click="suivant" wire:loading.attr="disabled"
                  class="rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark disabled:opacity-50">
            {{ $index === $total - 1 ? __('cit_passer_quiz.soumettre_le_quiz') : __('cit_passer_quiz.question_suivante') }}
          </button>
        </div>
      </div>

      <p class="text-xs text-slate2 text-center mt-4 mb-0">{{ __('cit_passer_quiz.a_la_fin_du_temps_le_quiz') }}</p>
    </div>

  @else
    <div class="card p-8 text-center">
      <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_passer_quiz.ce_quiz_ne_contient_aucune_question') }}</p>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_passer_quiz.signalez_le_un_quiz_vide_ne_devrait') }}</p>
      <a href="{{ route('citizen.course', $cours) }}" class="text-sm font-semibold text-primary">{{ __('cit_passer_quiz.retour_au_cours') }}</a>
    </div>
  @endif

</div>
