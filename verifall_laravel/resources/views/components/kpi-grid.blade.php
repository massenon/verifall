@props([
    // 'citizen' | 'staff' — les deux design systems du prototype ont un
    // vocabulaire de classes distinct, la structure est identique.
    'variant' => 'citizen',
    // Libellé du bouton d'accordéon, visible sur mobile uniquement.
    'title' => null,
    'cols' => 4,
])

@php
    $staff = $variant === 'staff';
    // Exigence PAGES_KPIS_UX_MOBILE_DESKTOP : l'accordéon KPI est présent sur
    // TOUTES les pages du projet, sans exception. Il est donc porté par le
    // composant lui-même et non laissé à l'appelant, qui l'oublierait.
    $heading = $title ?? ($staff ? "Vue d'ensemble" : 'Aperçu de votre activité');
    $grid = match ((int) $cols) {
        2 => 'grid-cols-2',
        3 => 'grid-cols-2 lg:grid-cols-3',
        default => 'grid-cols-2 lg:grid-cols-4',
    };
@endphp

<section {{ $attributes->merge(['class' => 'mb-6']) }} data-kpi-accordion>
    <button type="button"
            class="kpi-toggle w-full items-center justify-between {{ $staff ? 'st-card' : 'card' }} px-4 py-3 mb-3"
            aria-expanded="false">
        <span class="{{ $staff ? 'text-sm font-display font-bold text-[#0F172A]' : 'font-display font-bold text-ink' }}">
            {{ $heading }}
        </span>
        <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>
    </button>

    <div class="kpi-grid grid {{ $staff ? $grid : 'sm:'.str_replace('grid-cols-2', 'grid-cols-2', $grid) }} gap-4">
        {{ $slot }}
    </div>
</section>
