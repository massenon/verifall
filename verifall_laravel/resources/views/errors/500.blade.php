@include('errors.layout', [
    'code' => 500,
    'titre' => 'Nos équipes sont dessus',
    'message' => "Une erreur technique est survenue de notre côté. Elle a été signalée automatiquement. Réessayez dans quelques minutes.",
    'actions' => ['Retour à l’accueil' => '/'],
])
