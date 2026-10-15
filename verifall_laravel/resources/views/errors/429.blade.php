@include('errors.layout', [
    'code' => 429,
    'titre' => 'Doucement ! Réessayez dans un instant.',
    'message' => "Trop de requêtes ont été envoyées depuis votre appareil. Cette limite protège le service et vos unités. Patientez quelques secondes avant de recommencer.",
    'actions' => ['Retour à l’accueil' => '/'],
])
