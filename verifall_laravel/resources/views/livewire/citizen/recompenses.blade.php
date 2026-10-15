{{--
  | « Certificats & Badges » — transposition de compte-user/recompenses.html.
  |
  | Les badges verrouillés sont montrés avec leur condition : voir ce qui reste
  | à atteindre est ce qui donne envie de continuer.
--}}
<div>

  <header class="mb-6">
    <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_recompenses.fil_d_ariane') }}">
      <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">{{ __('cit_recompenses.dashboard') }}</a>
      <span class="text-line" aria-hidden="true">/</span>
      <span class="text-ink font-medium">{{ __('cit_recompenses.certificats_amp_badges') }}</span>
    </nav>
    <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight mb-1">{{ __('cit_recompenses.certificats_amp_badges') }}</h1>
    <p class="text-slate2 mb-0">{{ __('cit_recompenses.votre_niveau_vos_series_et_ce_que') }}</p>
  </header>

  {{-- ══════════════════════════════════════════════════ votre progression --}}
  <section class="card p-6 sm:p-8 mb-6">
    <p class="vf-eyebrow">{{ __('cit_recompenses.votre_progression') }}</p>
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-3">
      <span class="text-3xl font-display font-black text-ink">{{ __('cit_recompenses.niveau') }} {{ $niveau }}</span>
      <span class="text-lg font-display font-bold text-primary">{{ $nomDuNiveau }}</span>
    </div>
    <div class="max-w-xl">
      <div class="progress mb-1.5" style="height:10px"><span style="width:{{ $pourcentage }}%"></span></div>
      <p class="text-sm text-slate2 mb-0"><strong class="text-ink">{{ number_format($xpDansLePalier, 0, ',', ' ') }}</strong> / {{ number_format($xpParPalier, 0, ',', ' ') }} XP · {{ __('cit_recompenses.encore') }} {{ number_format($xpRestant, 0, ',', ' ') }} {{ __('cit_recompenses.xp_avant_le_niveau_suivant') }}</p>
    </div>
  </section>


  {{--
    | D'où viennent les XP.
    |
    | Le total est affiché juste au-dessus ; jusqu'ici rien ne l'expliquait.
    | La plateforme incrémentait un compteur depuis quatre endroits sans tenir
    | de journal : on savait combien de points quelqu'un avait, jamais d'où ils
    | venaient — donc impossible de trancher un « j'ai fini ce cours et je n'ai
    | rien reçu ».
    |
    | La ligne « points antérieurs » couvre les comptes ouverts avant le journal.
    | Voir `Recompenses::origineDesXp()`.
  --}}
  @if($origineDesXp !== [])
    <section class="card p-6 mb-6">
      <h2 class="text-base font-display font-bold text-ink mb-3">{{ __('cit_recompenses.d_ou_viennent_vos_xp') }}</h2>
      <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-2">
        @foreach($origineDesXp as $origine)
          <li class="flex items-baseline justify-between gap-3 text-sm border-b border-line pb-1.5">
            <span class="text-slate2">{{ $origine['libelle'] }}</span>
            <span class="font-bold text-ink shrink-0">+{{ number_format($origine['points'], 0, ',', ' ') }} XP</span>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  {{-- ══════════════════════════════════════════════════════ les chiffres --}}
  <section class="mb-8" data-kpi-accordion>
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{!! __('cit_recompenses.vue_d_ensemble_expand_more') !!}</button>
    <div class="kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_recompenses.niveau') }}</p><p class="vf-kpi-value">{{ $niveau }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_recompenses.xp_cumules') }}</p><p class="vf-kpi-value">{{ number_format($utilisateur->xp, 0, ',', ' ') }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_recompenses.badges') }}</p><p class="vf-kpi-value">{{ $debloques }} / {{ $badges->count() }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_recompenses.certificats') }}</p><p class="vf-kpi-value">{{ $certificats->count() }}</p></div>
    </div>
  </section>

  {{-- ════════════════════════════════ prochaine étape et série ════════ --}}
  <div class="grid lg:grid-cols-3 gap-5 mb-12 items-start">
    <section class="lg:col-span-2 card p-6">
      <p class="vf-eyebrow">{{ __('cit_recompenses.prochaine_etape') }}</p>
      @if($prochainBadge !== null)
        <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_recompenses.badge_nomme', ['nom' => $prochainBadge->name]) }}</h2>
        <div class="max-w-md">
          <p class="text-sm text-slate2 mb-4">{{ $prochainBadge->requirement_label }}</p>
        </div>
      @else
        <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_recompenses.tous_les_badges_sont_debloques') }}</h2>
        <div class="max-w-md">
          <p class="text-sm text-slate2 mb-4">{{ __('cit_recompenses.il_ne_reste_rien_a_decrocher_continuez') }}</p>
        </div>
      @endif
      <a href="{{ route('citizen.tutorials') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">{{ __('cit_recompenses.continuer_un_tutoriel') }} <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span></a>
    </section>
    <div class="card p-6">
      {{-- Le libellé change avec l'état. Annoncer « 7 jours consécutifs » puis,
           une ligne plus bas, « série interrompue », c'est se contredire dans
           la même carte. Le chiffre reste affiché — effacer la série effacerait
           aussi ce qui avait été accompli — mais il est présenté au passé. --}}
      <p class="vf-eyebrow">{{ $serieVivante ? __('cit_recompenses.votre_serie') : __('cit_recompenses.votre_derniere_serie') }}</p>

      @if($serieVivante)
        <p class="text-2xl font-display font-black text-ink mb-0.5">
          🔥 {{ trans_choice('cit_recompenses.jours_consecutifs', $utilisateur->streak_days) }}
        </p>
        <p class="text-sm text-slate2 mb-0">
          {{ trans_choice('cit_recompenses.plus_que_jours_avant_unites', $joursAvantLaPrime, ['unites' => App\Core\Gamification\Services\SerieDeVigilance::UNITES_DE_PALIER]) }}
        </p>
      @else
        <p class="text-2xl font-display font-black text-slate2 mb-0.5">
          {{ trans_choice('cit_recompenses.jours_d_affilee', $utilisateur->streak_days) }}
        </p>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_recompenses.serie_interrompue_une_verification_aujourd_hui_en') }}</p>
      @endif
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════ badges --}}
  <section class="mb-12">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <h2 class="text-xl font-display font-bold text-ink mb-0">{{ __('cit_recompenses.vos_badges') }}</h2>
      <button type="button" wire:click="verifierMesBadges" wire:loading.attr="disabled"
              class="text-sm font-semibold text-primary">{!! __('cit_recompenses.verifier_mes_badges_verification') !!}</button>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
      @foreach($badges as $badge)
        @php($obtenu = $this->obtention($badge))
        <div class="card p-5 text-center {{ $obtenu ? '' : 'opacity-60' }}" wire:key="badge-{{ $badge->id }}">
          <p class="text-3xl mb-2" aria-hidden="true">{{ $badge->icon }}</p>
          <p class="text-sm font-display font-bold text-ink mb-1">{{ $badge->name }}</p>
          <p class="text-xs text-slate2 mb-3">{{ $badge->description }}</p>

          @if($obtenu)
            <span class="vbadge vbadge-true">{{ __('cit_recompenses.debloque') }}</span>
            <span class="block text-xs text-slate2 mt-1.5">
              {{ $obtenu->unlocked_at->translatedFormat('j M Y') }}
              @if($badge->xp_reward > 0) · +{{ $badge->xp_reward }} XP @endif
              @if($badge->units_reward > 0) · +{{ $badge->units_reward }} {{ __('cit_recompenses.unites') }} @endif
            </span>
          @else
            {{-- La condition, pas juste un cadenas : un badge verrouillé sans
                 explication est une frustration gratuite. --}}
            <span class="inline-flex items-center gap-1 text-xs text-slate2">
              <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">lock</span>
              {{ $badge->requirement_label }}
            </span>
          @endif
        </div>
      @endforeach
    </div>
  </section>

  {{-- ══════════════════════════════════════════════════════ certificats --}}
  <section>
    <h2 class="text-xl font-display font-bold text-ink mb-4">{{ __('cit_recompenses.vos_certificats') }}</h2>

    @forelse($certificats as $certificat)
      <div class="card p-5 mb-3 flex flex-col sm:flex-row sm:items-center gap-4" wire:key="cert-{{ $certificat->id }}">
        <div class="min-w-0 flex-1">
          <p class="mb-1"><span class="vbadge vbadge-true">{{ __('cit_recompenses.certificat_authentifie') }}</span></p>
          <p class="text-base font-display font-bold text-ink mb-0.5">{{ $certificat->course_title }}</p>
          <p class="text-xs text-slate2 mb-0">
            {{ __('cit_recompenses.delivre_le') }} {{ $certificat->issued_at->translatedFormat('j F Y') }} ·
            {{ __('cit_recompenses.code') }} <span class="font-mono">{{ $certificat->code }}</span>
          </p>
        </div>
        <a href="{{ route('citizen.certificate', $certificat) }}"
           class="shrink-0 inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
          {{ __('cit_recompenses.voir_le_certificat') }}
        </a>
      </div>
    @empty
      <div class="card p-10 text-center">
        <span class="text-3xl block mb-2" aria-hidden="true">🎓</span>
        <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_recompenses.aucun_certificat_pour_l_instant') }}</p>
        <p class="text-sm text-slate2 mb-4">{{ __('cit_recompenses.terminez_un_cours_de_l_academie_pour') }}</p>
        <a href="{{ route('citizen.catalogue') }}"
           class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
          {{ __('cit_recompenses.voir_le_catalogue') }}
        </a>
      </div>
    @endforelse
  </section>

  {{-- ════════════════════════════════════════════════════ activité récente --}}
  <section class="card p-6 max-w-2xl mt-12">
    <h2 class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_recompenses.activite_recente') }}</h2>
    @if($activite !== [])
      <div class="vf-timeline">
        @foreach($activite as $evenement)
          <div class="vf-timeline-item">
            <p class="text-sm font-bold text-ink mb-0.5">{{ $evenement['titre'] }}</p>
            <p class="text-xs text-slate2 mb-0">{{ $evenement['detail'] }} · {{ $evenement['quand'] }}</p>
          </div>
        @endforeach
      </div>
    @else
      {{-- Rien d'inventé : le prototype montre quatre lignes de démonstration,
           mais une récompense qu'on n'a pas reçue n'a rien à faire dans son
           propre historique. --}}
      <p class="text-sm text-slate2 mb-0">{{ __('cit_recompenses.rien_encore_votre_premiere_lecon_terminee_ouvrira') }}</p>
    @endif
  </section>

</div>
