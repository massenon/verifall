<x-mail::message>
# {{ $titre }}

{{-- Purifié ici : un courriel part hors de l'application, là où plus rien ne
     le rattrape. Voir le garde XSS du dépôt, qui ne fait aucune exception. --}}
{!! clean($corps) !!}

@if ($libelleDuBouton)
<x-mail::button :url="$lienDuFactCheck">
{{ $libelleDuBouton }}
</x-mail::button>
@endif

<x-mail::subcopy>
Vous recevez ce message parce que vous êtes abonné à l'infolettre de Vérif'All.
Les alertes sont rares : elles ne partent que pour une intox qui circule vite.
[Se désabonner en un clic]({{ $lienDeDesabonnement }})
</x-mail::subcopy>
</x-mail::message>
