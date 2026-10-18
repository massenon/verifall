{{--
  Une icône Lucide en SVG : <x-lucide name="zap" class="w-4 h-4" />.
  Décorative par défaut ; `titre` la rend nommée (bouton icône seule).
--}}
@props(['name', 'class' => 'w-4 h-4', 'titre' => null])
{{ \App\Support\Icones\Lucide::svg($name, $class, $titre) }}
