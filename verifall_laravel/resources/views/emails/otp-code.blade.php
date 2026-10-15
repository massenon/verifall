<x-mail::message>
{{-- Le texte d'aperçu de la boîte de réception (§38 de la directive courriel).
     Il COMPLÈTE l'objet au lieu de le répéter : l'objet annonce un code, le
     preheader dit combien de temps il vaut. C'est l'information qui décide si
     l'on ouvre maintenant ou plus tard.

     Le code lui-même n'y figure PAS, et c'est délibéré : l'aperçu s'affiche
     sur un écran verrouillé, en notification, sans que personne ait eu à
     déverrouiller le téléphone. --}}
<x-slot:preheader>Votre code est valable {{ $minutes }} minutes et ne sert qu'une fois.</x-slot:preheader>

# Votre code de vérification

Voici le code à saisir pour {{ $purpose === \App\Enums\OtpPurpose::Registration ? 'activer votre compte' : 'poursuivre' }} :

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

Ce code est valable **{{ $minutes }} minutes** et ne peut servir qu'une seule fois.

Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : aucun
changement ne sera effectué sur votre compte.

{{-- Le courriel ne contient AUCUN lien de connexion automatique : un lien
     cliquable transformerait une boîte mail compromise en accès au compte. --}}

Merci de votre confiance,<br>
L'équipe Vérif'All — la boussole contre la désinformation numérique
</x-mail::message>
