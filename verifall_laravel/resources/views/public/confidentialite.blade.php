{{--
  | Politique de confidentialité.
  |
  | Structure, classes et bloc d'appel repris du prototype. Le contenu a été
  | porté aux exigences attendues d'un traitement de données personnelles :
  | responsable identifié, bases légales explicites, destinataires nommés,
  | durées de conservation chiffrées, droits et voie de recours.
  |
  | Le prototype tenait en six sections courtes et renvoyait à une adresse
  | (confidentialite@verifall.com) dont rien n'établit qu'elle existe. Elle est
  | remplacée par le réglage prévu pour cela, qui retombe sur l'adresse de
  | contact publique quand aucune adresse dédiée n'est déclarée : mieux vaut
  | une adresse générale qui reçoit qu'une adresse dédiée qui rebondit.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-36 pb-10">
        <div class="max-w-3xl mx-auto px-4">
          <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-display font-bold text-heading">{{ __('confidentialite.politique_de_confidentialite') }}</h1>
            <p class="text-lg text-body/80 mt-2 mb-0">{{ __('confidentialite.derniere_mise_a_jour') }} {{ now()->translatedFormat('j F Y') }}</p>
          </div>
          <div class="prose-vf text-[#606261]">
            <p>{{ __('confidentialite.la_protection_de_vos_donnees_personnelles_est') }}</p>

            <h6>{{ __('confidentialite.1_responsable_du_traitement') }}</h6>
            <p>{{ __('confidentialite.le_responsable_du_traitement_est') }} <x-mention-legale :valeur="$legal->entite()" reglage="legal_entity" :libelle="__('confidentialite.raison_sociale')" />{{ __('confidentialite.dont_le_siege_est_situe') }} <x-mention-legale :valeur="$legal->adresse()" reglage="legal_address" :libelle="__('confidentialite.adresse_du_siege')" />.</p>
            <p>{{ __('confidentialite.pour_toute_question_relative_a_vos_donnees') }} {{ $legal->contactDonnees() ?? 'contact@verifall.com' }}.</p>

            <h6>{{ __('confidentialite.2_donnees_collectees') }}</h6>
            <ul>
              <li>{!! __('confidentialite.donnees_fournies_par_vous_a_l_inscription') !!}</li>
              <li>{!! __('confidentialite.donnees_de_contenu_les_textes_images_videos') !!}</li>
              <li>{!! __('confidentialite.donnees_d_utilisation_les_tutoriels_suivis_les') !!}</li>
              <li>{!! __('confidentialite.donnees_techniques_type_de_navigateur_systeme_d') !!}</li>
              <li>{!! __('confidentialite.adresse_ip_elle_n_est_jamais_conservee') !!}</li>
            </ul>

            <h6>{{ __('confidentialite.3_bases_legales_et_finalites') }}</h6>
            <p>{{ __('confidentialite.chaque_traitement_repose_sur_une_base_precise') }}</p>
            <ul>
              <li>{!! __('confidentialite.execution_du_service_gestion_de_votre_compte') !!}</li>
              <li>{!! __('confidentialite.consentement_reception_de_l_infolettre_hebdomadaire_depot') !!}</li>
              <li>{!! __('confidentialite.interet_legitime_securite_de_la_plateforme_prevention') !!}</li>
              <li>{!! __('confidentialite.obligation_legale_conservation_des_traces_exigees_en') !!}</li>
            </ul>
            <p>{!! __('confidentialite.ce_que_nous_ne_faisons_pas_aucune') !!}</p>

            <h6>{{ __('confidentialite.4_contenus_soumis_a_verification') }}</h6>
            <p>{{ __('confidentialite.les_contenus_que_vous_soumettez_sont_analyses') }}</p>
            <p>{{ __('confidentialite.lorsqu_une_affirmation_deja_verifiee_est_soumise') }}</p>

            <h6>{{ __('confidentialite.5_destinataires_et_sous_traitants') }}</h6>
            <p>{{ __('confidentialite.vos_donnees_peuvent_etre_traitees_pour_notre') }}</p>
            <ul>
              <li><strong>{{ __('confidentialite.l_hebergeur') }}</strong> <x-mention-legale :valeur="$legal->hebergeur()" reglage="legal_host_name" :libelle="__('confidentialite.nom_de_l_hebergeur')" />.</li>
              <li>{!! __('confidentialite.les_services_d_analyse_tiers_employes_pendant') !!}</li>
              <li>{!! __('confidentialite.le_service_d_envoi_de_courriels_pour') !!}</li>
              <li>{!! __('confidentialite.l_autorite_judiciaire_lorsque_la_loi_nous') !!}</li>
            </ul>
            <p>{{ __('confidentialite.certains_de_ces_prestataires_sont_etablis_hors') }}</p>

            <h6>{{ __('confidentialite.6_durees_de_conservation') }}</h6>
            <p>{{ __('confidentialite.aucune_donnee_n_est_conservee_indefiniment') }}</p>
            <ul>
              <li>{!! __('confidentialite.compte_actif_vos_donnees_sont_conservees_tant') !!}</li>
              <li>{!! __('confidentialite.compte_supprime_la_suppression_prend_effet_apres') !!}</li>
              <li>{!! __('confidentialite.verifications_publiees_conservees_sans_limite_de_duree') !!}</li>
              <li>{!! __('confidentialite.journaux_de_securite_conserves_le_temps_necessaire') !!}</li>
              <li>{!! __('confidentialite.abonnement_a_l_infolettre_conserve_jusqu_au') !!}</li>
            </ul>

            <h6>{{ __('confidentialite.7_securite') }}</h6>
            <p>{{ __('confidentialite.les_communications_sont_chiffrees_par_https_les') }}</p>

            <h6>{{ __('confidentialite.8_vos_droits') }}</h6>
            <p>{{ __('confidentialite.vous_disposez_d_un_droit_d_acces') }}</p>
            <ul>
              <li>{!! __('confidentialite.acces_et_portabilite_depuis_votre_espace_citoyen') !!}</li>
              <li>{!! __('confidentialite.rectification_vos_informations_de_profil_se_modifient') !!}</li>
              <li>{!! __('confidentialite.effacement_la_suppression_du_compte_se_demande', ['lien_suppression' => '<a href="'.route('public.account-deletion').'" class="text-primary font-bold">'.__('confidentialite.lien_supprimer_mon_compte').'</a>']) !!}</li>
              <li>{!! __('confidentialite.opposition_et_retrait_du_consentement_le_desabonnement') !!}</li>
            </ul>
            <p>{{ __('confidentialite.ces_droits_s_exercent_a_l_adresse') }} {{ $legal->contactDonnees() ?? 'contact@verifall.com' }}{{ __('confidentialite.nous_repondons_dans_un_delai_raisonnable') }}</p>
            <p>{{ __('confidentialite.vous_pouvez_egalement_saisir_l_autorite_de') }}</p>

            <h6>{{ __('confidentialite.9_mineurs') }}</h6>
            <p>{{ __('confidentialite.la_plateforme_s_adresse_a_des_personnes') }} {{ $legal->contactDonnees() ?? 'contact@verifall.com' }}{{ __('confidentialite.il_sera_supprime') }}</p>

            <h6>{{ __('confidentialite.10_modification_de_la_presente_politique') }}</h6>
            <p>{{ __('confidentialite.toute_evolution_substantielle_est_annoncee_sur_la') }}</p>
          </div>
        </div>
      </section>

      <section class="pb-20">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-primary/10 rounded-xl p-10 md:p-14 text-center">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('confidentialite.rejoignez_un_espace_d_information_transparent_et') }}</h2>
            <p class="text-body/85 mb-6">{!! __('confidentialite.maintenant_que_vous_connaissez_nos_engagements_passez') !!}</p>
            <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('commun.compte.creer_compte') }}</a>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
