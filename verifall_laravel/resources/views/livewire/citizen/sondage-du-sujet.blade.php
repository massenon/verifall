{{--
  | Le sondage d'un sujet.
  |
  | Les résultats n'apparaissent QU'APRÈS le vote. Ce n'est pas une coquetterie :
  | montrer les chiffres avant oriente tous les votes suivants, et transforme un
  | sondage en machine à fabriquer du consensus. La condition est portée par le
  | service — cette vue n'a tout simplement pas les chiffres à afficher.
--}}
<div class="mt-4">

  @if($sondage !== null)
    <div class="card p-5">
      <p class="text-xs font-bold tracking-[.12em] uppercase text-slate2 mb-1">{{ __('cit_sondage_du_sujet.sondage') }}</p>
      <p class="text-sm font-display font-bold text-ink mb-3">{{ $sondage->question }}</p>

      @if($resultats !== null)
        {{-- On a voté (ou le sondage est clos) : les chiffres sont lisibles. --}}
        @foreach($resultats as $ligne)
          <div class="vf-sondage-resultat {{ $ligne['choisie'] ? 'is-mienne' : '' }}" wire:key="res-{{ $ligne['uuid'] }}">
            <div class="vf-sondage-ligne">
              <span class="vf-sondage-libelle">
                {{ $ligne['label'] }}
                @if($ligne['choisie'])
                  <span class="text-xs font-normal text-slate2">· {{ __('cit_sondage_du_sujet.votre_reponse') }}</span>
                @endif
              </span>
              <span class="vf-sondage-part">{{ $ligne['part'] }} % · {{ $ligne['votes'] }}</span>
            </div>
            <div class="progress" role="img"
                 aria-label="{{ $ligne['label'] }} : {{ $ligne['part'] }} {{ __('cit_sondage_du_sujet.pour_cent') }}">
              <span style="width: {{ $ligne['part'] }}%"></span>
            </div>
          </div>
        @endforeach

        <p class="text-xs text-slate2 mt-3 mb-0">
          {{ $sondage->votes_count }} {{ trans_choice('cit_sondage_du_sujet.participant', $sondage->votes_count) }}.
          @unless($sondage->estOuvert()) {{ __('cit_sondage_du_sujet.ce_sondage_est_clos') }} @endunless
        </p>

      @elseif($connecte)
        {{-- Pas encore voté : les réponses, et AUCUN chiffre. --}}
        @foreach($options as $option)
          <button type="button" wire:click="voter('{{ $option->uuid }}')" wire:key="opt-{{ $option->uuid }}"
                  class="vf-sondage-option">
            {{ $option->label }}
          </button>
        @endforeach

        <p class="text-xs text-slate2 mt-2 mb-0">{{ __('cit_sondage_du_sujet.les_resultats_s_afficheront_une_fois_votre') }}</p>

      @else
        <p class="text-sm text-slate2 mb-0">{{ __('cit_sondage_du_sujet.connectez_vous_pour_repondre_a_ce_sondage') }}</p>
      @endif
    </div>
  @endif

  {{-- ══════════════════════════════════════════════ créer, si l'on est l'auteur --}}
  @if($peutCreer)
    <div class="card p-5">
      @unless($formulaireOuvert)
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-display font-bold text-ink mb-0">{{ __('cit_sondage_du_sujet.poser_un_sondage') }}</p>
            <p class="text-xs text-slate2 mb-0">{{ __('cit_sondage_du_sujet.une_question_deux_a_six_reponses_un') }}</p>
          </div>
          <button type="button" wire:click="ouvrirFormulaire"
                  class="rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas">{{ __('cit_sondage_du_sujet.ajouter_un_sondage') }}</button>
        </div>
      @else
        <p class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_sondage_du_sujet.votre_sondage') }}</p>

        <label class="block text-xs font-semibold text-ink mb-1" for="sondage-question">{{ __('cit_sondage_du_sujet.question') }}</label>
        <input id="sondage-question" type="text" wire:model="question" maxlength="300"
               class="w-full bg-surface border border-line rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-primary">
        @error('question') <p class="text-xs text-vfalse mt-1 mb-0">{{ $message }}</p> @enderror

        <p class="block text-xs font-semibold text-ink mb-1 mt-3">{{ __('cit_sondage_du_sujet.reponses_possibles') }}</p>
        @foreach($optionsSaisies as $index => $option)
          <input type="text" wire:model="optionsSaisies.{{ $index }}" wire:key="opt-saisie-{{ $index }}"
                 maxlength="200" placeholder="{{ __('cit_sondage_du_sujet.reponse') }} {{ $index + 1 }}"
                 aria-label="{{ __('cit_sondage_du_sujet.reponse') }} {{ $index + 1 }}"
                 class="w-full bg-surface border border-line rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-primary mb-2">
        @endforeach
        @error('options') <p class="text-xs text-vfalse mb-0">{{ $message }}</p> @enderror

        <div class="flex justify-end gap-2 mt-3">
          <button type="button" wire:click="fermerFormulaire"
                  class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_sondage_du_sujet.annuler') }}</button>
          <button type="button" wire:click="creer" wire:loading.attr="disabled" wire:target="creer"
                  class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark disabled:opacity-40">{{ __('cit_sondage_du_sujet.publier_le_sondage') }}</button>
        </div>
      @endunless
    </div>
  @endif

</div>
