@props(['titre', 'sous' => null, 'fil' => []])
{{-- En-tête d'écran de la console : fil d'Ariane, titre, aide, actions à droite. --}}
<div {{ $attributes->class('flex flex-wrap items-end justify-between gap-4 mb-6') }}>
  <div class="min-w-0">
    @if (count($fil))
      <nav class="flex items-center gap-1.5 text-[13px] texte-3 mb-1.5" aria-label="Fil d'Ariane">
        @foreach ($fil as $i => $etape)@if ($i)<x-lucide name="chevron-right" class="w-3.5 h-3.5" />@endif<span>{{ $etape }}</span>@endforeach
      </nav>
    @endif
    <h1 class="text-[26px] sm:text-[28px] font-semibold leading-tight text-encre dark:text-white">{{ $titre }}</h1>
    @if ($sous)<p class="mt-1 text-sm texte-2 max-w-2xl">{{ $sous }}</p>
    @elseif (! $slot->isEmpty())<p class="mt-1 text-sm texte-2 max-w-2xl">{{ $slot }}</p>@endif
  </div>
  @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
