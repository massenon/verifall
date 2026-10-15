<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page: Contact
|--------------------------------------------------------------------------
|
| The email addresses stay exactly as they are: they are addresses, not words.
| Their HTML entity for the at sign (&#64;) is kept too, since it is what
| keeps them out of the simplest address harvesters.
|
| The honeypot label is translated like any other, and for a precise reason:
| it is read aloud by screen readers, and a French sentence dropped into an
| otherwise English page would be read in the wrong voice.
|
*/

return [
    'contactez_lequipe_verifall' => 'Contact the Vérif’All team',

    'votre_voix_est_importante_pour_nous_pour' => 'Your voice matters to us. To help us answer you properly, please pick the channel that fits best:',

    'pour_les_partenariats_amp_medias_utilisez_le' => '<strong class="text-heading">For partnerships &amp; media:</strong> use the form opposite, or write to <a href="mailto:partenariats&#64;verifall.com" class="text-primary font-medium">partenariats&#64;verifall.com</a>.',

    'pour_les_questions_amp_suggestions_rejoignez_la' => '<strong class="text-heading">For questions &amp; suggestions:</strong> join the conversation on our social channels, or send us a message through the form.',

    'une_question_un_partenariat_ou_un_besoin' => 'A question, a partnership or something specific? Write to us at <a href="mailto:contact&#64;verifall.com" class="text-primary font-medium">contact&#64;verifall.com</a>',

    'pour_signaler_une_information' => 'To report a piece of information:',

    'veuillez_utiliser_notre' => 'Please use our dedicated',

    'outil_de_verification' => 'checking tool',

    'dedie' => '.',

    'suivez_nous' => 'Follow us:',

    'ph_votre_nom' => 'Your name',

    'ph_nom_exemple_com' => 'name@example.com',

    'ph_nom_de_l_organisation' => 'Organisation name',

    // Le piège à robots. Traduit parce qu'un lecteur d'écran le lit à voix
    // haute, et qu'une phrase française dans une page anglaise s'y entend.
    'ne_remplissez_pas_ce_champ' => 'Do not fill in this field',

    'nom_complet' => 'Full name *',
    'adresse_e_mail' => 'Email address *',
    'telephone' => 'Phone',
    'organisation' => 'Organisation',
    'votre_message' => 'Your message *',
    'envoyer_le_message' => 'Send the message',
];
