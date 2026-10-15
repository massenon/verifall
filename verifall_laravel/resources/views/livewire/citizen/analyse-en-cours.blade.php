{{--
  | Témoin « une analyse tourne » (lot 1, B-03).
  |
  | Le `wire:poll` n'est posé QUE sur la branche active. Une page sans analyse
  | en cours — la très grande majorité — ne rend qu'un conteneur vide et
  | n'interroge rien : sans cette précaution, les vingt-cinq écrans citoyens
  | sondraient le serveur en permanence pour n'afficher jamais rien.
  |
  | Écarté de l'écran de suivi lui-même, qui sonde déjà toutes les trois
  | secondes et affiche les étapes du pipeline : deux témoins de la même chose
  | sur la même page, dont l'un moins précis que l'autre.
--}}
@if($verification && ! request()->routeIs('citizen.verification'))
  <a wire:poll.10s
     href="{{ route('citizen.verification', $verification) }}"
     class="inline-flex items-center gap-1.5 rounded-xl bg-surface border border-line px-3.5 py-2.5 hover:border-primary transition-colors"
     title="{{ __('cit_citizen.analyse_voir_la_progression') }}">
    {{-- Le point qui bat porte l'information à lui seul : on le voit du coin
         de l'œil, sans lire. Le libellé disparaît sous 640 px, où la barre
         d'en-tête est déjà pleine ; l'icône, elle, reste. --}}
    <span class="inline-block w-2 h-2 rounded-full bg-primary animate-pulse" aria-hidden="true"></span>
    <span class="material-symbols-rounded !text-[19px] text-primary" aria-hidden="true">travel_explore</span>
    <span class="hidden md:inline text-sm font-semibold text-ink">{{ __('cit_citizen.analyse_en_cours') }}</span>
    <span class="sr-only">{{ __('cit_citizen.analyse_en_cours_annonce') }}</span>
  </a>
@else
  {{-- Livewire exige une racine unique : un composant qui ne rend rien doit
       tout de même rendre un élément, sinon il ne peut plus se réhydrater. --}}
  <span class="hidden"></span>
@endif
