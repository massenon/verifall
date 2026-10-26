@props(['ton' => 'neutre', 'icone' => null])
{{-- Pastille de statut. Le ton n'est jamais la seule information : le texte la dit aussi. --}}
@php($classe = ['bon' => 'p-bon', 'alerte' => 'p-alerte', 'grave' => 'p-grave', 'critique' => 'p-critique', 'info' => 'p-info', 'neutre' => 'p-neutre'][$ton] ?? 'p-neutre')
<span {{ $attributes->class($classe) }}>@if ($icone)<x-lucide :name="$icone" />@endif{{ $slot }}</span>
