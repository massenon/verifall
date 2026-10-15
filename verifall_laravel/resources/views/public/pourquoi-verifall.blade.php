{{--
  | Pourquoi Vérif'All ? — transposition de pourquoi-verifall.html.
  |
  | Contenu, structure et classes repris du prototype. Seuls les liens et les
  | chemins d'images sont réécrits : ce sont les seules choses qu'une
  | application doit calculer plutôt que figer.
--}}
<x-layouts.public :title="$title">
    <main id="contenu" class="overflow-hidden">
      <section class="pt-32 lg:pt-40 pb-16">
        <div class="max-w-page mx-auto px-4">
          <div class="grid lg:grid-cols-2 gap-10">
            <div class="flex flex-col h-full lg:max-w-lg">
              <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('pourquoi_verifall.pourquoi_la_verite_est_devenue_si_fragile') }}</h2>
              <p class="mb-4">{{ __('pourquoi_verifall.ces_dernieres_annees_la_desinformation_a_pris') }}</p>
              <p class="font-semibold text-heading mb-0 mt-3">{{ __('pourquoi_verifall.faux_deces_faux_coups_detat_faux_recrutements') }}</p>
              <p class="mb-0 mt-3 text-body/75">{{ __('pourquoi_verifall.trop_souvent_tout_commence_par_un_message') }}</p>
              <h6 class="font-display font-bold text-heading mb-0 mt-4">{{ __('pourquoi_verifall.cest_pourquoi_verifall_est_nee') }}</h6>
            </div>
            <div class="grid grid-cols-2 gap-4 items-center">
              <div><img src="{{ asset('assets/images/about/pourquoi-verifall-1.webp') }}" class="rounded-lg w-full" alt="{{ __('pourquoi_verifall.image_illustrant_l_information') }}" loading="lazy"></div>
              <div>
                <div class="rounded-lg overflow-hidden mb-6"><img src="{{ asset('assets/images/about/pourquoi-verifall-2.webp') }}" class="w-full" alt="{{ __('pourquoi_verifall.image_illustrant_la_communication') }}" loading="lazy"></div>
                <img src="{{ asset('assets/images/about/pourquoi-verifall-3.webp') }}" class="rounded-lg w-full" alt="{{ __('pourquoi_verifall.image_illustrant_la_communaute') }}" loading="lazy">
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pb-16">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-dark rounded-xl relative overflow-hidden p-8 md:p-14 text-[#c4c5c7]">
            <div class="grid lg:grid-cols-12 gap-10 relative">
              <div class="lg:col-span-5 relative order-2 lg:order-1">
                <div class="stat-circle border border-white/50 h-[250px] w-[250px]">
                  <div class="flex justify-center items-baseline"><h4 class="text-2xl font-display font-bold text-white" data-counter="67">0</h4><span class="text-2xl font-display font-bold text-primary">+</span></div>
                  <p class="text-white/90 mb-0">{{ __('pourquoi_verifall.des_africains_ont_deja_cru_a_une') }}</p>
                </div>
                <div class="flex justify-end -mt-12 xl:-mt-16 lg:mt-0 -me-2">
                  <div class="stat-circle bg-[#f8f9fa] h-[200px] w-[200px]">
                    <div class="flex justify-center items-baseline"><h4 class="text-2xl font-display font-bold text-heading" data-counter="80">0</h4><span class="text-2xl font-display font-bold text-primary">+</span></div>
                    <p class="text-heading mb-0">{{ __('pourquoi_verifall.des_fake_news_circulent_dabord_sur_whatsapp') }}</p>
                  </div>
                </div>
                <div class="flex justify-center -mt-12 lg:mt-0">
                  <div class="stat-circle bg-primary h-[200px] w-[200px] text-white">
                    <div class="flex justify-center items-baseline"><h4 class="text-2xl font-display font-bold text-white" data-counter="12">0</h4><span class="text-2xl font-display font-bold text-white">+</span></div>
                    <p class="text-white mb-0">{{ __('pourquoi_verifall.seulement_verifient_une_information_avant_de_la') }}</p>
                  </div>
                </div>
              </div>
              <div class="lg:col-span-6 lg:col-start-7 order-1 lg:order-2">
                <h3 class="text-2xl md:text-3xl font-display font-bold text-white mb-4">{{ __('pourquoi_verifall.plus_qu_un_outil_votre_reflexe_anti') }}</h3>
                <p class="mb-10">{{ __('pourquoi_verifall.s_informer_ne_consiste_pas_seulement_a') }}</p>
                <div class="flex gap-4 mb-6">
                  <span class="text-primary mt-0.5" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg></span>
                  <div>
                    <h6 class="font-display font-bold text-white mb-1">{{ __('pourquoi_verifall.analysez_avant_de_diffuser') }}</h6>
                    <p class="pb-0">{{ __('pourquoi_verifall.utilisez_notre_application_simple_pour_analyser_n') }}</p>
                  </div>
                </div>
                <div class="flex gap-4 mb-6">
                  <span class="text-primary mt-0.5" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M2 1a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l4.586-4.586a1 1 0 0 0 0-1.414l-7-7A1 1 0 0 0 6.586 1zm4 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/></svg></span>
                  <div>
                    <h6 class="font-display font-bold text-white mb-1">{{ __('pourquoi_verifall.developpez_votre_esprit_critique') }}</h6>
                    <p class="pb-0">{{ __('pourquoi_verifall.apprenez_a_reconnaitre_les_signes_de_manipulation') }}</p>
                  </div>
                </div>
                <div class="flex gap-4">
                  <span class="text-primary mt-0.5" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.118a1 1 0 0 0 .101.025 1 1 0 0 0 .1-.025q.114-.034.294-.118c.24-.113.547-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524z"/></svg></span>
                  <div>
                    <h6 class="font-display font-bold text-white mb-1">{{ __('pourquoi_verifall.protegez_votre_communaute') }}</h6>
                    <p class="pb-0">{{ __('pourquoi_verifall.chaque_verification_contribue_a_un_environnement_numerique') }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pb-16">
        <div class="max-w-page mx-auto px-4">
          <div class="grid lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-6">
              <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-4 lg:mb-8">{{ __('pourquoi_verifall.notre_mission_en_3_axes') }}</h2>
              <p class="mb-4 lg:mb-8">{{ __('pourquoi_verifall.pour_batir_un_ecosysteme_informationnel_plus_sain') }}</p>
              <div class="flex items-center gap-4 mb-4">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary font-bold shrink-0">01</span>
                <h6 class="text-heading mb-0">{{ __('pourquoi_verifall.outiller_donner_a_chacun_le_pouvoir_de') }}</h6>
              </div>
              <div class="flex items-center gap-4 mb-4">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary font-bold shrink-0">02</span>
                <h6 class="text-heading mb-0">{{ __('pourquoi_verifall.eduquer_developper_l_esprit_critique_et_la') }}</h6>
              </div>
              <div class="flex items-center gap-4">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary font-bold shrink-0">03</span>
                <h6 class="text-heading mb-0">{{ __('pourquoi_verifall.federer_construire_une_communaute_engagee_pour_la') }}</h6>
              </div>
              <div class="flex gap-2 mt-6 lg:mt-10">
                <a href="{{ route('public.verify') }}"><img src="{{ asset('assets/images/elements/google-play.svg') }}" class="transition-transform hover:-translate-y-0.5" alt="{{ __('commun.alt_google_play') }}" width="135" height="40" loading="lazy"></a>
                <a href="{{ route('public.verify') }}"><img src="{{ asset('assets/images/elements/app-store.svg') }}" class="transition-transform hover:-translate-y-0.5" alt="{{ __('commun.alt_app_store') }}" width="135" height="40" loading="lazy"></a>
              </div>
            </div>
            <div class="lg:col-span-5 lg:col-start-8 relative">
              <div class="absolute top-1/2 left-0 -translate-x-1/2 -translate-y-1/2 hidden xl:block" aria-hidden="true">
                <h2 class="font-display font-bold text-heading/10 text-6xl uppercase -rotate-90">{{ __('pourquoi_verifall.mission') }}</h2>
              </div>
              <img src="{{ asset('assets/images/about/pourquoi-verifall.webp') }}" class="rounded-lg relative w-full" alt="{{ __('pourquoi_verifall.illustration_de_la_mission_de_verif_all') }}" loading="lazy">
            </div>
          </div>
        </div>
      </section>

      <section class="pb-24">
        <div class="max-w-2xl mx-auto px-4 text-center">
          <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('pourquoi_verifall.une_rumeur_ou_fake_news_vous_fait') }}</h2>
          <p class="mb-6">{{ __('pourquoi_verifall.ne_soyez_plus_le_relais_de_la') }}</p>
          <a href="{{ route('public.verify') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('pourquoi_verifall.verifier_une_information_maintenant') }}</a>
        </div>
      </section>
    </main>
</x-layouts.public>
