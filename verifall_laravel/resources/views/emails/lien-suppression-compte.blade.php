<x-mail::message>
# Confirmer la suppression de votre compte

@if($prenom){{ $prenom }}, nous @else Nous @endif avons reçu une demande de suppression
pour le compte associé à cette adresse.

Si vous êtes bien à l'origine de cette demande, cliquez sur le bouton ci-dessous.
Votre compte sera fermé immédiatement, et vos données personnelles effacées définitivement
au bout de 30 jours — délai pendant lequel vous pouvez encore annuler.

<x-mail::button :url="$lienConfirmation" color="error">
Confirmer la suppression
</x-mail::button>

Ce lien est **personnel** et expire dans **24 heures**. Passé ce délai, aucune
action ne sera prise : votre compte reste intact.

**Vous n'avez pas fait cette demande ?** Ne cliquez pas sur ce lien et ignorez
ce message. Votre compte ne sera pas affecté. Si vous pensez que quelqu'un a eu
accès à votre adresse e-mail, changez votre mot de passe par précaution.

Merci de la confiance que vous nous avez accordée,<br>
L'équipe Vérif'All — la boussole contre la désinformation numérique
</x-mail::message>
