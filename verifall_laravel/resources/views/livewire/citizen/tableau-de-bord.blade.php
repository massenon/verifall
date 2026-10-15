{{--
  | Tableau de bord — transposition de compte-user/dashboard.html.
  |
  | L'ordre des blocs est celui du prototype et ne bouge pas : priorités,
  | offre du mois, reprise, activité, vérifications récentes ; en colonne de
  | droite, Live Class, forum, unités, récompenses.
  |
  | Les chiffres sont TOUS mesurés. Là où le prototype affichait une valeur de
  | démonstration, on affiche ce que la base dit — y compris zéro.
--}}
<div>

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_tableau_de_bord.bonjour') }} {{ $utilisateur->first_name ?: $utilisateur->name }} 👋</h1>
      <p class="text-slate2 mt-1 mb-0">{{ __('cit_tableau_de_bord.votre_boussole_de_l_information_est_active') }} 🔥 <strong>{{ $utilisateur->streak_days }}</strong> {{ trans_choice('cit_tableau_de_bord.compte_jours', (int) $utilisateur->streak_days) }}.<span class="mt-1 flex items-center gap-1.5 text-xs text-slate2"><span class="w-2 h-2 rounded-full {{ $serieVivante ? 'bg-vtrue' : 'bg-line' }} inline-block" aria-hidden="true"></span>{{ $serieVivante ? __('cit_tableau_de_bord.serie_en_cours_revenez_demain') : __('cit_tableau_de_bord.serie_interrompue_une_verification_la_relance') }}</span></p>
    </div><div class="flex flex-wrap items-center gap-2"><a href="{{ route('citizen.verify') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brandred text-white text-sm font-semibold px-4 py-2.5 hover:opacity-90 min-h-[44px]"><span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span> {{ __('cit_tableau_de_bord.verifier_un_contenu') }}</a><a href="{{ route('citizen.units') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas"><span class="material-symbols-rounded !text-[19px] text-primary icon-fill" aria-hidden="true">bolt</span> {{ __('cit_tableau_de_bord.acheter_des_unites') }}</a></div>
  </div>

  <section data-kpi-accordion class="mb-6">
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{!! __('cit_tableau_de_bord.apercu_de_votre_activite_expand_more') !!}</button>
    <div class="kpi-grid grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="kpi-card"><p class="kpi-value">{{ number_format($nombreDeVerifications, 0, ',', ' ') }}</p><p class="kpi-label">{{ __('cit_tableau_de_bord.verifications') }}</p></div>
      <div class="kpi-card"><p class="kpi-value">{{ $coursSuivis }}</p><p class="kpi-label">{{ __('cit_tableau_de_bord.cours_suivis') }}</p></div>
      <div class="kpi-card"><p class="kpi-value"><span>{{ number_format((int) $utilisateur->xp, 0, ',', ' ') }}</span> <span class="text-sm font-bold text-xp">XP</span></p><p class="kpi-label">{{ __('cit_tableau_de_bord.points_d_experience') }}</p></div>
      <div class="kpi-card"><p class="kpi-value">{{ number_format((int) $utilisateur->units, 0, ',', ' ') }}</p><p class="kpi-label">{{ __('cit_tableau_de_bord.unites_disponibles') }}</p></div>
    </div>
  </section>

  <section class="card p-6 mb-6" data-prio-widget>
    <div class="flex items-start justify-between gap-3 mb-4">
      <div>
        <p class="vf-eyebrow">{{ __('cit_tableau_de_bord.priorites') }}</p>
        <h2 class="text-lg font-display font-bold text-ink mb-0">{{ __('cit_tableau_de_bord.a_traiter_maintenant') }}</h2>
      </div>
      <a href="{{ route('citizen.evaluations') }}" class="text-sm font-semibold text-primary shrink-0 min-h-[44px] inline-flex items-center">{{ __('cit_tableau_de_bord.voir_la_file') }}</a>
    </div>
    @if(count($priorites) > 0)
      <div class="grid sm:grid-cols-2 gap-2.5">
        @foreach($priorites as $priorite)
          <a href="{{ $priorite['lien'] }}" class="flex items-center gap-3 rounded-xl border border-line px-4 py-3 hover:border-primary min-h-[52px]">
            <span aria-hidden="true">{{ $priorite['icone'] }}</span>
            <span class="flex-1 text-sm font-medium text-ink">{{ $priorite['texte'] }}</span>
            <span class="text-xs font-bold text-primary shrink-0">{{ $priorite['action'] }} →</span>
          </a>
        @endforeach
      </div>
    @else
      <p class="text-sm text-vtrue font-semibold mb-0">{{ __('cit_tableau_de_bord.tout_est_a_jour_continuez_votre_serie') }}</p>
    @endif
  </section>

  {{-- L'offre du mois vient d'un cours dont la gratuité est datée. Aucune
       promotion en cours ⇒ pas de bannière : annoncer une offre qui n'existe
       pas serait une promesse en l'air. --}}
  @if($coursOffert !== null)
    <section class="card p-5 mb-6 flex flex-col sm:flex-row sm:items-center gap-4 !border-primary/40 bg-primary-soft/30">
      <span class="w-12 h-12 rounded-2xl bg-primary text-white inline-flex items-center justify-center text-2xl shrink-0" aria-hidden="true">🎁</span>
      <div class="flex-1">
        <p class="vf-eyebrow !text-primary">{{ __('cit_tableau_de_bord.offre_du_mois') }} {{ ucfirst(now()->translatedFormat('F')) }}</p>
        <h2 class="text-base font-display font-bold text-ink mb-0.5">{{ __('cit_tableau_de_bord.cours_offert_du_mois') }} « {{ $coursOffert->title }} »</h2>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_tableau_de_bord.chaque_mois_un_cours_gratuit_mis_en') }}</p>
      </div>
      <a href="{{ route('citizen.course', $coursOffert) }}" class="inline-flex items-center justify-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px] shrink-0">{{ __('cit_tableau_de_bord.s_inscrire_gratuitement') }}</a>
    </section>
  @endif

  <div class="grid lg:grid-cols-3 gap-6">
    <section class="lg:col-span-2 space-y-6">
      <div class="card p-6" data-resume-widget>
        <p class="text-xs font-bold tracking-[.14em] text-slate2 uppercase mb-3">{{ __('cit_tableau_de_bord.continuer_votre_apprentissage') }}</p>
        <div>
          @if($reprise !== null)
            <h3 class="text-lg font-display font-bold text-ink mb-2">{{ $reprise['cours']->title }}</h3>
            <div class="flex items-center gap-3 mb-2">
              <div class="progress flex-1"><span style="width:{{ $reprise['avancement'] }}%"></span></div>
              <span class="text-sm font-bold text-primary">{{ $reprise['avancement'] }}%</span>
            </div>
            @if($reprise['prochaineLecon'] !== null)
              <p class="text-sm text-slate2 mb-4">{{ __('cit_tableau_de_bord.prochaine_lecon') }} <strong class="text-ink">{{ $reprise['prochaineLecon']->title }}</strong></p>
              <a href="{{ route('citizen.lesson', ['course' => $reprise['cours'], 'lesson' => $reprise['prochaineLecon']]) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark">{{ __('cit_tableau_de_bord.reprendre') }} <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">arrow_forward</span></a>
            @else
              <p class="text-sm text-slate2 mb-4">{{ __('cit_tableau_de_bord.il_reste_un_quiz_ou_un_exercice') }}</p>
              <a href="{{ route('citizen.course', $reprise['cours']) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark">{{ __('cit_tableau_de_bord.reprendre') }} <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">arrow_forward</span></a>
            @endif
          @else
            <h3 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_tableau_de_bord.commencez_votre_premier_cours') }}</h3>
            <p class="text-sm text-slate2 mb-4">{{ __('cit_tableau_de_bord.le_clom_introduction_au_fact_checking_est') }}</p>
            <a href="{{ route('citizen.catalogue') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark">{{ __('cit_tableau_de_bord.decouvrir_le_catalogue') }} <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">arrow_forward</span></a>
          @endif
        </div>
      </div>

      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-display font-bold text-ink mb-0">{{ __('cit_tableau_de_bord.mon_activite_sur_30_jours') }}</h3>
          <span class="text-xs text-slate2">{{ __('cit_tableau_de_bord.verifications_et_lecons') }}</span>
        </div>
        <div class="relative h-[180px]">
          {{-- Les séries sont posées en attribut : le graphique se redessine au
               redimensionnement sans nouvelle requête. --}}
          <canvas data-activity-chart
                  data-jours="{{ json_encode($activite['jours']) }}"
                  data-verifications="{{ json_encode($activite['verifications']) }}"
                  data-lecons="{{ json_encode($activite['lecons']) }}"
                  height="180" aria-label="{{ __('cit_tableau_de_bord.activite_des_30_derniers_jours') }}" role="img"></canvas>
        </div>
        <div class="flex items-center gap-5 mt-3 text-xs text-slate2">
          <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-primary inline-block"></span> {{ __('cit_tableau_de_bord.legende_verifications') }}</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-vinfo inline-block"></span> {{ __('cit_tableau_de_bord.legende_lecons_terminees') }}</span>
        </div>
      </div>

      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-display font-bold text-ink mb-0">{{ __('cit_tableau_de_bord.verifications_recentes') }}</h3>
          <a href="{{ route('citizen.verifications') }}" class="text-sm font-semibold text-primary">{{ __('cit_tableau_de_bord.tout_voir') }}</a>
        </div>
        <div class="space-y-3">
          @forelse($verificationsRecentes as $verification)
            <a href="{{ $verification->report !== null ? route('citizen.report', $verification) : route('citizen.verification', $verification) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line hover:border-primary">
              @if($verification->report !== null)
                {{-- `verdict` est NULLABLE dès qu'un rapport est MIXTE OU
                     INSUFFISANT (contrat V-8) : `estMixte()` seul laissait
                     passer le second cas tel quel vers `verdict->badge()`
                     sur `null`. Se brancher sur `verdict !== null` couvre
                     les deux à la fois, et tout état futur qui porterait
                     aussi un verdict nul. --}}
                @if($verification->report->verdict !== null)
                  <span class="{{ $verification->report->verdict->badge() }}">{{ mb_strtoupper($verification->report->verdict->libelle()) }}</span>
                @else
                  <span class="{{ $verification->report->composition_status->badge() }}">{{ mb_strtoupper($verification->report->composition_status->libelle()) }}</span>
                @endif
              @else
                <span class="vbadge vbadge-progress">{{ __('cit_tableau_de_bord.en_cours') }}</span>
              @endif
              <span class="flex-1 min-w-0 text-sm text-ink font-medium truncate">{{ $verification->claim }}</span>
              <span class="text-xs text-slate2 shrink-0">{{ $verification->type->libelle() }} · {{ $verification->created_at->format('d/m/Y') }}</span>
            </a>
          @empty
            <p class="text-sm text-slate2 mb-0">{{ __('cit_tableau_de_bord.aucune_verification_pour_le_moment') }} <a href="{{ route('citizen.verify') }}" class="font-semibold text-primary">{{ __('cit_tableau_de_bord.commencez_par_un_lien_ou_un_texte') }}</a></p>
          @endforelse
        </div>
      </div>

    </section>

    <aside class="space-y-6">
      <div class="card p-6">
        <h3 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_tableau_de_bord.prochaine_live_class') }}</h3>
        @if($prochaineLiveClass !== null)
          <p class="text-base font-display font-bold text-ink leading-snug mb-1">{{ $prochaineLiveClass->title }}</p>
          <p class="text-sm text-slate2 mb-3">{{ $prochaineLiveClass->host?->name ?? __('cit_tableau_de_bord.animation_verif_all') }}</p>
          <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary-soft text-primary-dark text-sm font-bold px-3 py-1.5 mb-4">📅 {{ $prochaineLiveClass->starts_at->translatedFormat(__('cit_tableau_de_bord.format_date_live_class')) }}</span>
        @else
          <p class="text-sm text-slate2 mb-4">{{ __('cit_tableau_de_bord.aucune_seance_programmee_pour_l_instant_les') }}</p>
        @endif
        <a href="{{ route('citizen.live-classes') }}" class="w-full inline-flex justify-center rounded-xl border border-primary text-primary text-sm font-semibold px-4 py-2.5 hover:bg-primary hover:text-white transition-colors">{{ __('cit_tableau_de_bord.voir_les_live_class') }}</a>
      </div>
      <div class="card p-6">
        <h3 class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_tableau_de_bord.ca_discute_sur_le_forum') }}</h3>
        <div class="space-y-4 mb-4">
          @forelse($sujetsDuForum as $sujet)
            <a href="{{ route('citizen.thread', $sujet) }}" class="block group">
              <p class="text-sm font-semibold text-ink leading-snug mb-0.5 group-hover:text-primary transition-colors">{{ $sujet->title }}</p>
              <p class="text-xs text-slate2 mb-0">{{ trans_choice('cit_tableau_de_bord.compte_reponses', $sujet->replies_count, ['nombre' => $sujet->replies_count]) }} · {{ $sujet->author?->name ?? __('cit_tableau_de_bord.compte_supprime') }}</p>
            </a>
          @empty
            <p class="text-sm text-slate2 mb-0">{{ __('cit_tableau_de_bord.le_forum_n_a_pas_encore_de') }}</p>
          @endforelse
        </div>
        <a href="{{ route('citizen.forum') }}" class="w-full inline-flex justify-center rounded-xl border border-primary text-primary text-sm font-semibold px-4 py-2.5 hover:bg-primary hover:text-white transition-colors">{{ __('cit_tableau_de_bord.rejoindre_le_forum') }}</a>
      </div>
      <div class="card p-6 bg-forest !border-forest text-white">
        <p class="text-xs font-bold tracking-[.14em] text-white/50 uppercase mb-2">{{ __('cit_tableau_de_bord.unites_restantes') }}</p>
        <p class="text-4xl font-display font-black leading-none mb-1">{{ number_format((int) $utilisateur->units, 0, ',', ' ') }}</p>
        <p class="text-sm text-white/70 mb-4">{{ __('cit_tableau_de_bord.1_unite_1_verification_par_ia') }}</p>
        <div class="progress bg-white/20 mb-4"><span style="width:{{ min(100, (int) round((int) $utilisateur->units / 50 * 100)) }}%"></span></div>
        <a href="{{ route('citizen.units') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary">{{ __('cit_tableau_de_bord.historique_complet') }} <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span></a>
      </div>
      <div class="card p-6">
        <h3 class="text-base font-display font-bold text-ink mb-3">{{ __('cit_tableau_de_bord.vos_recompenses') }}</h3>
        <div class="flex items-center gap-4 text-sm">
          <span class="inline-flex items-center gap-1 font-bold text-xp"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">bolt</span><span>{{ number_format((int) $utilisateur->xp, 0, ',', ' ') }}</span> XP</span>
          <span class="inline-flex items-center gap-1 font-bold text-vinvest">🔥 <span>{{ $utilisateur->streak_days }}</span> {{ __('cit_tableau_de_bord.abreviation_jour') }}</span>
          <span class="inline-flex items-center gap-1 font-bold text-ink"><span class="material-symbols-rounded !text-[18px] text-primary" aria-hidden="true">military_tech</span><span>{{ $badgesObtenus }}</span></span>
        </div>
        <a href="{{ route('citizen.rewards') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary">Achievement Center <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span></a>

      </div>
    </aside>
  </div>

  {{-- Modale quiz d'onboarding post-inscription --}}
  @if($poserLeQuiz)
    <div class="modal-backdrop" id="modal-onboarding" role="dialog" aria-modal="true" aria-labelledby="ob-title" data-ouvrir-apres="800">
      <div class="modal p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-soft"><span class="material-symbols-rounded text-primary" aria-hidden="true">quiz</span></span>
          <div>
            <h4 id="ob-title" class="font-display font-bold text-ink text-lg leading-tight mb-0">{{ __('cit_tableau_de_bord.votre_premier_reflexe_fact_checking') }}</h4>
            <p class="text-xs text-slate2 mb-0">{{ __('cit_tableau_de_bord.1_question_30_secondes') }} +{{ $quizDAccueil::XP }} XP</p>
          </div>
        </div>
        <p class="text-sm text-ink font-medium mb-3">{{ $quizDAccueil->question() }}</p>
        <div class="space-y-2 mb-5">
          @foreach($quizDAccueil->reponses() as $index => $reponse)
            <label class="flex items-start gap-2.5 p-3 rounded-xl border border-line cursor-pointer hover:border-primary"><input type="radio" wire:model="reponseAccueil" name="ob" value="{{ $index }}" class="mt-0.5"> <span class="text-sm">{{ $reponse }}</span></label>
          @endforeach
        </div>
        @if($commentaireAccueil !== null)
          <p class="text-sm rounded-xl p-3 mb-4 {{ $accueilJuste ? 'bg-primary-soft text-vtrue' : 'bg-vfalse/10 text-vfalse' }} font-medium" role="status">{{ $commentaireAccueil }}</p>
        @endif
        <div class="flex gap-2 justify-end">
          <button type="button" wire:click="reporterLeQuizDAccueil" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_tableau_de_bord.me_rappeler_plus_tard') }}</button>
          <button type="button" wire:click="validerLeQuizDAccueil" class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{{ __('cit_tableau_de_bord.valider') }}</button>
        </div>
      </div>
    </div>
  @endif

  {{--
    | Modale de PREMIÈRE VÉRIFICATION, à l'issue de l'inscription.
    |
    | Elle n'apparaît qu'une fois, et jamais en même temps que le quiz
    | d'accueil : deux fenêtres superposées sur un compte neuf sont deux
    | fenêtres qu'on ferme sans lire.
    |
    | Elle n'embarque PAS le formulaire de soumission. Le dupliquer ici
    | signifierait maintenir deux fois la même validation, le même contrôle de
    | solde et le même suivi d'analyse — pour finir par les voir diverger. Elle
    | mène à l'écran qui fait ce travail.
  --}}
  @if($proposerPremiereVerification)
    <div class="modal-backdrop" id="modal-premiere-verification" role="dialog" aria-modal="true" aria-labelledby="pv-title" data-ouvrir-apres="800">
      <div class="modal p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-soft"><span class="material-symbols-rounded text-primary" aria-hidden="true">fact_check</span></span>
          <div>
            <h4 id="pv-title" class="font-display font-bold text-ink text-lg leading-tight mb-0">{{ __('cit_tableau_de_bord.verifiez_votre_premiere_info') }}</h4>
            <p class="text-xs text-slate2 mb-0">{{ __('cit_tableau_de_bord.un_lien_un_texte_une_image_ou') }}</p>
          </div>
        </div>
        <p class="text-sm text-ink mb-5">{{ __('cit_tableau_de_bord.une_rumeur_recue_sur_whatsapp_un_article') }}</p>
        <div class="flex gap-2 justify-end">
          <button type="button" wire:click="reporterPremiereVerification" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_tableau_de_bord.plus_tard') }}</button>
          <button type="button" wire:click="allerVerifier" class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{{ __('cit_tableau_de_bord.lancer_une_verification') }}</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale campagne — la dernière annonce publiée, s'il y en a une --}}
  @if($campagne !== null)
    <div class="modal-backdrop" id="modal-campaign" role="dialog" aria-modal="true" aria-labelledby="cp-title" data-ouvrir-apres="1000" wire:init="campagneVue">
      <div class="modal overflow-hidden">
        @if($campagne->cover_path)
          <img src="{{ asset($campagne->cover_path) }}" alt="{{ $campagne->title }}" class="w-full h-40 object-cover">
        @endif
        <div class="p-6">
          <span class="inline-block rounded-full bg-primary-soft text-primary text-xs font-bold px-3 py-1 mb-3">{{ __('cit_tableau_de_bord.campagne_en_cours') }}</span>
          <h4 id="cp-title" class="font-display font-bold text-ink text-lg mb-2">{{ $campagne->title }}</h4>
          <p class="text-sm text-slate2 mb-5">{{ $campagne->excerpt }}</p>
          <div class="flex gap-2 justify-end">
            <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas" data-modal-close>{{ __('cit_tableau_de_bord.plus_tard') }}</button>
            <a href="{{ route('public.blog.show', $campagne) }}" class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{{ __('cit_tableau_de_bord.lire_l_annonce') }}</a>
          </div>
        </div>
      </div>
    </div>
  @endif

  @push('scripts')
    <script src="{{ asset('assets/js/pages/tableau-de-bord.js') }}" defer></script>
  @endpush
</div>
