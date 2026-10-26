@props([
    'variant' => 'citizen',
    'value' => null,
    'label' => null,
    // Ligne de contexte sous le libellé (tendance, période…). Staff uniquement
    // dans le prototype : le KPI citoyen n'en a pas.
    'trend' => null,
])

@php $staff = $variant === 'staff'; @endphp

<div {{ $attributes->merge(['class' => $staff ? 'carte p-4' : 'kpi-card']) }}>
    <p class="{{ $staff ? 'font-titre text-[26px] leading-none font-semibold tabular-nums text-encre dark:text-white' : 'kpi-value' }}">{{ $value ?? $slot }}</p>
    <p class="{{ $staff ? 'mt-2 text-[13px] texte-2' : 'kpi-label' }}">{{ $label }}</p>
    @if ($trend && $staff)
        <p class="mt-1 text-xs texte-3">{{ $trend }}</p>
    @endif
</div>
