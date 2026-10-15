<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, forum
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
    'community_fact_checking_hub' => 'Community Fact-Checking Hub',
    'verif_all_forum' => 'Vérif\'All Forum',
    'que_souhaitez_vous_partager_avec_la_communaute' => 'Que souhaitez-vous partager avec la communauté ?',
    'titre' => 'Titre',
    'votre_message' => 'Votre message',
    'rubrique' => 'Rubrique',
    'je_signale_une_possible_intox_la_communaute' => '<input type="checkbox" wire:model.live="signalementFakeNews" class="mt-0.5 w-4 h-4 accent-[var(--vf-primary)]"> <span class="text-sm"> <strong class="text-ink">Je signale une possible intox</strong> <span class="block text-slate2 text-xs">La communauté pourra voter, et l\'équipe pourra transmettre au laboratoire.</span> </span>',
    'lien_de_la_source' => 'Lien de la source',
    'j_ajoute_un_sondage_une_question_deux' => '<input type="checkbox" wire:model.live="avecSondage" class="mt-0.5 w-4 h-4 accent-[var(--vf-primary)]"> <span class="text-sm"> <strong class="text-ink">J\'ajoute un sondage</strong> <span class="block text-slate2 text-xs">Une question, deux à six réponses. Chacun ne vote qu\'une fois.</span> </span>',
    'votre_question' => 'Votre question',
    'add_ajouter_une_reponse' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">add</span> Ajouter une réponse',
    'annuler' => 'Annuler',
    'publier_send' => 'Publier <span class="material-symbols-rounded !text-[17px]" aria-hidden="true">send</span>',
    'publier_un_sujet' => 'Publier un sujet',
    'tendances' => 'Tendances',
    'rumeurs_en_tendance' => '🔥 Rumeurs en tendance',
    'communaute' => 'Communauté',
    'contributeurs' => '🏆 Contributeurs',
    'laboratoire' => 'Laboratoire',
    'dernieres_verifications' => '✓ Dernières vérifications',
    'verdicts_officiels_du_laboratoire_verif_all_distincts' => 'Verdicts officiels du laboratoire Vérif\'All — distincts des avis de la communauté.',

    /*
     | Second passage : les libelles que l'extracteur avait sautes parce que
     | leur balise portait un attribut dynamique (wire:click, {{ }} dans la
     | classe). Ce sont surtout des boutons — ils restaient francais en
     | anglais.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'la_communaute_qui_verifie_l_information_ensemble' => 'La communauté qui vérifie l\'information ensemble.',
    'rubriques_du_forum' => 'Rubriques du forum',
    'toutes' => 'Toutes',
    'nouvelle_discussion' => 'Nouvelle discussion',
    'cette_video_montre_t_elle_reellement' => 'Cette vidéo montre-t-elle réellement… ?',
    'ou_l_avez_vous_vu_qu_est_ce_qui_vous_fait_douter' => 'Où l\'avez-vous vu ? Qu\'est-ce qui vous fait douter ?',
    'sans_rubrique' => 'Sans rubrique',
    'avez_vous_deja_recu_ce_message_sur_whatsapp' => 'Avez-vous déjà reçu ce message sur WhatsApp ?',
    'reponse_n' => 'Réponse',
    'retirer_la_reponse' => 'Retirer la réponse',
    'rien_dans_cette_rubrique' => 'Rien dans cette rubrique',
    'le_forum_est_encore_vide' => 'Le forum est encore vide',
    'choisissez_une_autre_rubrique_ou_lancez_la' => 'Choisissez une autre rubrique, ou lancez la discussion.',
    'signalez_une_rumeur_votre_sujet_sera_le' => 'Signalez une rumeur : votre sujet sera le premier.',

    /*
     | Les compteurs employaient `Str::plural()`, qui accorde en anglais et ne
     | traduit rien. `trans_choice` accorde dans la langue affichee.
     */
    'n_discussions' => 'discussion|discussions',
    'n_membres' => 'membre|membres',
    'n_verifications' => 'vérification|vérifications',
    'n_reponses' => 'réponse|réponses',
];
