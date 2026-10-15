{{--
  | Fiche de cours — transposition de compte-user/cours.html.
  |
  | Sept onglets, et un bouton principal qui change de sens selon l'état.
  | Structure, classes et libellés repris du prototype.
--}}
<div>

  <nav class="text-xs text-slate2 mb-3" aria-label="{{ __('cit_cours.fil_d_ariane') }}">
    <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
    <span class="text-line" aria-hidden="true">/</span>
    <a href="{{ route('citizen.catalogue') }}" class="hover:text-primary">{{ __('cit_cours.catalogue') }}</a>
    <span class="text-line" aria-hidden="true">/</span>
    <span class="text-ink font-medium">{{ $cours->title }}</span>
  </nav>

  {{-- ═══════════════════════════════════════════════════ en-tête du cours --}}
  <div class="card overflow-hidden mb-6">
    <div class="grid lg:grid-cols-2">
      <img src="{{ asset($cours->cover_path ?? 'assets/images/courses/placeholder-a.svg') }}"
           alt="{{ $cours->title }}" class="w-full h-56 lg:h-full object-cover">

      <div class="p-6 lg:p-8">
        <p class="text-xs font-bold text-primary uppercase tracking-wide mb-2">{{ $cours->category }}</p>
        <h1 class="text-2xl font-display font-bold text-ink leading-tight mb-2">{{ $cours->title }}</h1>

        {{-- Ni note sans avis, ni audience sans inscrits. --}}
        <p class="text-sm text-slate2 mb-3">
          @if($cours->reviews_count > 0)
            ★ {{ number_format($cours->rating, 1, ',', ' ') }} ·
          @endif
          @if($cours->students_count > 0)
            {{ number_format($cours->students_count, 0, ',', ' ') }} {{ trans_choice('cit_cours.apprenant_pluriel', $cours->students_count) }} ·
          @endif
          {{ $cours->level->libelle() }}
          · {{ $cours->duration_label }}
          @if($cours->delivers_certificate) · {{ __('cit_cours.certificat') }} @endif
        </p>

        @if($inscription)
          <div class="flex items-center gap-3 mb-4">
            <div class="progress flex-1"><span style="width:{{ $avancement }}%"></span></div>
            <span class="text-sm font-bold text-primary">{{ $avancement }}%</span>
          </div>
        @else
          <p class="text-sm text-slate2 mb-4">{{ $cours->description }}</p>
        @endif

        <div class="flex flex-wrap items-center gap-3">
          {{-- Le bouton dit CE QUI VA SE PASSER. « Commencer » sur un cours à
               moitié fait ferait croire à une progression perdue. --}}
          @if($certificat)
            <a href="{{ route('citizen.certificate', $certificat) }}"
               class="inline-flex items-center gap-2 rounded-xl bg-ink text-white font-semibold px-6 py-3">
              {{ __('cit_cours.voir_mon_certificat') }}
            </a>
          @elseif($prochaine)
            <a href="{{ route('citizen.lesson', ['course' => $cours, 'lesson' => $prochaine]) }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark">
              {{ $inscription->libelleDeReprise() }}
            </a>
          @elseif($inscription)
            <button type="button" wire:click="choisirOnglet('curriculum')"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark">{{ __('cit_cours.voir_le_curriculum') }}</button>
          @elseif($cours->estGratuit())
            <button type="button" wire:click="ouvrirModale"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark">{{ __('cit_cours.commencer_gratuitement') }}</button>
          @else
            <button type="button" wire:click="ouvrirModale"
                    class="inline-flex items-center gap-2 rounded-xl bg-xp text-white font-semibold px-6 py-3 hover:opacity-90">
              {{ __('cit_cours.acheter_avec_mes_unites') }} · {{ $cours->cost_units }}
            </button>
          @endif

          @if($cours->estGratuit())
            <span class="vbadge vbadge-true">{{ __('cit_cours.gratuit_majuscules') }}</span>
          @else
            <span class="vbadge" style="background:rgba(124,58,237,.12);color:#7C3AED">✦ {{ $cours->cost_units }} {{ __('cit_cours.unites') }}</span>
          @endif

          {{-- La prime versée à 100 %. Elle etait enregistree depuis le lot 14
               et AUCUN écran ne l'annonçait : le quiz affiche la sienne, le
               badge la sienne, le cours restait muet. Une récompense qu'on ne
               promet pas ne motive personne. --}}
          @if($cours->xp_reward > 0)
            <span class="vbadge" style="background:rgba(245,158,11,.14);color:#B45309">
              +{{ $cours->xp_reward }} {{ __('cit_cours.xp_a_la_fin') }}
            </span>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ onglets --}}
  @php($onglets = [
    'apercu' => __('cit_cours.onglet_apercu'),
    'curriculum' => __('cit_cours.onglet_curriculum'),
    'instructeur' => __('cit_cours.onglet_instructeur'),
    'avis' => __('cit_cours.onglet_avis'),
    'quiz' => __('cit_cours.onglet_quiz'),
    'exercices' => __('cit_cours.onglet_exercices'),
    'faq' => __('cit_cours.onglet_faq'),
  ])

  <div class="flex gap-1.5 overflow-x-auto pb-1 mb-4" role="tablist" aria-label="{{ __('cit_cours.sections_du_cours') }}">
    @foreach($onglets as $cle => $libelle)
      <button type="button" wire:click="choisirOnglet('{{ $cle }}')" wire:key="onglet-{{ $cle }}"
              class="pill-tab whitespace-nowrap {{ $onglet === $cle ? 'is-active' : '' }}"
              role="tab" aria-selected="{{ $onglet === $cle ? 'true' : 'false' }}">{{ $libelle }}</button>
    @endforeach
  </div>

  {{-- ───────────────────────────────────────────────────────────── aperçu --}}
  @if($onglet === 'apercu')
    <div class="card p-6">
      <h2 class="text-lg font-display font-bold text-ink mb-2">{{ __('cit_cours.a_propos_du_cours') }}</h2>
      <p class="text-sm mb-4">{{ $cours->description }}</p>

      {{-- Les prérequis étaient saisis par le mentor depuis le lot 14 et ne
           s'affichaient nulle part : les objectifs, si. Quelqu'un découvrait
           donc à la troisième leçon qu'il lui manquait une base. --}}
      @if($cours->prerequisites)
        <h3 class="text-base font-display font-bold text-ink mb-2">{{ __('cit_cours.prerequis') }}</h3>
        <ul class="grid sm:grid-cols-2 gap-2 text-sm mb-4">
          @foreach($cours->prerequisites as $attendu)
            <li class="flex gap-2">
              <span class="material-symbols-rounded text-slate2 !text-[18px]" aria-hidden="true">radio_button_unchecked</span>
              {{ $attendu }}
            </li>
          @endforeach
        </ul>
      @endif

      @if($cours->outcomes)
        <h3 class="text-base font-display font-bold text-ink mb-2">{{ __('cit_cours.ce_que_vous_allez_apprendre') }}</h3>
        <ul class="grid sm:grid-cols-2 gap-2 text-sm">
          @foreach($cours->outcomes as $acquis)
            <li class="flex gap-2">
              <span class="material-symbols-rounded text-vtrue !text-[18px]" aria-hidden="true">check_circle</span>
              {{ $acquis }}
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  @endif

  {{-- ───────────────────────────────────────────────────────── curriculum --}}
  @if($onglet === 'curriculum')
    <div class="card p-6" id="curriculum">
      <h2 class="text-lg font-display font-bold text-ink mb-2">{{ __('cit_cours.curriculum') }}</h2>

      @unless($inscription)
        <p class="text-sm text-slate2 mb-4">{{ __('cit_cours.inscrivez_vous_pour_ouvrir_les_lecons_le') }}</p>
      @endunless

      <ol class="divide-y divide-line">
        @foreach($lecons as $lecon)
          @php($acquise = $this->leconAcquise($lecon))
          @php($ouverte = $this->leconOuverte($lecon))
          <li class="flex items-center gap-3 py-3" wire:key="lecon-{{ $lecon->id }}">
            @if($acquise)
              <span class="material-symbols-rounded text-vtrue !text-[20px]" aria-hidden="true">check_circle</span>
            @elseif($ouverte)
              <span class="material-symbols-rounded text-primary !text-[20px]" aria-hidden="true">play_circle</span>
            @else
              <span class="material-symbols-rounded text-slate2 !text-[20px]" aria-hidden="true">lock</span>
            @endif

            @php($etiquette = __('cit_cours.lecon').' '.str_pad((string) $lecon->position, 2, '0', STR_PAD_LEFT).' — '.$lecon->title)

            @if($ouverte)
              <a href="{{ route('citizen.lesson', ['course' => $cours, 'lesson' => $lecon]) }}"
                 class="flex-1 text-sm font-medium text-ink hover:text-primary">{{ $etiquette }}</a>
            @else
              <span class="flex-1 text-sm text-slate2">{{ $etiquette }}</span>
            @endif

            <span class="text-xs text-slate2">{{ $lecon->minutes }} min</span>
          </li>
        @endforeach

        @foreach($quiz as $unQuiz)
          @php($reussi = $this->quizReussi($unQuiz))
          @php($ouvert = $this->quizOuvert($unQuiz))
          <li class="flex items-center gap-3 py-3" wire:key="quiz-{{ $unQuiz->id }}">
            @if($reussi)
              <span class="material-symbols-rounded text-vtrue !text-[20px]" aria-hidden="true">check_circle</span>
            @elseif($ouvert)
              <span class="material-symbols-rounded text-primary !text-[20px]" aria-hidden="true">quiz</span>
            @else
              <span class="material-symbols-rounded text-slate2 !text-[20px]" aria-hidden="true">lock</span>
            @endif

            @if($ouvert && ! $reussi)
              <a href="{{ route('citizen.quiz.take', ['course' => $cours, 'quiz' => $unQuiz]) }}"
                 class="flex-1 text-sm font-medium text-ink hover:text-primary">{{ $unQuiz->title }}</a>
            @else
              <span class="flex-1 text-sm {{ $reussi ? 'text-ink font-medium' : 'text-slate2' }}">{{ $unQuiz->title }}</span>
            @endif

            <span class="text-xs text-slate2">{{ $unQuiz->minutes }} min · {{ __('cit_cours.seuil') }} {{ $unQuiz->pass_score }} %</span>
          </li>
        @endforeach

        @foreach($exercices as $exercice)
          @php($remis = $this->exerciceRemis($exercice))
          <li class="flex items-center gap-3 py-3" wire:key="exo-{{ $exercice->id }}">
            @if($remis)
              <span class="material-symbols-rounded text-vtrue !text-[20px]" aria-hidden="true">check_circle</span>
            @else
              <span class="material-symbols-rounded {{ $inscription ? 'text-primary' : 'text-slate2' }} !text-[20px]" aria-hidden="true">assignment</span>
            @endif

            @if($inscription && ! $remis)
              <a href="{{ route('citizen.exercises') }}?exercice={{ $exercice->uuid }}"
                 class="flex-1 text-sm font-medium text-ink hover:text-primary">{{ __('cit_cours.exercice') }} — {{ $exercice->title }}</a>
            @else
              <span class="flex-1 text-sm {{ $remis ? 'text-ink font-medium' : 'text-slate2' }}">
                {{ __('cit_cours.exercice') }} — {{ $exercice->title }}{{ $remis ? ' — '.__('cit_cours.remis') : '' }}
              </span>
            @endif

            <span class="text-xs text-slate2">{{ $exercice->minutes }} min</span>
          </li>
        @endforeach
      </ol>
    </div>
  @endif

  {{-- ──────────────────────────────────────────────────────── instructeur --}}
  @if($onglet === 'instructeur')
    <div class="card p-6">
      @php($nom = $cours->instructor?->name ?? __('cit_cours.equipe_verif_all'))
      <div class="flex items-center gap-4">
        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-forest text-white font-display font-bold text-xl">
          {{ Str::of($nom)->explode(' ')->map(fn ($mot) => Str::substr($mot, 0, 1))->take(2)->implode('') }}
        </span>
        <div>
          <h2 class="text-lg font-display font-bold text-ink mb-0">{{ $nom }}</h2>
          <p class="text-sm text-slate2 mb-0">{{ __('cit_cours.equipe_verif_all') }}</p>
        </div>
      </div>
      <p class="text-sm mt-4 mb-0">
        {{ __('cit_cours.membre_de_l_equipe_verif_all') }} {{ Str::before($nom, ' ') }} {{ __('cit_cours.accompagne_les_citoyens_dans_l_apprentissage') }}
      </p>
    </div>
  @endif

  {{-- ─────────────────────────────────────────────────────────────── avis --}}
  @if($onglet === 'avis')
    <div class="card p-6">
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_cours.avis_des_apprenants') }}</h2>
      @if($cours->reviews_count > 0)
        <p class="text-3xl font-display font-black text-ink mb-4">
          {{ number_format($cours->rating, 1, ',', ' ') }}
          <span class="text-base font-normal text-slate2">/ 5</span>
          <span class="text-sm font-normal text-slate2">
            · {{ $cours->reviews_count }} {{ trans_choice('cit_cours.avis_pluriel', $cours->reviews_count) }}
          </span>
        </p>
      @else
        {{-- « 4,8 / 5 · 0 avis » était une contradiction à l'écran. --}}
        <p class="text-sm text-slate2 mb-4">{{ __('cit_cours.ce_cours_n_a_pas_encore_ete') }}</p>
      @endif

      <div class="rounded-2xl border border-line bg-canvas p-5 mb-4">
        <p class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_cours.donnez_votre_avis_sur_ce_cours') }}</p>

        <div class="flex items-center gap-1 mb-3" role="radiogroup" aria-label="{{ __('cit_cours.votre_note_sur_5') }}">
          @for($etoile = 1; $etoile <= 5; $etoile++)
            <button type="button" wire:click="noter({{ $etoile }})" wire:key="etoile-{{ $etoile }}"
                    class="w-11 h-11 inline-flex items-center justify-center text-2xl transition-colors {{ $note >= $etoile ? 'text-vinvest' : 'text-line hover:text-vinvest' }}"
                    role="radio" aria-checked="{{ $note === $etoile ? 'true' : 'false' }}"
                    aria-label="{{ $etoile }} {{ trans_choice('cit_cours.etoile_pluriel', $etoile) }}">★</button>
          @endfor
        </div>
        @error('note') <p class="text-xs text-vfalse mb-2">{{ $message }}</p> @enderror

        <label class="sr-only" for="avis-commentaire">{{ __('cit_cours.votre_commentaire') }}</label>
        <textarea id="avis-commentaire" wire:model="commentaire" rows="3" maxlength="1000"
                  placeholder="{{ __('cit_cours.ce_que_ce_cours_vous_a_apporte') }}"
                  class="w-full bg-surface border border-line rounded-xl px-3 py-2.5 text-sm outline-none focus:border-primary"></textarea>
        @error('commentaire') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror

        <button type="button" wire:click="publierAvis"
                class="mt-3 inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">{{ __('cit_cours.publier_mon_avis') }}</button>
      </div>

      @forelse($avis as $unAvis)
        <div class="border-t border-line pt-4 mt-4 first:border-0 first:pt-0 first:mt-0" wire:key="avis-{{ $unAvis->id }}">
          <p class="text-sm font-bold text-ink mb-0.5">{{ $unAvis->user?->name ?? __('cit_cours.apprenant') }}</p>
          <p class="text-sm text-vinvest mb-1" aria-label="{{ $unAvis->rating }} {{ __('cit_cours.sur_5') }}">{{ str_repeat('★', $unAvis->rating) }}<span class="text-line">{{ str_repeat('★', 5 - $unAvis->rating) }}</span></p>
          <p class="text-sm text-slate2 mb-0">{{ $unAvis->comment }}</p>
        </div>
      @empty
        <p class="text-sm text-slate2 mb-0">{{ __('cit_cours.aucun_avis_pour_l_instant_le_votre') }}</p>
      @endforelse
    </div>
  @endif

  {{-- ─────────────────────────────────────────────────────────────── quiz --}}
  @if($onglet === 'quiz')
    <div class="card p-6">
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_cours.quiz_du_cours') }}</h2>
      @forelse($quiz as $unQuiz)
        <div class="flex flex-wrap items-center gap-3 py-3 border-t border-line first:border-0" wire:key="qz-{{ $unQuiz->id }}">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-ink mb-0.5">{{ $unQuiz->title }}</p>
            <p class="text-xs text-slate2 mb-0">
              {{ $unQuiz->questions->count() }} {{ __('cit_cours.questions') }} · {{ $unQuiz->minutes }} min
              · {{ __('cit_cours.seuil') }} {{ $unQuiz->pass_score }} % · {{ $unQuiz->xp_reward }} XP
            </p>
          </div>
          @if($this->quizReussi($unQuiz))
            <span class="vbadge vbadge-true">{{ __('cit_cours.reussi') }}</span>
          @elseif($this->quizOuvert($unQuiz))
            <a href="{{ route('citizen.quiz.take', ['course' => $cours, 'quiz' => $unQuiz]) }}"
               class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark min-h-[44px]">
              {{ __('cit_cours.passer_le_quiz') }}
            </a>
          @else
            <span class="text-xs text-slate2">{{ __('cit_cours.terminez_les_lecons_pour_l_ouvrir') }}</span>
          @endif
        </div>
      @empty
        <p class="text-sm text-slate2 mb-0">{{ __('cit_cours.ce_cours_ne_comporte_pas_de_quiz') }}</p>
      @endforelse
    </div>
  @endif

  {{-- ────────────────────────────────────────────────────────── exercices --}}
  @if($onglet === 'exercices')
    <div class="card p-6">
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_cours.exercices_pratiques') }}</h2>
      @forelse($exercices as $exercice)
        <div class="py-3 border-t border-line first:border-0" wire:key="ex-{{ $exercice->id }}">
          <p class="text-sm font-medium text-ink mb-0.5">{{ $exercice->title }}</p>
          <p class="text-sm text-slate2 mb-2">{{ $exercice->description }}</p>
          <p class="text-xs text-slate2 mb-0">
            {{ $exercice->minutes }} min
            @if($this->exerciceRemis($exercice)) · <span class="text-vtrue font-semibold">{{ __('cit_cours.remis') }}</span> @endif
          </p>
        </div>
      @empty
        <p class="text-sm text-slate2 mb-0">{{ __('cit_cours.ce_cours_ne_comporte_pas_d_exercice') }}</p>
      @endforelse
    </div>
  @endif

  {{-- ──────────────────────────────────────────────────────────────── FAQ --}}
  @if($onglet === 'faq')
    <div class="card p-6">
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_cours.questions_frequentes') }}</h2>
      <div class="divide-y divide-line">
        <details class="py-3">
          <summary class="text-sm font-medium text-ink cursor-pointer">{{ __('cit_cours.le_certificat_est_il_verifiable') }}</summary>
          <p class="text-sm text-slate2 mt-2 mb-0">{{ __('cit_cours.oui_chaque_certificat_porte_un_code_que') }}</p>
        </details>
        <details class="py-3">
          <summary class="text-sm font-medium text-ink cursor-pointer">{{ __('cit_cours.puis_je_reprendre_un_quiz_rate') }}</summary>
          <p class="text-sm text-slate2 mt-2 mb-0">{{ __('cit_cours.autant_de_fois_que_necessaire_chaque_tentative') }}</p>
        </details>
        <details class="py-3">
          <summary class="text-sm font-medium text-ink cursor-pointer">{{ __('cit_cours.les_unites_depensees_sont_elles_rendues') }}</summary>
          <p class="text-sm text-slate2 mt-2 mb-0">{{ __('cit_cours.l_acces_a_un_cours_est_definitif') }}</p>
        </details>
      </div>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════ modales --}}
  @if($modale === 'gratuit')
    <div class="modal-backdrop is-open" role="dialog" aria-modal="true" aria-labelledby="mf-title">
      <div class="modal p-6">
        <h4 id="mf-title" class="font-display font-bold text-ink text-lg mb-2">{{ __('cit_cours.inscription_gratuite') }}</h4>
        <p class="text-sm text-slate2 mb-4">{{ __('cit_cours.vous_etes_sur_le_point_de_rejoindre') }}</p>
        <ul class="space-y-1.5 text-sm mb-5">
          <li class="flex gap-2">{!! __('cit_cours.check_circle_acces_immediat') !!}</li>
          <li class="flex gap-2">{!! __('cit_cours.check_circle_progression_sauvegardee') !!}</li>
          <li class="flex gap-2">{!! __('cit_cours.check_circle_certificat_a_100') !!}</li>
        </ul>
        <div class="flex gap-2 justify-end">
          <button type="button" wire:click="fermerModale" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_cours.annuler') }}</button>
          <button type="button" wire:click="inscrire" wire:loading.attr="disabled"
                  class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark disabled:opacity-50">{{ __('cit_cours.s_inscrire') }}</button>
        </div>
      </div>
    </div>
  @endif

  @if($modale === 'premium')
    @php($solde = $this->utilisateur()->units)
    <div class="modal-backdrop is-open" role="dialog" aria-modal="true" aria-labelledby="mb-title">
      <div class="modal p-6">
        <h4 id="mb-title" class="font-display font-bold text-ink text-lg mb-2">{{ __('cit_cours.debloquer_ce_cours') }}</h4>
        <p class="text-sm text-slate2 mb-4">
          {{ __('cit_cours.utiliser') }} <strong class="text-ink">{{ $cours->cost_units }}</strong> {{ __('cit_cours.unites_pour_acceder_a_ce_cours') }}
        </p>

        {{-- Le décompte AVANT de confirmer : personne ne doit découvrir son
             nouveau solde après coup. --}}
        <div class="rounded-xl bg-canvas border border-line divide-y divide-line text-sm mb-5">
          <p class="flex justify-between px-4 py-2.5 mb-0">{{ __('cit_cours.solde_actuel') }} <strong>{{ $solde }}</strong></p>
          <p class="flex justify-between px-4 py-2.5 mb-0">{{ __('cit_cours.cout_du_cours') }} <strong class="text-vfalse">−{{ $cours->cost_units }}</strong></p>
          <p class="flex justify-between px-4 py-2.5 mb-0">{{ __('cit_cours.solde_apres_achat') }} <strong class="text-primary">{{ $solde - $cours->cost_units }}</strong></p>
        </div>

        @if($solde < $cours->cost_units)
          <p class="text-sm text-vfalse mb-4">
            {{ __('cit_cours.il_vous_manque') }} {{ $cours->cost_units - $solde }} {{ trans_choice('cit_cours.unite_pluriel', $cours->cost_units - $solde) }}.
            <a href="{{ route('citizen.units') }}" class="font-semibold text-primary">{{ __('cit_cours.recharger') }}</a>
          </p>
        @endif

        <div class="flex gap-2 justify-end">
          <button type="button" wire:click="fermerModale" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_cours.annuler') }}</button>
          <button type="button" wire:click="inscrire" wire:loading.attr="disabled" @disabled($solde < $cours->cost_units) class="rounded-xl bg-xp text-white px-5 py-2.5 text-sm font-semibold hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"> {{ __('cit_cours.confirmer_l_achat') }}</button>
        </div>
      </div>
    </div>
  @endif

</div>
