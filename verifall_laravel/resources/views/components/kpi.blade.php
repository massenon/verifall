@props([
    'variant' => 'citizen',
    'value' => null,
    'label' => null,
    // Ligne de contexte sous le libellé (tendance, période…). Staff uniquement
    // dans le prototype : le KPI citoyen n'en a pas.
    'trend' => null,
])

@php $staff = $variant === 'staff'; @endphp

<div {{ $attributes->merge(['class' => $staff ? 'st-kpi' : 'kpi-card']) }}>
    <p class="{{ $staff ? 'st-kpi-v' : 'kpi-value' }}">{{ $value ?? $slot }}</p>
    <p class="{{ $staff ? 'st-kpi-l' : 'kpi-label' }}">{{ $label }}</p>
    @if ($trend && $staff)
        <p class="st-kpi-t">{{ $trend }}</p>
    @endif
</div>
