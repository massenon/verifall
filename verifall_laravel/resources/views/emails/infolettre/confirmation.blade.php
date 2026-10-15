<x-mail::message>
# Confirmez votre abonnement

@if($prenom)Bonjour {{ $prenom }},@else Bonjour,@endif

Vous — ou quelqu'un qui a saisi cette adresse — souhaitez recevoir chaque lundi
le récapitulatif des pires intox détectées au Bénin durant la semaine.

Un clic, et c'est réglé :

<x-mail::button :url="$lien">
Confirmer mon abonnement
</x-mail::button>

{{-- DOUBLE OPT-IN : tant que ce lien n'est pas cliqué, aucune infolettre ne
     part vers cette adresse. C'est ce qui empêche d'abonner quelqu'un d'autre
     depuis un formulaire public. --}}

**Vous n'avez rien demandé ?** N'y touchez pas. Sans ce clic, vous ne recevrez
rien d'autre de notre part — cette adresse restera inactive et sera oubliée.

Si le bouton ne fonctionne pas, recopiez cette adresse dans votre navigateur :
{{ $lien }}

À lundi,<br>
L'équipe {{ $nomDuSite }} — la boussole contre la désinformation numérique

<x-mail::subcopy>
Pour ne plus jamais recevoir de message de l'infolettre, [désabonnez-vous en un clic]({{ $lienDeDesabonnement }}).
Aucun mot de passe, aucune connexion ne vous sera demandée.
</x-mail::subcopy>
</x-mail::message>
