<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page /supprimer-mon-compte
|--------------------------------------------------------------------------
|
| Page publique décrivant la procédure de suppression de compte, les données
| effacées et conservées, et le délai de rétractation.
|
| La clé :jours est remplacée par SuppressionCompteService::JOURS_RETRACTATION
| pour éviter de coder la valeur en dur.
|
*/

return [
    'titre_page' => 'Supprimer mon compte',
    'derniere_mise_a_jour' => 'Dernière mise à jour :',

    'intro' => 'Vérif\'All garantit votre droit à l\'effacement, prévu par la loi béninoise n° 2017-20 portant code du numérique. Vous pouvez supprimer votre compte à tout moment, depuis l\'application, depuis le site web, ou en nous écrivant si vous n\'avez plus accès à votre compte.',

    '1_depuis_l_application' => '1. Depuis l\'application mobile',
    'depuis_l_application_intro' => 'Sur iOS et Android, la procédure de suppression se trouve dans les réglages du compte :',
    'depuis_l_app_etape_1' => 'Ouvrez l\'application et accédez à vos <strong>Réglages</strong>.',
    'depuis_l_app_etape_2' => 'Appuyez sur <strong>Mon compte</strong>, puis sur <strong>Supprimer mon compte</strong>.',
    'depuis_l_app_etape_3' => 'Saisissez votre mot de passe et tapez <strong>SUPPRIMER</strong> dans le champ de confirmation.',
    'depuis_l_app_etape_4' => 'Un code est envoyé à votre adresse e-mail. Saisissez-le pour valider.',
    'depuis_l_app_resultat' => 'Votre compte est fermé immédiatement. Vos données personnelles sont anonymisées au terme d\'un délai de :jours jours (voir ci-dessous).',

    '2_depuis_le_site_web' => '2. Depuis le site web',
    'depuis_le_web_intro' => 'La procédure est identique depuis l\'espace citoyen :',
    'depuis_le_web_etape_1' => 'Connectez-vous et accédez à vos <strong>Réglages</strong>.',
    'depuis_le_web_etape_2' => 'Ouvrez <strong>Sécurité</strong> puis cliquez sur <strong>Supprimer le compte</strong>.',
    'depuis_le_web_etape_3' => 'Saisissez votre mot de passe et tapez <strong>SUPPRIMER</strong> dans le champ de confirmation.',
    'depuis_le_web_etape_4' => 'Un code est envoyé à votre adresse e-mail. Saisissez-le pour valider.',

    '3_ce_qui_est_efface_et_conserve' => '3. Ce qui est effacé et ce qui est conservé',
    'ce_qui_est_efface_conserve_intro' => 'La suppression n\'est pas une disparition totale de toute trace. Voici précisément ce qui se passe :',
    'fermeture_immediate' => 'Fermeture immédiate',
    'fermeture_immediate_texte' => 'Votre compte est fermé immédiatement : vous ne pouvez plus vous connecter, vos données personnelles ne sont plus accessibles.',
    'apres_jours_jours' => 'Après :jours jours',
    'apres_jours_jours_texte' => 'Vos données personnelles sont anonymisées de façon irréversible : nom, prénom, adresse e-mail, photo de profil, biographie, historique de connexion.',
    'ce_qui_est_conserve' => 'Ce qui est conservé (anonymisé)',
    'ce_qui_est_conserve_texte' => 'Vos vérifications publiées, vos contributions au Forum et vos commentaires restent visibles — leur auteur est remplacé par « Utilisateur supprimé ». Ces contenus font partie de l\'archive publique de fact-checking et ne peuvent pas être retirés.',

    '4_annuler_la_suppression' => '4. Annuler la suppression',
    'annuler_intro' => 'Vous avez :jours jours pour changer d\'avis.',
    'annuler_texte' => 'Un e-mail contenant un lien d\'annulation vous est envoyé dès la clôture de votre compte. Ce lien est valable :jours jours. Une fois le délai passé, l\'anonymisation est irréversible et votre compte ne peut plus être restauré.',

    '5_sans_acces_au_compte' => '5. Vous n\'avez plus accès à votre compte ?',
    'sans_acces_intro' => 'Si vous avez perdu l\'accès à votre compte (mot de passe oublié, appareil perdu), vous pouvez demander la suppression par e-mail. Entrez l\'adresse e-mail enregistrée sur votre compte.',
    'champ_email_label' => 'Adresse e-mail',
    'champ_email_placeholder' => 'votre@adresse.com',
    'bouton_envoyer' => 'Envoyer le lien de suppression',
    'message_confirmation' => 'Si cette adresse est associée à un compte, un lien de suppression valable 24 heures vient d\'être envoyé. Vérifiez votre boîte de réception.',
    'note_securite' => 'Ce formulaire est limité à 3 envois par heure. Si vous ne recevez pas d\'e-mail, vérifiez vos spams ou écrivez à',

    'compte_ferme_confirmation' => 'Votre compte a été fermé. Un e-mail contenant votre lien d\'annulation vient de vous être envoyé. Vous avez 30 jours pour changer d\'avis.',
    'suppression_deja_en_cours' => 'Une suppression est déjà programmée pour ce compte. Consultez votre boîte e-mail pour le lien d\'annulation.',

    'footer_confidentialite' => 'Voir aussi notre',
    'footer_lien_confidentialite' => 'politique de confidentialité',
    'footer_et' => 'et nos',
    'footer_lien_mentions' => 'mentions légales',
    'footer_point' => '.',
];
