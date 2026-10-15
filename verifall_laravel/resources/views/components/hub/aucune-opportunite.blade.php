{{--
  | L'absence d'opportunité, dite honnêtement.
  |
  | « Rien ne correspond » ≠ « rien n'est publié ». Le second message, affiché
  | sur une recherche infructueuse, affirmerait que la plateforme n'a rien
  | vérifié — une contre-vérité, sur la page qui met en garde contre les fausses
  | offres.
  |
  | Et surtout : aucune annonce inventée pour remplir la grille. Elle ferait
  | candidater quelqu'un dans le vide.
--}}
@props(['filtres', 'titre'])

<div class="hub-card p-8 text-center">
  @if($filtres)
    <span class="text-3xl block mb-2" aria-hidden="true">🔎</span>
    <p class="text-base font-display font-bold text-ink mb-1">{{ __('composants.hub_aucune_opportunite_ne_correspond') }}</p>
    <p class="text-sm text-slate2 mb-4">{{ __('composants.hub_essayez_une_autre_recherche_ou_une_autre') }}</p>
    <button type="button" wire:click="reinitialiser" class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-4 py-2.5 hover:bg-primary hover:text-white transition-colors min-h-[44px] items-center">{{ __('composants.hub_reinitialiser_les_filtres') }}</button>
  @else
    <span class="text-3xl block mb-2" aria-hidden="true">💼</span>
    <p class="text-base font-display font-bold text-ink mb-1">{{ $titre }}</p>
    <p class="text-sm text-slate2 mb-0">{{ __('composants.hub_nous_ne_publions_que_des_opportunites') }}</p>
  @endif
</div>
