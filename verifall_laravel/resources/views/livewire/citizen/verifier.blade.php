{{--
  | « Vérifier une information » — transposition de compte-user/verifier.html.
  |
  | Structure, classes et ordre des blocs repris du prototype : le fil des
  | étapes, les cinq types avec leur coût, le contenu, le contexte, le bandeau
  | de coût et la colonne latérale.
--}}
<div>
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div>
      <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_verifier.fil_d_ariane') }}">
        <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
        <span class="text-line" aria-hidden="true">/</span>
        <span class="text-ink font-medium">{{ __('cit_verifier.verifier') }}</span>
      </nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_verifier.verifier_une_information') }}</h1>
      <p class="text-slate2 mt-1 mb-0">{{ __('cit_verifier.choisissez_le_format_donnez_nous_le_contenu') }}</p>
    </div>
  </div>

  <div class="flex items-center gap-2 text-[11px] font-bold tracking-[.14em] text-slate2 mb-6" aria-hidden="true">
    <span class="text-primary">{{ __('cit_verifier.etape_contenu') }}</span><span class="flex-1 h-px bg-line"></span>
    <span>{{ __('cit_verifier.etape_sources') }}</span><span class="flex-1 h-px bg-line"></span>
    <span>{{ __('cit_verifier.etape_preuves') }}</span><span class="flex-1 h-px bg-line"></span>
    <span>{{ __('cit_verifier.etape_analyse') }}</span><span class="flex-1 h-px bg-line"></span>
    <span>{{ __('cit_verifier.etape_verdict') }}</span>
  </div>

  {{-- ─────────────────────────────────────────── analyse en cours --}}
  @if($verification && ! $verification->estTerminee())
    <div class="card p-6 mb-5 border-primary" wire:poll.3s="rafraichir">
      <div class="flex flex-wrap items-center gap-4">
        <span class="material-symbols-rounded text-primary animate-spin !text-[28px]" aria-hidden="true">progress_activity</span>
        <div class="flex-1 min-w-[240px]">
          <p class="text-base font-bold text-ink mb-0.5">{{ $verification->status->libelle() }}</p>
          <p class="text-sm text-slate2 mb-0">
            {{ $verification->reference() }} · {{ $verification->type->libelle() }} —
            « {{ $verification->apercu(70) }} »
          </p>
        </div>
        <button type="button" wire:click="fermerSuivi" class="text-sm font-bold text-slate2 hover:text-ink">{{ __('cit_verifier.masquer') }}</button>
      </div>
    </div>
  @elseif($verification && $verification->report)
    <div class="card p-6 mb-5 border-primary">
      <div class="flex flex-wrap items-center gap-4">
        {{--
          | `verdict` est NULLABLE dès qu'un rapport est MIXTE OU INSUFFISANT
          | (contrat V-8) — `estMixte()` seul laissait passer le second cas
          | vers `verdict->badge()` sur `null`. On teste `verdict !== null`
          | avant de le déréférencer, ce qui couvre les deux à la fois.
        --}}
        <span class="{{ $verification->report->verdict !== null ? $verification->report->verdict->badge() : $verification->report->composition_status->badge() }}">{{ $verification->report->verdict !== null ? $verification->report->verdict->libelle() : $verification->report->composition_status->libelle() }}</span>
        <p class="flex-1 text-sm text-ink mb-0">
          {{ __('cit_verifier.rapport_pret_pour') }} {{ $verification->reference() }}.
        </p>
        <button type="button" wire:click="fermerSuivi" class="text-sm font-bold text-slate2 hover:text-ink">{{ __('cit_verifier.masquer') }}</button>
      </div>
    </div>
  @endif

  <div class="grid lg:grid-cols-12 gap-5 items-start">
    <div class="lg:col-span-8 card p-6">

      {{-- ① type de contenu --}}
      <p class="text-sm font-semibold text-ink mb-3">{{ __('cit_verifier.1_quel_type_de_contenu_voulez_vous') }}</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 mb-6">
        @foreach($types as $unType)
          {{-- Un module fermé garde son bouton, VISIBLE mais inactif, et le
               clic explique pourquoi par un toast. Le retirer laisserait croire
               que la plateforme n'analyse pas la vidéo — c'est une
               indisponibilité, pas un périmètre. Et l'état se déduit du moteur
               de transcription : le bouton se rouvrira tout seul. --}}
          @php($ferme = in_array($unType->value, $typesFermes, true))
          <button type="button" wire:click="choisirType('{{ $unType->value }}')"
                  aria-disabled="{{ $ferme ? 'true' : 'false' }}"
                  class="vtype-btn pill-tab !py-3 flex-col !rounded-xl relative {{ $type === $unType->value ? 'is-active' : '' }} {{ $ferme ? 'is-ferme' : '' }}">
            <span class="material-symbols-rounded" aria-hidden="true">{{ $unType->icone() }}</span>
            <span>{{ $unType->libelle() }}</span>
            <span class="badge-cost absolute top-0 right-0 m-2 rounded-full bg-brandred text-white text-[10px] font-bold px-1.5 py-0.5 leading-none">
              {{ $unType->cout() }} {{ trans_choice('cit_verifier.abreviation_points', $unType->cout()) }}
            </span>
          </button>
        @endforeach
      </div>

      {{-- ② le contenu --}}
      <p class="text-sm font-semibold text-ink mb-3">{{ __('cit_verifier.2_donnez_nous_le_contenu') }}</p>

      <form wire:submit="lancer">
        @if($typeChoisi === App\Enums\TypeContenu::Texte)
          <div class="ffield">
            <textarea id="v-text" placeholder=" " maxlength="1000"
                      wire:model.live.debounce.350ms="texte"></textarea>
            <label for="v-text">{{ __('cit_verifier.collez_le_message_la_rumeur_ou_l') }}</label>
          </div>
          {{-- Compteur rendu par le serveur : le script du prototype ne voit
               pas les valeurs réinjectées par Livewire. --}}
          <p class="char-counter">{{ mb_strlen($texte) }} / 1000</p>
          @error('texte') <p class="text-xs text-vfalse mb-0">{{ $message }}</p> @enderror

        @elseif($typeChoisi === App\Enums\TypeContenu::Lien)
          <div class="ffield">
            <input id="v-url" type="url" placeholder=" " wire:model="lien">
            <label for="v-url">{{ __('cit_verifier.collez_l_adresse_de_la_page_ou') }}</label>
          </div>
          @error('lien') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror

        @else
          <label for="v-file"
                 class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-line rounded-2xl px-6 py-10 text-center cursor-pointer hover:border-primary transition-colors">
            <span class="material-symbols-rounded text-primary !text-[32px]" aria-hidden="true">{{ $typeChoisi->icone() }}</span>
            <span class="text-sm font-semibold text-ink">{{ __('cit_verifier.choisir_un_fichier') }} {{ mb_strtolower($typeChoisi->libelle()) }}</span>
            <span class="text-xs text-slate2">{{ __('cit_verifier.il_reste_sur_nos_serveurs_prives_et') }}</span>
            <input id="v-file" type="file" class="sr-only" wire:model="fichier">
          </label>

          <div wire:loading wire:target="fichier" class="text-xs text-slate2 mt-2">{{ __('cit_verifier.envoi_du_fichier') }}</div>

          @if($fichier)
            <p class="text-sm text-vtrue font-medium mt-2">{{ $fichier->getClientOriginalName() }}</p>
          @endif
          @error('fichier') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
        @endif

        {{-- ③ contexte --}}
        <p class="text-sm font-semibold text-ink mt-6 mb-3">{!! __('cit_verifier.3_un_contexte_a_ajouter_facultatif') !!}</p>
        <div class="ffield">
          <input id="v-context" type="text" placeholder=" " maxlength="140"
                 wire:model.live.debounce.350ms="contexte">
          <label for="v-context">{{ __('cit_verifier.ou_l_avez_vous_vu_depuis_quand') }}</label>
        </div>
        <p class="char-counter">{{ mb_strlen($contexte) }} / 140</p>
        @error('contexte') <p class="text-xs text-vfalse mb-0">{{ $message }}</p> @enderror

        {{-- coût annoncé --}}
        <div class="mt-6 flex items-center justify-between flex-wrap gap-3">
          <p class="text-xs text-slate2 mb-0">
            <span class="material-symbols-rounded !text-[16px] text-primary" aria-hidden="true">toll</span>
            {{ __('cit_verifier.cette_verification_consommera') }}
            <strong class="text-ink">{{ trans_choice('cit_verifier.compte_unites', $cout, ['nombre' => $cout]) }}</strong> —
            @if($soldeSuffisant)
              {{ __('cit_verifier.il_vous_restera') }} <strong class="text-ink">{{ trans_choice('cit_verifier.compte_unites', $soldeApres, ['nombre' => $soldeApres]) }}</strong>.
            @else
              <strong class="text-vfalse">{{ __('cit_verifier.votre_solde_est_insuffisant') }}</strong>.
            @endif
          </p>

          <button type="submit" @disabled(! $soldeSuffisant)
                  class="inline-flex items-center gap-2 rounded-xl bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark disabled:opacity-40 disabled:cursor-not-allowed"><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">travel_explore</span> <span wire:loading.remove wire:target="lancer">{{ __('cit_verifier.lancer_la_verification') }}</span> <span wire:loading wire:target="lancer">{{ __('cit_verifier.lancement') }}</span></button>
        </div>

        @unless($soldeSuffisant)
          <p class="text-xs text-slate2 mt-2 mb-0">
            <a href="{{ route('citizen.units') }}" class="font-semibold text-primary">{{ __('cit_verifier.rechargez_votre_compte') }}</a>
            {{ __('cit_verifier.pour_lancer_cette_verification') }}
          </p>
        @endunless
      </form>
    </div>

    {{-- colonne latérale --}}
    <aside class="lg:col-span-4 space-y-4">
      <div class="card p-5">
        <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_verifier.le_saviez_vous') }}</p>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_verifier.un_contexte_precis_source_date_de_reception') }}</p>
      </div>

      <div class="card p-5">
        <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_verifier.votre_solde') }}</p>
        <p class="text-2xl font-display font-black text-ink mb-1">
          <span data-units-badge>{{ $utilisateur->units }}</span>
          <span class="text-sm font-bold text-slate2">{{ __('cit_verifier.unites') }}</span>
        </p>
        <a href="{{ route('citizen.units') }}" class="text-sm font-semibold text-primary">{{ __('cit_verifier.gerer_mes_unites') }}</a>
      </div>

      <div class="card p-5">
        <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_verifier.verifier_soi_meme') }}</p>
        <p class="text-sm text-slate2 mb-2">{{ __('cit_verifier.le_repertoire_d_outils_osint_vous_forme') }}</p>
        <a href="{{ route('citizen.tools') }}" class="text-sm font-semibold text-primary">{{ __('cit_verifier.voir_les_outils') }}</a>
      </div>
    </aside>
  </div>
</div>
