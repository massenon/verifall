@include('errors.layout', [
    'code' => 404,
    'titre' => "Cette page s'est égarée… mais pas vous.",
    'message' => "Le lien que vous avez suivi ne mène nulle part. Reprenez depuis l'accueil, ou lancez directement une vérification.",
    'actions' => [
        'Retour à l’accueil' => '/',
        'Vérifier une information' => '/mon-espace/verifier',
    ],
])
