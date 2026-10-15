<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my exercises
|--------------------------------------------------------------------------
|
| Three keys hold nothing but a ligature name: `format_list_bulleted`,
| `format_list_numbered` and `link`. They are the editor's toolbar ICONS, not
| words, and they are identical in both files on purpose.
|
| The attachment label carries its own `<input type="file">` with the Livewire
| binding. The markup is reproduced exactly: only the visible words change.
| The accepted formats and the 10 MB ceiling are stated because a limit
| discovered on rejection is a limit stated too late.
|
*/

return [
    'mes_exercices' => 'My exercises',
    'aucun_exercice_pour_le_moment' => 'No exercise for now.',
    'votre_reponse' => 'Your answer',

    'format_list_bulleted' => 'format_list_bulleted',
    'format_list_numbered' => 'format_list_numbered',
    'link' => 'link',

    'attach_file_joindre_un_fichier_pdf_zip' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">attach_file</span> Attach a file (PDF / ZIP / image · 10 MB max) <input type="file" class="sr-only" wire:model="piece" accept=".pdf,.zip,image/*">',

    'annuler' => 'Cancel',
    'soumettre' => 'Submit',

    'fil_d_ariane' => 'Breadcrumb',
    'exercices' => 'Exercises',
    'exercice' => 'Exercise',

    'vos_travaux_pratiques_corriges_par_un_mentor' => 'Your practical work, marked by a mentor.',
    'a_faire_ensuite' => 'Next up:',
    'min_texte_riche_fichier_pdf_zip_image' => 'min · rich text + PDF/ZIP/image file',

    'inscrivez_vous_au_cours' => '🔒 Enrol on the course',
    'faire_l_exercice' => 'Do the exercise',

    // Toolbar labels. The B / I / U letters stay: they are the same
    // abbreviations in English, and screen readers read these labels instead.
    'mise_en_forme' => 'Formatting',
    'gras' => 'Bold',
    'italique' => 'Italic',
    'souligne' => 'Underline',
    'liste' => 'List',
    'liste_numerotee' => 'Numbered list',
    'lien' => 'Link',

    'structurez_votre_analyse' => 'Structure your analysis: claims, primary sources, dates, conclusion…',
    'reponse_a_l_exercice' => 'Answer to the exercise',
];
