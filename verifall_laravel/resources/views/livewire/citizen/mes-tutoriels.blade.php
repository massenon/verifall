{{--
  | « Mes Tutoriels » — transposition de compte-user/mes-tutoriels.html.
  |
  | Trois sections conditionnelles (Continuer, Quiz à reprendre, Exercices à
  | terminer) : elles n'apparaissent que si elles ont quelque chose à dire.
--}}
<div>

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div><nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_mes_tutoriels.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_mes_tutoriels.apprentissage') }}</span> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_mes_tutoriels.mes_tutoriels') }}</span></nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_mes_tutoriels.mes_tutoriels') }}</h1><p class="text-slate2 mt-1 mb-0">{{ __('cit_mes_tutoriels.votre_parcours_d_apprentissage_continuez_vos_cours') }}</p>
    </div><div class="flex flex-wrap items-center gap-2">@if($reprise !== null)<a href="{{ route('citizen.course', $reprise->course) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark min-h-[44px]">{{ __('cit_mes_tutoriels.continuer_mon_apprentissage') }}</a>@endif<a href="{{ route('citizen.catalogue') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas min-h-[44px]">{{ __('cit_mes_tutoriels.catalogue') }}</a></div>
  </div>
  <nav class="flex gap-2 overflow-x-auto pb-1 mb-6 no-scrollbar" aria-label="{{ __('cit_mes_tutoriels.acces_rapides') }}">
    <a href="{{ route('citizen.live-classes') }}" class="vf-quick"><x-icone nom="videocam" :taille="19" class="text-primary" /> {{ __('cit_mes_tutoriels.live_class') }}</a>
    <a href="{{ route('citizen.evaluations') }}" class="vf-quick"><x-icone nom="quiz" :taille="19" class="text-primary" /> {{ __('cit_mes_tutoriels.mes_quiz') }}</a>
    <a href="{{ route('citizen.exercises') }}" class="vf-quick"><x-icone nom="assignment" :taille="19" class="text-primary" /> {{ __('cit_mes_tutoriels.exercices') }}</a>
    <a href="{{ route('citizen.catalogue') }}" class="vf-quick"><x-icone nom="school" :taille="19" class="text-primary" /> {{ __('cit_mes_tutoriels.catalogue') }}</a>
  </nav>

  <section data-kpi-accordion class="mb-8">
    <button type="button" class="kpi-toggle w-full items-center justify-between card px-4 py-3 mb-3" aria-expanded="false">{{-- Le libellé ET l'icône vivaient dans la chaîne de langue, en balisage
           brut rendu par `{!! !!}`. Un fichier de langue porte de la COPIE, pas
           de la structure — c'est ce que son propre en-tête annonce. Sorti d'ici,
           il n'y a plus de sortie non échappée, et l'icône suit le reste. --}}
      <span class="text-sm font-display font-bold text-ink">{{ __('cit_mes_tutoriels.resume_de_mon_apprentissage') }}</span>
      <x-icone nom="expand_more" :taille="22" class="transition-transform" /></button>
    <div class="kpi-grid grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_mes_tutoriels.moyenne_quiz') }}</p><p class="vf-kpi-value">{{ $moyenneQuiz === null ? '—' : $moyenneQuiz.' %' }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_mes_tutoriels.unites_investies') }}</p><p class="vf-kpi-value">{{ $unitesInvesties }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_mes_tutoriels.unites_gagnees') }}</p><p class="vf-kpi-value text-vtrue">{{ $unitesGagnees }}</p></div>
      <div class="vf-kpi"><p class="vf-kpi-label">{{ __('cit_mes_tutoriels.certificats_obtenus') }}</p><p class="vf-kpi-value text-xp">{{ $certificats }}</p></div>
    </div>
  </section>

  @if($reprise !== null)
    <section class="mb-12">
      <p class="vf-eyebrow">{{ __('cit_mes_tutoriels.continuer') }}</p>
      <h2 class="text-xl lg:text-2xl font-display font-bold text-ink mb-4">{{ __('cit_mes_tutoriels.continuer_votre_apprentissage') }}</h2>
      <div class="card overflow-hidden sm:flex">
        @if($reprise->course->cover_path)
          <img src="{{ asset($reprise->course->cover_path) }}" alt="" class="w-full sm:w-64 object-cover shrink-0" style="aspect-ratio:16/9" aria-hidden="true">
        @endif
        <div class="p-6 flex flex-col flex-1">
          <h3 class="text-lg font-display font-bold text-ink leading-snug mb-2">{{ $reprise->course->title }}</h3>
          <div class="flex items-center gap-3 mb-1.5"><span class="progress flex-1"><span style="width:{{ $avancements[$reprise->course->id] }}%"></span></span><strong class="text-sm">{{ $avancements[$reprise->course->id] }} %</strong></div>
          <a href="{{ route('citizen.course', $reprise->course) }}" class="mt-auto self-start inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-6 py-3 hover:bg-primary-dark min-h-[44px]">{{ $avancements[$reprise->course->id] > 0 ? __('cit_mes_tutoriels.reprendre') : __('cit_mes_tutoriels.commencer') }} {{ __('cit_mes_tutoriels.le_cours') }} <x-icone nom="arrow_forward" :taille="17" /></a>
        </div>
      </div>
    </section>
  @endif

  <section class="mb-12">
    <h2 class="text-xl font-display font-bold text-ink mb-4">{{ __('cit_mes_tutoriels.cours_en_cours') }}</h2>
    @if($enCours->count() > 1)
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($enCours->skip(1) as $inscription)
          <article class="card p-5 flex flex-col">
            <span class="vbadge vbadge-progress self-start mb-3">{{ __('cit_mes_tutoriels.en_cours_capitales') }}</span>
            <h3 class="text-base font-display font-bold text-ink leading-snug mb-3">{{ $inscription->course->title }}</h3>
            <div class="flex items-center gap-2.5 mb-1"><span class="progress flex-1"><span style="width:{{ $avancements[$inscription->course->id] }}%"></span></span><strong class="text-sm">{{ $avancements[$inscription->course->id] }} %</strong></div>
            <a href="{{ route('citizen.course', $inscription->course) }}" class="mt-auto self-start inline-flex items-center rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors min-h-[44px]">{{ $avancements[$inscription->course->id] > 0 ? __('cit_mes_tutoriels.reprendre') : __('cit_mes_tutoriels.commencer') }}</a>
          </article>
        @endforeach
      </div>
    @elseif($enCours->isEmpty())
      <div class="card p-10 text-center">
        <span class="text-3xl block mb-2" aria-hidden="true">🎓</span>
        <p class="text-base font-display font-bold text-ink mb-1">{{ __('cit_mes_tutoriels.votre_parcours_commence_ici') }}</p>
        <p class="text-sm text-slate2 mb-4">{{ __('cit_mes_tutoriels.decouvrez_les_tutoriels_verif_all_et_developpez') }}</p>
        <a href="{{ route('citizen.catalogue') }}" class="inline-flex rounded-xl bg-primary text-white text-sm font-semibold px-6 py-3 hover:bg-primary-dark min-h-[44px] items-center">{{ __('cit_mes_tutoriels.decouvrir_le_catalogue') }}</a>
      </div>
    @endif
  </section>

  @if($quizARefaire->isNotEmpty())
    <section class="mb-12">
      <h2 class="text-xl font-display font-bold text-ink mb-1">{{ __('cit_mes_tutoriels.quiz_a_reprendre') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_mes_tutoriels.vous_pouvez_progresser_chaque_tentative_compte') }}</p>
      <div class="space-y-3">
        @foreach($quizARefaire as $quiz)
          <article class="card p-5 flex flex-wrap items-center gap-4" style="border-color:rgba(214,164,25,.35);background:#FFFDF5">
            <div class="min-w-0 flex-1"><p class="text-sm font-display font-bold text-ink mb-0.5">{{ $quiz->title }}</p>
              <p class="text-xs text-slate2 mb-0">{{ $quiz->course?->title }} · {{ __('cit_mes_tutoriels.seuil') }} {{ $quiz->pass_score }} %</p></div>
            <a href="{{ route('citizen.quiz.take', ['course' => $quiz->course, 'quiz' => $quiz]) }}" class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">{{ __('cit_mes_tutoriels.reprendre_le_quiz') }}</a>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  @if($exercicesDus->isNotEmpty())
    <section class="mb-12">
      <h2 class="text-xl font-display font-bold text-ink mb-4">{{ __('cit_mes_tutoriels.exercices_a_terminer') }}</h2>
      <div class="space-y-3">
        @foreach($exercicesDus as $exercice)
          <article class="card p-5 flex flex-wrap items-center gap-4">
            <div class="min-w-0 flex-1"><p class="text-sm font-display font-bold text-ink mb-0.5">{{ $exercice->title }}</p>
              <p class="text-xs text-slate2 mb-0">{{ $exercice->course?->title }}</p></div>
            <a href="{{ route('citizen.exercises') }}" class="inline-flex items-center rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors min-h-[44px]">{{ __('cit_mes_tutoriels.ouvrir_l_exercice') }}</a>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  {{-- ═══════════════════════════════════════════════ GUIDES PRATIQUES

       « Lire le guide » pointait sur `route('public.tutorials')` : depuis son
       espace, le citoyen était éjecté vers le site vitrine — sans son en-tête,
       sans son solde, sans sa cloche — et le guide n'y était même pas, cette
       page listant des COURS.

       Le guide s'ouvre désormais SUR PLACE. Livewire remplace ce fragment,
       le gabarit citoyen ne bouge pas : la sensation d'une page, le coût d'un
       onglet. L'adresse suit (`?guide=…`), donc le lien se partage et le
       bouton Précédent fonctionne.                                          --}}
  <section class="mb-12">
    @php $ouvert = $this->guideOuvert(); @endphp

    @if($ouvert)
      {{-- ─────────────────────────────────── la fiche, en pleine largeur --}}
      <nav class="text-xs text-slate2 mb-3" aria-label="{{ __('cit_mes_tutoriels.fil_d_ariane') }}">
        <button type="button" wire:click="fermerLeGuide" class="hover:text-primary">{{ __('cit_mes_tutoriels.guides_pratiques') }}</button>
        <span class="text-line" aria-hidden="true">/</span>
        <span class="text-ink font-medium">{{ $ouvert['titre'] }}</span>
      </nav>

      <article class="card p-6 sm:p-8">
        <p class="text-[10px] font-bold tracking-[.1em] text-slate2 uppercase mb-2">{{ $ouvert['niveau'] }}</p>
        <h2 class="text-2xl font-display font-bold text-ink leading-snug mb-2">{{ $ouvert['titre'] }}</h2>
        <p class="text-sm text-slate2 mb-6">{{ $ouvert['resume'] }}</p>

        <ol class="space-y-5 mb-6">
          @foreach($ouvert['etapes'] as $rang => $etape)
            <li class="flex gap-4">
              {{-- Le numéro est une PASTILLE et non un puceau de liste : c'est
                   ce que le prototype montre, et c'est ce qui rend la marche à
                   suivre lisible d'un coup d'œil sur un téléphone. --}}
              <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white text-sm font-bold" aria-hidden="true">{{ $rang + 1 }}</span>
              <div>
                <h3 class="text-base font-display font-bold text-ink leading-snug mb-1">{{ $etape[0] }}</h3>
                <p class="text-sm text-slate2 mb-0">{{ $etape[1] }}</p>
              </div>
            </li>
          @endforeach
        </ol>

        <div class="flex flex-wrap gap-2 pt-4 border-t border-line">
          <button type="button" wire:click="fermerLeGuide"
                  class="inline-flex items-center gap-1.5 rounded-xl border border-line text-sm font-semibold text-ink px-5 py-2.5 hover:bg-canvas min-h-[44px]">
            <x-icone nom="arrow_back" :taille="17" /> {{ __('cit_mes_tutoriels.retour_aux_guides') }}
          </button>
          {{-- Le geste que le guide prépare : chaque fiche se termine en
               invitant à vérifier pour de bon. --}}
          <a href="{{ route('citizen.verify') }}"
             class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">
            <x-icone nom="travel_explore" :taille="17" /> {{ __('cit_mes_tutoriels.mettre_en_pratique') }}
          </a>
        </div>
      </article>

    @else
      {{-- ──────────────────────────────────────────────────── la liste --}}
      <h2 class="text-xl font-display font-bold text-ink mb-1">{{ __('cit_mes_tutoriels.guides_pratiques') }}</h2>
      <p class="text-sm text-slate2 mb-4">{{ __('cit_mes_tutoriels.des_formats_courts_pour_apprendre_un_reflexe') }}</p>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Les quatre guides vivaient en dur ici, en français. Ils sont passés
             dans le fichier de langue : c'est de la copie, pas de la structure. --}}
        @foreach($this->guides() as $guide)
          <article class="card p-5 flex flex-col">
            <p class="text-[10px] font-bold tracking-[.1em] text-slate2 uppercase mb-2">{{ $guide['niveau'] }}</p>
            <h3 class="text-base font-display font-bold text-ink leading-snug mb-1">{{ $guide['titre'] }}</h3>
            <p class="text-sm text-slate2 mb-4">{{ $guide['resume'] }}</p>
            {{-- Un BOUTON et non un lien : il n'y a pas de document à charger,
                 seulement un fragment à remplacer. Un <a href> promettrait une
                 navigation qui n'a pas lieu. --}}
            <button type="button" wire:click="ouvrirLeGuide('{{ $guide['cle'] }}')"
                    class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-primary self-start min-h-[44px]">
              {{ __('cit_mes_tutoriels.lire_le_guide') }} <x-icone nom="arrow_forward" :taille="15" />
            </button>
          </article>
        @endforeach
      </div>
    @endif
  </section>

  <section>
    <h2 class="text-xl font-display font-bold text-ink mb-4">{{ __('cit_mes_tutoriels.tutoriels_termines') }}</h2>
    @if($termines->isNotEmpty())
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach($termines as $inscription)
          @php($certificat = $certificatsParCours->get($inscription->course->id))
          <article class="card p-5 flex flex-col">
            <span class="vbadge vbadge-true self-start mb-3">✓ {{ __('cit_mes_tutoriels.termine_capitales') }}</span>
            <h3 class="text-base font-display font-bold text-ink leading-snug mb-1">{{ $inscription->course->title }}</h3>
            <p class="text-sm text-slate2 mb-4">{{ __('cit_mes_tutoriels.termine_le') }} {{ $inscription->completed_at?->format('d/m/Y') ?? '—' }}</p>
            <div class="mt-auto flex flex-wrap gap-2">
              <a href="{{ route('citizen.course', $inscription->course) }}" class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas min-h-[44px]">{{ __('cit_mes_tutoriels.voir_le_cours') }}</a>
              @if($certificat !== null)
                <a href="{{ route('citizen.certificate', $certificat) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark min-h-[44px]"><x-icone nom="workspace_premium" :taille="17" /> {{ __('cit_mes_tutoriels.telecharger_le_certificat') }}</a>
                <a href="{{ route('citizen.rewards') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary min-h-[44px] px-1">{{ __('cit_mes_tutoriels.voir_ma_recompense') }}</a>
              @endif
            </div>
          </article>
        @endforeach
      </div>
    @else
      <div class="card p-8 text-center">
        <p class="text-sm text-slate2 mb-0">{{ __('cit_mes_tutoriels.terminez_un_cours_a_100_pour_le') }}</p>
      </div>
    @endif
  </section>
</div>
