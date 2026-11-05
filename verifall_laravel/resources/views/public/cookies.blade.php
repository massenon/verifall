{{--
  | Politique des cookies.
  |
  | Structure, classes et bloc d'appel repris du prototype. Le contenu a été
  | porté aux exigences attendues : chaque traceur est nommé, sa finalité et sa
  | durée sont dites, et la manière de retirer son accord est décrite.
  |
  | UNE CORRECTION DE FOND. Le prototype affirmait employer Google Analytics et
  | renvoyait à un lien « Gérer mes cookies » présent en bas de page. Ni l'un ni
  | l'autre n'existent : aucun identifiant de mesure n'est configuré par défaut,
  | et aucun lien de gestion n'est posé dans le pied de page. Une politique qui
  | annonce des traceurs absents est aussi fautive qu'une politique qui en tait :
  | dans les deux cas, elle décrit autre chose que ce que fait le site.
  |
  | Le texte dit donc l'état réel, et ce qui change si la mesure d'audience est
  | un jour activée depuis les réglages.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-36 pb-10">
        <div class="max-w-3xl mx-auto px-4">
          <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-display font-bold text-heading">{{ __('cookies.politique_des_cookies') }}</h1>
            <p class="text-lg text-body/80 mt-2 mb-0">{{ __('cookies.derniere_mise_a_jour') }} {{ now()->translatedFormat('j F Y') }}</p>
          </div>
          <div class="prose-vf text-[#606261]">
            <h6>{{ __('cookies.1_ce_qu_est_un_cookie') }}</h6>
            <p>{{ __('cookies.un_cookie_est_un_petit_fichier_depose') }}</p>

            <div class="bg-primary/10 border border-primary/20 rounded-lg px-6 py-5 mb-6">
              <p class="font-semibold text-heading mb-2">{!! __('cookies.pourquoi_pas_de_bandeau_titre') !!}</p>
              <p class="mb-0">{!! __('cookies.pourquoi_pas_de_bandeau_texte') !!}</p>
            </div>

            <h6>{{ __('cookies.2_les_traceurs_strictement_necessaires') }}</h6>
            <p>{{ __('cookies.ils_font_fonctionner_le_service_et_ne') }}</p>
            <ul>
              <li>{!! __('cookies.cookie_de_session_vous_garde_connecte_d') !!}</li>
              <li>{!! __('cookies.jeton_de_securite_csrf_empeche_qu_un') !!}</li>
              <li>{!! __('cookies.preference_de_langue_retient_la_langue_choisie') !!}</li>
            </ul>

            <h6>{{ __('cookies.3_la_mesure_d_audience') }}</h6>
            <p>{{ __('cookies.aucune_mesure_d_audience_n_est_active') }}</p>
            <p>{{ __('cookies.si_une_mesure_d_audience_est_activee') }}</p>

            <h6>{{ __('cookies.4_ce_que_nous_ne_deposons_pas') }}</h6>
            <p>{{ __('cookies.aucun_traceur_publicitaire_aucun_pixel_de_suivi') }}</p>

            <h6>{{ __('cookies.5_retirer_votre_accord') }}</h6>
            <p>{{ __('cookies.votre_choix_se_modifie_a_tout_moment') }}</p>
            <p>{{ __('cookies.vous_pouvez_egalement_configurer_votre_navigateur_pour') }}</p>

            <h6>{{ __('cookies.6_traceurs_deposes_par_des_tiers') }}</h6>
            <p>{{ __('cookies.certaines_pages_affichent_des_contenus_heberges_ailleurs') }}</p>

            <h6>{{ __('cookies.7_contact') }}</h6>
            <p>{{ __('cookies.pour_toute_question_relative_a_cette_politique') }} {{ $legal->contactDonnees() ?? 'contact@verifall.com' }}{{ __('cookies.le_traitement_de_vos_donnees_personnelles') }} <a href="{{ route('public.privacy') }}" class="text-primary font-bold">{{ __('cookies.politique_de_confidentialite') }}</a>.</p>
          </div>
        </div>
      </section>

      <section class="pb-20">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-primary/10 rounded-xl p-10 md:p-14 text-center">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('cookies.rejoignez_un_espace_d_information_transparent_et') }}</h2>
            <p class="text-body/85 mb-6">{!! __('cookies.maintenant_que_vous_connaissez_nos_engagements_passez') !!}</p>
            <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('commun.compte.creer_compte') }}</a>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
