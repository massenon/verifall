{{--
  | Vérif'All — transposition de index.html.
  |
  | Contenu, structure et classes repris du prototype. Seuls les liens et les
  | chemins d'images sont réécrits : ce sont les seules choses qu'une
  | application doit calculer plutôt que figer.
--}}
<x-layouts.public :title="$title">
    <main id="contenu" class="overflow-hidden">
      <!-- ============ HERO ============ -->
      <section class="pt-32 lg:pt-40 pb-10">
        <div class="max-w-page mx-auto px-4 relative">
          <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
              <div class="inline-flex items-center gap-2 bg-[#f8f9fa] rounded-full py-2 px-3 mb-6">
                <span class="rounded-full bg-dark text-white text-xs font-semibold px-3 py-1">Vérif’All</span>
                <a href="#pourquoi-verifall" class="text-sm text-heading inline-flex items-center gap-1 hover:text-primary transition-colors">{{ __('accueil.votre_application_de_fact_checking') }}<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg></a>
              </div>
              <h3 class="relative text-3xl md:text-4xl font-display font-bold text-heading leading-snug mb-5">{{ __('accueil.protegez_vous_contre_les_fausses_informations') }}
                <span class="absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2 mt-1 hidden md:block" aria-hidden="true"><svg fill="#09B850" width="86" height="105" viewBox="0 0 86 105" fill="none" xmlns="http://www.w3.org/2000/svg">
    							<path d="M50.9805 4.63769C51.6165 4.24345 56.4262 10.3524 61.7246 18.1163C67.0564 25.8003 72.7966 35.1061 75.9634 40.6314C82.2158 51.6489 85.8889 61.2748 84.5362 62.0301C83.1029 62.7522 77.2708 54.3462 71.0415 43.3853C67.8986 37.9165 62.7042 28.3199 58.293 20.1246C53.8819 11.9293 50.2874 5.05535 50.9805 4.63769Z"/>
    							<path d="M64.0889 66.7297C63.5001 67.2372 60.6142 65.1098 57.1179 62.637C53.6451 60.2207 49.5285 57.539 47.2336 56.0303C42.5967 52.8997 39.0022 49.8707 39.6599 48.4069C40.2841 47.0231 45.3294 47.9307 50.3923 51.2838C52.9286 52.892 56.9393 56.2802 59.8742 59.6469C62.809 63.0137 64.6779 66.2223 64.0889 66.7297Z"/>
    							<path d="M55.1316 86.0595C55.1297 86.856 48.7063 87.4433 40.9853 87.6368C33.2643 87.8295 24.2555 87.492 19.0669 87.0413C8.68981 86.139 0.434871 83.7683 0.656712 82.218C0.878555 80.6685 9.34356 80.5005 19.5833 81.393C24.7149 81.867 33.5706 82.7978 41.1895 83.6415C48.832 84.5408 55.1571 85.32 55.1316 86.0595Z"/>
    						</svg></span>
              </h3>
              <h5 class="text-lg font-display font-bold text-heading mb-2">{{ __('accueil.verifiez_nimporte_quel_contenu_en_un_clic') }}</h5>
              <p class="mb-10">{!! __('accueil.fake_news_arnaques_au_recrutement_images_recyclees') !!}</p>
              <div class="grid sm:grid-cols-2 gap-2 max-w-xl mb-10">
                <a href="{{ route('public.verify') }}" class="inline-flex justify-center items-center rounded-lg bg-primary text-white text-[0.9rem] font-semibold px-4 py-3 hover:bg-primary-dark transition-colors">{{ __('accueil.je_veux_verifier_une_info') }}</a>
                <a href="{{ route('public.tutorials') }}" class="inline-flex justify-center items-center rounded-lg border-2 border-dark text-heading text-[0.9rem] font-semibold px-4 py-3 hover:bg-dark hover:text-white transition-colors">{{ __('accueil.apprenez_a_fact_checker') }}</a>
              </div>
              <div class="sm:flex gap-4 items-center">
                <div class="font-display font-bold text-heading mb-3 sm:mb-0">{{ __('accueil.pense_pour') }}</div>
                <ul class="flex flex-wrap gap-2">
                  <li><span class="rounded-md bg-[#f8f9fa] text-heading text-sm font-medium px-3 py-1.5">{{ __('accueil.jeunes') }}</span></li>
                  <li><span class="rounded-md bg-[#f8f9fa] text-heading text-sm font-medium px-3 py-1.5">{{ __('accueil.journalistes') }}</span></li>
                  <li><span class="rounded-md bg-[#f8f9fa] text-heading text-sm font-medium px-3 py-1.5">{{ __('accueil.enseignants') }}</span></li>
                  <li><span class="rounded-md bg-[#f8f9fa] text-heading text-sm font-medium px-3 py-1.5">{{ __('accueil.citoyens') }}</span></li>
                </ul>
              </div>
            </div>
            <div class="relative">
              <div class="grid sm:grid-cols-2 gap-4">
                <div class="iphone-x iphone-x-small rotate-sm-343 mx-auto sm:ms-10 mt-6 relative z-10" style="background:url('assets/images/mokeup/tableau-de-bord-principal-verifall-header-1.webp')" role="img" aria-label="{{ __('accueil.aria_apercu_tableau_de_bord') }}"><i></i><b></b></div>
                <div class="hidden sm:block">
                  <div class="iphone-x iphone-x-small rotate-13 ms-3 xl:-mt-14" style="background:url('assets/images/mokeup/tableau-de-bord-principal-verifall-header-2.webp')" role="img" aria-label="{{ __('accueil.aria_apercu_verification_rapide') }}"><i></i><b></b></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ PARTENAIRES ============ -->
      <!-- ============ PARTENAIRES ============ -->
      <section class="py-10">
        <div class="max-w-5xl mx-auto px-4 text-center">
          <h2 class="text-2xl md:text-3xl font-display font-semibold text-heading mb-8">{{ __('accueil.ceux_qui_soutiennent_verif_all') }}</h2>
          <div data-carousel data-carousel-autoplay="2800" class="carousel-cols carousel-partners">
            <div class="overflow-hidden">
              <div class="carousel-track items-center">
                @foreach ($partenaires as $partenaire)
                <div class="carousel-slide">
                  {{-- Le lien n'est rendu que s'il y en a un : un partenaire sans
                       site donne une image, pas un lien mort. --}}
                  @if ($partenaire->url !== null)
                  <a href="{{ $partenaire->url }}" target="_blank" rel="noopener" class="flex items-center justify-center h-[110px]"><img src="{{ asset($partenaire->logo_path) }}" class="grayscale max-h-[100px] object-contain px-2 sm:px-4" alt="{{ $partenaire->name }}" title="{{ $partenaire->name }}" loading="lazy"></a>
                  @else
                  <span class="flex items-center justify-center h-[110px]"><img src="{{ asset($partenaire->logo_path) }}" class="grayscale max-h-[100px] object-contain px-2 sm:px-4" alt="{{ $partenaire->name }}" title="{{ $partenaire->name }}" loading="lazy"></span>
                  @endif
                </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ DISTINCTIONS (AMU + WSIS 2026) ============ -->
      <section class="pt-24" id="distinctions">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-12">
            <span class="inline-flex items-center gap-2 rounded-full bg-warning/15 text-[#a97c00] text-sm font-bold px-4 py-1.5 mb-4">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935"/></svg>
              {{ __('accueil.distinctions') }}
            </span>
            <h2 class="text-2xl md:text-3xl xl:text-4xl font-display font-bold text-heading mb-3">{{ __('accueil.une_innovation_beninoise_saluee_du_campus_jusqu') }}</h2>
            <p class="text-body/85 mb-0">{{ __('accueil.de_la_scene_universitaire_a_la_scene') }}</p>
          </div>

          <div class="grid lg:grid-cols-2 gap-6 xl:gap-8">
            <article class="bg-dark rounded-xl overflow-hidden flex flex-col" data-reveal>
              <img src="{{ asset('assets/images/blog/verifall-prix-wsis-2026-annonce-vignette.jpg') }}" class="w-full h-[240px] object-cover" alt="{{ __('accueil.alt_prix_wsis') }}" title="{{ __('accueil.title_prix_wsis') }}" loading="lazy">
              <div class="p-8 flex flex-col flex-1">
                <span class="self-start rounded-full bg-primary text-white text-xs font-bold px-3 py-1.5 mb-4">Prix WSIS 2026 · UIT, Genève</span>
                <h3 class="text-xl xl:text-2xl font-display font-bold text-white leading-snug mb-3">{{ __('accueil.verif_all_remporte_le_prix_wsis_2026') }}</h3>
                <p class="text-white/75 text-sm leading-relaxed flex-1">{{ __('accueil.decernee_par_l_union_internationale_des_telecommunications') }}</p>
                <a href="{{ route('public.blog') }}" class="mt-5 inline-flex items-center gap-2 self-start rounded-full bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark hover:gap-3 transition-all">{{ __('accueil.lire_la_suite') }}
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                </a>
              </div>
            </article>

            <article class="bg-primary/10 rounded-xl overflow-hidden flex flex-col" data-reveal>
              <img src="{{ asset('assets/images/blog/2eme-prix-concours-objectif-developpement-durable-vignette.jpg') }}" class="w-full h-[240px] object-cover p-5" alt="{{ __('accueil.alt_concours_amu') }}" title="{{ __('accueil.title_concours_amu') }}" loading="lazy">
              <div class="p-8 flex flex-col flex-1">
                <span class="self-start rounded-full bg-dark text-white text-xs font-bold px-3 py-1.5 mb-4">Aix-Marseille Université</span>
                <h3 class="text-xl xl:text-2xl font-display font-bold text-heading leading-snug mb-3">{{ __('accueil.2e_prix_du_concours_objectif_developpement_durable') }}</h3>
                <p class="text-body/85 text-sm leading-relaxed flex-1">{{ __('accueil.face_a_des_projets_engages_venus_de') }}</p>
                <div class="mt-5 flex flex-wrap gap-3">
                  <a href="{{ route('public.team') }}" class="inline-flex items-center gap-2 rounded-full bg-dark text-white text-sm font-semibold px-5 py-2.5 hover:bg-black hover:gap-3 transition-all">{{ __('accueil.decouvrir_l_equipe_verif_all') }}
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                  </a>
                  <a href="{{ route('public.blog') }}" class="inline-flex items-center gap-2 rounded-full border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors">{{ __('accueil.lire_la_suite') }}</a>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>

      <!-- ============ POURQUOI ============ -->
      <section class="pt-24" id="pourquoi-verifall">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('accueil.pourquoi_verifall') }}</h2>
            <p class="mb-0">{!! __('accueil.la_desinformation_circule_vite_surtout_via_whatsapp') !!}</p>
          </div>
          <div class="max-w-4xl mx-auto mb-20">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-8 justify-items-center">
              <div>
                <div class="flex justify-center items-baseline"><h2 class="text-4xl font-display font-bold text-heading" data-counter="67">0</h2><span class="text-4xl font-display font-bold text-primary">%</span></div>
                <p class="text-center text-heading mt-1 mb-0">{{ __('accueil.des_africains_ont_deja_cru_a_une') }}</p>
              </div>
              <div>
                <div class="flex justify-center items-baseline"><h2 class="text-4xl font-display font-bold text-heading" data-counter="80">0</h2><span class="text-4xl font-display font-bold text-primary">%</span></div>
                <p class="text-center text-heading mt-1 mb-0">{{ __('accueil.des_fake_news_circulent_dabord_sur_whatsapp') }}</p>
              </div>
              <div class="col-span-2 md:col-span-1">
                <div class="flex justify-center items-baseline"><h2 class="text-4xl font-display font-bold text-heading" data-counter="12">0</h2><span class="text-4xl font-display font-bold text-primary">%</span></div>
                <p class="text-center text-heading mt-1 mb-0">{{ __('accueil.seulement_verifient_une_information_avant_de_la') }}</p>
              </div>
            </div>
          </div>

          <div class="grid xl:grid-cols-12 gap-6 xl:gap-12">
            <div class="xl:col-span-7">
              <div class="bg-dark rounded-xl p-6 sm:p-12 h-full flex flex-col text-[#c4c5c7]">
                <div class="mb-10">
                  <div class="inline-flex items-center gap-2 bg-danger text-white text-sm rounded-md py-1 px-2.5 mb-4">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
                    {{ __('accueil.probleme_reel') }}
                  </div>
                  <h2 class="text-xl font-display font-bold text-white leading-snug">{{ __('accueil.la_desinformation_se_propage_plus_vite_que') }}</h2>
                  <p class="mt-4">{{ __('accueil.entre_fausses_alertes_arnaques_au_recrutement_et') }}</p>
                </div>
                <span class="text-white mt-auto">{{ __('accueil.verif_all_est_la_premiere_application_beninoise') }}</span>
              </div>
            </div>
            <div class="xl:col-span-5">
              <div data-carousel>
                <div class="overflow-hidden pb-6">
                  <div class="carousel-track">
              <div class="carousel-slide px-0.5">
                <div class="bg-white border border-line rounded-xl p-4 h-full">
                  <div class="grid md:grid-cols-12 gap-4">
                    <div class="hidden md:block md:col-span-4">
                      <img src="{{ asset('assets/images/fakenews/fausse-rumeur.webp') }}" class="w-full h-full object-cover rounded-lg" alt="{{ __('accueil.alt_multiplication_des_fake_news') }}" loading="lazy">
                    </div>
                    <div class="md:col-span-8 flex flex-col p-1">
                      <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('accueil.multiplication_des_fake_news') }}</h5>
                      <q class="text-heading/80 mb-3">{{ __('accueil.citation_multiplication_des_fake_news') }}</q>
                      <small class="font-bold text-heading mt-auto">{{ __('accueil.reponse_multiplication_des_fake_news') }}</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="carousel-slide px-0.5">
                <div class="bg-white border border-line rounded-xl p-4 h-full">
                  <div class="grid md:grid-cols-12 gap-4">
                    <div class="hidden md:block md:col-span-4">
                      <img src="{{ asset('assets/images/fakenews/anarques.webp') }}" class="w-full h-full object-cover rounded-lg" alt="{{ __('accueil.alt_arnaques_au_recrutement_ventes') }}" loading="lazy">
                    </div>
                    <div class="md:col-span-8 flex flex-col p-1">
                      <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('accueil.arnaques_au_recrutement_ventes') }}</h5>
                      <q class="text-heading/80 mb-3">{{ __('accueil.citation_arnaques_au_recrutement_ventes') }}</q>
                      <small class="font-bold text-heading mt-auto">{{ __('accueil.reponse_arnaques_au_recrutement_ventes') }}</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="carousel-slide px-0.5">
                <div class="bg-white border border-line rounded-xl p-4 h-full">
                  <div class="grid md:grid-cols-12 gap-4">
                    <div class="hidden md:block md:col-span-4">
                      <img src="{{ asset('assets/images/fakenews/fausse-rumeur.webp') }}" class="w-full h-full object-cover rounded-lg" alt="{{ __('accueil.alt_audios_et_videos_fabriques_par_ia') }}" loading="lazy">
                    </div>
                    <div class="md:col-span-8 flex flex-col p-1">
                      <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('accueil.audios_et_videos_fabriques_par_ia') }}</h5>
                      <q class="text-heading/80 mb-3">{{ __('accueil.citation_audios_et_videos_fabriques_par_ia') }}</q>
                      <small class="font-bold text-heading mt-auto">{{ __('accueil.reponse_audios_et_videos_fabriques_par_ia') }}</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="carousel-slide px-0.5">
                <div class="bg-white border border-line rounded-xl p-4 h-full">
                  <div class="grid md:grid-cols-12 gap-4">
                    <div class="hidden md:block md:col-span-4">
                      <img src="{{ asset('assets/images/fakenews/fausse-rumeur.webp') }}" class="w-full h-full object-cover rounded-lg" alt="{{ __('accueil.alt_photos_recyclees_lors_des_crises') }}" loading="lazy">
                    </div>
                    <div class="md:col-span-8 flex flex-col p-1">
                      <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('accueil.photos_recyclees_lors_des_crises') }}</h5>
                      <q class="text-heading/80 mb-3">{{ __('accueil.citation_photos_recyclees_lors_des_crises') }}</q>
                      <small class="font-bold text-heading mt-auto">{{ __('accueil.reponse_photos_recyclees_lors_des_crises') }}</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="carousel-slide px-0.5">
                <div class="bg-white border border-line rounded-xl p-4 h-full">
                  <div class="grid md:grid-cols-12 gap-4">
                    <div class="hidden md:block md:col-span-4">
                      <img src="{{ asset('assets/images/fakenews/sante-danger.webp') }}" class="w-full h-full object-cover rounded-lg" alt="{{ __('accueil.alt_sante_attention_aux_remedes_miracles') }}" loading="lazy">
                    </div>
                    <div class="md:col-span-8 flex flex-col p-1">
                      <h5 class="text-lg font-display font-bold text-heading mb-3">{{ __('accueil.sante_attention_aux_remedes_miracles') }}</h5>
                      <q class="text-heading/80 mb-3">{{ __('accueil.citation_sante_attention_aux_remedes_miracles') }}</q>
                      <small class="font-bold text-heading mt-auto">{{ __('accueil.reponse_sante_attention_aux_remedes_miracles') }}</small>
                    </div>
                  </div>
                </div>
              </div>
                  </div>
                </div>
                <div class="flex items-center gap-4">
                  <button type="button" data-carousel-prev class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-dark text-white hover:bg-black transition-colors" aria-label="{{ __('accueil.aria_slide_precedent') }}"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/></svg></button>
                  <button type="button" data-carousel-next class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-dark text-white hover:bg-black transition-colors" aria-label="{{ __('accueil.aria_slide_suivant') }}"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg></button>
                  <span class="text-sm text-body" data-carousel-fraction aria-live="polite">1 / 5</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ 3 ÉTAPES ============ -->
      <section class="pt-24" id="comment-ca-marche">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('accueil.comment_verif_all_vous_aide_en_3') }}</h2>
            <p class="mb-0">{{ __('accueil.verifiez_toute_information_rapidement_comprenez_les_resultat') }}</p>
          </div>
          <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 overflow-hidden pb-4">
            <div class="bg-[#f8f9fa] rounded-xl overflow-hidden p-6 sm:p-10 flex flex-col" data-reveal>
              <div class="pb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-dark text-white font-bold mb-4">01</div>
                <h5 class="text-xl font-display font-bold text-heading mb-2">{{ __('accueil.importez_et_analysez_vos_contenus') }}</h5>
                <p class="text-heading/75">{{ __('accueil.collez_un_texte_ou_importez_une_image') }}</p>
              </div>
              <div class="mt-auto -mb-24 flex justify-center">
                <div class="iphone-x iphone-x-small rotate-335" style="background:url('assets/images/mokeup/import-analyse-de-contenu-verifall.webp'); width:250px; height:400px" role="img" aria-label="{{ __('accueil.aria_apercu_app_importez') }}"><i></i><b></b></div>
              </div>
            </div>
            <div class="bg-[#f8f9fa] rounded-xl overflow-hidden p-6 sm:p-10 flex flex-col" data-reveal>
              <div class="pb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-dark text-white font-bold mb-4">02</div>
                <h5 class="text-xl font-display font-bold text-heading mb-2">{{ __('accueil.recevez_un_resultat_clair_et_simple') }}</h5>
                <p class="text-heading/75">{!! __('accueil.lapplication_indique_simplement_si_linformation_est_fiable') !!}</p>
              </div>
              <div class="mt-auto -mb-24 flex justify-center">
                <div class="iphone-x iphone-x-small " style="background:url('assets/images/mokeup/resultat-de-verification-verifall.webp'); width:250px; height:400px" role="img" aria-label="{{ __('accueil.aria_apercu_app_recevez') }}"><i></i><b></b></div>
              </div>
            </div>
            <div class="bg-[#f8f9fa] rounded-xl overflow-hidden p-6 sm:p-10 flex flex-col" data-reveal>
              <div class="pb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-dark text-white font-bold mb-4">03</div>
                <h5 class="text-xl font-display font-bold text-heading mb-2">{{ __('accueil.apprenez_denoncez_et_participez') }}</h5>
                <p class="text-heading/75">{{ __('accueil.grace_aux_cours_et_quiz_vous_developpez') }}</p>
              </div>
              <div class="mt-auto -mb-24 flex justify-center">
                <div class="iphone-x iphone-x-small rotate-33" style="background:url('assets/images/mokeup/apprentisage-communaute-verifall.webp'); width:250px; height:400px" role="img" aria-label="{{ __('accueil.aria_apercu_app_apprenez') }}"><i></i><b></b></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ OUTILS (panneau sombre) ============ -->
      <section class="pt-24" id="fonctionnalites">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-24">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('accueil.verifall_votre_allie_contre_les_fausses_informations') }}</h2>
            <p class="mb-0">{{ __('accueil.verifiez_rapidement_et_facilement_la_veracite_de') }}</p>
          </div>
          <div class="bg-dark rounded-xl relative p-6 xl:p-14 text-[#c4c5c7]">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 items-start">
              <div class="lg:text-right order-2 lg:order-1">
                <div class="mb-10 lg:mb-16">
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-lg bg-primary text-white mb-4">
                    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/></svg>
                  </div>
                  <h3 class="text-lg font-display font-bold text-white mb-2">{{ __('accueil.verification_de_texte_et_liens') }}</h3>
                  <p>{{ __('accueil.analysez_des_messages_whatsapp_posts_et_articles') }}</p>
                </div>
                <div>
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-lg bg-primary text-white mb-4">
                    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/><path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/></svg>
                  </div>
                  <h3 class="text-lg font-display font-bold text-white mb-2">{{ __('accueil.verification_dimage') }}</h3>
                  <p>{{ __('accueil.importez_une_image_et_decouvrez_son_origine') }}</p>
                </div>
              </div>
              <div class="text-center relative order-1 lg:order-2 md:col-span-2 lg:col-span-1">
                <div class="iphone-x iphone-x-small mx-auto lg:-mt-24 mb-10" style="background:url('assets/images/mokeup/tableau-de-bord-principal-verifall.webp')" role="img" aria-label="{{ __('accueil.aria_tableau_de_bord_principal') }}"><i></i><b></b></div>
                <a class="inline-flex items-center gap-1.5 rounded-lg bg-primary text-white text-lg font-semibold px-5 py-3 hover:bg-primary-dark transition-colors" href="{{ route('public.features') }}">{{ __('accueil.voir_tous_nos_outils') }}
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/></svg>
                </a>
              </div>
              <div class="order-3">
                <div class="mb-10 lg:mb-16">
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-lg bg-primary text-white mb-4">
                    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6.79 5.093A.5.5 0 0 0 6 5.5v5a.5.5 0 0 0 .79.407l3.5-2.5a.5.5 0 0 0 0-.814z"/><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z"/></svg>
                  </div>
                  <h3 class="text-lg font-display font-bold text-white mb-2">{{ __('accueil.verification_de_video') }}</h3>
                  <p>{{ __('accueil.analysez_des_liens_youtube_tiktok_et_autres') }}</p>
                </div>
                <div>
                  <div class="inline-flex items-center justify-center w-14 h-14 rounded-lg bg-primary text-white mb-4">
                    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 3a3 3 0 0 1 6 0v5a3 3 0 0 1-6 0z"/><path d="M3.5 6.5A.5.5 0 0 1 4 7v1a4 4 0 0 0 8 0V7a.5.5 0 0 1 1 0v1a5 5 0 0 1-4.5 4.975V15h3a.5.5 0 0 1 0 1h-7a.5.5 0 0 1 0-1h3v-2.025A5 5 0 0 1 3 8V7a.5.5 0 0 1 .5-.5"/></svg>
                  </div>
                  <h3 class="text-lg font-display font-bold text-white mb-2">{{ __('accueil.verification_daudio') }}</h3>
                  <p>{{ __('accueil.verifiez_vos_audios_whatsapp_et_autres_en') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ APPRENTISSAGE ============ -->
      <section class="pt-24" id="apprentissage">
        <div class="max-w-page mx-auto px-4">
          <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
              <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-5">{{ __('accueil.apprendre_a_verifier_linformation_pas_a_la') }}</h2>
              <p class="mb-0">{{ __('accueil.verifall_integre_une_plateforme_dapprentissage_en_ligne') }}</p>
              <hr class="my-8 border-line">
              <div class="grid sm:grid-cols-2 gap-6">
                <div class="flex gap-3">
                  <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary shrink-0" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917z"/><path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466z"/></svg>
                  </span>
                  <h6 class="font-display font-bold text-heading text-[15px]">{{ __('accueil.cours_structures_et_progressifs_du_niveau_debutant') }}</h6>
                </div>
                <div class="flex gap-3">
                  <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary shrink-0" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.118a1 1 0 0 0 .101.025 1 1 0 0 0 .1-.025q.114-.034.294-.118c.24-.113.547-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524z"/><path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0"/></svg>
                  </span>
                  <h6 class="font-display font-bold text-heading text-[15px]">{{ __('accueil.methodes_fiables_et_verifiees_danalyse_de_sources') }}</h6>
                </div>
                <div class="flex gap-3">
                  <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary shrink-0" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M6.271 5.055a.5.5 0 0 1 .52.038l3.5 2.5a.5.5 0 0 1 0 .814l-3.5 2.5A.5.5 0 0 1 6 10.5v-5a.5.5 0 0 1 .271-.445"/></svg>
                  </span>
                  <h6 class="font-display font-bold text-heading text-[15px]">{{ __('accueil.videos_quiz_et_exercices_pratiques') }}</h6>
                </div>
                <div class="flex gap-3">
                  <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary shrink-0" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg>
                  </span>
                  <h6 class="font-display font-bold text-heading text-[15px]">{{ __('accueil.une_communaute_de_journalistes_et_acteurs') }}</h6>
                </div>
              </div>
            </div>
            <div class="flex justify-center lg:justify-end">
              <div class="bg-white border border-line rounded-xl p-5 max-w-sm w-full shadow-card">
                <h6 class="font-display font-bold text-heading mb-3">{{ __('accueil.cours_en_ligne_fact_checking') }}</h6>
                <img src="{{ asset('assets/images/lms/feature_img_lms.webp') }}" width="330" height="180" loading="lazy" class="rounded-lg w-full mb-4" alt="{{ __('accueil.alt_cours_en_ligne_fact_checking') }}">
                <h6 class="font-display font-bold text-heading mb-2">{{ __('accueil.identifier_une_fake_news_en_5_etapes') }}</h6>
                <ul class="flex items-center gap-3 text-sm text-body mb-3">
                  <li>{{ __('accueil.module_1') }}</li>
                  <li class="w-1 h-1 rounded-full bg-body/40" aria-hidden="true"></li>
                  <li>{{ __('accueil.duree_45_min') }}</li>
                </ul>
                <div class="flex items-center mb-4">
                  <img class="w-10 h-10 rounded-full" src="{{ asset('assets/images/verifall_avatar.png') }}" alt="{{ __('accueil.alt_avatar_verifall') }}" width="40" height="40" loading="lazy">
                  <small class="ms-2 font-bold text-heading">Vérif'All</small>
                </div>
                <a class="w-full inline-flex justify-center items-center rounded-lg bg-dark/10 text-heading font-semibold px-4 py-2.5 hover:bg-dark hover:text-white transition-colors" href="{{ route('public.tutorials') }}">{{ __('accueil.apprenez_a_fact_checker_bouton') }}</a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ ACTUALITÉS ============ -->
      <section class="pt-24" id="blog">
        <div class="max-w-page mx-auto px-4">
          <div class="grid xl:grid-cols-3 gap-10 items-center">
            <div class="text-center xl:text-left max-w-2xl mx-auto">
              <h2 class="text-2xl md:text-3xl font-display font-bold text-heading mb-4">{{ __('accueil.nos_actualites_et_analyses') }}</h2>
              <p class="mb-0">{{ __('accueil.decouvrez_des_articles_sur_la_lutte_contre') }}</p>
              <div class="mt-6">
                <a class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 text-primary text-lg font-semibold px-5 py-3 hover:bg-primary hover:text-white transition-colors" href="{{ route('public.facts') }}">{{ __('accueil.voir_nos_actualites') }}<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg></a>
              </div>
            </div>
            <div class="xl:col-span-2">
              <div data-carousel class="carousel-cols">
                <div class="flex items-center justify-end gap-2 mb-4">
                  <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-heading hover:bg-gray-200" data-carousel-prev aria-label="{{ __('accueil.aria_analyses_precedentes') }}">
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/></svg>
                  </button>
                  <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-heading hover:bg-gray-200" data-carousel-next aria-label="{{ __('accueil.aria_analyses_suivantes') }}">
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                  </button>
                </div>
                <div class="overflow-hidden">
                  <div class="carousel-track">
                  <div class="carousel-slide">
                    <article class="relative bg-white rounded-xl shadow-card overflow-hidden h-full">
                      <span class="fact-stamp" aria-hidden="true">FAKE NEWS</span>
                      <div class="p-6 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                          <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 text-danger text-xs font-bold px-3 py-1.5">{{ __('accueil.faux') }}</span>
                          <span class="text-xs text-body/60">{{ __('accueil.analyse_verif_all') }}</span>
                        </div>
                        <h6 class="font-display font-bold text-heading leading-snug mb-2">
                          <a href="{{ route('public.home') }}" class="card-title-link">{{ __('accueil.titre_fc_intervention_militaire_benin') }}</a>
                        </h6>
                        <p class="text-sm text-body/80 leading-relaxed flex-1">{{ __('accueil.une_video_d_echauffourees_au_senat_nigerian') }}</p>
                        <a href="{{ route('public.home') }}" class="mt-3 inline-flex items-center gap-1.5 text-primary text-sm font-bold hover:gap-2.5 transition-all">{{ __('accueil.lire_l_analyse_complete') }}
                          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                        </a>
                      </div>
                    </article>
                  </div>
                  <div class="carousel-slide">
                    <article class="relative bg-white rounded-xl shadow-card overflow-hidden h-full">
                      <span class="fact-stamp" aria-hidden="true">FAKE NEWS</span>
                      <div class="p-6 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                          <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 text-danger text-xs font-bold px-3 py-1.5">{{ __('accueil.faux') }}</span>
                          <span class="text-xs text-body/60">{{ __('accueil.analyse_verif_all') }}</span>
                        </div>
                        <h6 class="font-display font-bold text-heading leading-snug mb-2">
                          <a href="{{ route('public.home') }}" class="card-title-link">{{ __('accueil.titre_fc_arrestation_patrice_talon') }}</a>
                        </h6>
                        <p class="text-sm text-body/80 leading-relaxed flex-1">{{ __('accueil.le_president_n_a_jamais_ete_arrete') }}</p>
                        <a href="{{ route('public.home') }}" class="mt-3 inline-flex items-center gap-1.5 text-primary text-sm font-bold hover:gap-2.5 transition-all">{{ __('accueil.lire_l_analyse_complete') }}
                          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                        </a>
                      </div>
                    </article>
                  </div>
                  <div class="carousel-slide">
                    <article class="relative bg-white rounded-xl shadow-card overflow-hidden h-full">
                      <span class="fact-stamp" aria-hidden="true">FAKE NEWS</span>
                      <div class="p-6 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                          <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 text-danger text-xs font-bold px-3 py-1.5">{{ __('accueil.faux') }}</span>
                          <span class="text-xs text-body/60">{{ __('accueil.analyse_verif_all') }}</span>
                        </div>
                        <h6 class="font-display font-bold text-heading leading-snug mb-2">
                          <a href="{{ route('public.home') }}" class="card-title-link">{{ __('accueil.titre_fc_kemi_seba_passeport') }}</a>
                        </h6>
                        <p class="text-sm text-body/80 leading-relaxed flex-1">{{ __('accueil.l_interpellation_a_pretoria_est_reelle_le') }}</p>
                        <a href="{{ route('public.home') }}" class="mt-3 inline-flex items-center gap-1.5 text-primary text-sm font-bold hover:gap-2.5 transition-all">{{ __('accueil.lire_l_analyse_complete') }}
                          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                        </a>
                      </div>
                    </article>
                  </div>
                  <div class="carousel-slide">
                    <article class="relative bg-white rounded-xl shadow-card overflow-hidden h-full">
                      <span class="fact-stamp" aria-hidden="true">FAKE NEWS</span>
                      <div class="p-6 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                          <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 text-danger text-xs font-bold px-3 py-1.5">{{ __('accueil.faux') }}</span>
                          <span class="text-xs text-body/60">{{ __('accueil.analyse_verif_all') }}</span>
                        </div>
                        <h6 class="font-display font-bold text-heading leading-snug mb-2">
                          <a href="{{ route('public.home') }}" class="card-title-link">{{ __('accueil.titre_fc_franck_biya_vice_president') }}</a>
                        </h6>
                        <p class="text-sm text-body/80 leading-relaxed flex-1">{{ __('accueil.le_pretendu_decret_du_4_avril_2026') }}</p>
                        <a href="{{ route('public.home') }}" class="mt-3 inline-flex items-center gap-1.5 text-primary text-sm font-bold hover:gap-2.5 transition-all">{{ __('accueil.lire_l_analyse_complete') }}
                          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                        </a>
                      </div>
                    </article>
                  </div>
                  <div class="carousel-slide">
                    <article class="relative bg-white rounded-xl shadow-card overflow-hidden h-full">
                      <span class="fact-stamp" aria-hidden="true">FAKE NEWS</span>
                      <div class="p-6 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                          <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 text-danger text-xs font-bold px-3 py-1.5">{{ __('accueil.faux') }}</span>
                          <span class="text-xs text-body/60">{{ __('accueil.analyse_verif_all') }}</span>
                        </div>
                        <h6 class="font-display font-bold text-heading leading-snug mb-2">
                          <a href="{{ route('public.home') }}" class="card-title-link">{{ __('accueil.titre_fc_migrants_usa_rdc') }}</a>
                        </h6>
                        <p class="text-sm text-body/80 leading-relaxed flex-1">{{ __('accueil.les_photos_du_8_avril_2026_ne') }}</p>
                        <a href="{{ route('public.home') }}" class="mt-3 inline-flex items-center gap-1.5 text-primary text-sm font-bold hover:gap-2.5 transition-all">{{ __('accueil.lire_l_analyse_complete') }}
                          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                        </a>
                      </div>
                    </article>
                  </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
    </section>

      <!-- ============ STORES + FAQ ============ -->
      {{-- ============ PARRAINAGE ============
        | AJOUT au prototype, consigné (arbitrage 171).
        |
        | DÉLIBÉRÉMENT MINIMALISTE : une ligne, deux chiffres, un lien. La page
        | d accueil porte déjà huit sections ; y greffer un bloc promotionnel
        | complet déséquilibrerait la lecture et ferait de l'accueil une vitrine
        | de plus.
        |
        | Placé APRÈS le blog et AVANT la FAQ : quelqu'un qui a lu jusque là
        | connaît le produit. C'est le moment où « en parler autour de moi »
        | devient une idée, pas une sollicitation.
        |
        | Les chiffres viennent des constantes du service, jamais du texte.
      --}}
      <section class="pt-24" id="parrainage">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-[#f8f9fa] rounded-2xl border border-line p-8 md:p-10">
            <div class="flex flex-col md:flex-row md:items-center gap-6">
              <div class="flex-1">
                <h2 class="text-2xl md:text-3xl font-display font-bold text-heading mb-2">{{ __('accueil.invitez_un_proche_a_verifier') }}</h2>
                <p class="text-body/80 mb-0">
                  {{ __('accueil.chaque_personne_qui_fait_sa_premiere_verification') }}
                  {{-- Le barème vient des RÉGLAGES et non plus des constantes du
                       service : la page la plus visitée du site ne doit pas
                       annoncer un montant que l'administration a changé. --}}
                  {{ number_format(app(\App\Core\Marketing\Services\Parrainage::class)->unitesParrain(), 0, ',', ' ') }} {{ __('accueil.unites_virgule') }}
                  {{ __('accueil.et_en_recoit') }} {{ number_format(app(\App\Core\Marketing\Services\Parrainage::class)->unitesFilleul(), 0, ',', ' ') }}.
                </p>
              </div>
              <div class="shrink-0">
                <a href="{{ route('public.referral') }}" class="inline-flex items-center gap-2 rounded-lg bg-dark text-white font-semibold px-5 py-3 hover:bg-black transition-colors">
                  {{ __('accueil.comment_ca_marche') }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pt-24" id="faq-appstore">
        <div class="max-w-page mx-auto px-4">
          <div class="grid lg:grid-cols-2 gap-8 xl:gap-14">
            <div class="bg-[#f8f9fa] rounded-xl overflow-hidden flex flex-col">
              <div class="p-6 sm:p-12 pb-0">
                <h3 class="text-2xl md:text-3xl font-display font-bold text-heading mb-4">{{ __('accueil.bientot_disponible_sur_toutes_les_plateformes') }}</h3>
                <p class="text-heading/75 mb-6">{{ __('accueil.preparez_vous_a_decouvrir_notre_application_et') }}</p>
                <div class="flex gap-2">
                  <a href="{{ route('public.verify') }}"><img src="{{ asset('assets/images/elements/google-play.svg') }}" class="transition-transform hover:-translate-y-0.5" alt="{{ __('commun.alt_google_play') }}" width="135" height="40" loading="lazy"></a>
                  <a href="{{ route('public.verify') }}"><img src="{{ asset('assets/images/elements/app-store.svg') }}" class="transition-transform hover:-translate-y-0.5" alt="{{ __('commun.alt_app_store') }}" width="135" height="40" loading="lazy"></a>
                </div>
              </div>
              <div class="mt-auto pt-8">
                <img src="{{ asset('assets/images/elements/saas-screen.webp') }}" class="ms-10 rounded-tl-xl w-full max-w-[500px]" alt="Interface VerifAll" width="500" height="375" loading="lazy">
              </div>
            </div>
            <div class="bg-dark rounded-xl overflow-hidden relative p-6 sm:p-10 text-[#c4c5c7]">
              <div class="text-center relative mb-6">
                <img src="{{ asset('assets/images/logo-icon.png') }}" class="h-[60px] w-auto mx-auto mb-5" alt="{{ __('commun.alt_logo') }}">
                <h3 class="text-2xl font-display font-bold text-white mb-2">{{ __('accueil.questions_frequentes') }}</h3>
                <p>{{ __('accueil.pour_utilisateurs_et_partenaires_contactez_nous_pour') }}</p>
              </div>
              <div data-accordion class="accordion-dark relative">
                <div class="accordion-item mb-3 is-open">
                  <h4 class="m-0 text-base">{!! __('accueil.comment_fonctionne_verif_all') !!}</h4>
                  <div class="accordion-body">{{ __('accueil.reponse_comment_fonctionne_verif_all') }}</div>
                </div>
                <div class="accordion-item mb-3">
                  <h4 class="m-0 text-base">{!! __('accueil.qui_peut_rejoindre_la_communaute') !!}</h4>
                  <div class="accordion-body">{{ __('accueil.reponse_qui_peut_rejoindre_la_communaute') }}</div>
                </div>
                <div class="accordion-item mb-3">
                  <h4 class="m-0 text-base">{!! __('accueil.comment_obtenir_l_application') !!}</h4>
                  <div class="accordion-body">{{ __('accueil.reponse_comment_obtenir_l_application') }}</div>
                </div>
                <div class="accordion-item mb-3">
                  <h4 class="m-0 text-base">{!! __('accueil.comment_nous_contacter') !!}</h4>
                  <div class="accordion-body">{{ __('accueil.reponse_comment_nous_contacter') }}</div>
                </div>
              </div>
              <div class="text-center relative mt-6">
                <a href="{{ route('public.contact') }}" class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white text-lg font-semibold px-4 py-3 hover:bg-primary-dark transition-colors">{{ __('accueil.contactez_nous_pour_partenariats_ou_informations') }}</a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ NEWSLETTER ============ -->
      {{-- `newsletter-section` est l'ancre que vise la campagne « newsletter-boom ».
           Elle est posée ICI, sur la section que le prototype consacre déjà à
           l'abonnement, plutôt que sur un second bloc ajouté à côté. --}}
      <section class="py-24" id="tester-verifall">
        <span id="newsletter-section" class="sr-only" aria-hidden="true"></span>
        <div class="max-w-2xl mx-auto px-4 text-center">
          <figure class="mb-6 flex justify-center" aria-hidden="true"><svg width="223.6px" height="87.2px" viewBox="0 0 223.6 87.2" style="enable-background:new 0 0 223.6 87.2;" xml:space="preserve">
    					<path fill="#202124" d="M222.9,53.8c-13.2-3-28-3-41,0.9c-5.5,1.7-11,4.3-14.9,8.7c-1.3-0.1-2.6-0.2-3.8-0.1 c-5.8,0.1-11.6,1.5-16.9,3.7c-2.9,1.2-5.7,2.9-8.5,4.4c-3.7,1.9-7.4,3.8-11.3,5.3c-7.3,2.9-16,5-23.5,1.7c-1.5-0.6-2.9-1.5-4.1-2.6 c6.5-2.6,12.2-7.9,13.2-15c0.8-6.6-5.1-12.1-11.6-11.4c-5,0.6-7.5,5.6-8.3,10.1c-0.9,4.9-0.3,10.8,2.7,14.9 c0.2,0.2,0.4,0.5,0.5,0.7c-0.4,0.1-0.8,0.2-1.3,0.3c-6.6,1.5-14.3,0.3-20.3-2.9c-6.1-3.3-10.3-9.1-12.3-15.6 c-0.2-0.7-1.2-0.4-1,0.3c2.1,7.3,6.7,13.4,13.4,17.1c6.8,3.7,15.4,4.5,22.7,2.4c0,0,0.1,0,0.1,0c4.5,4.4,11.2,5.9,17.3,5.4 c8-0.6,15.6-4.1,22.7-7.7c5.6-2.9,10.8-6,17-7.6c3.8-0.9,7.8-1.5,11.8-1.3c-3.3,4.8-4.6,11.1-2.3,16.5c2.8,6.4,11.3,6.7,16.3,2.8 c5.1-4,2.8-12.4-1-16.4c-2.4-2.5-5.7-3.9-9.1-4.5c0.2-0.2,0.3-0.3,0.5-0.4c4.3-4,10.2-6.2,15.9-7.5c11.8-2.8,24.9-2.7,36.7,0 C223.6,56,224.1,54,222.9,53.8z M95.5,71.6c-1.2-2.4-1.7-5.1-1.8-7.8c-0.1-4.5,1.1-11.2,6.1-12.6c2.4-0.7,5.2,0.4,7.2,1.7 c2.9,1.9,3.5,5.5,2.9,8.7c-1.2,6.2-6.8,10.5-12.6,12.6C96.7,73.4,96,72.5,95.5,71.6z M171.5,66.3c5.7,1.8,10.3,7.8,8.5,14 c-1.1,3.9-6.1,5.2-9.6,4.8c-3.5-0.4-5.5-3.4-6.2-6.5c-1.1-4.7,0.6-9.5,3.5-13.1C169,65.7,170.2,65.9,171.5,66.3z"></path>
    					<polygon fill="#09B850" points="65.3,39 61,56.8 0.7,0.7"></polygon>
    					<path fill="#202124" d="M60.6,57.3L0.2,1.1C0,0.9-0.1,0.5,0.1,0.3C0.3,0,0.7-0.1,1,0.1l64.7,38.3c0.2,0.1,0.4,0.4,0.3,0.7l-4.3,17.8 c-0.1,0.2-0.2,0.4-0.4,0.5c-0.1,0-0.1,0-0.2,0C60.9,57.4,60.7,57.4,60.6,57.3z M5.6,4.3l55.1,51.2l3.9-16.3L5.6,4.3z"></path>
    					<polygon fill="#09B850" points="56.5,42.4 61,56.8 0.7,0.8"></polygon>
    					<path fill="#202124" d="M60.6,57.3L0.2,1.3C0,1.1-0.1,0.7,0.2,0.4c0.2-0.3,0.6-0.3,0.9-0.1l55.8,41.5c0.1,0.1,0.2,0.2,0.2,0.3 l4.6,14.4c0.1,0.3,0,0.6-0.3,0.8c-0.1,0.1-0.2,0.1-0.4,0.1C60.9,57.4,60.7,57.4,60.6,57.3z M10.1,8.7l49.6,45.9l-3.8-11.8 L10.1,8.7z"></path>
    					<polygon fill="#09B850" points="0.7,0.7 91.5,28.5 65.2,38.8 			"></polygon>
    					<path fill="#202124" d="M64.9,39.4L0.3,1.2C0,1.1-0.1,0.7,0.1,0.4C0.2,0.1,0.5-0.1,0.9,0l90.9,27.8c0.3,0.1,0.5,0.3,0.5,0.6 c0,0.3-0.2,0.5-0.4,0.6L65.4,39.4c-0.1,0-0.2,0-0.2,0C65.1,39.4,65,39.4,64.9,39.4z M5.8,2.9l59.5,35.2l24.3-9.5L5.8,2.9z"></path>
    					<polygon fill="#09B850" points="56.3,42.4 26.5,57.6 0.7,0.7 			"></polygon>
    					<path fill="#202124" d="M26.3,58.3c-0.2-0.1-0.3-0.2-0.4-0.3L0.1,0.9c-0.1-0.3,0-0.6,0.2-0.8C0.5,0,0.8,0,1.1,0.1l55.7,41.8 c0.2,0.1,0.3,0.4,0.3,0.6c0,0.2-0.2,0.4-0.4,0.5L26.8,58.2c-0.1,0-0.2,0.1-0.3,0.1C26.5,58.3,26.4,58.3,26.3,58.3z M2.3,2.7 l24.5,54l28.2-14.4L2.3,2.7z"></path>
    				</svg></figure>
          <h4 class="text-2xl md:text-3xl font-display font-bold text-heading mb-3">{{ __('accueil.rejoignez_la_communaute_anti_fake_news') }}</h4>
          <p>{{ __('accueil.inscrivez_vous_maintenant_et_recevez_des_mises') }}</p>
          <div class="relative mt-10">
            {{--
              | CE FORMULAIRE POSTE RÉELLEMENT — et c'était le contraire.
              |
              | Le prototype le portait avec `data-vf-form`, dont le gestionnaire
              | (`verifall.js`) appelle `ev.preventDefault()`, attend 900 ms et
              | affiche un toast de succès. Aucune `action`, aucun `name` sur le
              | champ : la promesse était faite, rien n'était envoyé nulle part.
              |
              | Ces deux attributs sont donc RETIRÉS. Les laisser reviendrait à
              | ce que le script avale la soumission : le message de bienvenue
              | s'afficherait toujours, et l'adresse ne serait toujours pas
              | enregistrée — le pire des deux mondes, puisque plus rien ne
              | signalerait la panne.
              |
              | Le balisage, les classes et le libellé du bouton sont inchangés.
            --}}
            @if (session('infolettre'))
              <p class="text-sm text-heading font-semibold mb-3" role="status">{{ session('infolettre') }}</p>
            @endif
            @error('email')
              <p class="text-sm text-vfalse mb-3" role="alert">{{ $message }}</p>
            @enderror
            <form method="POST" action="{{ route('public.newsletter.subscribe') }}" class="flex gap-1 bg-[#f8f9fa] border border-line rounded-lg relative z-10 p-2">
              @csrf
              <label for="nl-email" class="sr-only">{{ __('accueil.adresse_e_mail') }}</label>
              <input type="email" name="email" id="nl-email" required autocomplete="email" class="flex-1 bg-transparent px-4 py-3 text-heading outline-none plain" placeholder="{{ __('accueil.ph_entrez_votre_adresse_email') }}">
              <button type="submit" class="inline-flex items-center rounded-lg bg-dark text-white text-lg font-semibold px-5 py-3 hover:bg-black transition-colors">{{ __('accueil.s_inscrire') }}</button>
            </form>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
