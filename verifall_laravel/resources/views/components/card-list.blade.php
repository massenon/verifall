@props(['variant' => 'staff'])

{{-- Pendant mobile de <x-data-table> : mêmes données, présentation en cartes.
     Masqué à partir de lg, où la table reprend la main. --}}
<div {{ $attributes->merge(['class' => 'lg:hidden space-y-3']) }} data-card-list>
    {{ $slot }}
</div>
