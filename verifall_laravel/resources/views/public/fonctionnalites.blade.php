@push('styles')
  {{-- Bloc <style> que le prototype déclare en ligne dans cette page. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/fonctionnalites.css') }}">
@endpush

{{--
  | Fonctionnalités — transposition de fonctionnalites.html.
  |
  | Contenu, structure et classes repris du prototype. Seuls les liens et les
  | chemins d'images sont réécrits : ce sont les seules choses qu'une
  | application doit calculer plutôt que figer.
--}}
<x-layouts.public :title="$title">
    <main id="contenu" class="overflow-hidden">
      <section class="pt-32 lg:pt-40 pb-10">
        <div class="max-w-page mx-auto px-4 relative">
          <div class="grid lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-5">
              <h1 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('fonctionnalites.toute_la_puissance_du_fact_checking_a') }}</h1>
              <p class="mb-6">{{ __('fonctionnalites.decouvrez_l_arsenal_d_outils_que_verif') }}</p>
              <a href="#liste-fonctionnalites" class="inline-flex items-center rounded-lg bg-dark text-white text-lg font-semibold px-5 py-3 hover:bg-black transition-colors">{{ __('fonctionnalites.explorer_nos_fonctionnalites') }}</a>
            </div>
            <div class="lg:col-span-6 lg:col-start-7">
              <div class="relative pe-6 sm:pe-12">
                <div class="bg-dark rounded-xl absolute top-0 right-0 w-1/2 h-full" aria-hidden="true"></div>
                <img src="{{ asset('assets/images/elements/comming-soon.webp') }}" class="rounded-xl border-[5px] border-dark my-6 sm:my-10 relative w-full" alt="{{ __('fonctionnalites.illustration_technologique_verif_all') }}" loading="lazy">
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pb-16">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('fonctionnalites.un_ecosysteme_integre_pour_retablir_la_verite') }}</h2>
            <p class="mb-0">{{ __('fonctionnalites.ne_vous_contentez_pas_d_un_seul') }}</p>
          </div>
          <div class="grid xl:grid-cols-3 gap-6 xl:gap-10">
            <div class="bg-white border border-line rounded-xl p-6 flex flex-col md:flex-row xl:flex-col gap-6 h-full">
              <div class="md:flex-1 xl:flex-none">
                <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('fonctionnalites.orchestrateur_de_verification') }}</h5>
                <p class="mb-4">{{ __('fonctionnalites.notre_technologie_analyse_votre_contenu_en_le') }}</p>
                <a href="#liste-fonctionnalites" class="inline-flex items-center rounded-lg bg-dark text-white font-semibold px-4 py-2.5 hover:bg-black transition-colors">{{ __('fonctionnalites.voir_les_outils_integres') }}</a>
              </div>
              <div class="mt-auto"><img src="{{ asset('assets/images/elements/integration.webp') }}" alt="{{ __('fonctionnalites.illustration_de_l_orchestrateur') }}" class="w-full" loading="lazy"></div>
            </div>
            <div class="xl:col-span-2 grid gap-6 xl:gap-10">
              <div class="bg-primary/10 rounded-xl overflow-hidden p-8 sm:p-10 pb-0">
                <div class="grid md:grid-cols-2 gap-6">
                  <div>
                    <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('fonctionnalites.un_systeme_educatif_interactif') }}</h5>
                    <p class="mb-4">{{ __('fonctionnalites.ne_vous_contentez_pas_de_verifier_apprenez') }}</p>
                    <a href="{{ route('public.tutorials') }}" class="inline-flex items-center rounded-lg border border-primary text-primary font-semibold px-4 py-2.5 hover:bg-primary hover:text-white transition-colors mb-10">{{ __('fonctionnalites.commencer_a_apprendre') }}</a>
                  </div>
                  <div class="-me-6 -mb-2 sm:-me-16">
                    <img src="{{ asset('assets/images/lms/feature_img_lms-1.webp') }}" class="rounded-t-xl border-[5px] border-b-0 border-dark w-full" alt="{{ __('fonctionnalites.illustration_du_lms') }}" loading="lazy">
                  </div>
                </div>
              </div>
              <div class="grid lg:grid-cols-2 gap-6 xl:gap-10">
                <div class="bg-dark rounded-xl h-full overflow-hidden p-8 sm:p-10 flex flex-col">
                  <h5 class="text-lg font-display font-bold text-white mb-0">{{ __('fonctionnalites.rejoignez_une_communaute_engagee') }}</h5>
                  <div class="mt-auto pt-14">
                    <p class="text-white mb-4">{{ __('fonctionnalites.participez_a_notre_forum_echangez_avec_nos') }}</p>
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-4 py-2.5 hover:bg-primary-dark transition-colors">{{ __('fonctionnalites.explorer_la_communaute') }}</a>
                  </div>
                </div>
                <div class="bg-[#f8f9fa] rounded-xl h-full overflow-hidden p-6 sm:p-8 flex flex-col">
                  <div>
                    <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('fonctionnalites.un_doute_verifiez_en_quelques_secondes') }}</h5>
                    <p class="mb-0">{{ __('fonctionnalites.notre_outil_principal_est_concu_pour_etre') }}</p>
                  </div>
                  <div class="mt-auto pt-10">
                    <a href="{{ route('public.verify') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-4 py-2.5 hover:bg-primary-dark transition-colors">{{ __('fonctionnalites.verifier_une_information_maintenant') }}</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pb-16" id="liste-fonctionnalites">
        <div class="max-w-page mx-auto px-4">
          <div class="lg:flex justify-between items-center mb-8">
            <h4 class="text-2xl font-display font-bold text-heading mb-4 lg:mb-0">{{ __('fonctionnalites.decouvrez_nos_fonctionnalites') }}</h4>
            <ul class="flex flex-wrap gap-1" data-feature-tabs role="tablist" aria-label="{{ __('fonctionnalites.filtrer_les_fonctionnalites') }}">
              <li>{!! __('fonctionnalites.toutes') !!}</li>
              <li>{!! __('fonctionnalites.verification') !!}</li>
              <li>{!! __('fonctionnalites.education') !!}</li>
              <li>{!! __('fonctionnalites.communaute') !!}</li>
              <li>{!! __('fonctionnalites.plateforme') !!}</li>
            </ul>
          </div>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <div class="feature-card" data-filter-item data-filter-tags="verification">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.verification_de_texte') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.analysez_les_articles_et_messages_pour_detecter') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.detection_ia_analyse_semantique') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="verification">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.verification_d_image') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.retrouvez_l_origine_d_une_photo_sa') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.recherche_inversee_metadonnees') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="verification">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.verification_de_video') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.identifiez_les_videos_sorties_de_leur_contexte') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.detection_deepfake_analyse_d_images_cles') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="verification">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.verification_audio') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.detectez_les_voix_clonees_par_intelligence_artificielle') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.analyse_spectrale_detection_vocale_ia') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="education">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.module_d_apprentissage') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.devenez_autonome_grace_a_nos_tutoriels_interactifs') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.quiz_interactifs_certificats') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="communaute">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.communaute_forum') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.echangez_avec_des_citoyens_des_journalistes_et') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.moderation_expertise') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="communaute">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.hub_opportunites') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.accedez_a_un_flux_d_actualites_provenant') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.curation_fiabilite') }}</span>
              </div>
            </div>
            <div class="feature-card" data-filter-item data-filter-tags="plateforme">
              <div class="bg-white border border-line hover:border-primary hover:shadow-cardlg transition-all rounded-xl h-full p-6 flex flex-col">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('fonctionnalites.signalement') }}</h6>
                <p class="text-sm text-body/85 mb-4">{{ __('fonctionnalites.vous_avez_un_doute_soumettez_le_nous') }}</p>
                <span class="text-xs font-semibold text-body/70 mt-auto pt-3 border-t border-line">{{ __('fonctionnalites.intelligence_collective') }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pb-16">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-dark rounded-xl relative overflow-hidden p-6 sm:p-14 text-center">
            <div class="max-w-2xl mx-auto relative">
              <h3 class="text-2xl md:text-3xl font-display font-bold text-white mb-3">{{ __('fonctionnalites.une_rumeur_ou_fake_news_vous_fait') }}</h3>
              <p class="text-white/80 mb-6">{{ __('fonctionnalites.ne_soyez_plus_le_relais_de_la') }}</p>
              <a href="{{ route('public.verify') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('fonctionnalites.verifier_une_information_maintenant') }}</a>
            </div>
          </div>
        </div>
      </section>
      <section class="pb-24">
        <div class="max-w-2xl mx-auto px-4">
          <h2 class="text-3xl font-display font-bold text-heading text-center mb-10">{{ __('fonctionnalites.questions_frequentes') }}</h2>
          <div data-accordion>
              <div class="accordion-item mb-3">
                <h3 class="m-0 text-base">{!! __('fonctionnalites.comment_l_ia_detecte_t_elle_les') !!}</h3>
                <div class="accordion-body">{{ __('fonctionnalites.reponse_comment_l_ia_detecte_t_elle') }}</div>
              </div>
              <div class="accordion-item mb-3">
                <h3 class="m-0 text-base">{!! __('fonctionnalites.mes_donnees_signalees_sont_elles_privees') !!}</h3>
                <div class="accordion-body">{{ __('fonctionnalites.reponse_mes_donnees_signalees_sont_elles_privees') }}</div>
              </div>
              <div class="accordion-item mb-3">
                <h3 class="m-0 text-base">{!! __('fonctionnalites.c_est_quoi_un_deepfake') !!}</h3>
                <div class="accordion-body">{{ __('fonctionnalites.reponse_c_est_quoi_un_deepfake') }}</div>
              </div>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
