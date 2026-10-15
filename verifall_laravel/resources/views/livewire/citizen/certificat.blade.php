@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-certificat.css') }}">
@endpush

{{--
  | Certificat — transposition de compte-user/certificat.html.
  |
  | Le code de vérification est mis en avant : c'est lui qui donne sa valeur au
  | diplôme. Un certificat que personne ne peut contrôler ne vaut rien.
--}}
<div class="max-w-3xl mx-auto">

  <nav class="text-xs text-slate2 mb-3 no-print" aria-label="{{ __('cit_certificat.fil_d_ariane') }}">
    <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
    <span class="text-line" aria-hidden="true">/</span>
    <a href="{{ route('citizen.rewards') }}" class="hover:text-primary">{{ __('cit_certificat.certificats_et_badges') }}</a>
    <span class="text-line" aria-hidden="true">/</span>
    <span class="text-ink font-medium">{{ __('cit_certificat.certificat') }}</span>
  </nav>

  @if($certificat->estValide())
    {{-- Le document lui-même vient d'un composant partagé avec la page publique
         de vérification : le recruteur doit voir exactement ce que le titulaire
         a imprimé. --}}
    <div class="mb-5">
      <x-lms.certificat :certificat="$certificat" :habillage="$habillage" :qr-code="$qrCode" />
    </div>

    <div class="flex flex-wrap gap-2 justify-center no-print">
      {{-- Impression du navigateur plutôt qu'un PDF fabriqué côté serveur :
           le résultat est identique, imprimable et enregistrable en PDF depuis
           n'importe quel appareil, sans dépendance supplémentaire. --}}
      <button type="button" onclick="window.print()"
              class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">{!! __('cit_certificat.print_imprimer_enregistrer_en_pdf') !!}</button>
      <a href="{{ route('citizen.course', $certificat->course) }}"
         class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">
        {{ __('cit_certificat.revoir_le_cours') }}
      </a>
    </div>

  @else
    {{-- Un certificat révoqué reste consultable par son titulaire : le lui
         cacher le laisserait sans explication devant un tiers qui, lui, voit
         « révoqué ». --}}
    <div class="card p-8 text-center">
      <span class="material-symbols-rounded text-vfalse !text-[52px] mb-3" aria-hidden="true">gpp_bad</span>
      <h1 class="text-2xl font-display font-bold text-ink mb-2">{{ __('cit_certificat.certificat_revoque') }}</h1>
      <p class="text-sm text-slate2 mb-1">
        {{ __('cit_certificat.ce_certificat_a_ete_retire_le') }} {{ $certificat->revoked_at->translatedFormat('j F Y') }}.
      </p>
      @if($certificat->revoked_reason)
        <p class="text-sm text-slate2 mb-4">{{ __('cit_certificat.motif') }} {{ $certificat->revoked_reason }}</p>
      @endif
      <a href="{{ route('public.contact') }}" class="text-sm font-semibold text-primary">{{ __('cit_certificat.contester_cette_decision') }}</a>
    </div>
  @endif

</div>
