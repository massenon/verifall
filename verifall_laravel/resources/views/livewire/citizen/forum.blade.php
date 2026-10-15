@push('styles')
  {{-- `hub-pill` et `no-scrollbar` vivent en ligne dans la page du prototype ;
       sans cette feuille les pastilles de rubrique sortent en texte brut. --}}
  {{-- `user-forum.css`, pas `user-catalogue.css` : recopie d'une autre vue.
       Mesuré — la feuille du catalogue ne définit AUCUNE des classes que cette
       page emploie, celle du forum définit celles qui manquaient. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-forum.css') }}">
  {{-- Le sélecteur d'émoticônes. Feuille dédiée : le build Tailwind est figé,
       et une classe absente ne produirait aucune erreur, seulement un panneau
       sans largeur posé par-dessus le texte. --}}
  <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/emoticones.css') }}">
@endpush

@push('scripts')
  <script src="{{ \App\Support\Assets\ActifVersionne::url('assets/js/pages/emoticones.js') }}" defer></script>
@endpush

{{--
  | Forum citoyen — transposition de compte-user/forum.html.
  |
  | L'AVIS de la communauté et le VERDICT de Vérif'All sont affichés
  | séparément, et ce n'est pas cosmétique : les confondre reviendrait à faire
  | dire à la plateforme ce que pense la majorité.
--}}
<div>

  <p class="vf-eyebrow">{{ __('cit_forum.community_fact_checking_hub') }}</p>
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div><nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_forum.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">Forum</span></nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_forum.verif_all_forum') }}</h1>
      {{-- Chiffres COMPTÉS. Le prototype en annonce huit mille le jour de
           l'ouverture ; ça ne trompe personne longtemps, et ça discrédite le
           reste de la page. --}}
      <p class="text-slate2 mt-1 mb-0">{{ __('cit_forum.la_communaute_qui_verifie_l_information_ensemble') }} <strong>{{ number_format($statistiques['discussions'], 0, ',', ' ') }}</strong> {{ trans_choice('cit_forum.n_discussions', $statistiques['discussions']) }} · <strong>{{ number_format($statistiques['membres'], 0, ',', ' ') }}</strong> {{ trans_choice('cit_forum.n_membres', $statistiques['membres']) }} · <strong>{{ number_format($statistiques['verifications'], 0, ',', ' ') }}</strong> {{ trans_choice('cit_forum.n_verifications', $statistiques['verifications']) }}.</p>
    </div>
  </div>

  @if($categories->isNotEmpty())
    <div class="flex gap-1.5 overflow-x-auto pb-1 mb-4" role="tablist" aria-label="{{ __('cit_forum.rubriques_du_forum') }}">
      <button type="button" wire:click="choisirCategorie('')" class="pill-tab whitespace-nowrap {{ $categorie === '' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $categorie === '' ? 'true' : 'false' }}">{{ __('cit_forum.toutes') }}</button>
      @foreach($categories as $rubrique)
        <button type="button" wire:click="choisirCategorie('{{ $rubrique->slug }}')" wire:key="rub-{{ $rubrique->id }}" class="pill-tab whitespace-nowrap {{ $categorie === $rubrique->slug ? 'is-active' : '' }}" role="tab" aria-selected="{{ $categorie === $rubrique->slug ? 'true' : 'false' }}">{{ $rubrique->name }}</button>
      @endforeach
    </div>
  @endif

  <div class="grid lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
    <div class="space-y-4">

  {{-- ═══════════════════════════════════════════════════════════ composer --}}
    <div class="card p-5">
      <div class="flex items-center gap-3 mb-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-forest text-white font-bold text-sm shrink-0" aria-hidden="true">{{ Str::of(auth()->user()->name)->substr(0, 2)->upper() }}</span>
        <p class="text-sm text-slate2 mb-0 flex-1">{{ __('cit_forum.que_souhaitez_vous_partager_avec_la_communaute') }}</p>
      </div>
      <button type="button" wire:click="basculerComposer" class="{{ $composerOuvert ? 'hidden' : '' }} w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-primary text-primary text-sm font-semibold py-3 min-h-[48px]"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">add</span> {{ __('cit_forum.nouvelle_discussion') }}</button>

      <div class="{{ $composerOuvert ? '' : 'hidden' }} space-y-3">
        <div>
          <label class="block text-sm font-semibold text-ink mb-1" for="sujet-titre">{{ __('cit_forum.titre') }}</label>
          <input id="sujet-titre" type="text" wire:model="titre" maxlength="180"
                 placeholder="{{ __('cit_forum.cette_video_montre_t_elle_reellement') }}"
                 class="w-full min-h-[48px] bg-surface border border-line rounded-xl px-3.5 text-sm outline-none focus:border-primary">
          @error('titre') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm font-semibold text-ink mb-1" for="sujet-corps">{{ __('cit_forum.votre_message') }}</label>
          <textarea id="sujet-corps" wire:model="corps" rows="4" maxlength="5000" data-emoticones
                    placeholder="{{ __('cit_forum.ou_l_avez_vous_vu_qu_est_ce_qui_vous_fait_douter') }}"
                    class="w-full bg-surface border border-line rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-primary"></textarea>
          @error('corps') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm font-semibold text-ink mb-1" for="sujet-cat">{{ __('cit_forum.rubrique') }}</label>
          <select id="sujet-cat" wire:model="categorieChoisie"
                  class="w-full min-h-[48px] bg-surface border border-line rounded-xl px-3 text-sm">
            <option value="">{{ __('cit_forum.sans_rubrique') }}</option>
            @foreach($categories as $rubrique)
              <option value="{{ $rubrique->slug }}">{{ $rubrique->name }}</option>
            @endforeach
          </select>
        </div>

        <label class="flex items-start gap-2.5 p-3.5 rounded-xl border border-line cursor-pointer">{!! __('cit_forum.je_signale_une_possible_intox_la_communaute') !!}</label>

        @if($signalementFakeNews)
          <div>
            <label class="block text-sm font-semibold text-ink mb-1" for="sujet-source">{{ __('cit_forum.lien_de_la_source') }}</label>
            <input id="sujet-source" type="url" wire:model="lienSignale" maxlength="500"
                   placeholder="https://…"
                   class="w-full min-h-[48px] bg-surface border border-line rounded-xl px-3.5 text-sm outline-none focus:border-primary">
            {{-- Sans lien, il n'y a rien à vérifier : le champ devient
                 obligatoire dès que le signalement est coché. --}}
            @error('lienSignale') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
          </div>
        @endif

        {{-- ─────────────────────────────────────────────── sondage
          | Il pouvait déjà s'ajouter depuis la page d'un sujet publié. En
          | pratique, presque personne n'y revenait : la question qu'on veut
          | poser est présente à l'esprit au moment où l'on écrit, pas dix
          | minutes plus tard sur un autre écran.
        --}}
        <label class="flex items-start gap-2.5 p-3.5 rounded-xl border border-line cursor-pointer">{!! __('cit_forum.j_ajoute_un_sondage_une_question_deux') !!}</label>

        @if($avecSondage)
          <div class="space-y-2 p-3.5 rounded-xl border border-line bg-surface">
            <div>
              <label class="block text-sm font-semibold text-ink mb-1" for="sondage-question">{{ __('cit_forum.votre_question') }}</label>
              <input id="sondage-question" type="text" wire:model="questionSondage" maxlength="300"
                     placeholder="{{ __('cit_forum.avez_vous_deja_recu_ce_message_sur_whatsapp') }}"
                     class="w-full min-h-[48px] bg-canvas border border-line rounded-xl px-3.5 text-sm outline-none focus:border-primary">
              @error('questionSondage') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            @foreach($optionsSondage as $index => $option)
              <div class="flex items-center gap-2" wire:key="option-{{ $index }}">
                <input type="text" wire:model="optionsSondage.{{ $index }}" maxlength="120"
                       placeholder="{{ __('cit_forum.reponse_n') }} {{ $index + 1 }}"
                       class="flex-1 min-h-[44px] bg-canvas border border-line rounded-xl px-3.5 text-sm outline-none focus:border-primary">
                @if(count($optionsSondage) > \App\Core\Community\Services\Sondages::OPTIONS_MINIMUM)
                  <button type="button" wire:click="retirerUneOption({{ $index }})"
                          class="shrink-0 w-10 h-10 inline-flex items-center justify-center rounded-xl border border-line text-slate2 hover:text-vfalse"
                          aria-label="{{ __('cit_forum.retirer_la_reponse') }} {{ $index + 1 }}"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">close</span></button>
                @endif
              </div>
            @endforeach

            @error('optionsSondage') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror

            @if(count($optionsSondage) < \App\Core\Community\Services\Sondages::OPTIONS_MAXIMUM)
              <button type="button" wire:click="ajouterUneOption"
                      class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary">{!! __('cit_forum.add_ajouter_une_reponse') !!}</button>
            @endif
          </div>
        @endif

        <div class="flex flex-wrap items-center gap-2 mt-3">
          <button type="button" wire:click="basculerComposer" class="inline-flex items-center gap-1.5 text-sm text-slate2 hover:text-ink">{{ __('cit_forum.annuler') }}</button>
          <button type="button" wire:click="publier" wire:loading.attr="disabled" class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark disabled:opacity-50">{!! __('cit_forum.publier_send') !!}</button>
        </div>
      </div>
    </div>

  {{-- ═══════════════════════════════════════════════════════════════ fil --}}
  @if($sujets->isNotEmpty())
      <div class="space-y-4">
        @foreach($sujets as $sujet)
          <x-forum.carte-sujet :sujet="$sujet" :aime="$this->aAime($sujet)" wire:key="sujet-{{ $sujet->id }}" />
        @endforeach
      </div>

      <div class="mt-6">{{ $sujets->links() }}</div>
    @else
      <div class="card p-10 text-center">
        <span class="text-3xl block mb-2" aria-hidden="true">💬</span>
        <p class="text-base font-display font-bold text-ink mb-1">{{ $categorie !== '' ? __('cit_forum.rien_dans_cette_rubrique') : __('cit_forum.le_forum_est_encore_vide') }}</p>
        <p class="text-sm text-slate2 mb-4">{{ $categorie !== '' ? __('cit_forum.choisissez_une_autre_rubrique_ou_lancez_la') : __('cit_forum.signalez_une_rumeur_votre_sujet_sera_le') }}</p>
        <button type="button" wire:click="basculerComposer" class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark min-h-[44px]">{{ __('cit_forum.publier_un_sujet') }}</button>
      </div>
    @endif
    </div>

    {{-- ══════════════════════════════════════════ colonne de droite ════ --}}
    <aside class="space-y-4">
      @if($tendances->isNotEmpty())
        <div class="card p-5">
          <p class="vf-eyebrow">{{ __('cit_forum.tendances') }}</p>
          <h3 class="text-sm font-display font-bold text-ink mb-3">{{ __('cit_forum.rumeurs_en_tendance') }}</h3>
          <ol class="space-y-3 text-sm">
            @foreach($tendances as $sujet)
              <li class="flex gap-3 items-start" wire:key="tendance-{{ $sujet->id }}">
                <span class="font-display font-black {{ $loop->first ? 'text-primary' : 'text-line' }} text-base leading-none pt-0.5">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <a href="{{ route('citizen.thread', $sujet) }}" class="hover:text-primary font-medium">{{ Str::limit($sujet->title, 40) }} <span class="block text-xs font-normal text-slate2 mt-0.5">{{ $sujet->replies_count }} {{ trans_choice('cit_forum.n_reponses', $sujet->replies_count) }}</span></a>
              </li>
            @endforeach
          </ol>
        </div>
      @endif

      @if($contributeurs->isNotEmpty())
        <div class="card p-5">
          <p class="vf-eyebrow">{{ __('cit_forum.communaute') }}</p>
          <h3 class="text-sm font-display font-bold text-ink mb-3">{{ __('cit_forum.contributeurs') }}</h3>
          <ol class="space-y-2.5 text-sm">
            @foreach($contributeurs as $membre)
              <li class="flex items-center gap-2.5" wire:key="contrib-{{ $membre->id }}">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-soft text-primary-dark font-bold text-xs shrink-0" aria-hidden="true">{{ Str::of($membre->name)->substr(0, 2)->upper() }}</span>
                <span class="flex-1 font-medium">{{ $membre->name }}</span>
                <strong class="text-xp">{{ number_format((int) $membre->xp, 0, ',', ' ') }} XP</strong>
              </li>
            @endforeach
          </ol>
        </div>
      @endif

      @if($dernieresVerifications->isNotEmpty())
        <div class="card p-5">
          <p class="vf-eyebrow">{{ __('cit_forum.laboratoire') }}</p>
          <h3 class="text-sm font-display font-bold text-ink mb-1.5">{{ __('cit_forum.dernieres_verifications') }}</h3>
          <p class="text-xs text-slate2 mb-3">{{ __('cit_forum.verdicts_officiels_du_laboratoire_verif_all_distincts') }}</p>
          <ul class="vf-timeline text-sm">
            @foreach($dernieresVerifications as $verification)
              <li class="vf-timeline-item list-none" wire:key="verif-{{ $verification->id }}">
                <span class="{{ $verification->verdict->badge() }} !text-[10px] mr-1.5">{{ mb_strtoupper($verification->verdict->libelle()) }}</span>
                <a href="{{ route('citizen.hub.article', $verification) }}" class="hover:text-primary">{{ Str::limit($verification->title, 34) }}</a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
    </aside>
  </div>

</div>
