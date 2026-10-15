@push('styles')
  {{-- `hub-pill` vit dans le bloc <style> de la page du prototype : sans cette
       feuille, les pastilles de catégorie sortent en texte brut. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-outils.css') }}">
@endpush

{{--
  | « Répertoire des outils » — transposition de compte-user/outils.html.
  |
  | Même en-tête, mêmes pastilles, mêmes groupes par catégorie, même bandeau de
  | fin. Le filtrage passe par le serveur ; l'écran, lui, ne bouge pas.
--}}
<div>

  <header class="mb-6">
    <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_outils.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">{{ __('cit_outils.dashboard') }}</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_outils.outils') }}</span></nav>
    <p class="vf-eyebrow">{{ __('cit_outils.outils_osint_fact_checking') }}</p>
    <h1 class="text-3xl lg:text-4xl font-display font-bold text-ink mb-2">{{ __('cit_outils.repertoire_des_outils') }}</h1>
    <p class="text-[15px] text-slate2 max-w-[620px] mb-2">{{ __('cit_outils.les_bons_outils_pour_enqueter_verifier_et') }}</p>
    <p class="text-sm text-slate2 mb-5"><strong class="text-ink">{{ trans_choice('cit_outils.outils_references', $nombreTotal) }}</strong> · <span class="text-primary font-semibold">{{ __('cit_outils.selection_verif_all') }}</span></p>
    <div class="relative max-w-[720px] mb-4">
      <label class="sr-only" for="tool-search">{{ __('cit_outils.rechercher_un_outil') }}</label>
      <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-slate2 !text-[20px]" aria-hidden="true">search</span>
      <input id="tool-search" type="search" wire:model.live.debounce.350ms="recherche"
             placeholder="{{ __('cit_outils.rechercher_image_domaine_metadonnees_ia') }}"
             class="w-full h-[52px] bg-surface border border-line rounded-xl pl-11 pr-4 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10">
    </div>
    <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar" role="tablist" aria-label="{{ __('cit_outils.categories_d_outils') }}"><button type="button" wire:click="reinitialiser" class="hub-pill {{ $categorie === '' ? 'is-active' : '' }}">{{ __('cit_outils.tous') }}</button>@foreach(\App\Livewire\Citizen\Outils::CATEGORIES as $valeur => $libelle)<button type="button" wire:click="filtrer(@js($valeur))" class="hub-pill {{ $categorie === $valeur ? 'is-active' : '' }}">{{ __('cit_outils.categorie.'.$libelle) }}</button>@endforeach
    </div>
  </header>

  @if($recommandes->isNotEmpty())
    <section class="mb-12">
      <h2 class="text-xl font-display font-bold text-ink mb-4">{{ __('cit_outils.outils_recommandes') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($recommandes as $outil)
          <x-outil.carte :outil="$outil" />
        @endforeach
      </div>
    </section>
  @endif

  <section>
    <h2 class="text-xl font-display font-bold text-ink mb-5">{{ __('cit_outils.tous_les_outils') }}</h2>
    @forelse($parCategorie as $nomCategorie => $outils)
      <div class="mb-10">
        <div class="flex items-center gap-2.5 mb-4 pb-2 border-b border-line">
          <span class="material-symbols-rounded text-primary !text-[20px]" aria-hidden="true">{{ $icones[$nomCategorie] ?? 'build' }}</span>
          <h3 class="text-base font-display font-bold text-ink uppercase tracking-wide mb-0">{{ $nomCategorie }}</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          @foreach($outils as $outil)
            <x-outil.carte :outil="$outil" />
          @endforeach
        </div>
      </div>
    @empty
      <div class="card p-10 text-center">
        <span class="text-3xl block mb-2" aria-hidden="true">🔎</span>
        <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_outils.aucun_outil_trouve') }}</p>
        <p class="text-sm text-slate2 mb-4">{!! __('cit_outils.nous_n_avons_trouve_aucun_outil_correspondant') !!}</p>
        <button type="button" wire:click="reinitialiser" class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors min-h-[44px] items-center">{{ __('cit_outils.effacer_la_recherche') }}</button>
      </div>
    @endforelse
  </section>

  @if($coursOsint !== null)
    <section class="mt-12 rounded-2xl p-6 sm:p-8 bg-primary-soft border border-primary/20 flex flex-col sm:flex-row sm:items-center gap-5">
      <span class="text-3xl shrink-0" aria-hidden="true">🎓</span>
      <div class="flex-1">
        <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_outils.vous_connaissez_l_outil_maintenant_apprenez_a') }}</h2>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_outils.le_cours') }} <strong class="text-ink">{{ $coursOsint->title }}</strong> {{ __('cit_outils.vous_accompagne_etape_par_etape_dans') }}</p>
      </div>
      <a href="{{ route('citizen.course', $coursOsint) }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-3 hover:bg-primary-dark min-h-[48px]">{{ __('cit_outils.decouvrir_le_cours') }} <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">arrow_forward</span></a>
    </section>
  @endif

  <p class="text-xs text-slate2 mt-6 mb-0">{{ __('cit_outils.outils_externes_selectionnes_a_titre_informatif') }} <a href="{{ route('citizen.verify') }}" class="font-semibold text-primary">{{ __('cit_outils.soumettez_le_contenu_au_laboratoire') }}</a>.</p>
</div>
