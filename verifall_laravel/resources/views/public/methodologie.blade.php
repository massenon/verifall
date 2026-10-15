@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/transparence.css') }}">
@endpush

{{--
  | Méthodologie publique.
  |
  | Aucun chiffre n'est écrit ici : les étapes viennent de `EtapePipeline`, les
  | poids de `config/factcheck.weights`, les seuils de
  | `config/factcheck.decision`. Une pondération modifiée demain modifie cette
  | page — c'est la seule façon qu'elle reste vraie.
  |
  | Le gabarit public ouvre déjà un <main id="contenu"> : racine <div>.
--}}
<div>
  <section class="bg-[#f8f9fa] pt-36 pb-20">
    <div class="max-w-page mx-auto px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-display font-extrabold text-heading mb-3">{{ __('methodologie.notre_methodologie') }}</h1>
        <p class="text-lg text-body/80 mb-4">{{ __('methodologie.comment_une_affirmation_devient_un_verdict_les') }}</p>
        <p class="text-body/70 mb-0">{{ __('methodologie.les_chiffres_de_cette_page_sont_lus') }}</p>
      </div>
    </div>
  </section>

  @if ($moteursSimules)
    {{-- Honnêteté d'abord : tant qu'aucune clé d'API n'est branchée, le
         pipeline tourne sur des adaptateurs simulés. Le taire sur la page qui
         promet la méthode serait le seul mensonge vraiment grave possible ici.
         L'orchestrateur refuse de démarrer dans cet état en production. --}}
    <section class="pt-16">
      <div class="max-w-3xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-card border border-line p-6">
          <h2 class="text-lg font-display font-bold text-heading mb-2">{{ __('methodologie.etat_actuel_des_moteurs') }}</h2>
          <p class="text-body/85 leading-relaxed mb-0">{!! __('methodologie.les_moteurs_d_analyse_decrits_ci_dessous') !!}</p>
        </div>
      </div>
    </section>
  @endif

  {{-- ============ 1. LE PIPELINE ============ --}}
  <section class="py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('methodologie.le_parcours_d_une_verification') }}</h2>
      <p class="text-body/85 leading-relaxed mb-8">{{ __('methodologie.six_etapes_toujours_les_memes_dans_cet') }}</p>

      <ol class="etapes-pipeline space-y-6">
        @foreach ($etapes as $etape)
          <li>
            <h3 class="text-lg font-display font-bold text-heading mb-2">{{ $etape->libelle() }}</h3>
            <p class="text-body/85 leading-relaxed mb-0">{{ $etape->description() }}</p>
          </li>
        @endforeach
      </ol>
    </div>
  </section>

  {{-- ============ 2. LA PONDÉRATION ============ --}}
  <section class="pb-20">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('methodologie.le_poids_de_chaque_moteur') }}</h2>
      <p class="text-body/85 leading-relaxed mb-4">{!! __('methodologie.tous_les_moteurs_ne_se_valent_pas') !!}</p>
      <p class="text-body/85 leading-relaxed mb-8">{{ __('methodologie.les_moteurs_marques_signal_sont_ecartes_du') }}</p>

      <div class="bg-white rounded-xl shadow-card border border-line p-6">
        <ul class="list-none space-y-6 mb-0">
          @foreach ($moteurs as $moteur)
            <li>
              <div class="flex items-center flex-wrap gap-2 mb-2">
                <span class="font-semibold text-heading">{{ $moteur->libelle() }}</span>
                @unless ($moteur->porteUnVerdict())
                  <span class="text-xs uppercase tracking-wide text-body/70">{{ __('methodologie.signal_jamais_verdict') }}</span>
                @endunless
                <span class="ml-auto text-sm font-bold text-heading">{{ number_format($moteur->poids(), 2, ',', ' ') }}</span>
              </div>
              <div class="poids-piste">
                <div class="poids-barre @unless ($moteur->porteUnVerdict()) poids-barre--signal @endunless"
                     style="width: {{ round($moteur->poids() / $poidsMaximum * 100) }}%"></div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>

      <p class="text-sm text-body/70 mt-4 mb-0">{{ __('methodologie.les_scores_sont_normalises_sans_cela_une') }}</p>
    </div>
  </section>

  {{-- ============ 3. QUAND NOUS NE TRANCHONS PAS ============ --}}
  <section class="bg-[#f8f9fa] py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('methodologie.quand_nous_repondons_non_verifiable') }}</h2>
      <p class="text-body/85 leading-relaxed mb-8">{{ __('methodologie.non_verifiable_n_est_pas_un_echec') }}</p>

      <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-card border border-line p-6">
          <h3 class="text-lg font-display font-bold text-heading mb-2">{{ __('methodologie.trop_peu_de_moteurs_ont_repondu') }}</h3>
          <p class="text-body/85 leading-relaxed mb-0">
            {!! __('methodologie.en_dessous_de_n_avis_exploitables', ['nombre' => e($moteursMinimum)]) !!}
          </p>
        </div>
        <div class="bg-white rounded-xl shadow-card border border-line p-6">
          <h3 class="text-lg font-display font-bold text-heading mb-2">{{ __('methodologie.le_verdict_de_tete_ne_se_detache') }}</h3>
          <p class="text-body/85 leading-relaxed mb-0">
            {!! __('methodologie.un_verdict_n_est_retenu_que_s_il', ['ecart' => e($ecartMinimum), 'score' => e($scoreMinimum)]) !!}
          </p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-card border border-line p-6">
        <h3 class="text-lg font-display font-bold text-heading mb-2">{{ __('methodologie.les_moteurs_se_contredisent') }}</h3>
        <p class="text-body/85 leading-relaxed mb-0">{!! __('methodologie.quand_deux_theses_opposees_tiennent_la_tete') !!}</p>
      </div>
    </div>
  </section>

  {{-- ============ 4. LA REVUE HUMAINE ============ --}}
  <section class="py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('methodologie.ce_qui_declenche_une_relecture_humaine') }}</h2>
      <ul class="liste-puces text-body/85 leading-relaxed mb-4">
        <li>{!! __('methodologie.une_contradiction_entre_les_moteurs_dans_les') !!}</li>
        <li>{!! __('methodologie.un_controle_de_coherence_qui_echoue_le') !!}</li>
        <li>{!! __('methodologie.une_publication_au_nom_de_la_redaction') !!}</li>
      </ul>
      <p class="text-body/85 leading-relaxed mb-0">
        {!! __('methodologie.quand_les_moteurs_sont_satures', ['minutes' => e($delaiDeReprise), 'analyses' => e($analysesParHeure)]) !!}
      </p>
    </div>
  </section>

  {{-- ============ 5. LES LIMITES ============ --}}
  <section class="bg-[#f8f9fa] py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('methodologie.ce_que_nous_ne_savons_pas_faire') }}</h2>
      <p class="text-body/85 leading-relaxed mb-8">{{ __('methodologie.ce_qu_on_ne_mesure_pas_ne') }}</p>

      <ul class="list-none space-y-6 mb-0">
        <li class="bg-white rounded-xl shadow-card border border-line p-6">{!! __('methodologie.l_hypertrucage_video_n_est_pas_detecte') !!}</li>
        <li class="bg-white rounded-xl shadow-card border border-line p-6">{!! __('methodologie.nous_ne_nous_prononcons_pas_sur_l') !!}</li>
        <li class="bg-white rounded-xl shadow-card border border-line p-6">{!! __('methodologie.nous_ne_datons_pas_la_premiere_apparition') !!}</li>
      </ul>
    </div>
  </section>

  {{-- ============ 6. CORRECTIONS ============ --}}
  <section class="py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('methodologie.quand_nous_nous_trompons') }}</h2>
      <p class="text-body/85 leading-relaxed mb-4">{{ __('methodologie.un_verdict_qui_change_ne_disparait_pas') }}</p>
      <p class="text-body/85 leading-relaxed mb-4">
        <a href="{{ route('public.corrections') }}" class="text-primary font-bold">{{ __('methodologie.consulter_le_journal_des_corrections') }}</a>
      </p>
      <p class="text-sm text-body/70 mb-0">
        {{ __('methodologie.cette_page_applique_les_exigences_de_transparence') }}
        {{ __('methodologie.nos_ressources_et_leur_origine_sont_detaillees') }}
        <a href="{{ route('public.funding') }}" class="text-primary font-bold">{{ __('methodologie.financement') }}</a>.
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
