<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, journal des corrections
|--------------------------------------------------------------------------
|
| Cette page ne vient pas du prototype mais du code de conduite de l'IFCN,
| qui demande a une redaction de publier ses corrections.
|
| `aucun_verdict_n_a_encore_ete_corrige` est un CONSTAT, pas une reussite.
| Un journal vide ne prouve pas qu'on ne s'est jamais trompe, seulement qu'on
| n'a pas encore corrige : la traduction ne doit surtout pas s'en feliciter.
|
| Le dernier paragraphe est coupe par deux liens. Les fragments portent donc
| leur ponctuation, parce que l'ordre des mots change d'une langue a l'autre :
| le francais dit « sur la page methodologie », l'anglais « on the methodology
| page ». Le mot « page » passe donc d'un fragment a l'autre selon la langue,
| et c'est la seule facon d'obtenir une phrase juste des deux cotes.
|
*/

return [
    'journal_des_corrections' => 'Journal des corrections',
    'quand_un_verdict_change_l_ancien_reste' => 'Quand un verdict change, l\'ancien reste ici, avec sa date et la raison du changement.',
    'une_plateforme_qui_reecrit_ses_verdicts_en' => 'Une plateforme qui réécrit ses verdicts en silence ne peut pas exiger la même honnêteté des autres. Cette page est la contrepartie de ce que nous demandons.',
    'lire_la_verification_corrigee' => 'Lire la vérification corrigée',
    'aucun_verdict_n_a_encore_ete_corrige' => 'Aucun verdict n\'a encore été corrigé.',
    'des_qu_une_verification_publiee_changera_de' => 'Dès qu\'une vérification publiée changera de verdict, elle apparaîtra ici, avec l\'ancien verdict, la date et la raison.',
    'notre_methode_de_verification_est_decrite_en' => 'Notre méthode de vérification est décrite en détail sur la page',
    'methodologie' => 'méthodologie',
    'et_l_origine_de_nos_ressources_sur' => ', et l\'origine de nos ressources sur la page',
    'financement' => 'financement',
    'point_final' => '.',
];
