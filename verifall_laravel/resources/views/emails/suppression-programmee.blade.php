<x-mail::message>
# Votre compte sera supprimé

@if($prenom){{ $prenom }}, votre @else Votre @endif compte Vérif'All est désormais fermé.
Vos données seront **effacées définitivement le
{{ $echeance->translatedFormat('d F Y') }}**, soit dans {{ $jours }} jours.

D'ici là, vous pouvez encore revenir en arrière.

<x-mail::button :url="$lienAnnulation" color="success">
Annuler la suppression
</x-mail::button>

{{-- Ce lien N'EST PAS une commodité : le compte étant fermé, il n'est plus
     possible de se connecter pour annuler. Il est le mécanisme de
     rétractation lui-même. --}}

Ce lien est valable jusqu'à l'échéance. Passé cette date, l'effacement est
irréversible : nous ne conserverons plus ni votre nom, ni votre adresse, ni
aucune information permettant de vous identifier.

**Vous n'êtes pas à l'origine de cette demande ?** Cliquez sur le bouton
ci-dessus pour l'annuler immédiatement, puis changez votre mot de passe : la
demande a été faite depuis une session ouverte sur votre compte.

Merci de la confiance que vous nous avez accordée,<br>
L'équipe Vérif'All — la boussole contre la désinformation numérique
</x-mail::message>
