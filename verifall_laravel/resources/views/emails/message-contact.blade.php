<x-mail::message>
# Nouveau message de contact

**{{ $message->name }}** &lt;{{ $message->email }}&gt;
@if ($message->organisation)
Organisation : {{ $message->organisation }}
@endif
@if ($message->phone)
Téléphone : {{ $message->phone }}
@endif

Reçu le {{ $message->created_at?->translatedFormat('j F Y à H:i') }}.

---

{{ $message->message }}

---

<x-mail::button :url="route('staff.contact-messages')">
Ouvrir dans le back-office
</x-mail::button>

{{-- Répondre directement à ce courriel écrit à l'expéditeur : l'en-tête
     `Reply-To` porte son adresse. --}}
Vous pouvez répondre directement à ce message.

{{ config('app.name') }}
</x-mail::message>
