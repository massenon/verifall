<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| La page publique d'un rapport — `/rapport/{uuid}`
|--------------------------------------------------------------------------
|
| Ce que lit quelqu'un qui n'a pas de compte : il a scanné le QR d'une feuille
| imprimée, ou reçu le lien dans une conversation. Le ton s'adresse donc à un
| tiers, jamais à l'auteur de la demande — « votre contenu soumis » n'aurait
| ici aucun sens.
|
*/

return [
    'rapport_authentique' => "Rapport de vérification authentique, publié par Vérif'All ·",
    'un_doute_sur_une_autre_information' => 'Un doute sur une autre information ?',
    'soumettez_la_gratuitement' => 'Soumettez-la à nos moteurs de vérification. Sans compte, et gratuitement.',
    'verifier_une_information' => 'Vérifier une information',
    'ce_verdict_vous_parait_incomplet' => 'Ce verdict vous paraît incomplet ?',
    'signalez_un_fait_nouveau' => 'Si vous détenez un élément que cette analyse n’avait pas, signalez-le à la rédaction.',
    'ecrire_a_la_redaction' => 'Écrire à la rédaction',
    'mention_de_bas_de_page' => "Vérif'All — plateforme de vérification de l'information. Ce rapport est consultable librement et son contenu ne peut être modifié après publication.",
];
