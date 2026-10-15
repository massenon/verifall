@include('errors.layout', [
    'code' => 503,
    'titre' => 'Maintenance en cours',
    'message' => "Vérif'All est momentanément indisponible le temps d'une mise à jour. Le service revient dans quelques minutes.",
    'actions' => ['Retour à l’accueil' => '/'],
])
