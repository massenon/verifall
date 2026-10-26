@props([
    'variant' => 'citizen',
    // Nombre de lignes de chargement à empiler.
    'lines' => 3,
    'height' => '1rem',
])

@php $staff = $variant === 'staff'; @endphp

{{-- aria-hidden + role="status" sur le parent : le lecteur d'écran annonce
     « chargement » une seule fois au lieu d'égrener chaque barre. --}}
<div {{ $attributes->merge(['class' => 'space-y-3']) }} role="status" aria-label="{{ __('composants.skeleton_chargement_en_cours') }}">
    @for ($i = 0; $i < (int) $lines; $i++)
        <div class="{{ $staff ? 'squelette' : 'skeleton' }}"
             style="height: {{ $height }}; width: {{ $i === (int) $lines - 1 ? '60%' : '100%' }}"
             aria-hidden="true"></div>
    @endfor
    <span class="sr-only">{{ __('composants.skeleton_chargement_en_cours_points') }}</span>
</div>
