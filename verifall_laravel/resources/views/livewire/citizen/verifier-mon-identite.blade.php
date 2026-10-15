{{--
  | Vérifier mon identité — le dépôt qui n'existait pas.
  |
  | Le profil affichait « Identité non vérifiée » sans aucune action possible.
  | Cet écran est la moitié manquante, et il commence par RÉPONDRE aux trois
  | questions qu'on se pose avant d'envoyer une carte d'identité : qui la voit,
  | combien de temps elle est gardée, ce qui en est fait.
  |
  | Aucune classe nouvelle : uniquement celles du build figé et de `citizen.css`.
--}}
<div class="py-8">
  <div class="mx-auto w-full max-w-3xl px-4">

    <h1 class="text-2xl font-display font-bold text-ink mb-1">{{ __('cit_kyc.verifier_mon_identite') }}</h1>
    <p class="text-slate2 mb-6">{{ __('cit_kyc.la_verification_ouvre_les_fonctions_reservees') }}</p>

    {{-- ── L'ÉTAT ACTUEL, avant tout formulaire ────────────────────────────
         Proposer un dépôt à quelqu'un dont le dossier est en cours d'examen
         l'inviterait à recommencer, et le service refuserait. --}}
    @if($utilisateur->identiteVerifiee())
      <div class="card p-6 mb-6">
        <p class="text-sm font-display font-bold text-ink mb-1">
          <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">verified</span>
          {{ __('cit_kyc.votre_identite_est_verifiee') }}
        </p>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_kyc.il_n_y_a_rien_de_plus_a_faire') }}</p>
      </div>

    @elseif($dossier !== null && $dossier->status->estOuvert())
      <div class="card p-6 mb-6 bg-canvas">
        <p class="text-sm font-display font-bold text-ink mb-1">{{ $dossier->status->libelle() }}</p>
        <p class="text-sm text-slate2 mb-0">{{ $dossier->status->messageAuCitoyen() }}</p>
        <p class="text-xs text-slate2 mb-0 mt-2">
          {{ __('cit_kyc.depose') }} {{ $dossier->created_at?->diffForHumans() }}
        </p>
      </div>

    @else
      @if($dossier !== null && $dossier->rejection_reason)
        {{-- Le motif du refus EN TÊTE et en entier : c'est la seule chose qui
             empêche de redéposer exactement la même photo illisible. --}}
        <div class="card p-6 mb-6">
          <p class="text-sm font-display font-bold text-vfalse mb-1">{{ __('cit_kyc.dossier_precedent_refuse') }}</p>
          <p class="text-sm text-slate2 mb-0">{{ $dossier->rejection_reason }}</p>
        </div>
      @endif

      {{-- ── CE QU'ON FAIT DES PIÈCES ──────────────────────────────────────
           Avant de demander, pas après. --}}
      <div class="card p-6 mb-6 bg-canvas">
        <h2 class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_kyc.ce_que_nous_faisons_de_vos_pieces') }}</h2>
        <ul class="text-sm text-slate2 space-y-1.5 mb-0 list-none p-0">
          <li>· {{ __('cit_kyc.seules_les_personnes_habilitees_les_voient') }}</li>
          <li>· {{ __('cit_kyc.les_images_sont_detruites_apres_la_decision', ['jours' => $retentionJours]) }}</li>
          <li>· {{ __('cit_kyc.le_numero_n_est_jamais_conserve_en_clair') }}</li>
          <li>· {{ __('cit_kyc.aucune_piece_n_est_accessible_par_une_adresse') }}</li>
        </ul>
      </div>

      <form wire:submit="deposer" class="card p-6">
        <div class="mb-4">
          <label for="kyc-type" class="text-sm font-bold text-ink">{{ __('cit_kyc.type_de_piece') }}</label>
          <select id="kyc-type" wire:model.live="type" class="w-full rounded-xl border border-line p-3 text-sm text-ink mt-1">
            @foreach($pieces as $piece)
              <option value="{{ $piece->value }}">{{ $piece->libelle() }}</option>
            @endforeach
          </select>
          @error('type') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
          <label for="kyc-numero" class="text-sm font-bold text-ink">{{ __('cit_kyc.numero_de_la_piece') }}</label>
          <input id="kyc-numero" type="text" wire:model="numero" autocomplete="off"
                 class="w-full rounded-xl border border-line p-3 text-sm text-ink mt-1">
          @error('numero') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
          <label for="kyc-recto" class="text-sm font-bold text-ink">{{ __('cit_kyc.recto_de_la_piece') }}</label>
          <input id="kyc-recto" type="file" wire:model="recto" accept="image/jpeg,image/png,image/webp"
                 class="w-full rounded-xl border border-line p-3 text-sm text-ink mt-1">
          <p class="text-xs text-slate2 mt-1 mb-0">{{ __('cit_kyc.photo_nette_les_quatre_coins_visibles') }}</p>
          <div wire:loading wire:target="recto" class="text-xs text-slate2">{{ __('cit_kyc.envoi_en_cours') }}</div>
          @error('recto') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        {{-- Le verso n'est demandé que s'il existe : un passeport porte tout
             sur sa page d'identité, et exiger un verso inexistant bloquerait le
             dépôt sans que personne comprenne pourquoi. --}}
        @if($choisi?->exigeLeVerso())
          <div class="mb-4">
            <label for="kyc-verso" class="text-sm font-bold text-ink">{{ __('cit_kyc.verso_de_la_piece') }}</label>
            <input id="kyc-verso" type="file" wire:model="verso" accept="image/jpeg,image/png,image/webp"
                   class="w-full rounded-xl border border-line p-3 text-sm text-ink mt-1">
            <div wire:loading wire:target="verso" class="text-xs text-slate2">{{ __('cit_kyc.envoi_en_cours') }}</div>
            @error('verso') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
          </div>
        @endif

        <div class="mb-4">
          <label for="kyc-selfie" class="text-sm font-bold text-ink">{{ __('cit_kyc.photo_de_vous_facultative') }}</label>
          <input id="kyc-selfie" type="file" wire:model="selfie" accept="image/jpeg,image/png,image/webp"
                 class="w-full rounded-xl border border-line p-3 text-sm text-ink mt-1">
          <p class="text-xs text-slate2 mt-1 mb-0">{{ __('cit_kyc.elle_accelere_l_examen_sans_etre_obligatoire') }}</p>
          <div wire:loading wire:target="selfie" class="text-xs text-slate2">{{ __('cit_kyc.envoi_en_cours') }}</div>
          @error('selfie') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        {{-- Consentement EXPLICITE, décoché par défaut. Une case pré-cochée
             n'est pas un consentement. --}}
        <div class="mb-5">
          <label class="flex items-start gap-2 text-sm text-slate2">
            <input type="checkbox" wire:model="consentement" class="mt-1">
            <span>{{ __('cit_kyc.j_autorise_verif_all_a_examiner_ces_pieces', ['jours' => $retentionJours]) }}</span>
          </label>
          @error('consentement') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="deposer,recto,verso,selfie"
                class="rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:opacity-90">
          <span wire:loading.remove wire:target="deposer">{{ __('cit_kyc.deposer_mon_dossier') }}</span>
          <span wire:loading wire:target="deposer">{{ __('cit_kyc.depot_en_cours') }}</span>
        </button>
      </form>
    @endif

    <p class="text-sm mt-6 mb-0">
      <a href="{{ route('citizen.profile') }}" class="font-semibold text-primary">{{ __('cit_kyc.retour_au_profil') }}</a>
    </p>
  </div>
</div>
