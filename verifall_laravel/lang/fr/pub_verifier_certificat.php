<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, verification d'un certificat
|--------------------------------------------------------------------------
|
| Page autonome, sans le menu du site, publique et sans compte : un diplome
| que personne ne peut controler ne vaut rien, et obliger un recruteur a
| creer un compte le rendrait invérifiable.
|
| `certificat_introuvable` dit « introuvable », JAMAIS « faux ». Un code mal
| recopie n'est pas une fraude, et l'accusation serait injuste autant
| qu'inexacte. La traduction anglaise tient la meme ligne : elle invite a
| relire la saisie AVANT d'evoquer un document falsifie.
|
| Deux cles commencent par un point (`il_ne_fait_plus_foi`,
| `verifiez_la_saisie_ou_mefiez_vous_d`) : elles ferment une phrase coupee par
| une donnee, la date de retrait ou le code saisi. La ponctuation leur
| appartient, sinon elle se collerait au mauvais endroit une fois traduite.
|
| Le format d'exemple VERIF-A-XXXX-XXXX est un identifiant, pas du texte : il
| reste tel quel, ici comme dans le `placeholder` de la vue.
|
*/

return [
    'verifier_un_certificat' => 'Vérifier un certificat',
    'chaque_certificat_verif_all_porte_un_identifiant' => 'Chaque certificat Vérif\'All porte un identifiant unique et une signature numérique. Entrez l\'identifiant (ex. VERIF-A-XXXX-XXXX) pour contrôler son authenticité.',
    'identifiant_du_certificat' => 'Identifiant du certificat',
    'verifier_l_authenticite' => 'Vérifier l\'authenticité',
    'trop_de_verifications_depuis_cette_adresse_reessayez' => 'Trop de vérifications depuis cette adresse. Réessayez dans quelques minutes.',
    'certificat_authentique' => 'Certificat authentique',
    'delivre_par_l_academie_verif_all' => 'Délivré par l\'Académie Vérif\'All',
    'titulaire' => 'Titulaire',
    'cours' => 'Cours',
    'emis_le' => 'Émis le',
    'identifiant' => 'Identifiant',
    'certificat_revoque' => 'Certificat révoqué',
    'ce_certificat_a_bien_ete_delivre_puis' => 'Ce certificat a bien été délivré, puis retiré le',
    'il_ne_fait_plus_foi' => '. Il ne fait plus foi.',
    'certificat_introuvable' => 'Certificat introuvable',
    'aucun_certificat_ne_correspond_a_l_identifiant' => 'Aucun certificat ne correspond à l\'identifiant',
    'verifiez_la_saisie_ou_mefiez_vous_d' => '. Vérifiez la saisie, ou méfiez-vous d\'un document falsifié.',
];
