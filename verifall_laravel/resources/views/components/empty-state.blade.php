@props([
    'variant' => 'citizen',
    'icon' => 'inbox',
    'title' => 'Rien à afficher pour le moment',
    // Le vide n'est jamais une impasse : DESIGN_SYSTEM impose un CTA de sortie.
    'description' => null,
])

@php $staff = $variant === 'staff'; @endphp

<div {{ $attributes->merge(['class' => ($staff ? 'st-card' : 'card').' text-center px-6 py-12']) }}>
    <span class="material-symbols-rounded !text-[40px] {{ $staff ? 'text-[#94A3B8]' : 'text-slate2/50' }}"
          aria-hidden="true">{{ $icon }}</span>

    <h3 class="mt-3 font-display font-bold {{ $staff ? 'text-[#0F172A]' : 'text-ink' }}">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 text-sm {{ $staff ? 'text-[#475569]' : 'text-slate2' }}">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
