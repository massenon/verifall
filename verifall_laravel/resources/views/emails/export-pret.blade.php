<x-mail::message>
@if($echec)
# Votre export n'a pas abouti

Nous n'avons pas pu constituer votre archive. Aucune donnée n'a été perdue :
relancez la demande depuis vos réglages, et si l'échec se répète, écrivez-nous.

@else
# Votre export est prêt

Votre archive contient l'ensemble des données que nous conservons à votre sujet,
en JSON et en CSV.

<x-mail::button :url="$lien">
Télécharger mon archive
</x-mail::button>

Ce lien est **personnel** et expire dans **{{ $heures }} heures**. Passé ce délai,
l'archive est détruite ; vous pourrez en demander une nouvelle à tout moment.

{{-- L'archive n'est PAS jointe : un courriel se transfère, se relaie et se
     conserve sur des serveurs que nous ne maîtrisons pas. Un lien de courte
     durée limite la fenêtre pendant laquelle ces données circulent. --}}

Le fichier rassemble en un seul endroit tout ce qui vous concerne : conservez-le
avec la même prudence qu'une pièce d'identité.

@endif
Merci de votre confiance,<br>
L'équipe Vérif'All — la boussole contre la désinformation numérique
</x-mail::message>
