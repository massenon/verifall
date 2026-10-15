{{--
  | « Mes Évaluations » — transposition de compte-user/evaluations.html.
  |
  | Le tableau sur grand écran, la liste de cartes sur téléphone : les deux
  | existent dans le prototype, et c'est la feuille de page qui bascule de l'un
  | à l'autre. Ne garder que le tableau rendrait la page illisible au pouce.
--}}
<div>

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div><nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_evaluations.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_evaluations.mes_evaluations') }}</span></nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_evaluations.mes_evaluations') }}</h1><p class="text-slate2 mt-1 mb-0">{{ __('cit_evaluations.tous_vos_quiz_et_exercices_scores_reels') }}</p>
    </div>
  </div>
  <section class="mb-6" data-kpi-accordion>
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{!! __('cit_evaluations.apercu_de_vos_evaluations_expand_more') !!}</button>
    <div class="kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_evaluations.quiz_reussis') }}</p><p class="vf-kpi-value text-vtrue">{{ $nombreReussis }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_evaluations.score_moyen') }}</p><p class="vf-kpi-value">{{ $scoreMoyen === null ? '—' : $scoreMoyen.' %' }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_evaluations.tentatives') }}</p><p class="vf-kpi-value" style="color:#2563EB">{{ $nombreTentatives }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_evaluations.exercices_soumis') }}</p><p class="vf-kpi-value text-xp">{{ $nombreSoumis }}</p></div>
    </div>
  </section>

  <div class="flex gap-1.5 mb-5" data-tabs>
    <button type="button" class="pill-tab is-active" data-tab="ev-quiz">{{ __('cit_evaluations.quiz') }}</button>
    <button type="button" class="pill-tab" data-tab="ev-assign">{{ __('cit_evaluations.exercices') }}</button>
  </div>
  <div data-tabs-scope>
    <div data-tab-panel="ev-quiz" class="is-active">
      @if($quiz->isNotEmpty())
        <div class="table-wrap card overflow-hidden">
          <table class="data-table">
            <thead><tr><th scope="col">{{ __('cit_evaluations.quiz') }}</th><th scope="col">{{ __('cit_evaluations.cours') }}</th><th scope="col">{{ __('cit_evaluations.score') }}</th><th scope="col">{{ __('cit_evaluations.tentatives_2') }}</th><th scope="col">{{ __('cit_evaluations.statut') }}</th><th scope="col"><span class="sr-only">{{ __('cit_evaluations.action') }}</span></th></tr></thead>
            <tbody>
              @foreach($quiz as $ligne)
                <tr>
                  <td class="font-semibold max-w-xs"><span class="line-clamp-1">{{ $ligne['quiz']->title }}</span></td>
                  <td class="text-slate2 max-w-xs"><span class="line-clamp-1">{{ $ligne['quiz']->course?->title }}</span></td>
                  <td>
                    @if($ligne['score'] === null)
                      <span class="text-slate2">—</span> <span class="text-xs text-slate2">{{ __('cit_evaluations.slash_min') }} {{ $ligne['quiz']->pass_score }} %</span>
                    @else
                      <span class="font-bold {{ $ligne['passed'] ? 'text-vtrue' : 'text-vinvest' }}">{{ $ligne['score'] }} %</span> <span class="text-xs text-slate2">{{ __('cit_evaluations.slash_min') }} {{ $ligne['quiz']->pass_score }} %</span>
                    @endif
                  </td>
                  <td>{{ $ligne['attempts'] }}</td>
                  <td><x-evaluation.badge-quiz :ligne="$ligne" /></td>
                  <td class="text-right"><x-evaluation.action-quiz :ligne="$ligne" /></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="cards-wrap space-y-3">
          @foreach($quiz as $ligne)
            <article class="card p-4">
              <div class="flex items-center justify-between gap-2 mb-2"><x-evaluation.badge-quiz :ligne="$ligne" />
                <span class="text-sm font-bold {{ $ligne['passed'] ? 'text-vtrue' : 'text-vinvest' }}">{{ $ligne['score'] === null ? '—' : $ligne['score'].' %' }}</span></div>
              <h2 class="text-sm font-display font-bold text-ink mb-1">{{ $ligne['quiz']->title }}</h2>
              <p class="text-xs text-slate2 mb-0">{{ $ligne['quiz']->course?->title }}</p>
              <p class="text-xs text-slate2 pb-3 mb-3 border-b border-line">{{ $ligne['attempts'] }} {{ trans_choice('cit_evaluations.tentative_plurielle', $ligne['attempts']) }} · {{ __('cit_evaluations.minimum') }} {{ $ligne['quiz']->pass_score }} %</p>
              <div><x-evaluation.action-quiz :ligne="$ligne" /></div>
            </article>
          @endforeach
        </div>
      @else
        <div class="card p-8 text-center">
          <span class="material-symbols-rounded text-slate2 !text-[36px] block mx-auto mb-2" aria-hidden="true">quiz</span>
          <p class="text-sm text-slate2 mb-3">{{ __('cit_evaluations.vous_n_avez_encore_passe_aucun_quiz') }}</p>
          <a href="{{ route('citizen.catalogue') }}" class="inline-flex rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">{{ __('cit_evaluations.decouvrir_l_academie') }}</a>
        </div>
      @endif
    </div>
    <div data-tab-panel="ev-assign">
      @if($exercices->isNotEmpty())
        <div class="space-y-3">
          @foreach($exercices as $ligne)
            <article class="card p-4 flex flex-wrap items-center justify-between gap-3">
              <div class="min-w-0"><h2 class="text-sm font-display font-bold text-ink mb-0.5">{{ $ligne['exercice']->title }}</h2>
                <p class="text-xs text-slate2 mb-0">{{ $ligne['exercice']->course?->title }}</p></div>
              <div class="flex items-center gap-3">
                @if(! $ligne['inscrit'])
                  <span class="vbadge" style="background:#F6F8F7;color:#64748B" title="{{ __('cit_evaluations.disponible_apres_inscription_au_cours') }}">{{ __('cit_evaluations.verrouille') }}</span>
                  <a href="{{ route('citizen.course', $ligne['exercice']->course) }}" class="text-sm font-semibold text-slate2 shrink-0">{{ __('cit_evaluations.s_inscrire_au_cours') }}</a>
                @elseif($ligne['statut'] === null)
                  <span class="vbadge vbadge-doubt">{{ __('cit_evaluations.a_rendre') }}</span>
                  <a href="{{ route('citizen.exercises') }}" class="text-sm font-semibold text-primary shrink-0">{{ __('cit_evaluations.rendre_l_exercice') }}</a>
                @else
                  <span class="{{ $ligne['statut']->badge() }}">{{ mb_strtoupper($ligne['statut']->libelle()) }}</span>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="card p-8 text-center">
          <span class="material-symbols-rounded text-slate2 !text-[36px] block mx-auto mb-2" aria-hidden="true">assignment</span>
          <p class="text-sm text-slate2 mb-3">{{ __('cit_evaluations.aucun_exercice_soumis_pour_l_instant') }}</p>
          <a href="{{ route('citizen.exercises') }}" class="inline-flex rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">{{ __('cit_evaluations.voir_mes_exercices') }}</a>
        </div>
      @endif
    </div>
  </div>

  @push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/pages/user-evaluations.css') }}">
  @endpush
</div>
