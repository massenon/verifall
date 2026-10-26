@props([
    'variant' => 'citizen',
    'icon' => 'inbox',
    'title' => 'Rien à afficher pour le moment',
    // Le vide n'est jamais une impasse : DESIGN_SYSTEM impose un CTA de sortie.
    'description' => null,
])

@php $staff = $variant === 'staff'; @endphp

<div {{ $attributes->merge(['class' => ($staff ? 'carte' : 'card').' text-center px-6 py-12']) }}>
    <x-lucide :name="$icon" class="{{ $staff ? 'w-8 h-8 mx-auto text-encre-3 dark:text-nuit-3' : 'w-4 h-4 text-slate2/50' }}" />

    <h3 class="mt-3 font-display font-bold {{ $staff ? 'text-encre dark:text-white' : 'text-ink' }}">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 text-sm {{ $staff ? 'text-encre-2 dark:text-nuit-2' : 'text-slate2' }}">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
