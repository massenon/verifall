<x-mail::message>
@if($prenom)
Bonjour {{ $prenom }},
@else
Bonjour,
@endif

{{-- Purifié à l'écriture (voir `Infolettre::diffuser()`) ET ici.
  |
  | Deux fois, et ce n'est pas une redondance oubliée : la purification à
  | l'écriture protège la base, celle-ci protège le message. Un courriel part
  | hors de l'application, là où plus rien ne le rattrape. --}}
<div>{!! clean($corps) !!}</div>

<x-mail::button :url="route('public.facts')">
Voir tous les fact-checks
</x-mail::button>

L'équipe {{ $nomDuSite }} — la boussole contre la désinformation numérique

<x-mail::subcopy>
Vous recevez ce message parce que vous êtes abonné à l'infolettre de {{ $nomDuSite }}.
[Se désabonner en un clic]({{ $lienDeDesabonnement }})
</x-mail::subcopy>
</x-mail::message>
