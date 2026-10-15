<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: community notes on a thread
|--------------------------------------------------------------------------
|
| ── THE BRIDGING RULE MUST SURVIVE TRANSLATION INTACT ────────────────────
|
| "judged useful by people who do not usually vote the same way" is not a
| turn of phrase: it is the publication criterion itself. A note is shown
| because it convinced BOTH sides, not because it collected the most votes.
| Translating it as "judged useful by the community" would describe a
| popularity contest, which is precisely the mechanism this one replaces.
|
| Three more sentences are load-bearing and are kept as plain statements:
|
|   - the notes do not commit Vérif'All (they are not our verdict);
|   - a note without a source is just one more opinion, and will be refused;
|   - reporting removes nothing automatically, a human decides.
|
| The last one matters most. A reporting button that silently hid content
| would hand any organised group a veto over the others.
|
*/

return [
    'contexte_ajoute_par_la_communaute' => 'Context added by the community',

    'ces_notes_ont_ete_jugees_utiles_par' => 'These notes were judged useful by people who do not usually vote the same way. They do not commit Vérif\'All.',

    'compte_supprime' => 'Deleted account',
    'utile' => 'Helpful',
    'pas_utile' => 'Not helpful',

    'votre_note_sur_ce_sujet' => 'Your note on this thread',
    'ajouter_du_contexte' => 'Add context',

    'une_note_courte_avec_au_moins_une' => 'A short note, with at least one source. It will only be published if people who disagree with each other both find it useful.',

    'proposer_une_note' => 'Propose a note',
    'votre_note' => 'Your note',
    'ce_que_le_sujet_omet_et_ce' => 'What the thread leaves out, and what puts it right.',
    'sources_une_adresse_web_par_ligne' => 'Sources, one web address per line',

    'une_note_sans_source_n_est_qu' => 'A note without a source is just one more opinion: it will be refused.',

    'annuler' => 'Cancel',
    'enregistrer_la_note' => 'Save the note',

    'votre_reputation' => 'Your standing:',
    'dementis_nes_de_vos_signalements' => ':count debunk came from your reports|:count debunks came from your reports',
    'signalements_retenus' => ':count report upheld|:count reports upheld',
    'notes_publiees' => ':count note published|:count notes published',
    'notes_a_evaluer' => ':count note to review|:count notes to review',
    'elles_ne_sont_pas_publiees_votre_avis' => 'They are not published. Your view, set against everyone else\'s, will decide their fate.',
    'cette_note_vous_aide_t_elle_a' => 'Does this note help you understand the thread?',

    'signaler_cette_note' => 'Report this note',

    'un_moderateur_l_examinera_le_signalement_n' => 'A moderator will look at it. Reporting removes nothing automatically: a human decides.',

    'motif' => 'Reason',
    'choisir_un_motif' => 'Choose a reason…',
    'envoyer_le_signalement' => 'Send the report',
];
