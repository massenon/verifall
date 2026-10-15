{{--
  | Icônes en SVG EN LIGNE, dessinées dans la page.
  |
  | ## Ce qu'elles remplacent
  |
  | `<span class="material-symbols-rounded">nom</span>` — une police d'icônes.
  | Trois défauts la condamnent sur cette plateforme :
  |
  |  · **Elle se télécharge.** La police Material Symbols pèse plusieurs
  |    centaines de kilo-octets pour quelques glyphes réellement employés. Sur
  |    une connexion mobile béninoise, c'est payé à chaque premier chargement.
  |  · **Elle échoue en clair.** Tant qu'elle n'est pas arrivée, le navigateur
  |    affiche le NOM du glyphe : on lit « arrow_forward » ou « workspace_premium »
  |    à l'écran. Hors ligne — et l'application a un service worker — c'est
  |    l'état permanent.
  |  · **Elle ne s'imprime pas toujours.** Une police d'icônes que le moteur
  |    d'impression ne charge pas laisse le texte du glyphe sur le papier.
  |
  | Un SVG en ligne n'a aucun de ces trois problèmes : il est déjà dans le HTML,
  | il est net à toute taille, et il suit la couleur du texte par
  | `currentColor`.
  |
  | ## Emploi
  |
  |     <x-icone nom="arrow_forward" />
  |     <x-icone nom="school" taille="20" class="text-primary" />
  |
  | Le nom reprend celui de Material Symbols : la transposition depuis le
  | prototype reste lisible, et l'on retrouve l'origine de chaque icône.
--}}
@props([
    'nom',
    'taille' => 18,
])

@php
    /*
     | Les tracés, sur une grille de 24. Repris de Material Symbols pour que le
     | dessin ne change pas — le prototype est la référence visuelle.
     |
     | Une icône inconnue ne rend RIEN plutôt qu'un carré vide ou un nom en
     | toutes lettres : mieux vaut un manque discret qu'un défaut affiché.
     */
    $traces = [
        'arrow_forward' => 'M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z',
        'arrow_back' => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20z',
        'videocam' => 'M17 10.5V7a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1v-3.5l4 4v-11z',
        'quiz' => 'M20 3H4a2 2 0 00-2 2v14a2 2 0 002 2h16a2 2 0 002-2V5a2 2 0 00-2-2zm-8.5 14h-2v-2h2zm2.07-7.75l-.9.92c-.5.51-.67.93-.67 1.83h-2v-.5c0-.9.34-1.71.9-2.3l1.24-1.26A1.5 1.5 0 0012 6.5a1.5 1.5 0 00-3 0H7a3.5 3.5 0 117 0c0 .7-.28 1.33-.73 1.79z',
        'assignment' => 'M19 3h-4.18A3 3 0 0012 1a3 3 0 00-2.82 2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-7 0a1 1 0 110 2 1 1 0 010-2zM7 7h10v2H7zm0 4h10v2H7zm0 4h7v2H7z',
        'school' => 'M12 3L1 9l11 6 9-4.91V17h2V9zM5 13.18v4L12 21l7-3.82v-4L12 17z',
        'workspace_premium' => 'M9.68 13.69L12 11.93l2.31 1.76-.88-2.85L15.75 9h-2.84L12 6.19L11.09 9H8.25l2.31 1.84zM20 10c0-4.42-3.58-8-8-8s-8 3.58-8 8c0 2.03.76 3.87 2 5.28V23l6-2 6 2v-7.72A7.96 7.96 0 0020 10zM12 4c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6 2.69-6 6-6z',
        'menu_book' => 'M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5z',
        'timer' => 'M15 1H9v2h6zm-4 13h2V8h-2zm8.03-6.61l1.42-1.42a11 11 0 00-1.41-1.41l-1.42 1.42A9 9 0 1021 13a8.94 8.94 0 00-1.97-5.61zM12 20a7 7 0 117-7 7 7 0 01-7 7z',
        'check_circle' => 'M12 2a10 10 0 100 20 10 10 0 000-20zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8z',
        'travel_explore' => 'M19.3 16.9a4.5 4.5 0 00.6-5.35 4.5 4.5 0 10-1.72 6.13l3.19 3.19 1.41-1.41zm-3.3.1a2.5 2.5 0 112.5-2.5 2.5 2.5 0 01-2.5 2.5zM12 20v2A10 10 0 1122 12h-2a8 8 0 10-8 8z',
        'expand_more' => 'M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6z',
    ];

    $trace = $traces[$nom] ?? null;
@endphp

@if ($trace)
    <svg {{ $attributes->merge(['class' => 'inline-block shrink-0 align-middle']) }}
         width="{{ $taille }}" height="{{ $taille }}"
         viewBox="0 0 24 24" fill="currentColor"
         aria-hidden="true" focusable="false">
        <path d="{{ $trace }}"/>
    </svg>
@endif
