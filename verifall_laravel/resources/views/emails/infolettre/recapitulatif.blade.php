<x-mail::message>
# Les intox de la semaine

@if($prenom)Bonjour {{ $prenom }},@else Bonjour,@endif

Voici ce que l'équipe a vérifié **{{ $periode }}**. Chaque titre renvoie au
fact-check complet, avec ses sources.

@foreach ($articles as $article)
### {{ $article->libelleDuBandeau() }} — {{ $article->title }}

{{ $article->excerpt }}

[Lire le fact-check]({{ route('public.fact', ['article' => $article->slug]) }})

@endforeach

{{-- Aucun chiffre d'audience, aucune « intox la plus partagée » : rien ici
     n'est mesuré, donc rien ici ne se chiffre. Ce qui est affiché est ce qui
     a été publié, et rien d'autre. --}}

<x-mail::button :url="route('public.facts')">
Voir tous les fact-checks
</x-mail::button>

Une rumeur vous parvient sur WhatsApp ? Envoyez-la nous : c'est souvent comme
cela que commence une vérification.

À lundi prochain,<br>
L'équipe {{ $nomDuSite }} — la boussole contre la désinformation numérique

<x-mail::subcopy>
Vous recevez ce message parce que vous avez confirmé votre abonnement à l'infolettre de {{ $nomDuSite }}.
[Se désabonner en un clic]({{ $lienDeDesabonnement }}) — sans mot de passe et sans connexion.
</x-mail::subcopy>
</x-mail::message>
