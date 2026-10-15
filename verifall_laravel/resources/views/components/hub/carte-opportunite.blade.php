@props(['offre'])

@php($restants = $offre->joursRestants())

{{--
  | Une opportunité du Hub.
  |
  | Le compte à rebours est affiché parce qu'une date brute ne dit rien à qui
  | parcourt vingt annonces : « plus que 3 jours » se lit d'un coup d'œil,
  | « 27 août 2026 » demande un calcul.
--}}
<article class="hub-card p-5 flex flex-col">

  <div class="flex flex-wrap items-center gap-1.5 mb-3">
    <span class="hub-badge hub-badge-ok">
      <span class="material-symbols-rounded !text-[14px]" aria-hidden="true">verified</span>{{ __('composants.offre_verifiee') }}
    </span>
    <span class="hub-badge hub-badge-neutral">{{ $offre->type->libelle() }}</span>
    @if($offre->location)
      <span class="hub-badge hub-badge-neutral">{{ $offre->location }}</span>
    @endif
  </div>

  <h3 class="text-base font-display font-bold text-ink leading-snug mb-0.5">{{ $offre->title }}</h3>
  <p class="text-sm text-slate2 mb-1.5">{{ $offre->organisation }}</p>
  <p class="text-sm text-slate2 mb-3">{{ Str::limit(strip_tags($offre->description), 120) }}</p>

  <div class="mt-auto">
    @if($restants === null)
      <p class="text-xs text-slate2 mb-3">{{ __('composants.offre_sans_date_limite_annoncee') }}</p>
    @elseif($restants < 0)
      {{-- Encore visible pendant le délai de grâce : les candidatures de la
           veille se traitent encore. Mais on ne prétend pas que c'est ouvert. --}}
      <p class="text-xs text-vdoubt font-semibold mb-3">
        {{ __('composants.offre_date_limite_depassee') }}
      </p>
    @elseif($restants === 0)
      <p class="text-xs text-vfalse font-semibold mb-3">{{ __('composants.offre_dernier_jour_pour_candidater') }}</p>
    @else
      <p class="text-xs {{ $restants <= 3 ? 'text-vfalse font-semibold' : 'text-slate2' }} mb-3">
        {{ __('composants.offre_plus_que') }} {{ $restants }} {{ $restants > 1 ? __('composants.offre_jours') : __('composants.offre_jour') }}
        {{ __('composants.offre_jusqu_au') }} {{ $offre->deadline->translatedFormat('j F Y') }}.
      </p>
    @endif

    {{-- L'avertissement ne s'affiche que sur les recrutements : l'écrire sous
         une conférence payante serait faux. --}}
    @if($offre->type->estUnEmploi())
      <p class="text-xs text-slate2 mb-3">{{ __('composants.offre_aucun_frais_de_dossier_jamais') }}</p>
    @endif

    @if($offre->apply_url)
      {{-- `noopener noreferrer` : le lien sort du produit, et le site d'arrivée
           n'a pas à savoir d'où vient la visite. --}}
      <a href="{{ $offre->apply_url }}" target="_blank" rel="noopener noreferrer"
         class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark min-h-[44px]">
        {{ __('composants.offre_voir_l_opportunite') }}
        <span class="material-symbols-rounded !text-[15px]" aria-hidden="true">arrow_forward</span>
      </a>
    @endif
  </div>

</article>
