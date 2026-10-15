{{--
  | Notes de communauté d'un sujet.
  |
  | Trois zones qui ne se confondent jamais :
  |   1. les notes PUBLIÉES — le contexte, pour tout le monde ;
  |   2. les notes de la personne qui n'ont pas encore franchi le pont ;
  |   3. la file d'évaluation, repliée, où l'on vote sur les notes des autres.
  |
  | Le SCORE DE PONTAGE n'apparaît nulle part, et c'est délibéré : hors
  | contexte, ce nombre ne veut rien dire, et l'afficher inviterait à le jouer.
--}}
<div class="mt-4">

  {{-- ═══════════════════════════════════════════ le contexte, s'il existe --}}
  @if($publiees->isNotEmpty())
    <div class="card p-5">
      <div class="flex items-start justify-between gap-3 mb-1">
        <div>
          <p class="text-sm font-display font-bold text-ink mb-0">{{ __('cit_notes_du_sujet.contexte_ajoute_par_la_communaute') }}</p>
          <p class="text-xs text-slate2 mb-0">{{ __('cit_notes_du_sujet.ces_notes_ont_ete_jugees_utiles_par') }}</p>
        </div>
        <span class="vbadge vbadge-true shrink-0">{{ $publiees->count() }}</span>
      </div>

      <div class="mt-3 space-y-3">
        @foreach($publiees as $note)
          <article class="vf-note vf-note--publiee" wire:key="note-pub-{{ $note->uuid }}">
            <p class="vf-note-texte mb-0">{{ $note->body }}</p>

            @if($note->sourcesValides() !== [])
              <ul class="vf-note-sources">
                @foreach($note->sourcesValides() as $source)
                  {{-- `nofollow noopener` : une plateforme qui combat la
                       désinformation ne transmet pas son autorité de
                       référencement aux liens qu'on lui soumet. --}}
                  <li><a href="{{ $source }}" target="_blank" rel="nofollow noopener noreferrer">{{ Str::limit($source, 70) }}</a></li>
                @endforeach
              </ul>
            @endif

            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-line">
              <span class="text-xs text-slate2">
                {{ $note->author?->name ?? __('cit_notes_du_sujet.compte_supprime') }} · {{ $note->created_at?->diffForHumans() }}
              </span>

              @if($connecte && $note->author?->id !== auth()->id())
                <span class="ml-auto flex items-center gap-2">
                  <button type="button" wire:click="ouvrirSignalement('{{ $note->uuid }}')" class="text-xs font-semibold text-slate2 hover:text-vfalse" aria-label="{{ __('cit_notes_du_sujet.signaler_cette_note') }}"> <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">flag</span></button>
                  @php($monVote = $mesVotes[$note->id] ?? null)
                  <button type="button" wire:click="voter('{{ $note->uuid }}', true)"
                          class="vf-note-vote {{ $monVote === true ? 'is-on' : '' }}"
                          aria-pressed="{{ $monVote === true ? 'true' : 'false' }}">
                    <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">thumb_up</span>
                    {{ __('cit_notes_du_sujet.utile') }}
                  </button>
                  <button type="button" wire:click="voter('{{ $note->uuid }}', false)"
                          class="vf-note-vote {{ $monVote === false ? 'is-off' : '' }}"
                          aria-pressed="{{ $monVote === false ? 'true' : 'false' }}">
                    <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">thumb_down</span>
                    {{ __('cit_notes_du_sujet.pas_utile') }}
                  </button>
                </span>
              @endif
            </div>
          </article>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ══════════════════════════════════════════════════════ mes propres notes --}}
  @if($miennes->isNotEmpty())
    <div class="card p-5 mt-4">
      <p class="text-sm font-display font-bold text-ink mb-3">{{ __('cit_notes_du_sujet.votre_note_sur_ce_sujet') }}</p>

      @foreach($miennes as $note)
        <article class="vf-note {{ $note->status === \App\Enums\StatutNoteCommunaute::NonRetenue ? 'vf-note--ecartee' : 'vf-note--attente' }} mb-3"
                 wire:key="note-mienne-{{ $note->uuid }}">
          <span class="{{ $note->status->badge() }}">{{ $note->status->libelle() }}</span>

          <p class="vf-note-texte mt-2 mb-0">{{ $note->body }}</p>

          @if($note->sourcesValides() !== [])
            <ul class="vf-note-sources">
              @foreach($note->sourcesValides() as $source)
                <li><a href="{{ $source }}" target="_blank" rel="nofollow noopener noreferrer">{{ Str::limit($source, 70) }}</a></li>
              @endforeach
            </ul>
          @endif

          {{-- Le texte de l'état vient de l'énumération : une note en attente
               n'est PAS une note rejetée, et l'écran doit le dire dans ces
               termes-là — sans quoi personne n'en réécrit jamais. --}}
          <p class="text-xs text-slate2 mt-3 mb-0">{{ $note->status->explication() }}</p>
        </article>
      @endforeach
    </div>
  @endif

  {{-- ══════════════════════════════════════════════════ proposer une note --}}
  @if($connecte && $miennes->isEmpty() && $publiees->where('user_id', auth()->id())->isEmpty())
    <div class="card p-5 mt-4">
      @unless($formulaireOuvert)
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-display font-bold text-ink mb-0">{{ __('cit_notes_du_sujet.ajouter_du_contexte') }}</p>
            <p class="text-xs text-slate2 mb-0">{{ __('cit_notes_du_sujet.une_note_courte_avec_au_moins_une') }}</p>
          </div>
          <button type="button" wire:click="ouvrirFormulaire"
                  class="rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas">{{ __('cit_notes_du_sujet.proposer_une_note') }}</button>
        </div>
      @else
        <p class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_notes_du_sujet.proposer_une_note') }}</p>

        <label class="block text-xs font-semibold text-ink mb-1" for="note-texte">{{ __('cit_notes_du_sujet.votre_note') }}</label>
        <textarea id="note-texte" wire:model="texte" rows="4" maxlength="1000"
                  placeholder="{{ __('cit_notes_du_sujet.ce_que_le_sujet_omet_et_ce') }}"
                  class="w-full bg-surface border border-line rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-primary"></textarea>
        @error('texte') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror

        <label class="block text-xs font-semibold text-ink mb-1 mt-3" for="note-sources">{{ __('cit_notes_du_sujet.sources_une_adresse_web_par_ligne') }}</label>
        <textarea id="note-sources" wire:model="sourcesSaisies" rows="3"
                  placeholder="https://…"
                  class="w-full bg-surface border border-line rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-primary"></textarea>
        @error('sources') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror

        <p class="text-xs text-slate2 mt-2 mb-0">{{ __('cit_notes_du_sujet.une_note_sans_source_n_est_qu') }}</p>

        <div class="flex justify-end gap-2 mt-3">
          <button type="button" wire:click="fermerFormulaire"
                  class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_notes_du_sujet.annuler') }}</button>
          <button type="button" wire:click="proposer" wire:loading.attr="disabled" wire:target="proposer"
                  class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark disabled:opacity-40">{{ __('cit_notes_du_sujet.enregistrer_la_note') }}</button>
        </div>
      @endunless

      @if($reputation !== null)
        {{-- Le palier n'apparaît JAMAIS seul : les faits qui le produisent sont
             affichés à côté, sinon le libellé serait un chiffre inventé. --}}
        <p class="vf-reputation mt-4 pt-3 border-t border-line mb-0">
          <span>{{ __('cit_notes_du_sujet.votre_reputation') }}</span>
          <strong>{{ $reputation['palier'] }}</strong>
          <span>·</span>
          <span>{{ trans_choice('cit_notes_du_sujet.dementis_nes_de_vos_signalements', $reputation['dementis']) }}</span>
          <span>·</span>
          <span>{{ trans_choice('cit_notes_du_sujet.signalements_retenus', $reputation['signalements_retenus']) }}</span>
          <span>·</span>
          <span>{{ trans_choice('cit_notes_du_sujet.notes_publiees', $reputation['notes_publiees']) }}</span>
        </p>
      @endif
    </div>
  @endif

  {{-- ══════════════════════════════════════════════════ file d'évaluation --}}
  @if($connecte && $aEvaluer->isNotEmpty())
    <div class="card p-5 mt-4">
      <button type="button" wire:click="basculerLaFile"
              class="flex w-full items-center justify-between gap-3 text-left"
              aria-expanded="{{ $fileOuverte ? 'true' : 'false' }}">
        <span>
          <span class="block text-sm font-display font-bold text-ink">
            {{ trans_choice('cit_notes_du_sujet.notes_a_evaluer', $aEvaluer->count()) }}
          </span>
          <span class="block text-xs text-slate2">
            {{ __('cit_notes_du_sujet.elles_ne_sont_pas_publiees_votre_avis') }}
          </span>
        </span>
        <span class="material-symbols-rounded text-slate2" aria-hidden="true">
          {{ $fileOuverte ? 'expand_less' : 'expand_more' }}
        </span>
      </button>

      @if($fileOuverte)
        <div class="mt-3 space-y-3">
          @foreach($aEvaluer as $note)
            <article class="vf-note vf-note--attente" wire:key="note-file-{{ $note->uuid }}">
              <p class="vf-note-texte mb-0">{{ $note->body }}</p>

              @if($note->sourcesValides() !== [])
                <ul class="vf-note-sources">
                  @foreach($note->sourcesValides() as $source)
                    <li><a href="{{ $source }}" target="_blank" rel="nofollow noopener noreferrer">{{ Str::limit($source, 70) }}</a></li>
                  @endforeach
                </ul>
              @endif

              <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-line">
                <span class="text-xs text-slate2">{{ __('cit_notes_du_sujet.cette_note_vous_aide_t_elle_a') }}</span>
                <button type="button" wire:click="ouvrirSignalement('{{ $note->uuid }}')" class="text-xs font-semibold text-slate2 hover:text-vfalse" aria-label="{{ __('cit_notes_du_sujet.signaler_cette_note') }}"> <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">flag</span></button>
                @php($monVote = $mesVotes[$note->id] ?? null)
                <span class="ml-auto flex gap-2">
                  <button type="button" wire:click="voter('{{ $note->uuid }}', true)"
                          class="vf-note-vote {{ $monVote === true ? 'is-on' : '' }}"
                          aria-pressed="{{ $monVote === true ? 'true' : 'false' }}">
                    <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">thumb_up</span>
                    {{ __('cit_notes_du_sujet.utile') }}
                  </button>
                  <button type="button" wire:click="voter('{{ $note->uuid }}', false)"
                          class="vf-note-vote {{ $monVote === false ? 'is-off' : '' }}"
                          aria-pressed="{{ $monVote === false ? 'true' : 'false' }}">
                    <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">thumb_down</span>
                    {{ __('cit_notes_du_sujet.pas_utile') }}
                  </button>
                </span>
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </div>
  @endif

  {{-- ═══════════════════════════════════ signaler une note à la modération --}}
  @if($noteSignalee !== null)
    <div class="modal-backdrop is-open" role="dialog" aria-modal="true" aria-labelledby="note-sig-titre">
      <div class="modal p-6">
        <h4 id="note-sig-titre" class="font-display font-bold text-ink text-lg mb-1">{{ __('cit_notes_du_sujet.signaler_cette_note') }}</h4>

        {{-- L'algorithme de pontage juge des PONTS, pas des insultes : une note
             injurieuse doit avoir une issue humaine, et le dire d'emblée évite
             de laisser croire qu'un vote « pas utile » suffit. --}}
        <p class="text-sm text-slate2 mb-4">{{ __('cit_notes_du_sujet.un_moderateur_l_examinera_le_signalement_n') }}</p>

        <label class="block text-sm font-semibold text-ink mb-1" for="note-sig-motif">{{ __('cit_notes_du_sujet.motif') }}</label>
        <select id="note-sig-motif" wire:model="motifSignalement"
                class="w-full bg-surface border border-line rounded-xl px-3 py-2.5 text-sm mb-1">
          <option value="">{{ __('cit_notes_du_sujet.choisir_un_motif') }}</option>
          @foreach($motifs as $cle => $libelle)
            <option value="{{ $cle }}">{{ $libelle }}</option>
          @endforeach
        </select>
        @error('motifSignalement') <p class="text-xs text-vfalse mb-0">{{ $message }}</p> @enderror

        <div class="flex gap-2 justify-end mt-4">
          <button type="button" wire:click="fermerSignalement"
                  class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_notes_du_sujet.annuler') }}</button>
          <button type="button" wire:click="signaler" wire:loading.attr="disabled" wire:target="signaler"
                  class="rounded-xl bg-vfalse text-white px-5 py-2.5 text-sm font-semibold hover:opacity-90 disabled:opacity-50">{{ __('cit_notes_du_sujet.envoyer_le_signalement') }}</button>
        </div>
      </div>
    </div>
  @endif

</div>
