{{--
  | « Mes Exercices » — transposition de compte-user/exercices.html.
  |
  | La zone de saisie riche reste celle du design system (`data-rich`), avec sa
  | barre d'outils et son compteur. Son contenu est recopié dans un champ caché
  | à chaque frappe : Livewire ne voit pas un `contenteditable`.
--}}
<div>

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div><nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_exercices.fil_d_ariane') }}"><a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">Dashboard</a> <span class="text-line" aria-hidden="true">/</span> <span class="text-ink font-medium">{{ __('cit_exercices.exercices') }}</span></nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_exercices.mes_exercices') }}</h1><p class="text-slate2 mt-1 mb-0">{{ __('cit_exercices.vos_travaux_pratiques_corriges_par_un_mentor') }}@if($prochaineEcheance !== null) <strong class="text-vinvest">{{ __('cit_exercices.a_faire_ensuite') }} {{ $prochaineEcheance->title }}</strong>.@endif</p>
    </div>
  </div>
  <div class="space-y-4">
    @forelse($exercices as $exercice)
      @php($remise = $remises->get($exercice->id))
      @php($ouvert = in_array($exercice->course_id, $coursSuivis, true))
      <article class="card p-5 flex flex-col sm:flex-row sm:items-center gap-4">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-soft shrink-0"><span class="material-symbols-rounded text-primary" aria-hidden="true">assignment</span></span>
        <div class="flex-1 min-w-0"><p class="text-xs text-slate2 mb-0.5">{{ $exercice->course?->title }}</p>
          <h3 class="text-base font-display font-bold text-ink mb-1">{{ $exercice->title }}</h3>
          <p class="text-xs text-slate2 mb-0">{{ $exercice->minutes }} {{ __('cit_exercices.min_texte_riche_fichier_pdf_zip_image') }}</p></div>
        <div class="shrink-0">
          @if(! $ouvert)
            <span class="vbadge" style="background:#F1F5F9;color:#64748B">{{ __('cit_exercices.inscrivez_vous_au_cours') }}</span>
          @elseif($remise !== null)
            {{-- Libellés en capitales et coche du prototype. --}}
            <span class="{{ $remise->status->badge() }}">{{ $remise->status === \App\Enums\StatutDevoir::Valide ? '✓ ' : '' }}{{ mb_strtoupper($remise->status->libelle()) }}</span>
          @else
            <button type="button" wire:click="ouvrir(@js($exercice->uuid))" class="rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark">{{ __('cit_exercices.faire_l_exercice') }}</button>
          @endif
        </div>
      </article>
    @empty
      <p class="card p-8 text-center text-sm text-slate2">{{ __('cit_exercices.aucun_exercice_pour_le_moment') }}</p>
    @endforelse
  </div>

  <div class="modal-backdrop" id="modal-assignment" role="dialog" aria-modal="true" aria-labelledby="as-title">
    <div class="modal p-6">
      <h4 id="as-title" class="font-display font-bold text-ink text-lg mb-1">{{ $exercice?->title ?? __('cit_exercices.exercice') }}</h4>
      <p class="text-sm text-slate2 mb-4">{{ $exercice?->description }}</p>
      <p class="text-sm font-semibold text-ink mb-2">{{ __('cit_exercices.votre_reponse') }}</p>
      <div class="rich-area" data-rich data-maxlength="1500" data-cible="reponse-exercice">
        <div class="rich-toolbar" role="toolbar" aria-label="{{ __('cit_exercices.mise_en_forme') }}">
          <button type="button" class="rich-btn" data-cmd="bold" aria-label="{{ __('cit_exercices.gras') }}"><strong>B</strong></button>
          <button type="button" class="rich-btn italic" data-cmd="italic" aria-label="{{ __('cit_exercices.italique') }}">I</button>
          <button type="button" class="rich-btn underline" data-cmd="underline" aria-label="{{ __('cit_exercices.souligne') }}">U</button>
          <button type="button" class="rich-btn" data-cmd="insertUnorderedList" aria-label="{{ __('cit_exercices.liste') }}"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">{{ __('cit_exercices.format_list_bulleted') }}</span></button>
          <button type="button" class="rich-btn" data-cmd="insertOrderedList" aria-label="{{ __('cit_exercices.liste_numerotee') }}"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">{{ __('cit_exercices.format_list_numbered') }}</span></button>
          <button type="button" class="rich-btn" data-cmd="createLink" aria-label="{{ __('cit_exercices.lien') }}"><span class="material-symbols-rounded !text-[18px]" aria-hidden="true">{{ __('cit_exercices.link') }}</span></button>
        </div>
        <div class="rich-editor" contenteditable="true" data-placeholder="{{ __('cit_exercices.structurez_votre_analyse') }}" aria-label="{{ __('cit_exercices.reponse_a_l_exercice') }}"></div>
      </div>
      <input type="hidden" id="reponse-exercice" wire:model="reponse">
      <p class="char-counter" data-char-counter></p>
      <label class="mt-3 flex items-center gap-2 text-sm text-slate2 cursor-pointer">{!! __('cit_exercices.attach_file_joindre_un_fichier_pdf_zip') !!}</label>
      @if($piece)
        <p class="text-sm text-vtrue font-medium mt-1">✓ {{ $piece->getClientOriginalName() }}</p>
      @endif
      @error('piece')<p class="text-sm text-vfalse mt-1" role="alert">{{ $message }}</p>@enderror
      <div class="flex gap-2 justify-end mt-5">
        <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas" data-modal-close>{{ __('cit_exercices.annuler') }}</button>
        <button type="button" wire:click="soumettre" class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{{ __('cit_exercices.soumettre') }}</button>
      </div>
    </div>
  </div>

  @push('scripts')
    <script src="{{ asset('assets/js/pages/modales-livewire.js') }}" defer></script>
  @endpush
</div>
