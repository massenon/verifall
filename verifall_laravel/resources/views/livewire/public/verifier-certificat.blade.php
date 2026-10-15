@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-certificat.css') }}">
@endpush

{{--
  | Vérification publique d'un certificat — verify-certificate.html.
  |
  | Page autonome, sans le menu du site : voir `components/layouts/certificat`.
  | Publique et sans compte — un diplôme que personne ne peut contrôler ne vaut
  | rien, et obliger un recruteur à créer un compte le rendrait invérifiable.
--}}
<main class="flex-1 max-w-xl w-full mx-auto px-4 py-12">
  <h1 class="text-2xl md:text-3xl font-display font-bold text-ink text-center mb-2">{{ __('pub_verifier_certificat.verifier_un_certificat') }}</h1>
  <p class="text-sm text-center mb-8">{{ __('pub_verifier_certificat.chaque_certificat_verif_all_porte_un_identifiant') }}</p>

  <form wire:submit="verifier" class="bg-white border border-line rounded-2xl p-6 shadow-sm">
    <label for="cert-id" class="block text-sm font-semibold text-ink mb-2">{{ __('pub_verifier_certificat.identifiant_du_certificat') }}</label>
    <input id="cert-id" type="text" wire:model="code" placeholder="VERIF-A-1B2C-3D4E" autocomplete="off" spellcheck="false"
           class="w-full border border-line rounded-xl px-4 py-3 font-mono text-ink outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 uppercase">
    <button type="submit" class="mt-4 w-full rounded-xl bg-primary text-white font-semibold py-3 hover:bg-primary-dark">{{ __('pub_verifier_certificat.verifier_l_authenticite') }}</button>
  </form>

  @if($tropDeTentatives)
    {{-- Sans limite, la page devient un oracle : on y énumère des codes
         jusqu'à en trouver un qui existe. --}}
    <div class="mt-6 bg-white border-2 border-amber-300 rounded-2xl p-6 text-center">
      <p class="text-sm text-ink mb-0">{{ __('pub_verifier_certificat.trop_de_verifications_depuis_cette_adresse_reessayez') }}</p>
    </div>
  @elseif($cherche)
    @if($certificat && $certificat->estValide())
      <div class="mt-6 bg-white border-2 border-primary rounded-2xl p-6 text-center">
        <p class="text-4xl mb-2" aria-hidden="true">✅</p>
        <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('pub_verifier_certificat.certificat_authentique') }}</h2>
        <p class="text-sm mb-4">{{ __('pub_verifier_certificat.delivre_par_l_academie_verif_all') }}</p>
        <dl class="text-sm text-left max-w-xs mx-auto space-y-1.5">
          <div class="flex justify-between gap-4"><dt class="text-slate2">{{ __('pub_verifier_certificat.titulaire') }}</dt><dd class="font-semibold text-ink">{{ $certificat->holder_name }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-slate2">{{ __('pub_verifier_certificat.cours') }}</dt><dd class="font-semibold text-ink text-right">{{ $certificat->course_title }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-slate2">{{ __('pub_verifier_certificat.emis_le') }}</dt><dd class="font-semibold text-ink">{{ $certificat->issued_at->translatedFormat('j F Y') }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-slate2">{{ __('pub_verifier_certificat.identifiant') }}</dt><dd class="font-mono text-ink">{{ $certificat->code }}</dd></div>
        </dl>
      </div>

      {{-- LE DOCUMENT, sous le relevé de faits.
           Les deux répondent à deux questions successives : « ce code
           existe-t-il ? », puis « est-ce bien celui que je tiens ? ». Sans le
           second, il n'y a rien à comparer à la feuille qu'on vient de remettre
           au recruteur. --}}
      <div class="mt-6">
        <x-lms.certificat :certificat="$certificat" :habillage="$habillage" :qr-code="$qrCode" />
      </div>
    @elseif($certificat)
      <div class="mt-6 bg-white border-2 border-red-300 rounded-2xl p-6 text-center">
        <p class="text-4xl mb-2" aria-hidden="true">⚠️</p>
        <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('pub_verifier_certificat.certificat_revoque') }}</h2>
        <p class="text-sm mb-0">{{ __('pub_verifier_certificat.ce_certificat_a_bien_ete_delivre_puis') }} {{ $certificat->revoked_at->translatedFormat('j F Y') }}{{ __('pub_verifier_certificat.il_ne_fait_plus_foi') }}</p>
      </div>
    @else
      {{-- « Introuvable », jamais « faux » : un code mal recopié n'est pas une
           fraude, et l'accusation serait injuste comme inexacte. --}}
      <div class="mt-6 bg-white border-2 border-red-300 rounded-2xl p-6 text-center">
        <p class="text-4xl mb-2" aria-hidden="true">❌</p>
        <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('pub_verifier_certificat.certificat_introuvable') }}</h2>
        <p class="text-sm mb-0">{{ __('pub_verifier_certificat.aucun_certificat_ne_correspond_a_l_identifiant') }} <strong class="font-mono">{{ mb_strtoupper(trim($code)) }}</strong>{{ __('pub_verifier_certificat.verifiez_la_saisie_ou_mefiez_vous_d') }}</p>
      </div>
    @endif
  @endif
</main>
