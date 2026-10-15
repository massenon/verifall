@push('styles')
  {{-- Le thème du verdict, le médaillon, la barre de fiabilité, le rendu
       Markdown de la synthèse et les directives d'impression A4 vivent ici.
       PAS dans `tailwind.css`, qui est figé ; pas dans `citizen.css`, parce
       que `/rapport/{uuid}` sert le même corps depuis un gabarit public qui ne
       charge pas la feuille citoyenne. --}}
  <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/rapport.css') }}">
@endpush

{{--
  | « Rapport de vérification » — l'enveloppe CITOYENNE.
  |
  | Le corps du rapport — verdict, argumentation, preuves — vit dans
  | `<x-rapport.corps>`, partagé avec la page publique : les deux lecteurs
  | doivent lire exactement le même verdict, sans quoi le rapport ne prouve
  | rien. Ce gabarit-ci n'ajoute que ce qui appartient à l'AUTEUR de la
  | demande : sa contre-expertise, son avis d'utilité, ses actions.
  |
  | ## Le thème du verdict ne se décide plus ici
  |
  | Ce fichier portait un `match (true)` de cinquante lignes qui testait
  | `str_contains($verdict, 'faux')` pour composer un tableau `$theme`. Deux
  | défauts, et le second était invisible :
  |
  | 1. C'était de la logique métier dans une vue — un second jeu de règles à
  |    côté de `VerdictFactCheck`, qui portait déjà `badge()` et `couleurBarre()`.
  | 2. Les DIX-HUIT classes qu'il composait (`bg-red-50/80`, `border-emerald-200`,
  |    `bg-amber-600`…) N'EXISTENT PAS dans le build Tailwind figé. Le Hero
  |    s'affichait sans fond, sans bordure et sans badge coloré. Écrites dans un
  |    tableau PHP plutôt que dans un attribut `class`, elles échappaient même à
  |    `ClassesTailwindPresentesTest`.
--}}
<div>
  <div class="vf-report-shell">

    {{-- ══════════════════════════════════════════════════════════════
         BARRE D'ACTIONS
         Retour discret à gauche ; à droite, les trois actions par ordre
         d'intention : relancer une vérification (ce pour quoi on revient),
         imprimer, partager. Le `flex-wrap` tient la ligne sur un téléphone
         plutôt que de la faire déborder.
    ══════════════════════════════════════════════════════════════ --}}
    <div class="vf-barre-actions no-print">
      <a href="{{ route('citizen.verifications') }}" class="vf-lien-retour">
        <span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
        {{ __('cit_rapport.retour_a_l_historique') }}
      </a>

      <div class="vf-barre-boutons">
        <a href="{{ route('citizen.verify') }}"
           class="vf-btn-primaire inline-flex items-center gap-2 rounded-xl text-sm font-semibold px-4 py-2">
          <span class="material-symbols-rounded" aria-hidden="true">add_circle</span>
          {{ __('cit_rapport.lancer_une_autre_verification') }}
        </a>

        <button type="button" onclick="window.print()"
                class="vf-btn-secondaire inline-flex items-center gap-2 rounded-xl text-sm font-semibold px-4 py-2">
          <span class="material-symbols-rounded" aria-hidden="true">picture_as_pdf</span>
          {{ __('cit_rapport.picture_as_pdf_pdf') }}
        </button>

        {{-- Le lien partagé est le lien PUBLIC : celui qui le reçoit n'a pas
             de compte, et `citizen.report` lui rendrait un écran de connexion. --}}
        <x-menu-partage
          :lien="$lienPublic"
          :verdict="$rapport->verdict"
          :affirmation="$verification->apercu(120)"
          aligne="droite" />
      </div>
    </div>

    <x-rapport.corps
      :verification="$verification"
      :rapport="$rapport"
      :moteurs="$moteurs"
      :sources="$sources"
      :haine="$haine"
      :synthese="$synthese"
      :dissidents="$dissidents"
      :qr-code="$qrCode"
      :public="false">

      {{-- Contre-expertise — un droit de l'auteur de la demande. --}}
      <div class="vf-card border border-line bg-surface no-print">
        <h2 class="text-sm font-display font-bold text-ink mb-2">{{ __('cit_rapport.ce_verdict_ne_vous_semble_pas_correct') }}</h2>

        @if($contestation !== null && $contestation->status->estOuverte())
          <p class="text-xs text-slate2 mb-0">{{ $contestation->status->messageAuDemandeur() }}</p>
          <p class="text-xs text-slate2 mb-0 mt-1">{{ __('cit_rapport.demandee') }} {{ $contestation->created_at?->diffForHumans() }}</p>
        @elseif($contestation !== null)
          <p class="text-xs text-slate2 mb-2">{{ $contestation->status->messageAuDemandeur() }}</p>
          @if($contestation->resolution)
            <blockquote class="rounded-xl bg-canvas border border-line p-3 text-xs text-slate2 mb-2">{{ $contestation->resolution }}</blockquote>
          @endif
          <button type="button" wire:click="ouvrirLaContestation"
                  class="w-full rounded-xl border border-line text-ink text-sm font-semibold px-4 py-2 hover:bg-canvas transition-colors">
            {{ __('cit_rapport.contester_a_nouveau') }}
          </button>
        @else
          <p class="text-xs text-slate2 mb-3 leading-relaxed">{{ __('cit_rapport.demandez_une_contre_expertise_par_nos_journalistes') }}</p>
          <button type="button" wire:click="ouvrirLaContestation"
                  class="vf-btn-sombre w-full rounded-xl text-sm font-semibold px-4 py-2">
            {{ __('cit_rapport.demander_un_expert') }}
          </button>
        @endif
      </div>

      {{-- Avis d'utilité --}}
      <div class="vf-card bg-surface border border-line no-print">
        <h2 class="text-sm font-display font-bold text-ink mb-3">{{ __('cit_rapport.ce_rapport_vous_a_t_il_ete_utile') }}</h2>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" wire:click="noter(true)"
                  aria-pressed="{{ $avis?->useful === true ? 'true' : 'false' }}"
                  class="rounded-xl border text-sm font-semibold px-3 py-2 transition-colors flex items-center justify-center gap-2 {{ $avis?->useful === true ? 'bg-primary text-white border-primary' : 'border-line text-ink hover:bg-canvas' }}">
            <span class="material-symbols-rounded" aria-hidden="true">thumb_up</span>
            {{ __('cit_rapport.oui_tout_a_fait') }}
          </button>
          <button type="button" wire:click="noter(false)"
                  aria-pressed="{{ $avis?->useful === false ? 'true' : 'false' }}"
                  class="rounded-xl border text-sm font-semibold px-3 py-2 transition-colors flex items-center justify-center gap-2 {{ $avis?->useful === false ? 'vf-btn-sombre' : 'border-line text-ink hover:bg-canvas' }}">
            <span class="material-symbols-rounded" aria-hidden="true">thumb_down</span>
            {{ __('cit_rapport.non_pas_vraiment') }}
          </button>
        </div>
      </div>

      {{-- Bannière Académie — transposée du prototype (compte-user/rapport.html).
           `!border-forest` et `text-white/70` n'existent pas dans le build Tailwind
           figé : la bordure et l'opacité du texte secondaire sont donc posées dans
           rapport.css, qui peut grandir, plutôt qu'inventées ici en classes. --}}
      <div class="vf-card bg-forest text-white no-print">
        <span class="inline-flex rounded-full bg-primary text-white text-xs font-bold tracking-widest px-3 py-1 mb-3 uppercase">
          {{ __('cit_rapport.academie') }}
        </span>
        <h2 class="text-base font-display font-bold text-white mb-2">{{ __('cit_rapport.devenez_fact_checker_certifie') }}</h2>
        <p class="vf-academie-texte text-sm mb-4 leading-relaxed">{{ __('cit_rapport.apprenez_a_debusquer_les_pieges_du_web') }}</p>
        <a href="{{ route('citizen.catalogue') }}" class="inline-flex rounded-xl bg-white text-ink text-sm font-semibold px-5 py-2 hover:opacity-90 transition-opacity">
          {{ __('cit_rapport.commencer') }}
        </a>
      </div>
    </x-rapport.corps>
  </div>

  {{-- Modale de contre-expertise --}}
  @if($tiroirContestation)
    <div class="modal-backdrop is-open no-print" wire:key="modale-contre-expertise">
      <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="ce-titre">
        <h2 id="ce-titre" class="text-lg font-display font-bold text-ink mb-1">{{ __('cit_rapport.demander_une_contre_expertise') }}</h2>
        <p class="text-sm text-slate2 mb-4">{{ __('cit_rapport.vous_pensez_que_le_resultat_merite_une') }}</p>

        <form wire:submit="demanderUnExpert">
          <div class="mb-2">
            <label for="ce-motif" class="sr-only">{{ __('cit_rapport.motif') }}</label>
            <textarea id="ce-motif" rows="5" wire:model="motifContestation"
                      maxlength="{{ $motifMaximum }}"
                      class="vf-champ text-sm text-ink"
                      placeholder="{{ __('cit_rapport.expliquez_brievement_ce_qui_vous_parait_faux') }}"></textarea>
          </div>
          @error('motifContestation')
            <p class="text-xs text-vfalse mb-2">{{ $message }}</p>
          @enderror

          <p class="text-xs text-slate2 mb-4">{{ __('cit_rapport.une_seule_demande_a_la_fois_par_rapport') }}</p>

          <div class="flex items-center justify-end gap-2">
            <button type="button" wire:click="fermerLaContestation"
                    class="rounded-xl border border-line text-ink text-sm font-semibold px-4 py-2 hover:bg-canvas transition-colors">
              {{ __('cit_rapport.annuler') }}
            </button>
            <button type="submit" wire:loading.attr="disabled"
                    class="vf-btn-sombre rounded-xl text-sm font-semibold px-4 py-2">
              {{ __('cit_rapport.envoyer_la_demande') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
