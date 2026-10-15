<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, sujet
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues en syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur.
|
*/

return [
    'moderation' => 'Modération',
    'verdict_officiel' => 'Verdict officiel',
    'publier_le_verdict' => 'Publier le verdict',
    'le_verdict_officiel_engage_verif_all_il' => 'Le verdict officiel engage Vérif\'All. Il s\'affiche au-dessus de l\'avis de la communauté, et ne s\'en déduit pas.',
    'votre_avis_sur_cette_information' => 'Votre avis sur cette information',
    'c_est_un_sondage_pas_une_verification' => 'C\'est un sondage, pas une vérification : il dit ce que pense la communauté, pas ce qui est vrai.',
    'escalade_en_fact_check' => 'Escaladé en fact-check',
    'ce_signalement_a_ete_confie_a_nos' => 'Ce signalement a été confié à nos moteurs d\'analyse. Le verdict officiel apparaîtra sur ce sujet dès qu\'il sera rendu.',
    'faire_verifier_cette_information' => 'Faire vérifier cette information',
    'nos_moteurs_d_analyse_s_en_saisissent' => 'Nos moteurs d\'analyse s\'en saisissent, et le verdict officiel revient s\'afficher ici. Gratuit, et une seule fois par sujet.',
    'science_escalader_en_fact_check' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">science</span> Escalader en fact-check',
    'flag_signaler_ce_sujet' => '<button type="button" wire:click="ouvrirSignalement" class="inline-flex items-center gap-1 text-xs font-semibold text-slate2 hover:text-vfalse"> <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">flag</span> Signaler ce sujet </button>',
    'personne_n_a_encore_repondu_votre_message' => 'Personne n\'a encore répondu. Votre message ouvrira la discussion.',
    'ce_fil_est_clos' => 'Ce fil est clos',
    'la_moderation_a_mis_fin_a_la' => 'La modération a mis fin à la discussion. Le contenu reste consultable.',
    'repondre_au_sujet_plutot' => '← Répondre au sujet plutôt',
    'votre_reponse' => 'Votre réponse',
    'envoyer' => 'Envoyer',
    'un_moderateur_examinera_ce_contenu_le_signalement' => 'Un modérateur examinera ce contenu. Le signalement n\'entraîne aucun retrait automatique : un humain tranche.',
    'motif' => 'Motif',
    'precisions_facultatif' => 'Précisions (facultatif)',
    'annuler' => 'Annuler',
    'envoyer_le_signalement' => 'Envoyer le signalement',

    // Libelles restes en dur : boutons a wire:click, ternaires PHP et
    // attributs visibles, tous sautes par l'extracteur.
    'fil_d_ariane' => 'Fil d\'Ariane',
    'rouvrir_le_fil' => 'Rouvrir le fil',
    'clore_le_fil' => 'Clore le fil',
    'desepingler' => 'Désépingler',
    'epingler' => 'Épingler',
    'choisir' => 'Choisir…',
    'votre_avis' => 'Votre avis',
    'vous_avez_repondu' => 'Vous avez répondu',
    'recliquez_pour_retirer_votre_avis' => 'Recliquez pour retirer votre avis.',

    'escalade_par' => 'Escaladé par',
    'le' => 'Le',
    'verification' => 'Vérification',

    'compte_reponses' => ':nombre réponse|:nombre réponses',
    'compte_supprime' => 'Compte supprimé',
    'reponse_officielle' => 'Réponse officielle',
    'repondre' => 'Répondre',
    'signaler_cette_reponse' => 'Signaler cette réponse',

    'votre_reponse_a_ce_message' => 'Votre réponse à ce message',
    'repondre_au_sujet' => 'Répondre au sujet',
    'apportez_un_element_une_source_une_nuance' => 'Apportez un élément, une source, une nuance…',

    'signaler' => 'Signaler',
    'ce_sujet' => 'ce sujet',
    'cette_reponse' => 'cette réponse',
    'choisir_un_motif' => 'Choisir un motif…',
    'ce_qui_vous_semble_problematique' => 'Ce qui vous semble problématique',
];
