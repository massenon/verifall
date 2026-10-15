<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public front, certificate verification
|--------------------------------------------------------------------------
|
| A standalone page with no site menu, public and account-free: a diploma
| nobody can check is worth nothing, and forcing a recruiter to create an
| account would make it unverifiable in practice.
|
| `certificat_introuvable` says "not found", NEVER "fake". A mistyped code is
| not a fraud, and the accusation would be as unfair as it is inaccurate. The
| English holds the same line: it invites the reader to re-check what they
| typed BEFORE raising the possibility of a forged document.
|
| Two keys open with a full stop (`il_ne_fait_plus_foi`,
| `verifiez_la_saisie_ou_mefiez_vous_d`): they close a sentence interrupted by
| a value — the revocation date, or the code entered. The punctuation belongs
| to them, otherwise it would land in the wrong place once translated.
|
| The example format VERIF-A-XXXX-XXXX is an identifier, not prose: it stays
| as it is, here and in the view's `placeholder`.
|
*/

return [
    'verifier_un_certificat' => 'Verify a certificate',
    'chaque_certificat_verif_all_porte_un_identifiant' => 'Every Vérif\'All certificate carries a unique identifier and a digital signature. Enter the identifier (e.g. VERIF-A-XXXX-XXXX) to check that it is genuine.',
    'identifiant_du_certificat' => 'Certificate identifier',
    'verifier_l_authenticite' => 'Check authenticity',
    'trop_de_verifications_depuis_cette_adresse_reessayez' => 'Too many checks from this address. Try again in a few minutes.',
    'certificat_authentique' => 'Genuine certificate',
    'delivre_par_l_academie_verif_all' => 'Issued by the Vérif\'All Academy',
    'titulaire' => 'Holder',
    'cours' => 'Course',
    'emis_le' => 'Issued on',
    'identifiant' => 'Identifier',
    'certificat_revoque' => 'Certificate revoked',
    'ce_certificat_a_bien_ete_delivre_puis' => 'This certificate was indeed issued, then withdrawn on',
    'il_ne_fait_plus_foi' => '. It no longer stands as proof.',
    'certificat_introuvable' => 'Certificate not found',
    'aucun_certificat_ne_correspond_a_l_identifiant' => 'No certificate matches the identifier',
    'verifiez_la_saisie_ou_mefiez_vous_d' => '. Check what you typed, or treat the document as possibly forged.',
];
