@props([
    'variant' => 'staff',
    // Compteur « X résultat(s) » exigé au-dessus de chaque table.
    'count' => null,
    'selectable' => true,
])

@php $staff = $variant === 'staff'; @endphp

@if (! is_null($count))
    <p class="text-sm font-semibold {{ $staff ? 'text-[#0F172A]' : 'text-ink' }} mb-3">
        <span data-result-count>{{ $count }}</span> {{ __('composants.table_resultats') }}
    </p>
@endif

{{-- La table est réservée au desktop : en mobile, l'appelant fournit un
     <x-card-list>. Une table miniaturisée est explicitement proscrite. --}}
<div {{ $attributes->merge(['class' => 'hidden lg:block '.($staff ? 'st-table-wrap st-card' : 'card').' overflow-x-auto']) }}>
    <table class="{{ $staff ? 'st-table' : 'data-table' }}">
        <thead>
            <tr>
                @if ($selectable)
                    <th class="w-10">
                        <input type="checkbox" class="w-4 h-4 accent-[#059669]"
                               data-sel-all aria-label="{{ __('composants.table_tout_selectionner') }}">
                    </th>
                @endif
                {{ $head }}
            </tr>
        </thead>
        <tbody data-tbody>{{ $slot }}</tbody>
    </table>
</div>
