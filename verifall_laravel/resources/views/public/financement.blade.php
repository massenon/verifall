@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/transparence.css') }}">
@endpush

{{--
  | Financement.
  |
  | Cette page ne figure pas au prototype : elle vient des exigences de
  | transparence du code de conduite de l'IFCN, qui demande à une rédaction de
  | dire d'où viennent ses ressources.
  |
  | RÈGLE ABSOLUE appliquée ici : aucun montant, aucune date, aucun partenaire
  | qui ne soit déjà établi par le dépôt. Un seul montant est publiable —
  | les 800 € du 2ᵉ prix du concours d'Aix-Marseille Université, financés par
  | le Fonds de dotation Demain (database/seeders/ActualitesVerifallSeeder.php).
  | Les autres soutiens sont nommés SANS montant, parce qu'aucune source du
  | dépôt n'en établit. Voir docs/ARBITRAGES.md, entrée 148.
--}}
<x-layouts.public :title="$title">
  <section class="bg-[#f8f9fa] pt-36 pb-20">
    <div class="max-w-page mx-auto px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-display font-extrabold text-heading mb-3">{{ __('financement.financement') }}</h1>
        <p class="text-lg text-body/80 mb-4">{{ __('financement.d_ou_viennent_nos_ressources_et_qui') }}</p>
        <p class="text-body/70 mb-0">{{ __('financement.nous_ne_publions_que_ce_que_nous') }}</p>
      </div>
    </div>
  </section>

  {{-- ============ DOTATIONS ============ --}}
  <section class="py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('financement.dotations_recues') }}</h2>
      <p class="text-body/85 leading-relaxed mb-8">{{ __('financement.verif_all_a_ete_distinguee_deux_fois') }}</p>

      <ul class="list-none space-y-6 mb-0">
        <li class="bg-white rounded-xl shadow-card border border-line p-6">{!! __('financement.concours_objectif_developpement_durable_800_2e_prix') !!}</li>

        <li class="bg-white rounded-xl shadow-card border border-line p-6">{!! __('financement.prix_wsis_2026_categorie_media_decerne_le') !!}</li>
      </ul>
    </div>
  </section>

  {{-- ============ SOUTIENS ============ --}}
  <section class="bg-[#f8f9fa] py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('financement.soutiens_du_projet_et_du_lancement') }}</h2>
      <p class="text-body/85 leading-relaxed mb-4">{!! __('financement.le_developpement_de_la_plateforme_et_son') !!}</p>
      <p class="text-body/85 leading-relaxed mb-4">{!! __('financement.le_lancement_public_le_16_juillet_2026') !!}</p>
      <p class="text-body/85 leading-relaxed mb-4">{!! __('financement.figurent_egalement_parmi_les_partenaires_du_projet') !!}</p>
      <p class="text-sm text-body/70 mb-0">{{ __('financement.ces_soutiens_sont_nommes_sans_montant_aucun') }}</p>
    </div>
  </section>

  {{-- ============ SOUTENIR ============
    | Posé au MILIEU de la page, et non en pied.
    |
    | Quelqu'un qui lit une page de financement s'y trouve pour savoir d'où
    | vient l'argent. Il vient de lire les dotations reçues et les soutiens du
    | lancement : c'est à cet endroit précis que la question « puis-je aider ? »
    | se pose, pas après avoir refermé la page.
    |
    | LE FORMULAIRE N'ENCAISSE RIEN, et le dit en toutes lettres. Aucun
    | prestataire de paiement n'est en service. Prendre un montant en donnant
    | l'apparence d'un paiement mentirait deux fois : sur ce qui se passe, et
    | sur ce qui est reçu.
  --}}
  <section class="bg-[#f8f9fa] py-16" id="soutenir">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('financement.soutenir_verif_all') }}</h2>
      <p class="text-body/85 leading-relaxed mb-6">{{ __('financement.verifier_une_information_coute_des_appels_a') }}</p>

      @if (session('soutien'))
        <div class="rounded-lg border border-primary/30 bg-white px-4 py-3 text-heading mb-6" role="status">
          {{ session('soutien') }}
        </div>
      @endif

      <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6 sm:p-8">
        <p class="text-sm text-body/70 mb-6">{{ __('financement.ce_formulaire_n_encaisse_aucun_paiement_il') }}</p>

        <form method="POST" action="{{ route('public.funding.support') }}" class="grid md:grid-cols-2 gap-5">
          @csrf
          {{-- Piège à robots : masqué par le style, jamais par type="hidden". --}}
          <div class="sr-only" aria-hidden="true">
            <label for="sp-website">{{ __('financement.ne_remplissez_pas_ce_champ') }}</label>
            <input type="text" id="sp-website" name="website" tabindex="-1" autocomplete="off">
          </div>
          <input type="hidden" name="rendu_a" value="{{ time() }}">

          <div>
            <label class="block text-sm font-medium text-heading mb-2" for="sp-nom">{{ __('financement.nom_complet') }}</label>
            <input type="text" id="sp-nom" name="name" value="{{ old('name') }}" required autocomplete="name" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('financement.placeholder_votre_nom') }}">
            @error('name')<p class="text-sm text-vfalse mt-2" role="alert">{{ $message }}</p>@enderror
          </div>

          <div>
            <label class="block text-sm font-medium text-heading mb-2" for="sp-email">{{ __('financement.adresse_e_mail') }}</label>
            <input type="email" id="sp-email" name="email" value="{{ old('email') }}" required autocomplete="email" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('financement.placeholder_adresse_e_mail') }}">
            @error('email')<p class="text-sm text-vfalse mt-2" role="alert">{{ $message }}</p>@enderror
          </div>

          <div>
            <label class="block text-sm font-medium text-heading mb-2" for="sp-org">{{ __('financement.organisation') }}</label>
            <input type="text" id="sp-org" name="organisation" value="{{ old('organisation') }}" autocomplete="organization" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('financement.placeholder_organisation') }}">
          </div>

          <div>
            {{-- Texte libre : quelqu'un écrit « 50 € », « selon mes moyens » ou
                 « du temps de formation ». Contraindre le champ à un nombre
                 écarterait les deux dernières réponses, qui valent la première. --}}
            <label class="block text-sm font-medium text-heading mb-2" for="sp-montant">{{ __('financement.montant_ou_nature_du_soutien') }}</label>
            <input type="text" id="sp-montant" name="donation_amount" value="{{ old('donation_amount') }}" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('financement.placeholder_montant_ou_nature') }}">
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-heading mb-2" for="sp-recurrence">{{ __('financement.frequence') }}</label>
            <select id="sp-recurrence" name="donation_recurrence" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain">
              <option value="ponctuel">{{ __('financement.frequence_une_seule_fois') }}</option>
              <option value="mensuel">{{ __('financement.frequence_chaque_mois') }}</option>
              <option value="annuel">{{ __('financement.frequence_chaque_annee') }}</option>
              <option value="autre">{{ __('financement.frequence_a_en_discuter') }}</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-heading mb-2" for="sp-msg">{{ __('financement.message') }}</label>
            <textarea id="sp-msg" name="message" class="w-full h-[120px] border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('financement.placeholder_facultatif') }}">{{ old('message') }}</textarea>
          </div>

          <div class="md:col-span-2">
            <button type="submit" class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white text-lg font-semibold px-4 py-3.5 hover:bg-primary-dark transition-colors">{{ __('financement.proposer_mon_soutien') }}</button>
          </div>
        </form>

        <p class="text-sm text-body/70 mt-5 mb-0">{{ __('financement.aucun_financeur_n_exerce_de_controle_editorial') }}</p>
      </div>
    </div>
  </section>

  {{-- ============ RECETTES PROPRES ============ --}}
  <section class="py-16">
    <div class="max-w-3xl mx-auto px-4">
      <h2 class="text-3xl font-display font-bold text-heading mb-3">{{ __('financement.nos_recettes_propres') }}</h2>
      <ul class="liste-puces text-body/85 leading-relaxed mb-4">
        <li>{!! __('financement.packs_d_unites_de_verification_le_modele') !!}</li>
        <li>{!! __('financement.aucun_espace_publicitaire_n_est_vendu_les') !!}</li>
      </ul>
      <p class="text-body/85 leading-relaxed mb-0">
        {{ __('financement.les_verdicts_ne_sont_ni_sponsorises_ni') }} <a href="{{ route('public.corrections') }}" class="text-primary font-bold">{{ __('financement.le_journal_des_corrections') }}</a>
        {{ __('financement.en_fait_foi_et') }}
        <a href="{{ route('public.methodology') }}" class="text-primary font-bold">{{ __('financement.notre_methodologie') }}</a>
        {{ __('financement.decrit_comment_un_verdict_est_etabli') }}
      </p>
    </div>
  </section>

  {{-- ============ CE QUI MANQUE ENCORE ============ --}}
  <section class="pb-20">
    <div class="max-w-3xl mx-auto px-4">
      <div class="bg-white rounded-xl shadow-card border border-line p-6">
        <h2 class="text-lg font-display font-bold text-heading mb-2">{{ __('financement.ce_que_cette_page_ne_dit_pas') }}</h2>
        <p class="text-body/85 leading-relaxed mb-0">{{ __('financement.nous_ne_publions_pas_encore_de_budget') }}</p>
      </div>
    </div>
  </section>

  <section class="pb-20">
    <div class="max-w-page mx-auto px-4">
      <div class="bg-primary/10 rounded-xl p-10 md:p-14 text-center">
        <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-3">{{ __('financement.une_question_sur_nos_ressources') }}</h2>
        <p class="text-body/85 mb-6">{{ __('financement.ecrivez_nous_nous_repondons_aux_demandes_de') }}</p>
        <a href="{{ route('public.contact') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('financement.nous_contacter') }}</a>
      </div>
    </div>
  </section>
</x-layouts.public>
