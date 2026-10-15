<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: the forum
|--------------------------------------------------------------------------
|
| ── THE LINE THE FORUM DEPENDS ON ────────────────────────────────────────
|
| "Official verdicts from the Vérif'All laboratory, separate from community
| opinions." That sentence is the whole reason the forum can sit next to a
| fact-checking platform without damaging it. A reader who mistakes a popular
| thread for a published verdict has been misled by the layout, not by a
| falsehood. The English keeps the separation explicit.
|
| Two labels carry their own checkbox with the Livewire binding. The markup is
| reproduced exactly; only the visible words change. Each one states what the
| box actually causes: a report lets the community vote AND lets the team pass
| it to the laboratory; a poll allows one vote per person.
|
| "Community Fact-Checking Hub" is already English in the French file and
| stays. "Vérif'All Forum" is a name.
|
*/

return [
    'community_fact_checking_hub' => 'Community Fact-Checking Hub',
    'verif_all_forum' => 'Vérif\'All Forum',

    'que_souhaitez_vous_partager_avec_la_communaute' => 'What would you like to share with the community?',

    'titre' => 'Title',
    'votre_message' => 'Your message',
    'rubrique' => 'Section',

    'je_signale_une_possible_intox_la_communaute' => '<input type="checkbox" wire:model.live="signalementFakeNews" class="mt-0.5 w-4 h-4 accent-[var(--vf-primary)]"> <span class="text-sm"> <strong class="text-ink">I am reporting a possible hoax</strong> <span class="block text-slate2 text-xs">The community will be able to vote, and the team will be able to pass it to the laboratory.</span> </span>',

    'lien_de_la_source' => 'Link to the source',

    'j_ajoute_un_sondage_une_question_deux' => '<input type="checkbox" wire:model.live="avecSondage" class="mt-0.5 w-4 h-4 accent-[var(--vf-primary)]"> <span class="text-sm"> <strong class="text-ink">I am adding a poll</strong> <span class="block text-slate2 text-xs">One question, two to six answers. Each person votes only once.</span> </span>',

    'votre_question' => 'Your question',

    'add_ajouter_une_reponse' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">add</span> Add an answer',

    'annuler' => 'Cancel',
    'publier_send' => 'Publish <span class="material-symbols-rounded !text-[17px]" aria-hidden="true">send</span>',
    'publier_un_sujet' => 'Start a thread',

    'tendances' => 'Trending',
    'rumeurs_en_tendance' => '🔥 Trending rumours',
    'communaute' => 'Community',
    'contributeurs' => '🏆 Contributors',
    'laboratoire' => 'Laboratory',
    'dernieres_verifications' => '✓ Latest checks',

    'verdicts_officiels_du_laboratoire_verif_all_distincts' => 'Official verdicts from the Vérif\'All laboratory, separate from community opinions.',

    /*
     | Second pass: the labels the extractor skipped because their tag carried
     | a dynamic attribute. Mostly buttons — they stayed French in English.
     |
     | The composer placeholders are questions on purpose. "Where did you see
     | it?" asks for the one thing a moderator needs and a rumour rarely has:
     | a source. A neutral "Your message" would have collected less.
     */
    'fil_d_ariane' => 'Breadcrumb',
    'la_communaute_qui_verifie_l_information_ensemble' => 'The community that checks information together.',
    'rubriques_du_forum' => 'Forum sections',
    'toutes' => 'All',
    'nouvelle_discussion' => 'New discussion',
    'cette_video_montre_t_elle_reellement' => 'Does this video really show… ?',
    'ou_l_avez_vous_vu_qu_est_ce_qui_vous_fait_douter' => 'Where did you see it? What makes you doubt it?',
    'sans_rubrique' => 'No section',
    'avez_vous_deja_recu_ce_message_sur_whatsapp' => 'Have you already received this message on WhatsApp?',
    'reponse_n' => 'Answer',
    'retirer_la_reponse' => 'Remove answer',
    'rien_dans_cette_rubrique' => 'Nothing in this section',
    'le_forum_est_encore_vide' => 'The forum is still empty',
    'choisissez_une_autre_rubrique_ou_lancez_la' => 'Pick another section, or start the discussion.',
    'signalez_une_rumeur_votre_sujet_sera_le' => 'Report a rumour: yours will be the first thread.',

    /*
     | The counters used `Str::plural()`, which inflects in English and
     | translates nothing. `trans_choice` inflects in the displayed language.
     */
    'n_discussions' => 'discussion|discussions',
    'n_membres' => 'member|members',
    'n_verifications' => 'check|checks',
    'n_reponses' => 'reply|replies',
];
