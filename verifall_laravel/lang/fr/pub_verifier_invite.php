<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, « Verifiez avant de partager » (visiteur sans compte)
|--------------------------------------------------------------------------
|
| Transposition de verifier-une-information.html. Le formulaire RESTE a
| l'ecran et le resultat s'affiche a sa suite.
|
| ── LES PLURIELS ─────────────────────────────────────────────────────────
| Trois cles passent par `trans_choice()` : `verifications_gratuites_restantes`,
| `il_vous_reste_verifications_gratuites` et `dans_minutes`. La vue accordait
| a la main, en repetant `{{ $n > 1 ? 's' : '' }}` sur CHAQUE mot de la
| chaine — « verification/s gratuite/s restante/s ». Le procede ne survit pas
| a la traduction : l'anglais n'accorde pas l'adjectif, et « remaining » ne
| prend jamais de « s ». Chaque langue porte donc ses deux formes completes.
|
| ── CE QUI EST DIT, ET POURQUOI ──────────────────────────────────────────
| `analyse_mise_en_attente` annonce une ATTENTE, jamais un echec. Rendre
| « verdict indisponible » a quelqu'un qui decouvre le produit reviendrait a
| lui dire que la plateforme ne marche pas, alors qu'elle attend seulement que
| les moteurs se liberent.
|
| Les cinq puces de `le_dossier_complet_vous_attend` nomment PRECISEMENT ce
| qui reste derriere l'inscription. On ne cache pas la REPONSE, seulement le
| dossier qui la soutient : une promesse vague laisserait croire qu'on retient
| le verdict, ce qui serait a la fois faux et deloyal.
|
| `sans_compte_lien_ou_texte_uniquement` est dit FRANCHEMENT plutot que
| laisse a deviner : personne ne doit chercher un bouton d'envoi de fichier
| qui n'existe pas sur cette page.
|
*/

return [
    'verifiez_avant_de' => 'Vérifiez avant de',
    'partager' => 'partager',
    'collez_un_lien_ou_un_texte_pour' => 'Collez un lien ou un texte pour une analyse instantanée :',
    'faux_deces_faux_coups_d_etat_faux' => 'faux décès, faux coups d\'État, faux recrutements, arnaques santé…',
    'type_de_contenu_a_verifier' => 'Type de contenu à vérifier',
    'lien_url' => 'Lien URL',
    'texte_message' => 'Texte / Message',
    'collez_un_url_douteux' => 'Collez un url douteux *',
    'collez_le_message_douteux' => 'Collez le message douteux *',
    'copiez_ici_le_message_whatsapp_la_publication' => 'Copiez ici le message WhatsApp, la publication ou la rumeur…',
    'lancer_la_verification_gratuite' => 'Lancer la vérification gratuite',
    'analyse_lancee' => 'Analyse lancée…',
    'verifications_gratuites_restantes' => ':count vérification gratuite restante, sans inscription.|:count vérifications gratuites restantes, sans inscription.',
    'vous_avez_utilise_vos_verifications_gratuites' => 'Vous avez utilisé vos vérifications gratuites.',
    'creez_un_compte' => 'Créez un compte',
    'pour_continuer' => 'pour continuer.',
    'sans_compte_lien_ou_texte_uniquement' => 'Sans compte : lien ou texte uniquement.',
    'l_analyse_d_images_de_videos_et_d' => 'L\'analyse d\'images, de vidéos et d\'audio demande',
    'un_compte' => 'un compte',
    'reception_du_contenu' => 'Réception du contenu',
    'votre_soumission_est_enregistree' => 'Votre soumission est enregistrée.',
    'analyse_mise_en_attente' => 'Analyse mise en attente',
    'plutot_que_de_vous_rendre_un_verdict' => 'Plutôt que de vous rendre un verdict que rien n\'appuie, nous reprenons l\'analyse',
    'des_maintenant' => 'dès maintenant.',
    'dans_minutes' => 'dans :count minute.|dans :count minutes.',
    'revenez_sur_cette_page_dans_une_dizaine' => 'Revenez sur cette page dans une dizaine de minutes : votre demande vous y attendra.',
    'relancer_maintenant' => 'Relancer maintenant',
    'relance' => 'Relance…',
    'l_analyse_prend_plus_de_temps_que' => 'L\'analyse prend plus de temps que prévu.',
    'un_moteur_ne_repond_peut_etre_pas' => 'Un moteur ne répond peut-être pas.',
    'reessayer' => 'Réessayer',
    'ne_fermez_pas_cette_page_sans_compte' => 'Ne fermez pas cette page : sans compte, votre rapport ne sera pas conservé.',
    'indice_de_fiabilite' => 'Indice de fiabilité',
    'le_dossier_complet_vous_attend' => 'Le dossier complet vous attend',
    'les_moteurs_mobilises_et_l_avis_de' => 'les :nombre moteurs mobilisés et l\'avis de chacun',
    'les_sources_consultees_avec_leur_position_et' => 'les :nombre sources consultées, avec leur position et leur fiabilité',
    'l_indice_de_discours_haineux_du_contenu' => 'l\'indice de discours haineux du contenu soumis',
    'l_analyse_des_images_videos_et_fichiers_audio' => 'l\'analyse des images, vidéos et fichiers audio',
    'l_historique_de_vos_verifications_et_le_rapport' => 'l\'historique de vos vérifications, et le rapport en PDF',
    'creer_un_compte_gratuit' => 'Créer un compte gratuit',
    'il_vous_reste_verifications_gratuites' => 'Il vous reste :count vérification gratuite :|Il vous reste :count vérifications gratuites :',
    'le_formulaire_est_reste_en_haut_de' => 'le formulaire est resté en haut de page.',
    'besoin_d_analyse_complete' => 'Besoin d\'analyse complète ?',
    'en_creant_un_compte_vous_debloquez_l' => 'En créant un compte, vous débloquez l\'analyse des images, vidéos et fichiers audio, et l\'accès aux outils de fact-checking.',
    'creer_un_compte' => 'Créer un compte',
];
