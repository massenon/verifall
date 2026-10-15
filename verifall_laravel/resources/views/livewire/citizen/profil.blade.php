{{--
  | « Mon profil » — transposition de compte-user/profil.html.
  |
  | La structure, les classes et l'ordre des blocs sont ceux du prototype. Un
  | seul écart, signalé sur place : l'adresse e-mail sort du formulaire commun
  | pour passer par une vérification du nouveau destinataire.
--}}
<div>
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div>
      <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_profil.fil_d_ariane') }}">
        <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">{{ __('cit_profil.dashboard') }}</a>
        <span class="text-line" aria-hidden="true">/</span>
        <span class="text-ink font-medium">{{ __('cit_profil.mon_profil') }}</span>
      </nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_profil.mon_profil') }}</h1>
      <p class="text-slate2 mt-1 mb-0">{{ __('cit_profil.vos_informations_publiques_et_votre_identite_verifiee') }}</p>
    </div>
  </div>

  <div class="grid lg:grid-cols-3 gap-6 items-start">

    {{-- Carte d'identité --}}
    <div class="card p-8 text-center">
      <span class="inline-flex items-center justify-center w-28 h-28 rounded-full bg-primary-soft text-primary-dark font-display font-black text-3xl mb-4">{{ $utilisateur->initiales() }}</span>
      <h2 class="text-xl font-display font-bold text-ink mb-0.5">{{ $utilisateur->nomAffichage() }}</h2>
      {{-- Une directive collée à un mot (« vérificateur@if ») n'est pas
           reconnue par Blade et reste du texte : le @endif se retrouve alors
           orphelin et la vue ne compile plus. --}}
      <p class="text-sm text-slate2 mb-3">
        {{ __('cit_profil.citoyen_verificateur') }}{{ $utilisateur->city ? ' · '.$utilisateur->city : '' }}
      </p>

      {{-- Le badge du prototype est ici adossé à une date réelle : afficher une
           vérification que rien ne soutient serait pire que ne rien afficher. --}}
      @if($utilisateur->identiteVerifiee())
        <span class="inline-flex items-center gap-1 rounded-full bg-primary-soft text-primary-dark text-sm font-bold px-3.5 py-1.5 mb-4">
          <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">verified</span> {{ __('cit_profil.identite_verifiee_kyc') }}
        </span>
      @else
        {{-- Le badge non vérifié est désormais un LIEN.
             Il annonçait un état sans offrir aucun moyen d'en changer : la
             colonne `kyc_verified_at` n'était levable que depuis la console.
             La chaîne de dépôt existe depuis le 1er septembre 2026. --}}
        <a href="{{ route('citizen.kyc') }}"
           class="inline-flex items-center gap-1 rounded-full bg-canvas text-slate2 border border-line text-sm font-bold px-3.5 py-1.5 mb-4 hover:text-primary">
          <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">shield</span> {{ __('cit_profil.identite_non_verifiee') }}
          <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">chevron_right</span>
        </a>
      @endif

      <p class="text-sm text-slate2 mb-1">{{ __('cit_profil.membre_depuis') }} {{ $utilisateur->created_at?->translatedFormat('F Y') }}</p>
      <p class="text-sm text-slate2 mb-0">
        <span data-xp-badge>{{ number_format($utilisateur->xp, 0, ',', ' ') }}</span> XP
        &middot; {{ $utilisateur->streak_days }} {{ __('cit_profil.jour') }}{{ $utilisateur->streak_days > 1 ? 's' : '' }} {{ __('cit_profil.d_affilee') }}
      </p>
    </div>

    {{-- Informations personnelles --}}
    <div class="lg:col-span-2 card p-6 sm:p-8">
      <h2 class="text-lg font-display font-bold text-ink mb-5">{{ __('cit_profil.informations_personnelles') }}</h2>

      <form wire:submit="enregistrer">
        <div class="grid sm:grid-cols-2 gap-4 mb-4">
          <div>
            <label for="pf-prenom" class="block text-sm font-bold text-ink mb-1.5">{!! __('cit_profil.prenom') !!}</label>
            <input id="pf-prenom" type="text" wire:model="prenom" autocomplete="given-name"
                   class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 @error('prenom') border-vfalse @enderror">
            @error('prenom') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
          </div>
          <div>
            <label for="pf-nom" class="block text-sm font-bold text-ink mb-1.5">{!! __('cit_profil.nom') !!}</label>
            <input id="pf-nom" type="text" wire:model="nom" autocomplete="family-name"
                   class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 @error('nom') border-vfalse @enderror">
            @error('nom') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
          <div>
            <label for="pf-tel" class="block text-sm font-bold text-ink mb-1.5">{{ __('cit_profil.telephone') }}</label>
            <input id="pf-tel" type="tel" wire:model="telephone" autocomplete="tel" placeholder="+229 97 00 00 00"
                   class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 @error('telephone') border-vfalse @enderror">
            @error('telephone') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
          </div>
          <div>
            <label for="pf-ville" class="block text-sm font-bold text-ink mb-1.5">{{ __('cit_profil.ville') }}</label>
            <select id="pf-ville" wire:model="ville"
                    class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm outline-none focus:border-primary">
              <option value="">—</option>
              @foreach($villes as $nomVille)
                <option value="{{ $nomVille }}">{{ $nomVille }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="mb-6">
          <label for="pf-bio" class="block text-sm font-bold text-ink mb-1.5">{{ __('cit_profil.bio_publique') }}</label>
          <textarea id="pf-bio" rows="4" maxlength="240" wire:model.live.debounce.350ms="bio"
                    class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 resize-y @error('bio') border-vfalse @enderror"></textarea>
          {{-- Compteur rendu par le serveur : le script du prototype ne voit
               pas les valeurs réinjectées par Livewire. --}}
          <p class="char-counter">{{ mb_strlen($bio) }} / 240</p>
          @error('bio') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="inline-flex rounded-xl bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark">{!! __('cit_profil.enregistrer_les_modifications_enregistrement') !!}</button>
      </form>

      {{-- ------------------------------------------------ adresse e-mail --}}
      <div class="mt-8 pt-6 border-t border-line">
        <label class="block text-sm font-bold text-ink mb-1.5">{!! __('cit_profil.adresse_e_mail') !!}</label>

        @if(! $changementEmail)
          <div class="flex flex-wrap items-center gap-3">
            <input type="email" value="{{ $utilisateur->email }}" disabled
                   class="flex-1 bg-canvas border border-line rounded-xl px-4 py-3 text-sm text-slate2">
            <button type="button" wire:click="ouvrirChangementEmail"
                    class="rounded-xl border border-line px-5 py-3 text-sm font-semibold text-ink hover:border-primary">{{ __('cit_profil.modifier') }}</button>
          </div>
          <p class="text-xs text-slate2 mt-2 mb-0">{{ __('cit_profil.cette_adresse_vous_identifie_et_sert_a') }}</p>

        @elseif(! $codeEnvoye)
          <form wire:submit="envoyerCodeEmail" class="flex flex-wrap items-start gap-3">
            <div class="flex-1">
              <input type="email" wire:model="nouvelEmail" autocomplete="email" placeholder="{{ __('cit_profil.nouvelle_adresse_exemple') }}"
                     class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 @error('nouvelEmail') border-vfalse @enderror">
              @error('nouvelEmail') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-xl bg-primary text-white text-sm font-semibold px-5 py-3 hover:bg-primary-dark">{{ __('cit_profil.envoyer_le_code') }}</button>
            <button type="button" wire:click="annulerChangementEmail"
                    class="rounded-xl border border-line px-5 py-3 text-sm font-semibold text-slate2 hover:text-ink">{{ __('cit_profil.annuler') }}</button>
          </form>

        @else
          <form wire:submit="confirmerEmail" class="flex flex-wrap items-start gap-3">
            <div class="flex-1">
              <input type="text" inputmode="numeric" maxlength="6" wire:model="codeEmail" autocomplete="one-time-code"
                     placeholder="{{ __('cit_profil.code_a_6_chiffres') }}"
                     class="w-full bg-surface border border-line rounded-xl px-4 py-3 text-sm tracking-[0.4em] outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 @error('codeEmail') border-vfalse @enderror">
              @error('codeEmail') <p class="text-xs text-vfalse mt-1.5 mb-0">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-xl bg-primary text-white text-sm font-semibold px-5 py-3 hover:bg-primary-dark">{{ __('cit_profil.confirmer') }}</button>
            <button type="button" wire:click="annulerChangementEmail"
                    class="rounded-xl border border-line px-5 py-3 text-sm font-semibold text-slate2 hover:text-ink">{{ __('cit_profil.annuler') }}</button>
          </form>
          <p class="text-xs text-slate2 mt-2 mb-0">{{ __('cit_profil.le_code_expire_dans_10_minutes_tant') }}</p>
        @endif
      </div>

    </div>
  </div>
</div>
