@props([
    'variant' => 'staff',
    'id' => 'bottom-sheet',
    'title' => 'Actions',
])

{{-- Pendant mobile du menu ⋮ desktop : les actions de ligne s'ouvrent en
     feuille basse, avec des cibles ≥48 px (le CSS du prototype l'impose). --}}
<div class="{{ $variant === 'staff' ? 'feuille' : 'modal' }} lg:hidden"
     data-sheet
     id="{{ $id }}"
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}-title">

    <div class="feuille-fond" data-sheet-close></div>

    <div class="feuille-panneau">
        <div class="feuille-poignee" aria-hidden="true"></div>
        <h2 id="{{ $id }}-title" class="sr-only">{{ $title }}</h2>
        {{ $slot }}
    </div>
</div>
