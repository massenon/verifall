{{--
  | La page servie par le service worker quand le réseau manque ET que la page
  | demandée n'a jamais été consultée sur cet appareil.
  |
  | Elle réemploie le gabarit des pages d'erreur : c'est le même moment pour la
  | personne — quelque chose n'a pas marché, et il faut lui dire quoi faire.
  | Elle ne promet RIEN sur le retour du réseau : personne ici ne le sait.
--}}
@include('errors.layout', [
    'code' => __('commun.hors_ligne.code'),
    'titre' => __('commun.hors_ligne.titre'),
    'message' => __('commun.hors_ligne.message'),
    'actions' => [__('commun.hors_ligne.retour_accueil') => '/'],
])
