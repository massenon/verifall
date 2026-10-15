{{--
  | « Mes unités » — transposition de compte-user/unites.html.
  |
  | Structure, classes et ordre des blocs repris du prototype. Le client ne
  | choisit qu'un identifiant de pack : ni le prix ni la quantité ne transitent
  | par le navigateur.
--}}
<div>
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div>
      <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_unites.fil_d_ariane') }}">
        <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
        <span class="text-line" aria-hidden="true">/</span>
        <span class="text-ink font-medium">{{ __('cit_unites.mes_unites') }}</span>
      </nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_unites.mes_unites') }}</h1>
      <p class="text-slate2 mt-1 mb-0">{{ __('cit_unites.la_monnaie_de_vos_verifications_et_de') }}</p>
    </div>
  </div>

  {{-- ───────────────────────────────────────────────── les trois cartes --}}
  <div class="grid md:grid-cols-3 gap-4 mb-6">
    <div class="card p-6 bg-forest !border-forest text-white">
      <p class="text-xs font-bold tracking-[.14em] text-white/50 uppercase mb-2">{{ __('cit_unites.solde_actuel') }}</p>
      <p class="text-5xl font-display font-black leading-none mb-1" data-units-badge>{{ $utilisateur->units }}</p>
      <p class="text-sm text-white/70 mb-0">{{ trans_choice('cit_unites.compte_unites_disponibles', (int) $utilisateur->units) }}</p>
    </div>

    <div class="card p-6">
      <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_unites.dotation_mensuelle') }}</p>
      <p class="text-sm text-slate2 mb-3">
        {{ config('economy.monthly_grant.units') }} {{ __('cit_unites.unites_offertes_chaque_1er_du_mois') }}
        {{ __('cit_unites.prochaine_dotation') }} <strong class="text-ink">{{ $this->prochaineDotation['date'] }}</strong>.
      </p>
      <span class="vbadge vbadge-progress">
        {{ trans_choice('cit_unites.dans_n_jours', (int) $this->prochaineDotation['jours'], ['nombre' => $this->prochaineDotation['jours']]) }}
      </span>
    </div>

    <div class="card p-6">
      <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_unites.recharger') }}</p>
      <p class="text-sm text-slate2 mb-3">
        {{ __('cit_unites.packs_a_partir_de') }} {{ number_format((int) $packs->min('price_fcfa'), 0, ',', ' ') }} FCFA
        {{ __('cit_unites.via_mobile_money') }}
      </p>
      <button type="button" wire:click="ouvrirAchat"
              class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark">{!! __('cit_unites.bolt_acheter_des_unites') !!}</button>
    </div>
  </div>

  {{-- ─────────────────────────────────────────────── attente du paiement --}}
  @if($paiement && $paiement->estEnAttente() && ! $paiement->aExpire())
    <div class="card p-6 mb-6 border-primary" wire:poll.3s="rafraichirPaiement">
      <div class="flex flex-wrap items-center gap-4">
        <span class="material-symbols-rounded text-primary animate-spin !text-[28px]" aria-hidden="true">progress_activity</span>
        <div class="flex-1">
          <p class="text-base font-bold text-ink mb-0.5">
            {{ __('cit_unites.validez_la_demande_sur_votre_telephone') }} ({{ $paiement->provider->ussd() }})
          </p>
          <p class="text-sm text-slate2 mb-0">
            {{ number_format($paiement->amount_fcfa, 0, ',', ' ') }} {{ __('cit_unites.fcfa_pour') }} {{ $paiement->units }} {{ __('cit_unites.unites') }} ·
            {{ $paiement->provider->libelle() }} · {{ $paiement->numeroMasque() }}
          </p>
        </div>

        @if($simulable)
          {{-- Développement uniquement. Passe par le VRAI rappel signé : une
               simulation qui court-circuiterait le webhook ne prouverait rien
               de la chaîne qu'elle exerce. --}}
          <button type="button" wire:click="simulerPaiement"
                  class="rounded-xl border border-primary text-primary text-sm font-semibold px-4 py-2.5 hover:bg-primary hover:text-white transition-colors">{{ __('cit_unites.simuler_le_paiement') }}</button>
        @endif

        <button type="button" wire:click="abandonnerPaiement"
                class="text-sm font-bold text-slate2 hover:text-vfalse">{{ __('cit_unites.annuler') }}</button>
      </div>
    </div>
  @endif

  {{-- ──────────────────────────────────────────────────── historique --}}
  <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_unites.historique_des_transactions') }}</h2>

  <div class="card p-4 mb-4 flex flex-col md:flex-row gap-3">
    <div class="relative flex-1">
      <label class="sr-only" for="tx-search">{{ __('cit_unites.rechercher_une_transaction') }}</label>
      <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate2 !text-[19px]" aria-hidden="true">search</span>
      <input id="tx-search" type="search" placeholder="{{ __('cit_unites.rechercher_une_transaction_placeholder') }}"
             wire:model.live.debounce.350ms="recherche"
             class="w-full bg-canvas border border-line rounded-xl pl-10 pr-3 py-2.5 text-sm outline-none focus:border-primary">
    </div>

    <select wire:model.live="sens" aria-label="{{ __('cit_unites.filtrer_par_type') }}"
            class="bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm">
      <option value="">{{ __('cit_unites.type_tous') }}</option>
      <option value="credit">{{ __('cit_unites.credit') }}</option>
      <option value="debit">{{ __('cit_unites.debit') }}</option>
    </select>

    <select wire:model.live="parPage" aria-label="{{ __('cit_unites.transactions_par_page') }}"
            class="bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm">
      <option value="10">10 {{ __('cit_unites.par_page') }}</option>
      <option value="25">25 {{ __('cit_unites.par_page') }}</option>
      <option value="50">50 {{ __('cit_unites.par_page') }}</option>
    </select>

    <button type="button" wire:click="exporterCsv"
            class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas">{!! __('cit_unites.download_exporter_csv') !!}</button>

    <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas">{!! __('cit_unites.print_imprimer') !!}</button>
  </div>

  @if($mouvements->total() === 0)
    <p class="card p-8 text-center text-sm text-slate2">
      @if($recherche !== '' || $sens !== '')
        {{ __('cit_unites.aucune_transaction_ne_correspond_a_vos_filtres') }}
      @else
        {{ __('cit_unites.aucun_mouvement_pour_l_instant_votre_premiere') }} {{ $this->prochaineDotation['date'] }}.
      @endif
    </p>
  @else
    <div class="table-wrap card overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">{{ __('cit_unites.ref') }}</th>
            <th scope="col">{{ __('cit_unites.libelle') }}</th>
            <th scope="col">{{ __('cit_unites.montant') }}</th>
            <th scope="col">{{ __('cit_unites.type') }}</th>
            <th scope="col">{{ __('cit_unites.date') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($mouvements as $mouvement)
            <tr wire:key="tx-{{ $mouvement->id }}" data-row-el>
              <td class="font-mono text-xs text-slate2">{{ $mouvement->reference() }}</td>
              <td>{{ $mouvement->libelleAffiche() }}</td>
              <td class="font-bold {{ $mouvement->estCredit() ? 'text-vtrue' : 'text-ink' }}">
                {{ $mouvement->montantAffiche() }}
              </td>
              <td>
                <span class="vbadge {{ $mouvement->estCredit() ? 'vbadge-true' : 'vbadge-doubt' }}">
                  {{ $mouvement->estCredit() ? __('cit_unites.credit') : __('cit_unites.debit') }}
                </span>
              </td>
              <td class="text-slate2">{{ $mouvement->created_at?->translatedFormat('d M Y') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4">{{ $mouvements->links() }}</div>
  @endif

  {{-- ───────────────────────────────────────────── modale : achat --}}
  @if($modaleAchat)
    <div class="modal-backdrop is-open" wire:key="modale-achat">
      <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="buy-title">
        <h2 id="buy-title" class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_unites.acheter_des_unites') }}</h2>
        <p class="text-sm text-slate2 mb-4">{{ __('cit_unites.paiement_securise_via_mobile_money_mtn_momo') }}</p>

        <div class="grid grid-cols-3 gap-2 mb-4">
          @foreach($packs as $definition)
            <button type="button" wire:click="choisirPack('{{ $definition->code }}')" wire:key="pack-{{ $definition->id }}"
                    class="pack-btn rounded-xl border-2 p-3 text-center transition-colors
                           {{ $pack === $definition->code ? 'border-primary bg-primary-soft' : 'border-line hover:border-primary' }}">
              <span class="block text-xl font-display font-black text-ink">{{ $definition->units }}</span>
              <span class="block text-xs text-slate2">{{ number_format($definition->price_fcfa, 0, ',', ' ') }} FCFA</span>
            </button>
          @endforeach
        </div>

        <form wire:submit="payer">
          <div class="ffield mb-2">
            <input id="buy-phone" type="tel" inputmode="tel" placeholder=" " maxlength="14" wire:model="numero">
            <label for="buy-phone">{{ __('cit_unites.numero_mobile_money_ex_97_00_00') }}</label>
          </div>
          @error('numero') <p class="text-xs text-vfalse mb-3">{{ $message }}</p> @enderror

          <div class="flex gap-2 justify-end mt-4">
            <button type="button" wire:click="fermerAchat"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_unites.annuler') }}</button>
            <button type="submit"
                    class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">
              {{ __('cit_unites.payer') }} {{ number_format((int) $packs->firstWhere('code', $pack)?->price_fcfa, 0, ',', ' ') }} FCFA
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
