@props(['titre' => null, 'aide' => null])
{{-- Carte de la console : titre facultatif, actions à droite de l'en-tête. --}}
<section {{ $attributes->class('carte') }}>
  @if ($titre)
    <div class="carte-tete">
      <div><h2 class="carte-titre">{{ $titre }}</h2>@if ($aide)<p class="text-xs texte-3 mt-0.5">{{ $aide }}</p>@endif</div>
      @isset($actions){{ $actions }}@endisset
    </div>
  @endif
  <div class="{{ $titre ? 'carte-corps' : 'p-5' }}">{{ $slot }}</div>
</section>
