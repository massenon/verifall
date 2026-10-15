{{--
  | « Mon Parrainage » — transposition de compte-user/parrainage.html.
  |
  | La récompense arrive quand le filleul fait sa PREMIÈRE VÉRIFICATION, pas à
  | son inscription : payer l'inscription seule, c'est payer les faux comptes.
--}}
<div x-data="{ copie: '' }">

  <header class="mb-6">
    <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_mon_parrainage.fil_d_ariane') }}">
      <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
      <span class="text-line" aria-hidden="true">/</span>
      <span class="text-ink font-medium">{{ __('cit_mon_parrainage.mon_parrainage') }}</span>
    </nav>
    <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight mb-1">{{ __('cit_mon_parrainage.mon_parrainage') }}</h1>
    <p class="text-slate2 mb-0">{{ __('cit_mon_parrainage.partagez_verif_all_faites_decouvrir_la_verification') }}</p>
  </header>

  {{-- ═══════════════════════════════════════════════════ je suis filleul
    | Ce bloc manquait entièrement.
    |
    | L'écran ne montrait que le versant PARRAIN. Quelqu'un arrivé par le lien
    | d'un ami n'y trouvait aucune trace de ce qui lui avait été promis, ni de
    | ce qui lui restait à faire pour l'obtenir.
    |
    | C'est pourtant le côté qui compte le plus au départ : c'est celui qui a
    | encore un geste à faire. Le parrain, lui, n'a plus qu'à attendre.
    |
    | Rien ne s'affiche pour qui n'a pas été parrainé. Un encart vide se lirait
    | comme une panne.
  --}}
  @if ($monParrainage !== null)
    <section class="card p-5 mb-8 border-l-4 border-primary" aria-labelledby="filleul-titre">
      <div class="flex items-start gap-3">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-soft shrink-0">
          <span class="material-symbols-rounded text-primary" aria-hidden="true">volunteer_activism</span>
        </span>
        <div class="min-w-0">
          <h2 id="filleul-titre" class="font-display font-bold text-ink text-lg leading-tight mb-1">
            {{ __('cit_mon_parrainage.vous_avez_ete_parraine_par') }} {{ $monParrainage->referrer?->name ?? __('cit_mon_parrainage.un_membre') }}
          </h2>
          <p class="text-sm text-slate2 mb-3">
            {{ $monParrainage->status->explicationFilleul() }}
          </p>

          <div class="flex flex-wrap items-center gap-3">
            <x-badge-statut :statut="$monParrainage->status->cleBadge()" :label="$monParrainage->status->libelle()" />

            @if ($monParrainage->rewarded_at !== null)
              {{-- Le chiffre versé, lu sur le parrainage et non sur la
                   constante : si le barème change un jour, cet écran doit
                   continuer de dire ce qui a RÉELLEMENT été crédité. --}}
              <span class="text-sm text-ink font-semibold">
                +{{ number_format((int) $monParrainage->referred_points, 0, ',', ' ') }} {{ __('cit_mon_parrainage.unites_recues') }}
              </span>
            @else
              <span class="text-sm text-slate2">
                {{ number_format($pointsFilleulRecus, 0, ',', ' ') }} {{ __('cit_mon_parrainage.unites_vous_attendent') }}
              </span>
            @endif
          </div>

          @if ($monParrainage->rewarded_at === null)
            <a href="{{ route('citizen.verify') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark">
              <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">fact_check</span>
              {{ __('cit_mon_parrainage.lancer_ma_premiere_verification') }}
            </a>
          @endif
        </div>
      </div>
    </section>
  @endif

  {{-- ══════════════════════════════════════════════════════ indicateurs --}}
  <section class="mb-8" data-kpi-accordion>
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{!! __('cit_mon_parrainage.resume_du_parrainage_expand_more') !!}</button>
    <div class="kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-4">
      {{-- « Invitations ouvertes » et non « envoyées » : un partage WhatsApp
           ne se constate pas depuis un serveur, et compter un chiffre qu'on ne
           mesure pas serait mentir avec aplomb. --}}
      <div class="vf-kpi">
        <p class="vf-kpi-label">{{ __('cit_mon_parrainage.invitations_ouvertes') }}</p>
        <p class="vf-kpi-value">{{ $indicateurs['visites'] }}</p>
      </div>
      <div class="vf-kpi">
        <p class="vf-kpi-label">{{ __('cit_mon_parrainage.inscrits') }}</p>
        <p class="vf-kpi-value">{{ $indicateurs['inscrits'] }}</p>
      </div>
      <div class="vf-kpi">
        <p class="vf-kpi-label">{{ __('cit_mon_parrainage.actifs') }}</p>
        <p class="vf-kpi-value">{{ $indicateurs['actifs'] }}</p>
      </div>
      <div class="vf-kpi">
        <p class="vf-kpi-label">{{ __('cit_mon_parrainage.unites_gagnees') }}</p>
        <p class="vf-kpi-value">+{{ $indicateurs['points'] }}</p>
      </div>
    </div>
  </section>

  <div class="grid lg:grid-cols-3 gap-6 items-start mb-8">

    {{-- ══════════════════════════════════════════════════ lien et code --}}
    <div class="lg:col-span-2 card p-6">
      <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_mon_parrainage.votre_invitation') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_mon_parrainage.partagez_ce_lien_pour_inviter_vos_proches') }}</p>

      <div class="flex items-stretch gap-2 mb-5">
        <input type="text" readonly value="{{ $lien }}" aria-label="{{ __('cit_mon_parrainage.votre_lien_d_invitation') }}"
               class="flex-1 min-w-0 bg-canvas border border-line rounded-xl px-3.5 py-2.5 text-sm text-ink font-mono">
        <button type="button"
                x-on:click="navigator.clipboard.writeText(@js($lien)); copie = 'lien'; setTimeout(() =>copie = '', 2000)" class="shrink-0 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]"> <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">content_copy</span> <span x-text="copie === 'lien' ? @js(__('cit_mon_parrainage.copie')) : @js(__('cit_mon_parrainage.copier_le_lien'))">{{ __('cit_mon_parrainage.copier_le_lien') }}</span></button>
      </div>

      <div class="flex items-center justify-between gap-3 rounded-xl bg-canvas border border-line px-4 py-3">
        <div class="min-w-0">
          <p class="text-xs text-slate2 mb-0.5">{{ __('cit_mon_parrainage.code_de_parrainage') }}</p>
          <p class="text-lg font-mono font-bold text-ink tracking-wider mb-0">{{ $code }}</p>
        </div>
        <button type="button"
                x-on:click="navigator.clipboard.writeText(@js($code)); copie = 'code'; setTimeout(() =>copie = '', 2000)" class="shrink-0 text-sm font-semibold text-primary min-h-[44px]" x-text="copie === 'code' ? @js(__('cit_mon_parrainage.copie')) : @js(__('cit_mon_parrainage.copier'))">{{ __('cit_mon_parrainage.copier') }}</button>
      </div>
    </div>

    {{-- ══════════════════════════════════════════════════════ QR code --}}
    <div class="card p-6 text-center">
      <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_mon_parrainage.votre_qr_code') }}</h2>

      {{-- Rendu côté serveur : un QR dessiné en JavaScript ne s'imprime pas,
           ne se copie pas dans un message, et disparaît quand le script
           échoue. Le SVG reste net à toute taille — un QR flou ne se scanne
           pas. --}}
      <div class="inline-block bg-white p-2 rounded-xl border border-line mb-3"
           role="img" aria-label="{{ __('cit_mon_parrainage.qr_code_de_votre_lien_d_invitation') }}">
        {!! app(App\Core\Marketing\Services\Parrainage::class)->qrCode(auth()->user(), 180) !!}
      </div>

      <p class="text-xs text-slate2 mb-0">{{ __('cit_mon_parrainage.scannez_pour_decouvrir_verif_all') }}</p>
      <p class="text-xs text-slate2 font-mono mb-0">{{ __('cit_mon_parrainage.code_capitales') }} {{ $code }}</p>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════ la récompense --}}
  <section class="card p-6 mb-8">
    <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_mon_parrainage.recompense_actuelle') }}</h2>
    <p class="text-sm text-slate2 mb-4">{!! __('cit_mon_parrainage.lorsque_votre_filleul_realise_sa_premiere_verification') !!}</p>
    <div class="grid sm:grid-cols-2 gap-4">
      <div class="card p-6 !border-[#EAB308]/40 bg-[#FFFBEB]">
        <p class="text-xs font-bold tracking-[.14em] text-[#A16207] uppercase mb-2">{{ __('cit_mon_parrainage.vous') }}</p>
        <p class="text-3xl font-display font-black text-ink mb-1">+{{ $pointsParrain }} {{ __('cit_mon_parrainage.unites') }}</p>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_mon_parrainage.creditees_des_que_votre_filleul_realise_sa') }}</p>
      </div>
      <div class="card p-6 !border-[#EAB308]/40 bg-[#FFFBEB]">
        <p class="text-xs font-bold tracking-[.14em] text-[#A16207] uppercase mb-2">{{ __('cit_mon_parrainage.votre_filleul') }}</p>
        <p class="text-3xl font-display font-black text-ink mb-1">+{{ $pointsFilleul }} {{ __('cit_mon_parrainage.unites') }}</p>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_mon_parrainage.creditees_sur_sa_premiere_verification_en_plus') }}</p>
      </div>
    </div>
    {{-- Dit franchement : sans cette phrase, quelqu'un qui voit « 8 inscrits »
         et « 0 unité » croit à un bug. --}}
    <p class="text-xs text-slate2 mt-3 mb-0">{{ __('cit_mon_parrainage.l_inscription_seule_ne_declenche_rien_c') }}</p>
  </section>

  {{-- ══════════════════════════════════════════════ comment ça marche --}}
  <section class="mb-8" id="pr-how">
    <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_mon_parrainage.comment_fonctionne_le_parrainage') }}</h2>
    <p class="text-sm text-slate2 mb-4">{{ __('cit_mon_parrainage.quatre_etapes_simples_pas_de_formulaire_complique') }}</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
      @foreach ([
        ['01', 'Votre invitation', "Vérif'All crée automatiquement votre lien, votre code et votre QR Code personnels."],
        ['02', 'Vous partagez', 'Envoyez votre invitation par WhatsApp, e-mail, réseaux sociaux ou directement avec votre QR Code.'],
        ['03', "Votre filleul rejoint Vérif'All", 'Il ouvre votre invitation, crée son compte et découvre son avantage de bienvenue.'],
        ['04', 'Vous êtes récompensés', "Lorsque l'action éligible est réalisée, les récompenses sont automatiquement attribuées."],
      ] as [$rang, $titre, $texte])
        <div class="card p-5">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary-soft text-primary-dark font-display font-black text-sm mb-3">{{ $rang }}</span>
          <p class="text-sm font-display font-bold text-ink mb-1">{{ $titre }}</p>
          <p class="text-sm text-slate2 mb-0">{{ $texte }}</p>
        </div>
      @endforeach
    </div>
  </section>

  {{-- ══════════════════ mes filleuls et l'historique, côte à côte ══════ --}}
  <div class="grid lg:grid-cols-2 gap-6 mb-8 items-start">

  <section class="card p-6">
    <h2 class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_mon_parrainage.mes_invitations') }}</h2>
    <p class="text-sm text-slate2 mb-4">{{ __('cit_mon_parrainage.suivez_simplement_l_etat_de_vos_invitations') }}</p>

    @forelse($filleuls as $filleul)
      <article class="rounded-xl border border-line p-4 mb-3" wire:key="filleul-{{ $filleul->id }}">
        <div class="flex items-center justify-between gap-2 mb-2">
          {{-- Prénom et initiale seulement : afficher le nom complet ou
               l'adresse livrerait au parrain des données que son filleul ne
               lui a pas confiées. --}}
          <p class="text-sm font-bold text-ink mb-0">👤 {{ $filleul->nomDuFilleul() }}</p>
          <span class="{{ $filleul->status->badge() }}">{{ $filleul->status->libelle() }}</span>
        </div>

        <div class="grid sm:grid-cols-3 gap-3 text-sm">
          <div class="flex items-center justify-between sm:block">
            <span class="text-slate2">{{ __('cit_mon_parrainage.inscription') }}</span>
            <span class="block sm:mt-0.5 font-semibold text-vtrue">✓</span>
          </div>
          <div class="flex items-center justify-between sm:block">
            <span class="text-slate2">{{ __('cit_mon_parrainage.premiere_verification') }}</span>
            <span class="block sm:mt-0.5 font-semibold {{ $filleul->qualified_at ? 'text-vtrue' : 'text-slate2' }}">
              {{ $filleul->qualified_at ? '✓' : '◌ '.__('cit_mon_parrainage.a_realiser') }}
            </span>
          </div>
          <div class="flex items-center justify-between sm:block">
            <span class="text-slate2">{{ __('cit_mon_parrainage.recompense') }}</span>
            <span class="block sm:mt-0.5 font-semibold text-ink">
              @if($filleul->rewarded_at)
                +{{ $filleul->referrer_points }} {{ __('cit_mon_parrainage.unites') }} · {{ $filleul->rewarded_at->translatedFormat('j M') }}
              @else
                {{ __('cit_mon_parrainage.en_attente') }}
              @endif
            </span>
          </div>
        </div>

        <p class="text-xs text-slate2 mt-3 mb-0">{{ $filleul->status->explication() }}</p>
      </article>
    @empty
      <div class="card p-10 text-center">
        <span class="text-3xl block mb-2" aria-hidden="true">🤝</span>
        <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_mon_parrainage.personne_n_a_encore_rejoint') }}</p>
        <p class="text-sm text-slate2 mb-0">{{ __('cit_mon_parrainage.partagez_votre_lien_chaque_personne_qui_verifie') }}</p>
      </div>
    @endforelse
  </section>

  {{-- ═════════════════════════════════════ historique des récompenses --}}
  <section class="card p-6">
    <h2 class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_mon_parrainage.historique_des_recompenses') }}</h2>
    @forelse($historique as $mouvement)
      <div class="flex items-start gap-3 rounded-xl border border-line p-3.5 mb-3" wire:key="recompense-{{ $mouvement->id }}">
        <span class="text-lg" aria-hidden="true">🎁</span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-ink mb-0.5">+{{ $mouvement->delta }} {{ trans_choice('cit_mon_parrainage.n_unites', $mouvement->delta) }}</p>
          <p class="text-xs text-slate2 mb-0">{{ $mouvement->label }} · {{ $mouvement->created_at->translatedFormat('j F Y') }}</p>
        </div>
      </div>
    @empty
      {{-- Pas d'historique fabriqué : le prototype en montre trois lignes, mais
           afficher une récompense qui n'a pas été versée serait une promesse. --}}
      <p class="text-sm text-slate2 mb-0">{{ __('cit_mon_parrainage.aucune_recompense_pour_l_instant_la_premiere') }}</p>
    @endforelse

    @if($prochainPalier !== null)
      <div class="rounded-xl bg-canvas border border-line p-4 mt-4">
        <p class="text-sm font-bold text-ink mb-1">{{ __('cit_mon_parrainage.votre_progression_vers_le_prochain_bonus') }}</p>
        <p class="text-xs text-slate2 mb-2">{{ $prochainPalier['acquis'] }} / {{ $prochainPalier['objectif'] }} {{ __('cit_mon_parrainage.invitations_eligibles_plus_que') }} {{ $prochainPalier['restant'] }} {{ __('cit_mon_parrainage.pour_debloquer_le_prochain_palier') }}</p>
        <div class="progress"><span style="width:{{ $prochainPalier['pourcentage'] }}%"></span></div>
      </div>
    @endif
  </section>

  </div>

  {{-- ═══════════════════════════════════════ pourquoi inviter ses proches --}}
  <section class="card p-6 sm:p-8 mb-8">
    <h2 class="text-lg font-display font-bold text-ink mb-3">{{ __('cit_mon_parrainage.pourquoi_inviter_vos_proches') }}</h2>
    <ul class="grid sm:grid-cols-2 gap-x-8 gap-y-2 text-sm text-slate2 mb-0 list-none pl-0">
      @foreach ([
        "Aider votre entourage à vérifier l'information",
        'Réduire la circulation des fausses informations',
        'Faire découvrir des outils de fact-checking accessibles',
        'Apprendre ensemble',
        'Recevoir des avantages en retour',
        'Renforcer une communauté de confiance au Bénin',
      ] as $raison)
        <li class="flex items-start gap-2"><span class="text-vtrue font-bold shrink-0">✓</span>{{ $raison }}</li>
      @endforeach
    </ul>
    <p class="text-sm text-slate2 mt-4 mb-0">{!! __('cit_mon_parrainage.partagez_aussi_autour_de_vous_votre_qr') !!}</p>
  </section>

  {{-- ══════════════════════════════════════════════ questions fréquentes --}}
  <section class="mb-8">
    <h2 class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_mon_parrainage.questions_frequentes') }}</h2>
    <div class="space-y-2" data-accordion>
      {{-- Le prototype laisse ses six réponses en gabarit (« {a} ») : elles
           sont écrites ici d'après ce que le dispositif fait réellement. --}}
      @foreach ([
        ["Qu'est-ce que mon lien de parrainage ?", "Une adresse qui vous est propre. Quiconque crée un compte après l'avoir ouverte est rattaché à vous, sans que personne ait à saisir quoi que ce soit."],
        ['Comment mon filleul est-il reconnu ?', 'Par le code contenu dans votre lien, retenu jusqu\'à la création du compte. Il peut aussi le saisir à la main à l\'inscription.'],
        ['Quand recevrai-je ma récompense ?', "Lorsque votre filleul réalise sa première vérification — pas à son inscription. C'est l'usage réel qui compte, et c'est ce qui protège le dispositif des faux comptes."],
        ['Que reçoit mon filleul ?', 'Des unités créditées sur sa première vérification, en plus de son avantage de bienvenue.'],
        ['Mon lien expire-t-il ?', "Non. Il reste valable, et il survit même à la fermeture de votre compte : un filleul déjà rattaché ne perd pas son rattachement."],
        ['Puis-je m\'auto-parrainer ?', "Non. Un compte ne peut pas se parrainer lui-même, et une activité qui semble automatisée ou frauduleuse peut faire suspendre la récompense."],
      ] as [$question, $reponse])
        <div class="card overflow-hidden">
          <button type="button" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left" data-acc-toggle aria-expanded="false">
            <span class="text-sm font-bold text-ink">{{ $question }}</span>
            <span class="material-symbols-rounded shrink-0 transition-transform" aria-hidden="true">expand_more</span>
          </button>
          <div class="hidden px-5 pb-4" data-acc-panel><p class="text-sm text-slate2 mb-0">{{ $reponse }}</p></div>
        </div>
      @endforeach
    </div>
  </section>

  <section class="card p-6 bg-canvas">
    <h2 class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_mon_parrainage.un_programme_concu_pour_etre_transparent') }}</h2>
    <p class="text-sm text-slate2 mb-0">{{ __('cit_mon_parrainage.les_recompenses_sont_attribuees_automatiquement_lorsque_les') }}</p>
  </section>

  <div class="modal-backdrop" id="modal-share">
    <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="sh-title">
      <h2 id="sh-title" class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_mon_parrainage.partager_mon_invitation') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_mon_parrainage.invitez_quelqu_un_a_decouvrir_verif_all') }}</p>
      <div class="grid grid-cols-3 gap-2 mb-3">
        <a class="share-opt text-center" target="_blank" rel="noopener noreferrer" href="https://api.whatsapp.com/send?text={{ urlencode($messageDInvitation) }}">WhatsApp</a>
        <a class="share-opt text-center" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($lien) }}">Facebook</a>
        <a class="share-opt text-center" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?url={{ urlencode($lien) }}">X</a>
        <a class="share-opt text-center" target="_blank" rel="noopener noreferrer" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($lien) }}">LinkedIn</a>
        <a class="share-opt text-center" href="mailto:?subject={{ rawurlencode(__('cit_mon_parrainage.invitation_verif_all')) }}&body={{ rawurlencode($messageDInvitation) }}">{{ __('cit_mon_parrainage.e_mail') }}</a>
        <button type="button" class="share-opt" data-copier="{{ $lien }}">{{ __('cit_mon_parrainage.copier') }}</button>
      </div>
      <button type="button" class="w-full rounded-xl bg-primary text-white text-sm font-semibold py-2.5 hover:bg-primary-dark mb-2" data-partage-natif data-message="{{ $messageDInvitation }}" data-lien="{{ $lien }}">{{ __('cit_mon_parrainage.partager_avec_mon_telephone') }}</button>
      <button type="button" class="w-full rounded-xl text-sm font-semibold text-slate2 py-2 hover:bg-canvas" data-modal-close>{{ __('cit_mon_parrainage.fermer') }}</button>
    </div>
  </div>

  @push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/pages/user-parrainage.css') }}">
  @endpush

  @push('scripts')
    <script src="{{ asset('assets/js/pages/parrainage.js') }}" defer></script>
  @endpush
</div>
