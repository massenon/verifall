{{--
  | « Mes Statistiques » — transposition de compte-user/statistiques.html.
  |
  | Les quatre graphiques sont dessinés au canvas, comme dans le prototype ;
  | leurs séries sont posées en attributs par le serveur. Rien n'est simulé.
--}}
<div>

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div><nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_statistiques.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_statistiques.statistiques') }}</span></nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_statistiques.mes_statistiques') }}</h1><p class="text-slate2 mt-1 mb-0">{{ __('cit_statistiques.votre_activite_de_verification_votre_progression') }}<span class="block text-xs text-slate2 mt-1">{{ __('cit_statistiques.derniere_mise_a_jour') }} {{ now()->translatedFormat(__('cit_statistiques.format_derniere_mise_a_jour')) }}</span></p>
    </div>
  </div>
  <section data-kpi-accordion class="mb-6">
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{!! __('cit_statistiques.apercu_de_votre_pratique_expand_more') !!}</button>
    <div class="kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="vf-kpi"><p class="vf-kpi-value">{{ $nombreDeVerifications }}</p><p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_statistiques.verifications_totales') }}</p><p class="vf-kpi-trend">@if($evolutionMensuelle > 0)<span class="text-vtrue font-bold">↗ +{{ $evolutionMensuelle }}</span>&nbsp;{{ __('cit_statistiques.vs_30_jours_precedents') }} @elseif($evolutionMensuelle < 0)<span class="text-vfalse font-bold">↘ {{ $evolutionMensuelle }}</span>&nbsp;{{ __('cit_statistiques.vs_30_jours_precedents') }} @else {{ __('cit_statistiques.stable_sur_30_jours') }} @endif</p></div>
      <div class="vf-kpi"><p class="vf-kpi-value">{{ $intoxDetectees }}</p><p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_statistiques.intox_detectees') }}</p><p class="vf-kpi-trend">{{ __('cit_statistiques.partages_potentiellement_evites') }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-value">{{ $leconsTerminees }}</p><p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_statistiques.lecons_terminees') }}</p><p class="vf-kpi-trend">@if($leconsCetteSemaine > 0)<span class="text-vtrue font-bold">↗ +{{ $leconsCetteSemaine }}</span>&nbsp;{{ __('cit_statistiques.cette_semaine') }} @else {{ __('cit_statistiques.aucune_cette_semaine') }} @endif</p></div>
      <div class="vf-kpi"><p class="vf-kpi-value">🔥 <span>{{ $utilisateur->streak_days }}</span> {{ __('cit_statistiques.abreviation_jour') }}</p><p class="vf-kpi-label !mb-0 mt-1.5">{{ __('cit_statistiques.serie_actuelle') }}</p><p class="vf-kpi-trend">{{ __('cit_statistiques.record_personnel') }} {{ trans_choice('cit_statistiques.compte_jours', (int) $utilisateur->streak_best, ['nombre' => $utilisateur->streak_best]) }}</p></div>
    </div>
  </section>

  <div class="grid lg:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="vf-eyebrow">{{ __('cit_statistiques.activite') }}</p>
          <h2 class="text-lg font-display font-bold text-ink mb-0.5">{{ $this->titreDeLaPeriode() }}</h2>
        </div>
        <select wire:model.live="periode" class="bg-surface border border-line rounded-xl px-2.5 py-2 text-xs font-semibold min-h-[44px]" aria-label="{{ __('cit_statistiques.periode_du_graphique') }}">
          <option value="7">{{ __('cit_statistiques.7_derniers_jours') }}</option>
          <option value="30">{{ __('cit_statistiques.30_derniers_jours') }}</option>
          <option value="90">{{ __('cit_statistiques.3_derniers_mois') }}</option>
          <option value="365">{{ __('cit_statistiques.cette_annee') }}</option>
        </select>
      </div>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_statistiques.verifications_lecons_et_quiz_cumules') }}</p>
      <div class="relative h-[220px]" wire:key="activite-{{ $periode }}">
        <canvas data-line-chart height="220" role="img" aria-label="{{ __('cit_statistiques.courbe_d_activite') }}"
                data-jours="{{ json_encode($activite['jours']) }}"
                data-valeurs="{{ json_encode($activite['valeurs']) }}"></canvas>
        @if(array_sum($activite['valeurs']) === 0)
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <p class="text-sm font-bold text-ink mb-1">{{ __('cit_statistiques.pas_encore_de_donnees_sur_cette_periode') }}</p>
            <p class="text-xs text-slate2 mb-3">{{ __('cit_statistiques.effectuez_une_verification_pour_alimenter_votre_courbe') }}</p>
            <a href="{{ route('citizen.verify') }}" class="inline-flex rounded-xl bg-primary text-white text-xs font-semibold px-4 py-2 min-h-[40px] items-center">{{ __('cit_statistiques.verifier_un_contenu') }} →</a>
          </div>
        @endif
      </div>
    </div>
    <div class="card p-6">
      <p class="vf-eyebrow">{{ __('cit_statistiques.verdicts') }}</p>
      <h2 class="text-lg font-display font-bold text-ink mb-0.5">{{ __('cit_statistiques.repartition_de_mes_verdicts') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_statistiques.sur_l_ensemble_de_mes_verifications') }}</p>
      @php
        $vrai = $verdicts[\App\Enums\VerdictFactCheck::Vrai->value] ?? 0;
        $doute = $verdicts[\App\Enums\VerdictFactCheck::Trompeur->value] ?? 0;
        $faux = $verdicts[\App\Enums\VerdictFactCheck::Faux->value] ?? 0;
        $tranche = $vrai + $doute + $faux;
        $part = fn (int $n): string => $tranche > 0 ? ' · '.round($n * 100 / $tranche).' %' : '';
      @endphp
      <div class="flex items-center justify-center mb-4 overflow-hidden"><div class="relative w-full max-w-[190px] aspect-square"><canvas data-donut class="w-full h-full" width="190" height="190" role="img" aria-label="{{ __('cit_statistiques.repartition_des_verdicts') }}"
              data-vrai="{{ $vrai }}" data-doute="{{ $doute }}" data-faux="{{ $faux }}"></canvas>
      <span class="absolute inset-0 flex flex-col items-center justify-center"><span class="text-3xl font-display font-black text-ink leading-none">{{ $nombreDeVerifications }}</span><span class="text-[11px] font-semibold text-slate2 mt-0.5">{{ __('cit_statistiques.verifications') }}</span></span>
      @if($tranche === 0)
        <span class="absolute inset-0 flex flex-col items-center justify-center text-center px-3"><span class="text-xs text-slate2">{{ __('cit_statistiques.pas_encore_de_donnees_effectuez_votre_premiere') }}</span></span>
      @endif
      </div></div>
      <div class="space-y-2 text-sm">
        <p class="flex items-center gap-2 mb-0"><span class="w-3 h-3 rounded-sm bg-vtrue inline-block"></span> ✓ {{ __('cit_statistiques.verifie_vrai') }} <strong class="ml-auto">{{ $vrai }}{{ $part($vrai) }}</strong></p>
        <p class="flex items-center gap-2 mb-0"><span class="w-3 h-3 rounded-sm bg-vinvest inline-block"></span> ⚠ {{ __('cit_statistiques.douteux') }} <strong class="ml-auto">{{ $doute }}{{ $part($doute) }}</strong></p>
        <p class="flex items-center gap-2 mb-0"><span class="w-3 h-3 rounded-sm bg-vfalse inline-block"></span> ✕ {{ __('cit_statistiques.verifie_faux') }} <strong class="ml-auto">{{ $faux }}{{ $part($faux) }}</strong></p>
      </div>
    </div>
  </div>

  <div class="grid lg:grid-cols-2 gap-6">
    <div class="card p-6">
      <p class="vf-eyebrow">{{ __('cit_statistiques.contenus') }}</p>
      <h2 class="text-lg font-display font-bold text-ink mb-0.5">{{ __('cit_statistiques.types_de_contenus_verifies') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_statistiques.ce_que_vous_soumettez_le_plus') }}</p>
      <div class="relative h-[220px]">
        <canvas data-bars height="220" role="img" aria-label="{{ __('cit_statistiques.types_de_contenus_verifies_aria') }}"
                data-types="{{ json_encode(array_map(fn ($t) => $t->libelle(), \App\Enums\TypeContenu::cases())) }}"
                data-valeurs="{{ json_encode(array_values($types)) }}"></canvas>
        @if(array_sum($types) === 0)
          <div class="absolute inset-0 flex items-center justify-center"><p class="text-xs text-slate2 mb-0">{{ __('cit_statistiques.aucun_contenu_verifie_pour_le_moment') }}</p></div>
        @endif
      </div>
    </div>
    <div class="card p-6">
      <p class="vf-eyebrow">{{ __('cit_statistiques.habitudes') }}</p>
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_statistiques.ma_semaine_de_serie') }}</h2>
      <div class="flex gap-3 mb-3">
        @foreach($semaine as $jour)
          <span class="vf-streak-day{{ $jour['actif'] ? ' is-on' : '' }}{{ $jour['aujourdhui'] ? ' is-today' : '' }}"
                aria-label="{{ $jour['lettre'] }} : {{ $jour['futur'] ? __('cit_statistiques.jour_a_venir') : ($jour['actif'] ? ($jour['aujourdhui'] ? __('cit_statistiques.aujourd_hui_actif') : __('cit_statistiques.jour_actif')) : __('cit_statistiques.jour_sans_activite')) }}"><span class="vf-streak-dot"></span>{{ $jour['lettre'] }}</span>
        @endforeach
      </div>
      <p class="text-sm font-bold text-ink mb-0.5">🔥 {{ trans_choice('cit_statistiques.jours_consecutifs', (int) $utilisateur->streak_days, ['nombre' => $utilisateur->streak_days]) }}</p>
      <p class="text-sm text-slate2 mb-0">{{ __('cit_statistiques.connectez_vous_chaque_jour_pour_maintenir_votre') }}</p>
    </div>
  </div>

  <section class="vf-insight p-6 sm:p-8 mt-6 flex flex-col sm:flex-row sm:items-center gap-5">
    <div class="flex-1">
      <p class="vf-eyebrow">{{ __('cit_statistiques.votre_insight') }}</p>
      <p class="text-base font-bold text-ink mb-1">{{ trans_choice('cit_statistiques.vous_avez_effectue_verifications', $nombreDeVerifications, ['nombre' => $nombreDeVerifications]) }}@if($evolutionMensuelle > 0) {{ __('cit_statistiques.votre_activite_est_en_hausse') }} @endif.</p>
      <p class="text-sm text-slate2 mb-0">{{ trans_choice('cit_statistiques.continuez_votre_serie_de_jours', (int) $utilisateur->streak_days, ['nombre' => $utilisateur->streak_days]) }}</p>
    </div>
    <a href="{{ route('citizen.verify') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-3 hover:bg-primary-dark min-h-[48px]">{{ __('cit_statistiques.continuer_a_verifier') }} <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span></a>
  </section>

  @push('scripts')
    <script src="{{ asset('assets/js/pages/statistiques.js') }}" defer></script>
  @endpush
</div>
