@props([
    'recherche' => 'recherche',      // propriété Livewire de la recherche
    'parPage' => 'parPage',          // propriété Livewire du nombre de lignes
    'placeholder' => 'Rechercher…',
    'actifs' => 0,                   // nombre de filtres avancés posés (ouvre le panneau)
    'reinitialiser' => 'reinitialiser',
    'avecRecherche' => true,
    'options' => [10, 25, 50, 100],
])
{{-- Barre minimale de toute liste de la console : recherche, filtres avancés repliables,
     nombre de lignes par page, remise à zéro. Les filtres se posent dans le slot, côté écran. --}}
<div x-data="{ ouvert: {{ (int) $actifs > 0 ? 'true' : 'false' }} }" {{ $attributes->class('carte p-4 mb-4') }}>
  <div class="flex flex-wrap items-center gap-3">
    @if ($avecRecherche)
      <div class="relative flex-1 min-w-[220px]">
        <label for="liste-{{ $recherche }}" class="sr-only">Rechercher</label>
        <x-lucide name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 texte-3" />
        <input id="liste-{{ $recherche }}" type="search" wire:model.live.debounce.350ms="{{ $recherche }}" class="champ !pl-9" placeholder="{{ $placeholder }}">
      </div>
    @endif

    @if (! $slot->isEmpty())
      <button type="button" class="btn-second" x-on:click="ouvert = ! ouvert" :aria-expanded="ouvert">
        <x-lucide name="sliders-horizontal" />Filtres avancés
        @if ((int) $actifs > 0)<span class="pastille p-bon">{{ $actifs }}</span>@endif
      </button>
    @endif

    <label class="flex items-center gap-2 text-sm texte-2">
      <span class="hidden sm:inline">Lignes</span>
      <select wire:model.live="{{ $parPage }}" class="champ !w-auto" aria-label="Lignes par page">
        @foreach ($options as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
      </select>
    </label>

    <button type="button" class="btn-fantome" wire:click="{{ $reinitialiser }}"><x-lucide name="rotate-ccw" />Réinitialiser</button>
  </div>

  @if (! $slot->isEmpty())
    <div x-show="ouvert" x-cloak x-transition.opacity class="mt-4 pt-4 border-t filet grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {{ $slot }}
    </div>
  @endif
</div>
