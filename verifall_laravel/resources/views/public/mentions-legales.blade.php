{{--
  | Mentions légales.
  |
  | Structure, classes et bloc d'appel repris du prototype. Deux choses seules
  | ont changé, et elles étaient nécessaires :
  |
  | 1. LES ESPACES RÉSERVÉS ONT DISPARU. Le prototype publiait « [Votre Adresse
  |    Complète] », « [Votre Nom Complet] », « [Nom de votre hébergeur] » sur la
  |    page même qu'ouvre celui qui veut savoir à qui il a affaire. Ces valeurs
  |    viennent désormais des réglages, modifiables sans déploiement.
  |
  | 2. LES VERDICTS CITÉS SONT LES VRAIS. Le texte annonçait « VRAI », « FAUX »,
  |    « DOUTEUX ». La plateforme n'a jamais émis de verdict « DOUTEUX » : ses
  |    quatre verdicts sont VRAI, FAUX, TROMPEUR et NON VÉRIFIABLE. Une page
  |    légale qui décrit des verdicts inexistants engage sur du vide.
  |
  | Trois sections ont été ajoutées, absentes du prototype et attendues d'une
  | organisation de fact-checking : la politique de correction, le signalement
  | d'un contenu illicite, et la médiation en cas de litige.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-36 pb-10">
        <div class="max-w-3xl mx-auto px-4">
          <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-display font-bold text-heading">{{ __('mentions_legales.mentions_legales') }}</h1>
            <p class="text-lg text-body/80 mt-2 mb-0">{{ __('mentions_legales.derniere_mise_a_jour', ['date' => now()->translatedFormat('j F Y')]) }}</p>
          </div>
          <div class="prose-vf text-[#606261]">
            <p>{{ __('mentions_legales.conformement_aux_dispositions_de_la_loi_n2017') }}</p>

            <h6>{{ __('mentions_legales.1_edition_du_site_et_de_l') }}</h6>
            <p>{{ __('mentions_legales.le_site_web_et_l_application_sont_edites_par') }}<br>
            <x-mention-legale :valeur="$legal->entite()" reglage="legal_entity" :libelle="__('mentions_legales.libelle_raison_sociale')" /><br>
            {{ __('mentions_legales.organisation_enregistree_en_republique_du_benin') }}<br>
            {{ __('mentions_legales.adresse') }} <x-mention-legale :valeur="$legal->adresse()" reglage="legal_address" :libelle="__('mentions_legales.libelle_adresse_du_siege')" /><br>
            {{ __('mentions_legales.courriel') }} {{ $legal->contact() ?? 'contact@verifall.com' }}</p>

            <h6>{{ __('mentions_legales.2_directeur_de_la_publication') }}</h6>
            <p>{{ __('mentions_legales.le_directeur_de_la_publication_est') }} <x-mention-legale :valeur="$legal->directeur()" reglage="legal_director" :libelle="__('mentions_legales.libelle_directeur_de_la_publication')" />{{ __('mentions_legales.en_sa_qualite_de_responsable_du_contenu') }}</p>

            <h6>{{ __('mentions_legales.3_hebergement') }}</h6>
            <p>{{ __('mentions_legales.la_plateforme_est_hebergee_par') }}<br>
            <x-mention-legale :valeur="$legal->hebergeur()" reglage="legal_host_name" :libelle="__('mentions_legales.libelle_nom_de_l_hebergeur')" /><br>
            @if ($legal->adresseHebergeur() !== null)
              {{ __('mentions_legales.adresse') }} {{ $legal->adresseHebergeur() }}<br>
            @endif
            @if ($legal->contactHebergeur() !== null)
              {{ __('mentions_legales.contact') }} {{ $legal->contactHebergeur() }}
            @endif
            </p>

            <h6>{{ __('mentions_legales.4_propriete_intellectuelle') }}</h6>
            <p>{{ __('mentions_legales.l_ensemble_des_elements_de_la_plateforme') }}</p>
            <p>{{ __('mentions_legales.les_rapports_de_verification_peuvent_etre_cites') }}</p>

            <h6>{{ __('mentions_legales.5_nature_et_portee_des_verifications') }}</h6>
            <p>{{ __('mentions_legales.verif_all_publie_quatre_verdicts_et_quatre') }}</p>
            <p>{{ __('mentions_legales.les_rapports_de_verification_sont_fournis_a') }}</p>
            <p>{{ __('mentions_legales.la_plateforme_contient_des_liens_vers_des') }}</p>

            <h6>{{ __('mentions_legales.6_politique_de_correction') }}</h6>
            <p>{{ __('mentions_legales.une_erreur_de_verdict_se_corrige') }} <a href="{{ route('public.corrections') }}" class="text-primary font-bold">{{ __('mentions_legales.journal_public_des_corrections') }}</a>{{ __('mentions_legales.consultable_sans_compte') }}</p>
            <p>{{ __('mentions_legales.pour_signaler_une_erreur_dans_une_verification', ['courriel' => $legal->contact() ?? 'contact@verifall.com']) }} <a href="{{ route('public.methodology') }}" class="text-primary font-bold">{{ __('mentions_legales.methodologie') }}</a>.</p>

            <h6>{{ __('mentions_legales.7_signalement_d_un_contenu_illicite') }}</h6>
            <p>{{ __('mentions_legales.tout_contenu_publie_par_un_utilisateur', ['courriel' => $legal->contact() ?? 'contact@verifall.com']) }}</p>
            <p>{{ __('mentions_legales.chaque_signalement_est_examine_et_fait_l') }}</p>

            <h6>{{ __('mentions_legales.8_donnees_personnelles') }}</h6>
            <p>{{ __('mentions_legales.le_traitement_des_donnees_personnelles_est_decrit') }} <a href="{{ route('public.privacy') }}" class="text-primary font-bold">{{ __('mentions_legales.politique_de_confidentialite') }}</a>{{ __('mentions_legales.l_usage_des_traceurs_est_decrit_dans') }} <a href="{{ route('public.cookies') }}" class="text-primary font-bold">{{ __('mentions_legales.politique_des_cookies') }}</a>.</p>

            <h6>{{ __('mentions_legales.9_financement_et_independance') }}</h6>
            <p>{{ __('mentions_legales.les_sources_de_financement_sont_publiees_sur') }} <a href="{{ route('public.funding') }}" class="text-primary font-bold">{{ __('mentions_legales.financement') }}</a>{{ __('mentions_legales.aucun_financeur_n_exerce_de_controle_editorial') }}</p>

            <h6>{{ __('mentions_legales.10_droit_applicable_et_reglement_des_litiges') }}</h6>
            <p>{{ __('mentions_legales.l_utilisation_de_la_plateforme_est_soumise', ['courriel' => $legal->contact() ?? 'contact@verifall.com']) }}</p>
            <p>{{ __('mentions_legales.a_defaut_d_accord_competence_exclusive_est') }}</p>

            <h6 id="accessibilite">{{ __('mentions_legales.11_accessibilite_numerique') }}</h6>
            <p>{{ __('mentions_legales.verifall_s_engage_a_rendre_son_site_accessible') }}</p>
            <p>{{ __('mentions_legales.etat_de_conformite_actuel') }}</p>
            <p>{{ __('mentions_legales.pour_signaler_un_probleme_accessibilite') }} {{ $legal->contact() ?? 'contact@verifall.com' }}{{ __('mentions_legales.accusé_reception_accessibilite') }}</p>
            <p>{{ __('mentions_legales.voie_de_recours_accessibilite') }}</p>
          </div>
        </div>
      </section>

      <section class="pb-20">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-primary/10 rounded-xl p-10 md:p-14 text-center">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('mentions_legales.rejoignez_un_espace_d_information_transparent_et') }}</h2>
            <p class="text-body/85 mb-6">{!! __('mentions_legales.maintenant_que_vous_connaissez_nos_engagements_passez') !!}</p>
            <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('mentions_legales.creer_mon_compte') }}</a>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
