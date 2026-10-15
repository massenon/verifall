@include('errors.layout', [
    'code' => 403,
    'titre' => 'Accès non autorisé',
    'message' => "Votre compte n'a pas les droits nécessaires pour consulter cette page. Si vous pensez qu'il s'agit d'une erreur, contactez un administrateur.",
    'actions' => ['Retour à l’accueil' => '/'],
])
