@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/transparence.css') }}">
@endpush

{{--
  | Journal des corrections.
  |
  | Cette page n'existe pas dans le prototype : elle vient du code de conduite
  | de l'IFCN, qui demande à une rédaction de publier ses corrections. Sa mise
  | en page reprend celle des « Actualités » — même héros gris, même largeur de
  | colonne, mêmes cartes — pour qu'elle appartienne au site plutôt que d'y
  | être posée (voir docs/ARBITRAGES.md, entrée 147).
  |
  | Le gabarit public ouvre déjà un <main id="contenu"> autour de ce bloc : la
  | racine est donc un <div>, et non un second <main>.
--}}
<div>
  <section class="bg-[#f8f9fa] pt-36 pb-20">
    <div class="max-w-page mx-auto px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-display font-extrabold text-heading mb-3">{{ __('pub_corrections.journal_des_corrections') }}</h1>
        <p class="text-lg text-body/80 mb-4">
          {{ __('pub_corrections.quand_un_verdict_change_l_ancien_reste') }}
        </p>
        <p class="text-body/70 mb-0">
          {{ __('pub_corrections.une_plateforme_qui_reecrit_ses_verdicts_en') }}
        </p>
      </div>
    </div>
  </section>

  <section class="py-16">
    <div class="max-w-3xl mx-auto px-4">
      @if ($revisions->isNotEmpty())
        <ol class="list-none space-y-6">
          @foreach ($revisions as $revision)
            <li class="bg-white rounded-xl shadow-card border border-line p-6" wire:key="correction-{{ $revision->uuid }}">
              <div class="flex items-center flex-wrap gap-2 mb-4">
                <span class="verdict-ancien">{{ $revision->ancien_verdict->libelle() }}</span>
                <svg class="verdict-fleche" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                <span class="{{ $revision->nouveau_verdict->badgePublic() }}">{{ $revision->nouveau_verdict->libelle() }}</span>
                <time class="ml-auto text-sm text-body/70" datetime="{{ $revision->created_at?->toDateString() }}">
                  {{ $revision->created_at?->translatedFormat('j F Y') }}
                </time>
              </div>

              <h2 class="text-lg font-display font-bold text-heading leading-snug mb-2">{{ $revision->affirmation }}</h2>

              {{-- La raison, telle qu'elle a été écrite. Échappée : elle vient
                   d'une saisie humaine, et cette page est publique. --}}
              <p class="text-body/85 leading-relaxed mb-0">{{ $revision->raison }}</p>

              @php($lien = $revision->lienPublic())
              @if ($lien !== null)
                <a href="{{ $lien }}" class="inline-flex items-center gap-1.5 text-primary text-sm font-bold mt-4 hover:gap-3 transition-all">
                  {{ __('pub_corrections.lire_la_verification_corrigee') }}
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                </a>
              @endif
            </li>
          @endforeach
        </ol>

        <div class="mt-10">{{ $revisions->links() }}</div>
      @else
        {{-- Aucune correction n'est un fait, pas un vide à meubler. La page le
             dit sans s'en féliciter : un journal vide ne prouve pas qu'on ne
             s'est jamais trompé, seulement qu'on n'a pas encore corrigé. --}}
        <div class="bg-white rounded-xl shadow-card border border-line p-6 text-center">
          <p class="text-body/85 mb-2">{{ __('pub_corrections.aucun_verdict_n_a_encore_ete_corrige') }}</p>
          <p class="text-sm text-body/70 mb-0">
            {{ __('pub_corrections.des_qu_une_verification_publiee_changera_de') }}
          </p>
        </div>
      @endif

      <p class="text-sm text-body/70 mt-10 mb-0">
        {{ __('pub_corrections.notre_methode_de_verification_est_decrite_en') }}
        <a href="{{ route('public.methodology') }}" class="text-primary font-bold">{{ __('pub_corrections.methodologie') }}</a>{{ __('pub_corrections.et_l_origine_de_nos_ressources_sur') }}
        <a href="{{ route('public.funding') }}" class="text-primary font-bold">{{ __('pub_corrections.financement') }}</a>{{ __('pub_corrections.point_final') }}
      </p>
    </div>
  </section>

  {{-- Infolettre : posée sur les pages ÉDITORIALES seulement (arbitrage 166).
       Quelqu'un qui vient de lire un démenti est la personne la plus susceptible
       d'en vouloir la suite ; sur les mentions légales, ce serait du bruit. --}}
  <div class="pb-16">
    <x-infolettre-abonnement />
  </div>
</div>
