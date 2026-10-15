@push('styles')
  {{-- Le build Tailwind est figé : les classes des notes, du sondage et de la
       ligne de réputation n'existent nulle part ailleurs. Sans cette feuille,
       elles s'écriraient dans le balisage sans rien produire. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/notes-communaute.css') }}">
  <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/emoticones.css') }}">
@endpush

@push('scripts')
  <script src="{{ \App\Support\Assets\ActifVersionne::url('assets/js/pages/emoticones.js') }}" defer></script>
@endpush

{{--
  | Un sujet du forum — transposition de compte-user/forum-sujet.html.
  |
  | Le verdict officiel surplombe l'avis de la communauté sans s'y mêler.
--}}
<div class="max-w-3xl mx-auto">

  <nav class="text-xs text-slate2 mb-3" aria-label="{{ __('cit_sujet.fil_d_ariane') }}">
    <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a>
    <span class="text-line" aria-hidden="true">/</span>
    <a href="{{ route('citizen.forum') }}" class="hover:text-primary">Forum</a>
    <span class="text-line" aria-hidden="true">/</span>
    <span class="text-ink font-medium">{{ Str::limit($sujet->title, 44) }}</span>
  </nav>

  <x-forum.carte-sujet :sujet="$sujet" :aime="$this->aAime($sujet)" :lien="false" />

  {{--
    | Bandeau de MODÉRATION. Visible seulement pour qui en a le droit, et posé
    | ici plutôt que dans le back-office : un modérateur qui lit le forum doit
    | pouvoir clore un fil qui dérape sans changer d'application.
  --}}
  @if($peutModerer)
    <div class="card p-4 mt-4 border-l-4" style="border-left-color:var(--vf-primary)">
      <p class="text-xs font-bold tracking-[.12em] uppercase text-slate2 mb-3">{{ __('cit_sujet.moderation') }}</p>

      <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="basculerVerrou"
                class="inline-flex items-center gap-1.5 rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas min-h-[44px]">
          <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">{{ $sujet->is_locked ? 'lock_open' : 'lock' }}</span>
          {{ $sujet->is_locked ? __('cit_sujet.rouvrir_le_fil') : __('cit_sujet.clore_le_fil') }}
        </button>

        <button type="button" wire:click="basculerEpingle"
                class="inline-flex items-center gap-1.5 rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas min-h-[44px]">
          <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">push_pin</span>
          {{ $sujet->is_pinned ? __('cit_sujet.desepingler') : __('cit_sujet.epingler') }}
        </button>

        {{-- Le bouton d'escalade a quitté ce bandeau : il a désormais son propre
             bloc, visible aussi de l'auteur du sujet. Le laisser ici en double
             afficherait deux boutons au modérateur. --}}
      </div>

      <div class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t border-line">
        <div class="min-w-[200px]">
          <label class="block text-xs font-semibold text-ink mb-1" for="verdict-officiel">{{ __('cit_sujet.verdict_officiel') }}</label>
          <select id="verdict-officiel" wire:model="verdictChoisi"
                  class="w-full bg-surface border border-line rounded-xl px-3 min-h-[44px] text-sm">
            <option value="">{{ __('cit_sujet.choisir') }}</option>
            @foreach($verdicts as $unVerdict)
              <option value="{{ $unVerdict->value }}">{{ $unVerdict->libelle() }}</option>
            @endforeach
          </select>
        </div>
        <button type="button" wire:click="apposerLeVerdict"
                class="inline-flex items-center rounded-xl bg-ink text-white text-sm font-semibold px-4 py-2.5 hover:opacity-90 min-h-[44px]">{{ __('cit_sujet.publier_le_verdict') }}</button>
      </div>

      {{-- Rappelé au modérateur au moment où il tranche : c'est LUI qui engage
           la plateforme, pas le sondage affiché juste au-dessus. --}}
      <p class="text-xs text-slate2 mt-2 mb-0">{{ __('cit_sujet.le_verdict_officiel_engage_verif_all_il') }}</p>
    </div>
  @endif

  {{-- ══════════════════════════════════════════════ l'avis de la personne --}}
  <div class="card p-5 mt-4">
    <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_sujet.votre_avis_sur_cette_information') }}</p>
    <p class="text-xs text-slate2 mb-3">{{ __('cit_sujet.c_est_un_sondage_pas_une_verification') }}</p>

    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('cit_sujet.votre_avis') }}">
      @foreach($avis as $option)
        @php($choisi = $monAvis === $option)
        <button type="button" wire:click="voter('{{ $option->value }}')" wire:key="avis-{{ $option->value }}"
                role="radio" aria-checked="{{ $choisi ? 'true' : 'false' }}"
                class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2.5 text-sm font-bold min-h-[44px] transition-colors"
                style="{{ $choisi
                    ? 'background:'.$option->couleur().';border-color:'.$option->couleur().';color:#fff'
                    : 'border-color:var(--vf-line);color:'.$option->couleur() }}">
          {{ $option->libelle() }}
        </button>
      @endforeach
    </div>

    @if($monAvis)
      <p class="text-xs text-slate2 mt-2 mb-0">
        {{ __('cit_sujet.vous_avez_repondu') }} « {{ $monAvis->libelle() }} ». {{ __('cit_sujet.recliquez_pour_retirer_votre_avis') }}
      </p>
    @endif
  </div>

  {{-- ════════════════════════════════════════ escalade en fact-check --}}
  @if($sujet->verification)
    <div class="card p-5 mt-4 border-l-4" style="border-left-color:var(--vf-primary)">
      <p class="text-sm font-display font-bold text-ink mb-1">{{ __('cit_sujet.escalade_en_fact_check') }}</p>
      <p class="text-sm text-slate2 mb-2">{{ __('cit_sujet.ce_signalement_a_ete_confie_a_nos') }}</p>

      {{-- TRAÇABILITÉ : qui a demandé, quand, et quelle vérification en est née.
           Sans ces trois informations, « transmis au laboratoire » est une
           promesse sans débiteur : personne ne peut dire depuis combien de
           temps on attend, ni ce qu'on attend au juste.

           La référence est affichée, pas rendue cliquable : la page de rapport
           est réservée à qui a commandé l'analyse, et une analyse née du forum
           n'appartient à personne. Un lien y mènerait à un 404. --}}
      <dl class="text-xs text-slate2 mb-0">
        @if($sujet->escalatedBy)
          <div class="flex gap-2">
            <dt class="font-semibold text-ink">{{ __('cit_sujet.escalade_par') }}</dt>
            <dd class="mb-0">{{ $sujet->escalatedBy->name }}</dd>
          </div>
        @endif
        @if($sujet->escalated_at)
          <div class="flex gap-2">
            <dt class="font-semibold text-ink">{{ __('cit_sujet.le') }}</dt>
            <dd class="mb-0">{{ $sujet->escalated_at->translatedFormat('d F Y') }}</dd>
          </div>
        @endif
        <div class="flex gap-2 items-center">
          <dt class="font-semibold text-ink">{{ __('cit_sujet.verification') }}</dt>
          <dd class="mb-0">
            <span class="{{ $sujet->verification->status->badge() }}">{{ $sujet->verification->status->libelle() }}</span>
          </dd>
        </div>
      </dl>
    </div>
  @elseif($this->peutEscalader())
    {{-- LE PONT entre le forum et le laboratoire. Il était réservé au bandeau
         de modération, donc invisible pour la quasi-totalité des visiteurs ;
         celui qui a signalé la rumeur est pourtant celui qui veut savoir. --}}
    <div class="card p-5 mt-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-display font-bold text-ink mb-0">{{ __('cit_sujet.faire_verifier_cette_information') }}</p>
          <p class="text-xs text-slate2 mb-0">{{ __('cit_sujet.nos_moteurs_d_analyse_s_en_saisissent') }}</p>
        </div>
        <button type="button" wire:click="transmettreAuLabo" wire:loading.attr="disabled" wire:target="transmettreAuLabo"
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark disabled:opacity-50">{!! __('cit_sujet.science_escalader_en_fact_check') !!}</button>
      </div>
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════ sondage --}}
  <livewire:citizen.sondage-du-sujet :slug="$sujet->slug" :key="'sondage-'.$sujet->id" />

  {{-- ═════════════════════════════════════════ notes de communauté --}}
  <livewire:citizen.notes-du-sujet :slug="$sujet->slug" :key="'notes-'.$sujet->id" />

  <p class="text-right mt-3 mb-0">{!! __('cit_sujet.flag_signaler_ce_sujet') !!}</p>

  {{-- ═══════════════════════════════════════════════════════════ réponses --}}
  <h2 class="text-lg font-display font-bold text-ink mt-8 mb-3">
    {{ trans_choice('cit_sujet.compte_reponses', $sujet->replies_count, ['nombre' => $sujet->replies_count]) }}
  </h2>

  @forelse($reponses as $reponseFil)
    <div class="card p-5 mb-3 {{ $reponseFil->is_official ? 'border-l-4' : '' }}"
         @if($reponseFil->is_official) style="border-left-color:var(--vf-primary)" @endif
         wire:key="rep-{{ $reponseFil->id }}">

      <div class="flex items-center gap-2.5 mb-2">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-canvas border border-line text-[11px] font-bold text-ink shrink-0">
          {{ $this->initiales($reponseFil->author?->name) }}
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-ink mb-0">
            {{ $reponseFil->author?->name ?? __('cit_sujet.compte_supprime') }}
            <span class="text-xs font-normal text-slate2">· {{ $reponseFil->created_at?->diffForHumans() }}</span>
          </p>
        </div>
        @if($reponseFil->is_official)
          {{-- La parole de la plateforme se reconnaît au premier coup d'œil :
               c'est elle qui l'engage. --}}
          <span class="vbadge vbadge-true shrink-0">{{ __('cit_sujet.reponse_officielle') }}</span>
        @endif
      </div>

      <div class="text-sm text-slate2 prose-lesson">{!! clean($reponseFil->body) !!}</div>

      <div class="flex items-center gap-4 mt-3 pt-3 border-t border-line text-sm">
        <button type="button" wire:click="aimerReponse('{{ $reponseFil->uuid }}')"
                class="inline-flex items-center gap-1 font-semibold {{ $this->aAimeReponse($reponseFil) ? 'text-primary' : 'text-slate2 hover:text-ink' }}"
                aria-pressed="{{ $this->aAimeReponse($reponseFil) ? 'true' : 'false' }}">
          <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">thumb_up</span>
          <span>{{ $reponseFil->likes_count }}</span>
        </button>
        @unless($sujet->is_locked)
          <button type="button" wire:click="citer('{{ $reponseFil->uuid }}')" class="inline-flex items-center gap-1 text-slate2 font-semibold hover:text-ink"> <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">reply</span> {{ __('cit_sujet.repondre') }}</button>
        @endunless

        <button type="button" wire:click="ouvrirSignalement('{{ $reponseFil->uuid }}')" class="inline-flex items-center gap-1 text-slate2 font-semibold hover:text-vfalse ml-auto" aria-label="{{ __('cit_sujet.signaler_cette_reponse') }}"> <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">flag</span></button>
      </div>

      @if($reponseFil->children->isNotEmpty())
        <div class="mt-3 pl-4 border-l-2 border-line space-y-3">
          @foreach($reponseFil->children as $enfant)
            <div wire:key="sous-{{ $enfant->id }}">
              <p class="text-xs font-semibold text-ink mb-0.5">
                {{ $enfant->author?->name ?? __('cit_sujet.compte_supprime') }}
                <span class="font-normal text-slate2">· {{ $enfant->created_at?->diffForHumans() }}</span>
              </p>
              <div class="text-sm text-slate2 prose-lesson">{!! clean($enfant->body) !!}</div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  @empty
    <p class="text-sm text-slate2 mb-4">{{ __('cit_sujet.personne_n_a_encore_repondu_votre_message') }}</p>
  @endforelse

  {{-- ══════════════════════════════════════════════════════════ répondre --}}
  @if($sujet->is_locked)
    <div class="card p-5 text-center">
      <span class="material-symbols-rounded text-slate2 !text-[28px] mb-1" aria-hidden="true">lock</span>
      <p class="text-sm font-bold text-ink mb-1">{{ __('cit_sujet.ce_fil_est_clos') }}</p>
      <p class="text-sm text-slate2 mb-0">{{ __('cit_sujet.la_moderation_a_mis_fin_a_la') }}</p>
    </div>
  @else
    <div class="card p-5">
      <p class="text-sm font-display font-bold text-ink mb-2">
        {{ $repondA ? __('cit_sujet.votre_reponse_a_ce_message') : __('cit_sujet.repondre_au_sujet') }}
      </p>

      @if($repondA)
        <button type="button" wire:click="citer" class="text-xs font-semibold text-primary mb-2">{{ __('cit_sujet.repondre_au_sujet_plutot') }}</button>
      @endif

      <label class="sr-only" for="fil-reponse">{{ __('cit_sujet.votre_reponse') }}</label>
      <textarea id="fil-reponse" wire:model="reponse" rows="3" maxlength="5000" data-emoticones
                placeholder="{{ __('cit_sujet.apportez_un_element_une_source_une_nuance') }}"
                class="w-full bg-surface border border-line rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-primary"></textarea>
      @error('reponse') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror

      <div class="flex justify-end mt-3">
        <button type="button" wire:click="repondre" wire:loading.attr="disabled"
                class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark disabled:opacity-40 disabled:cursor-not-allowed min-h-[44px]">{{ __('cit_sujet.envoyer') }}</button>
      </div>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════ fenêtre de signalement --}}
  @if($signalementOuvert)
    <div class="modal-backdrop is-open" role="dialog" aria-modal="true" aria-labelledby="sig-titre">
      <div class="modal p-6">
        <h4 id="sig-titre" class="font-display font-bold text-ink text-lg mb-1">
          {{ __('cit_sujet.signaler') }} {{ $signaleUuid === null ? __('cit_sujet.ce_sujet') : __('cit_sujet.cette_reponse') }}
        </h4>

        {{-- Ce que le signalement déclenche, dit d'emblée : promettre un
             retrait serait mentir, et décevoir quelqu'un qui a signalé de
             bonne foi le dissuade de recommencer. --}}
        <p class="text-sm text-slate2 mb-4">{{ __('cit_sujet.un_moderateur_examinera_ce_contenu_le_signalement') }}</p>

        <label class="block text-sm font-semibold text-ink mb-1" for="sig-motif">{{ __('cit_sujet.motif') }}</label>
        <select id="sig-motif" wire:model="motifSignalement"
                class="w-full bg-surface border border-line rounded-xl px-3 min-h-[44px] text-sm mb-1">
          <option value="">{{ __('cit_sujet.choisir_un_motif') }}</option>
          @foreach($motifs as $cle => $libelle)
            <option value="{{ $cle }}">{{ $libelle }}</option>
          @endforeach
        </select>
        @error('motifSignalement') <p class="text-xs text-vfalse mb-2">{{ $message }}</p> @enderror

        <label class="block text-sm font-semibold text-ink mb-1 mt-3" for="sig-detail">{{ __('cit_sujet.precisions_facultatif') }}</label>
        <textarea id="sig-detail" wire:model="detailSignalement" rows="3" maxlength="1000"
                  placeholder="{{ __('cit_sujet.ce_qui_vous_semble_problematique') }}"
                  class="w-full bg-surface border border-line rounded-xl px-3 py-2.5 text-sm"></textarea>
        @error('detailSignalement') <p class="text-xs text-vfalse mb-0">{{ $message }}</p> @enderror

        <div class="flex gap-2 justify-end mt-4">
          <button type="button" wire:click="fermerSignalement"
                  class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_sujet.annuler') }}</button>
          <button type="button" wire:click="signaler" wire:loading.attr="disabled"
                  class="rounded-xl bg-vfalse text-white px-5 py-2.5 text-sm font-semibold hover:opacity-90 disabled:opacity-50">{{ __('cit_sujet.envoyer_le_signalement') }}</button>
        </div>
      </div>
    </div>
  @endif

</div>
