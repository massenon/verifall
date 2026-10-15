
<x-layouts.public :title="$title">
    <main id="contenu" class="overflow-hidden">
      <section class="pt-28 lg:pt-32">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-dark rounded-xl relative overflow-hidden p-6 sm:p-14">
            <div class="relative max-w-2xl mx-auto text-center">
              <h3 class="text-2xl md:text-3xl font-display font-bold text-white mb-3">{{ __('comment_ca_marche.une_rumeur_ou_fake_news_vous_fait') }}</h3>
              <p class="text-white/80 mb-6">{{ __('comment_ca_marche.ne_soyez_plus_le_relais_de_la') }}</p>
              <a href="{{ route('public.verify') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors mb-14">{{ __('comment_ca_marche.verifier_une_information_maintenant') }}</a>
              <p class="text-white text-center mb-4">{{ __('comment_ca_marche.comment_fonctionne_la_verification') }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="pt-20">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-16">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-2">{{ __('comment_ca_marche.la_verification_en_trois_etapes') }}</h2>
            <p>{{ __('comment_ca_marche.transformez_le_doute_en_certitude_voici_comment') }}</p>
          </div>
          <div class="max-w-6xl mx-auto">
            <!-- Étape 1 -->
            <div class="lg:flex lg:flex-nowrap items-center relative mb-16 lg:mb-24">
              <div class="lg:w-7/12 relative z-10">
                <div class="bg-[#f8f9fa] rounded-xl px-8 md:px-12 py-12 lg:pe-20">
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white font-bold text-heading mb-4">01</div>
                  <h4 class="text-2xl font-display font-bold text-heading mb-4">{{ __('comment_ca_marche.soumettez_votre_doute') }}</h4>
                  <p class="mb-5">{{ __('comment_ca_marche.un_message_whatsapp_un_lien_suspect_une') }}</p>
                  <ul class="space-y-2 mb-6">
                    <li class="flex gap-2 text-heading"><svg width="15" height="15" viewBox="0 0 16 16" fill="#09B850" class="shrink-0 mt-1" aria-hidden="true"><path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708"/></svg>{{ __('comment_ca_marche.simple_et_rapide') }}</li>
                    <li class="flex gap-2 text-heading"><svg width="15" height="15" viewBox="0 0 16 16" fill="#09B850" class="shrink-0 mt-1" aria-hidden="true"><path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708"/></svg>{{ __('comment_ca_marche.multi_formats_supportes') }}</li>
                    <li class="flex gap-2 text-heading"><svg width="15" height="15" viewBox="0 0 16 16" fill="#09B850" class="shrink-0 mt-1" aria-hidden="true"><path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708"/></svg>{{ __('comment_ca_marche.accessible_a_tous') }}</li>
                  </ul>
                  <a class="inline-flex items-center gap-1.5 rounded-lg bg-dark text-white font-semibold px-4 py-2.5 hover:bg-black transition-colors" href="{{ route('public.verify') }}">{{ __('comment_ca_marche.commencer_une_verification') }}<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg></a>
                </div>
              </div>
              <div class="lg:w-7/12 lg:-ms-16 mt-6 lg:mt-0">
                <div class="bg-primary/50 rounded-xl p-8 sm:p-10 overflow-hidden lg:ms-3">
                  <img src="{{ asset('assets/images/how-it-works/step-1.webp') }}" alt="{{ __('comment_ca_marche.alt_etape_1') }}" class="w-full rounded-lg" loading="lazy">
                </div>
              </div>
            </div>
            <!-- Étape 2 -->
            <div class="lg:flex lg:flex-nowrap items-center relative mb-16 lg:mb-24">
              <div class="lg:w-7/12 lg:-me-16 relative z-10 order-2 lg:order-1 mt-6 lg:mt-0">
                <div class="bg-white rounded-xl shadow-cardlg p-8 sm:p-10 overflow-hidden lg:me-3">
                  <img src="{{ asset('assets/images/how-it-works/step-2.webp') }}" alt="{{ __('comment_ca_marche.alt_etape_2') }}" class="w-full rounded-lg" loading="lazy">
                </div>
              </div>
              <div class="lg:w-7/12 order-1 lg:order-2">
                <div class="bg-[#f8f9fa] rounded-xl px-8 md:px-12 py-12 lg:ps-24">
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white font-bold text-heading mb-4">02</div>
                  <h4 class="text-2xl font-display font-bold text-heading mb-4">{{ __('comment_ca_marche.une_analyse_a_360_par_notre_orchestrateur') }}</h4>
                  <p class="mb-6">{{ __('comment_ca_marche.plus_besoin_de_jongler_entre_dix_sites') }}</p>
                  <div class="grid grid-cols-2 gap-6">
                    <div>
                      <div class="flex items-baseline"><h4 class="text-3xl font-display font-bold text-heading" data-counter="15">0</h4><span class="text-3xl font-display font-bold text-primary">+</span></div>
                      <p class="mb-0 text-heading">{{ __('comment_ca_marche.outils_integres') }}</p>
                    </div>
                    <div>
                      <div class="flex items-baseline"><h4 class="text-3xl font-display font-bold text-heading" data-counter="100">0</h4><span class="text-3xl font-display font-bold text-primary">+</span></div>
                      <p class="mb-0 text-heading">{{ __('comment_ca_marche.donnees_analyses') }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Étape 3 -->
            <div class="lg:flex lg:flex-nowrap items-center relative">
              <div class="lg:w-7/12 relative z-10">
                <div class="bg-[#f8f9fa] rounded-xl px-8 md:px-12 py-12 lg:pe-20">
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white font-bold text-heading mb-4">03</div>
                  <h4 class="text-2xl font-display font-bold text-heading mb-3">{{ __('comment_ca_marche.obtenez_un_verdict_clair_et_explique') }}</h4>
                  <p class="mb-5">{{ __('comment_ca_marche.recevez_un_resultat_simple_vrai_faux_douteux') }}</p>
                  <ul class="space-y-4">
                    <li class="flex">
                      <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary text-white shrink-0" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M0 4s0-2 2-2h12s2 0 2 2v6s0 2-2 2h-4q0 1 .25 1.5H11a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1h.75Q6 13 6 12H2s-2 0-2-2zm1.398-.855a.76.76 0 0 0-.254.302A1.5 1.5 0 0 0 1 4.01V10c0 .325.078.502.145.602q.105.156.302.254a1.5 1.5 0 0 0 .538.143L2.01 11H14c.325 0 .502-.078.602-.145a.76.76 0 0 0 .254-.302 1.5 1.5 0 0 0 .143-.538L15 9.99V4c0-.325-.078-.502-.145-.602a.76.76 0 0 0-.302-.254A1.5 1.5 0 0 0 13.99 3H2c-.325 0-.502.078-.602.145"/></svg>
                      </span>
                      <div class="ms-4">
                        <p class="text-heading font-bold mb-0">{{ __('comment_ca_marche.rapport_complet') }}</p>
                        <small>{{ __('comment_ca_marche.toutes_les_sources_et_preuves_a_votre') }}</small>
                      </div>
                    </li>
                    <li class="flex">
                      <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary text-white shrink-0" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5m-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3m11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3"/></svg>
                      </span>
                      <div class="ms-4">
                        <p class="text-heading font-bold mb-0">{{ __('comment_ca_marche.partage_facile') }}</p>
                        <small>{{ __('comment_ca_marche.partagez_la_verite_pas_la_rumeur') }}</small>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="lg:w-7/12 lg:-ms-16 mt-6 lg:mt-0">
                <div class="bg-dark rounded-xl p-8 sm:p-10 overflow-hidden lg:ms-3">
                  <img src="{{ asset('assets/images/how-it-works/step-3.webp') }}" alt="{{ __('comment_ca_marche.alt_etape_3') }}" class="w-full rounded-lg" loading="lazy">
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="py-24">
        <div class="max-w-2xl mx-auto px-4">
          <h2 class="text-3xl font-display font-bold text-heading text-center mb-10">{{ __('comment_ca_marche.questions_frequentes') }}</h2>
          <div data-accordion>
              <div class="accordion-item mb-3">
                <h3 class="m-0 text-base">{!! __('comment_ca_marche.qui_sont_les_mentors_de_la_plateforme') !!}</h3>
                <div class="accordion-body">{{ __('comment_ca_marche.les_mentors_sont_des_journalistes_professionnels') }}</div>
              </div>
              <div class="accordion-item mb-3">
                <h3 class="m-0 text-base">{!! __('comment_ca_marche.combien_de_temps_prend_une_verification_humaine') !!}</h3>
                <div class="accordion-body">{{ __('comment_ca_marche.si_l_ia_ne_peut_pas_trancher') }}</div>
              </div>
              <div class="accordion-item mb-3">
                <h3 class="m-0 text-base">{!! __('comment_ca_marche.comment_obtenir_mon_certificat_de_fact_checker') !!}</h3>
                <div class="accordion-body">{{ __('comment_ca_marche.il_vous_suffit_de_suivre_nos_tutoriels') }}</div>
              </div>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
