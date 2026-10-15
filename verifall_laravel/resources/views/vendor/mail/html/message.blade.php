<x-mail::layout>
{{-- Preheader : relayé tel quel jusqu'au gabarit de mise en page, qui le pose
     en tête de corps. Facultatif — un courriel qui n'en déclare pas n'en aura
     pas, et Gmail complètera l'aperçu avec son premier paragraphe, comme
     aujourd'hui. --}}
@isset($preheader)
<x-slot:preheader>{{ $preheader }}</x-slot:preheader>
@endisset

{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
