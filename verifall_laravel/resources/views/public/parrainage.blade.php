{{--
  | « Parrainage » — page publique informative.
  |
  | AJOUT au prototype, consigné aux arbitrages. Le programme existait en base,
  | avec son écran citoyen et son écran d'administration, mais aucune page ne
  | l'expliquait à qui n'a pas encore de compte. Or c'est précisément la
  | personne qui reçoit un lien de parrainage : elle arrive sans savoir ce qu'on
  | lui propose.
  |
  | AUCUN CHIFFRE N'EST ÉCRIT EN DUR, et depuis le lot 3 ils ne viennent même
  | plus des constantes du service : ils sont lus dans les RÉGLAGES, où le
  | porteur du projet les fixe sans développeur. Une page promotionnelle qui
  | annonce un barème périmé est une promesse rompue, et c'est le pire endroit
  | pour en faire une.
  |
  | Structure et classes reprises des autres pages éditoriales du prototype.
--}}
@php
    $bareme = app(\App\Core\Marketing\Services\Parrainage::class);
    $pointsParrain = $bareme->unitesParrain();
    $pointsFilleul = $bareme->unitesFilleul();
@endphp

<x-layouts.public :title="$title">
    <main id="contenu">

      {{-- ============ EN-TÊTE ============ --}}
      <section class="bg-[#f8f9fa] pt-36 pb-20">
        <div class="max-w-3xl mx-auto px-4 text-center">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold px-4 py-1.5 mb-5">
            <i class="bi bi-people-fill" aria-hidden="true"></i>
            {{ __('parrainage.programme_de_parrainage') }}
          </span>
          <h1 class="text-4xl md:text-5xl font-display font-bold text-heading leading-tight mb-4">{!! __('parrainage.une_rumeur_se_partage_vite_un_dementi') !!}</h1>
          <p class="text-lg text-body/80 mb-8">{{ __('parrainage.invitez_vos_proches_a_verifier_avant_de') }}</p>
          <div class="flex flex-wrap items-center justify-center gap-3">
            @auth
              <a href="{{ route('citizen.referral') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark transition-colors">
                {{ __('parrainage.voir_mon_code_de_parrainage') }}
              </a>
            @else
              <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary text-white font-semibold px-6 py-3 hover:bg-primary-dark transition-colors">
                {{ __('parrainage.creer_mon_compte_gratuit') }}
              </a>
              <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white text-heading font-semibold px-6 py-3 hover:text-primary transition-colors">
                {{ __('parrainage.j_ai_deja_un_compte') }}
              </a>
            @endauth
          </div>
        </div>
      </section>

      {{-- ============ COMMENT ÇA MARCHE ============ --}}
      <section class="py-16">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-3xl mx-auto text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('parrainage.comment_ca_marche') }}</h2>
            <p class="text-body/80 mb-0">{{ __('parrainage.trois_etapes_et_aucune_n_exige_quoi') }}</p>
          </div>

          <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6">
              <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary/10 text-primary font-display font-bold text-xl mb-4">1</span>
              <h3 class="text-xl font-display font-bold text-heading mb-2">{{ __('parrainage.vous_partagez_votre_lien') }}</h3>
              <p class="text-body/80 mb-0">{{ __('parrainage.votre_espace_citoyen_vous_donne_un_code') }}</p>
            </div>

            <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6">
              <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary/10 text-primary font-display font-bold text-xl mb-4">2</span>
              <h3 class="text-xl font-display font-bold text-heading mb-2">{{ __('parrainage.votre_filleul_s_inscrit') }}</h3>
              <p class="text-body/80 mb-0">{{ __('parrainage.il_cree_son_compte_depuis_votre_lien') }}</p>
            </div>

            <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6">
              <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary/10 text-primary font-display font-bold text-xl mb-4">3</span>
              <h3 class="text-xl font-display font-bold text-heading mb-2">{{ __('parrainage.il_fait_sa_premiere_verification') }}</h3>
              <p class="text-body/80 mb-0">{{ __('parrainage.c_est_a_ce_moment_et_pas') }}</p>
            </div>
          </div>
        </div>
      </section>

      {{-- ============ CE QUE CHACUN REÇOIT ============ --}}
      <section class="bg-[#f8f9fa] py-16">
        <div class="max-w-3xl mx-auto px-4">
          <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('parrainage.ce_que_chacun_recoit') }}</h2>
          <p class="text-body/85 leading-relaxed mb-8">{{ __('parrainage.les_unites_servent_a_lancer_des_analyses') }}</p>

          <div class="grid sm:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-line p-6 text-center">
              <p class="text-4xl font-display font-bold text-primary mb-1">{{ number_format($pointsParrain, 0, ',', ' ') }}</p>
              <p class="text-sm font-semibold text-heading mb-1">{{ __('parrainage.unites_pour_vous') }}</p>
              <p class="text-sm text-body/70 mb-0">{{ __('parrainage.pour_chaque_filleul_qui_verifie') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-line p-6 text-center">
              <p class="text-4xl font-display font-bold text-primary mb-1">{{ number_format($pointsFilleul, 0, ',', ' ') }}</p>
              <p class="text-sm font-semibold text-heading mb-1">{{ __('parrainage.unites_pour_lui') }}</p>
              <p class="text-sm text-body/70 mb-0">{{ __('parrainage.en_plus_de_sa_dotation_mensuelle') }}</p>
            </div>
          </div>
        </div>
      </section>

      {{-- ============ LES RÈGLES ============
        | Écrites en clair, et pas en petits caractères.
        |
        | Un programme de parrainage attire les tentatives de fraude : les
        | conditions doivent être lisibles AVANT que quelqu'un ne perde son
        | temps, pas découvertes après coup.
      --}}
      <section class="py-16">
        <div class="max-w-3xl mx-auto px-4">
          <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('parrainage.les_regles_en_clair') }}</h2>
          <ul class="liste-puces text-body/85 leading-relaxed mb-0">
            <li>{!! __('parrainage.la_recompense_part_a_la_premiere_verification') !!}</li>
            <li>{!! __('parrainage.une_seule_recompense_par_filleul_elle_est') !!}</li>
            <li>{!! __('parrainage.on_ne_se_parraine_pas_soi_meme') !!}</li>
            <li>{!! __('parrainage.le_lien_ne_se_rattache_qu_a') !!}</li>
            <li>{!! __('parrainage.si_le_parrain_ferme_son_compte_le') !!}</li>
          </ul>
        </div>
      </section>

      {{-- ============ APPEL ============ --}}
      <section class="pb-20">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-primary/10 rounded-xl p-10 md:p-14 text-center">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('parrainage.faites_circuler_la_verification') }}</h2>
            <p class="text-body/85 mb-6">{{ __('parrainage.au_benin_une_rumeur_voyage_en_image') }}</p>
            @auth
              <a href="{{ route('citizen.referral') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('parrainage.partager_mon_lien') }}</a>
            @else
              <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('parrainage.creer_mon_compte') }}</a>
            @endauth
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
