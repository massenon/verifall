@include('errors.layout', [
    'code' => 419,
    'titre' => 'Session expirée par sécurité',
    'message' => "Votre session a été fermée après une période d'inactivité. Reconnectez-vous pour reprendre là où vous en étiez.",
    'actions' => ['Reconnecter' => '/connexion', 'Retour à l’accueil' => '/'],
])
