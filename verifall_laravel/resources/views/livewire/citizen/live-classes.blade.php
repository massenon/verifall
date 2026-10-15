{{--
  | « Live Classes » — transposition de compte-user/live-classes.html.
  |
  | Les onglets restent gérés côté navigateur (`data-tabs` du design system) :
  | passer d'un onglet à l'autre est un geste de lecture, pas une requête.
--}}
<div>

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div><nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_live_classes.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_live_classes.live_classes') }}</span></nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_live_classes.live_classes') }}</h1><p class="text-slate2 mt-1 mb-0">{{ __('cit_live_classes.vos_classes_zoom_en_direct_avec_les') }}</p>
    </div>
  </div>
  <div class="flex gap-1.5 mb-5" data-tabs data-tabs-hook="">
    <button type="button" class="pill-tab is-active" data-tab="lc-upcoming">{{ __('cit_live_classes.a_venir') }}</button>
    <button type="button" class="pill-tab" data-tab="lc-live">{{ __('cit_live_classes.en_direct') }}</button>
    <button type="button" class="pill-tab" data-tab="lc-past">{{ __('cit_live_classes.terminees') }}</button>
  </div>
  <div data-tabs-scope>
    <div data-tab-panel="lc-upcoming" class="is-active space-y-4">
      @forelse($aVenir as $seance)
        <x-live-class.carte :seance="$seance" statut="upcoming" />
      @empty
        {{-- Le prototype renvoie ici « vers les notifications ». Il n'existe aucun
             magasin de notifications : la cloche de la barre supérieure est décorative,
             et promettre un canal muet est pire que ne rien promettre. --}}
        <x-live-class.vide :message="__('cit_live_classes.aucune_classe_planifiee_pour_le_moment_les')" />
      @endforelse
    </div>
    <div data-tab-panel="lc-live" class="space-y-4">
      @forelse($enDirect as $seance)
        <x-live-class.carte :seance="$seance" statut="live" />
      @empty
        <x-live-class.vide :message="__('cit_live_classes.aucune_classe_en_direct_actuellement')" />
      @endforelse
    </div>
    <div data-tab-panel="lc-past" class="space-y-4">
      @forelse($terminees as $seance)
        <x-live-class.carte :seance="$seance" statut="past" />
      @empty
        <x-live-class.vide :message="__('cit_live_classes.aucune_classe_terminee')" />
      @endforelse
    </div>
  </div>

  <div class="modal-backdrop" id="modal-join">
    <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="join-title">
      <div class="flex items-center gap-3 mb-3">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-vfalse/10 text-vfalse"><span class="material-symbols-rounded !text-[24px]" aria-hidden="true">videocam</span></span>
        <h2 id="join-title" class="text-lg font-display font-bold text-ink mb-0">{{ __('cit_live_classes.rejoindre_la_classe') }}</h2>
      </div>
      <p class="text-sm text-slate2 mb-1">{{ __('cit_live_classes.vous_allez_rejoindre') }}</p>
      <p class="text-sm font-semibold text-ink mb-4" data-join-title></p>
      <div class="rounded-xl bg-canvas border border-line p-3 mb-4">
        <p class="text-xs text-slate2 mb-1">{{ __('cit_live_classes.lien_zoom_de_la_session') }}</p>
        <p class="text-sm font-mono text-ink mb-0 break-all" data-join-link></p>
      </div>
      <div class="flex gap-2 justify-end">
        <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas" data-modal-close>{{ __('cit_live_classes.annuler') }}</button>
        <button type="button" class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark" data-join-copy>{{ __('cit_live_classes.copier_le_lien_et_rejoindre') }}</button>
      </div>
    </div>
  </div>

  @push('scripts')
    <script src="{{ asset('assets/js/pages/live-classes.js') }}" defer></script>
  @endpush
</div>
