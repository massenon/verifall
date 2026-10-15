@push('styles')
  <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/rapport.css') }}">
@endpush

{{--
  | `/rapport/{uuid}` — le même rapport, pour qui n'a pas de compte.
  |
  | Aucune modale d'inscription, aucun bandeau qui recouvre le verdict :
  | quelqu'un arrive ici parce qu'on lui a envoyé un démenti, ou parce qu'il
  | vient de scanner un code sur une feuille. Lui barrer la lecture pour lui
  | vendre un compte, c'est lui apprendre à ne plus cliquer sur nos liens.
  |
  | L'invitation à vérifier vient APRÈS, dans la colonne, à côté de la preuve
  | qu'il est en train de lire.
--}}
<div>
  <div class="vf-report-shell">

    {{-- Bandeau d'authenticité — la première chose que doit savoir un visiteur. --}}
    <div class="vf-public-bandeau no-print">
      <span class="material-symbols-rounded text-primary" aria-hidden="true">verified_user</span>
      <p class="mb-0">
        {{ __('pub_rapport.rapport_authentique') }}
        <span class="font-mono font-bold text-ink">{{ $verification->reference() }}</span>
      </p>
    </div>

    <x-rapport.corps
      :verification="$verification"
      :rapport="$rapport"
      :moteurs="$moteurs"
      :sources="$sources"
      :haine="$haine"
      :synthese="$synthese"
      :dissidents="$dissidents"
      :qr-code="$qrCode"
      :public="true">

      {{-- Pont vers l'action : le visiteur a un doute à lui, maintenant. --}}
      <div class="vf-card bg-surface border border-line no-print">
        <h2 class="text-sm font-display font-bold text-ink mb-2">{{ __('pub_rapport.un_doute_sur_une_autre_information') }}</h2>
        <p class="text-xs text-slate2 mb-3 leading-relaxed">{{ __('pub_rapport.soumettez_la_gratuitement') }}</p>
        <a href="{{ route('public.verify') }}"
           class="flex items-center justify-center gap-2 w-full rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2 hover:bg-primary-dark transition-colors">
          <span class="material-symbols-rounded" aria-hidden="true">search_check</span>
          {{ __('pub_rapport.verifier_une_information') }}
        </a>
      </div>

      {{-- Signaler un fait nouveau.
           Cadré : la page publique n'ouvre pas de contre-expertise elle-même —
           ce droit appartient à l'auteur de la demande. Elle oriente vers le
           formulaire de contact de la rédaction, qui est la bonne porte pour un
           tiers qui détient un élément que l'analyse n'avait pas. --}}
      <div class="vf-card bg-canvas border border-line no-print">
        <h2 class="text-sm font-display font-bold text-ink mb-2">{{ __('pub_rapport.ce_verdict_vous_parait_incomplet') }}</h2>
        <p class="text-xs text-slate2 mb-3 leading-relaxed">{{ __('pub_rapport.signalez_un_fait_nouveau') }}</p>
        <a href="{{ route('public.contact') }}"
           class="vf-btn-secondaire flex items-center justify-center gap-2 w-full rounded-xl text-sm font-semibold px-4 py-2">
          <span class="material-symbols-rounded" aria-hidden="true">forum</span>
          {{ __('pub_rapport.ecrire_a_la_redaction') }}
        </a>
      </div>

      {{-- Partage. Le lien est déjà public : rien à convertir. --}}
      <div class="vf-card bg-surface border border-line no-print">
        <h2 class="text-sm font-display font-bold text-ink mb-2 flex items-center gap-2">
          <span class="material-symbols-rounded text-primary" aria-hidden="true">campaign</span>
          {{ __('cit_rapport.diffuser_la_verite') }}
        </h2>
        <p class="text-xs text-slate2 mb-3">{{ __('cit_rapport.partagez_ce_rapport_pour_stopper_la_propagation') }}</p>
        <x-menu-partage
          :lien="$lienPublic"
          :verdict="$rapport->verdict"
          :affirmation="$verification->apercu(120)"
          aligne="gauche" />
      </div>
    </x-rapport.corps>

    <p class="vf-public-pied no-print">
      {{ __('pub_rapport.mention_de_bas_de_page') }}
    </p>
  </div>
</div>
