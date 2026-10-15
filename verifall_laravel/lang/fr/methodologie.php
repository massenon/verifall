<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page methodologie
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
    'signal_jamais_verdict' => 'signal, jamais verdict',

    // Les trois paragraphes ci-dessous portent des seuils qui viennent du
    // service : ils arrivent en parametre, jamais en dur. Le <strong> reste
    // dans la valeur pour que chaque langue place l'accent ou sa syntaxe
    // l'exige — decouper la phrase autour du gras figerait l'ordre francais.
    'en_dessous_de_n_avis_exploitables' => 'En dessous de <strong>:nombre</strong> avis exploitables, aucun verdict n\'est fabriqué. Le rapport sort quand même, un écran vide serait pire, mais il annonce qu\'il est partiel, et les unités dépensées sont rendues.',
    'un_verdict_n_est_retenu_que_s_il' => 'Un verdict n\'est retenu que s\'il devance le suivant d\'au moins <strong>:ecart points</strong> et atteint <strong>:score sur 100</strong>. Sans le premier seuil, on trancherait à pile ou face ; sans le second, on affirmerait sur presque rien.',
    'quand_les_moteurs_sont_satures' => 'Quand les moteurs sont saturés, l\'analyse n\'est pas ratée : elle est reprogrammée environ <strong>:minutes minutes</strong> plus tard et la personne est prévenue. Chaque compte est par ailleurs limité à <strong>:analyses analyses par heure</strong>, pour qu\'un usage intensif n\'épuise pas les quotas de tout le monde.',

    'consulter_le_journal_des_corrections' => 'Consulter le journal des corrections',
    'cette_page_applique_les_exigences_de_transparence' => 'Cette page applique les exigences de transparence du code de conduite de l\'International Fact-Checking Network. Vérif\'All n\'en est pas signataire à ce jour.',
    'nos_ressources_et_leur_origine_sont_detaillees' => 'Nos ressources et leur origine sont détaillées sur la page',
    'financement' => 'financement',

    'notre_methodologie' => 'Notre méthodologie',
    'comment_une_affirmation_devient_un_verdict_les' => 'Comment une affirmation devient un verdict : les étapes, les moteurs consultés, le poids de chacun, et les cas où nous refusons de trancher.',
    'les_chiffres_de_cette_page_sont_lus' => 'Les chiffres de cette page sont lus dans la configuration de l\'application. Ils changent avec elle.',
    'etat_actuel_des_moteurs' => 'État actuel des moteurs',
    'les_moteurs_d_analyse_decrits_ci_dessous' => 'Les moteurs d\'analyse décrits ci-dessous fonctionnent aujourd\'hui en mode <strong>simulé</strong> : aucune clé d\'accès aux services tiers n\'est encore configurée sur cette instance. Les verdicts produits ne doivent donc pas être tenus pour des vérifications. La bascule vers les moteurs réels ne change ni les étapes, ni les poids, ni les seuils exposés sur cette page.',
    'le_parcours_d_une_verification' => 'Le parcours d\'une vérification',
    'six_etapes_toujours_les_memes_dans_cet' => 'Six étapes, toujours les mêmes, dans cet ordre. Elles sont marquées au moment où elles sont franchies : si l\'analyse cale, l\'affichage cale avec elle.',
    'le_poids_de_chaque_moteur' => 'Le poids de chaque moteur',
    'tous_les_moteurs_ne_se_valent_pas' => 'Tous les moteurs ne se valent pas. Un fact-check déjà publié par une rédaction est une <strong>preuve</strong> ; un verdict de modèle de langage est une <strong>inférence</strong>. Sur une plateforme de fact-checking, la preuve prime, sinon nous ne serions qu\'un agent conversationnel de plus.',
    'les_moteurs_marques_signal_sont_ecartes_du' => 'Les moteurs marqués « signal » sont écartés du calcul du verdict : ils renseignent sans trancher. Un détecteur de contenu généré dit qu\'un texte ressemble à une production automatique, pas qu\'il est faux.',
    'les_scores_sont_normalises_sans_cela_une' => 'Les scores sont normalisés : sans cela, une analyse qui interroge plus de moteurs paraîtrait toujours plus sûre qu\'une autre.',
    'quand_nous_repondons_non_verifiable' => 'Quand nous répondons « non vérifiable »',
    'non_verifiable_n_est_pas_un_echec' => '« Non vérifiable » n\'est pas un échec : c\'est un résultat, et le seul honnête quand les preuves manquent. Sans lui, un système serait poussé à trancher au hasard. Trois situations y mènent.',
    'trop_peu_de_moteurs_ont_repondu' => 'Trop peu de moteurs ont répondu',
    'le_verdict_de_tete_ne_se_detache' => 'Le verdict de tête ne se détache pas',
    'les_moteurs_se_contredisent' => 'Les moteurs se contredisent',
    'quand_deux_theses_opposees_tiennent_la_tete' => 'Quand deux thèses opposées tiennent la tête, le rapport part en <strong>arbitrage humain</strong> et affiche les deux, avec leurs sources. Un cas particulier fait exception : lorsque les deux thèses en présence sont « vrai » et « faux », c\'est la signature d\'une affirmation partiellement exacte présentée de travers, le verdict devient « trompeur », et passe lui aussi devant un humain.',
    'ce_qui_declenche_une_relecture_humaine' => 'Ce qui déclenche une relecture humaine',
    'une_contradiction_entre_les_moteurs_dans_les' => 'Une <strong>contradiction</strong> entre les moteurs, dans les conditions décrites ci-dessus.',
    'un_controle_de_coherence_qui_echoue_le' => 'Un <strong>contrôle de cohérence</strong> qui échoue : le verdict proposé est contredit par la majorité des preuves réunies. Un rapport dont les sources démentent son propre verdict n\'est jamais publié en l\'état.',
    'une_publication_au_nom_de_la_redaction' => 'Une <strong>publication au nom de la rédaction</strong> : un verdict écrit par l\'équipe et servi à la place d\'une analyse exige une analyse rédigée et au moins une source vérifiable.',
    'ce_que_nous_ne_savons_pas_faire' => 'Ce que nous ne savons pas faire',
    'ce_qu_on_ne_mesure_pas_ne' => 'Ce qu\'on ne mesure pas ne s\'affiche pas. Voici les trois limites que nous connaissons, écrites ici plutôt que laissées à deviner, un blanc se lit comme un feu vert.',
    'l_hypertrucage_video_n_est_pas_detecte' => '<h3 class="text-lg font-display font-bold text-heading mb-2">L\'hypertrucage vidéo n\'est pas détecté</h3> <p class="text-body/85 leading-relaxed mb-0"> Détecter une vidéo truquée demande une analyse image par image, hors de portée de notre hébergement. Une vidéo est traitée comme une image plus une bande son : recherche inversée sur des vignettes, transcription, lecture des métadonnées. C\'est utile, la majorité des vidéos trompeuses sont d\'anciennes vidéos recontextualisées, pas des trucages, mais ce n\'est pas une détection d\'hypertrucage. La détection de voix synthétique n\'est pas couverte non plus. </p>',
    'nous_ne_nous_prononcons_pas_sur_l' => '<h3 class="text-lg font-display font-bold text-heading mb-2">Nous ne nous prononçons pas sur l\'origine d\'un texte</h3> <p class="text-body/85 leading-relaxed mb-0"> La détection de texte généré par intelligence artificielle est peu fiable, et son mode d\'échec est asymétrique : elle produit des faux positifs sur les écrits de locuteurs non natifs et sur les copies d\'étudiants, c\'est-à-dire sur une partie de notre public. Accuser à tort quelqu\'un d\'avoir fait écrire son texte par une machine est un dommage réel, causé par un outil présenté comme une aide. Un texte généré par une machine peut d\'ailleurs être exact, et un texte écrit par un humain peut être faux. </p>',
    'nous_ne_datons_pas_la_premiere_apparition' => '<h3 class="text-lg font-display font-bold text-heading mb-2">Nous ne datons pas la première apparition d\'une image</h3> <p class="text-body/85 leading-relaxed mb-0"> Nous savons dire sur quelles pages une image apparaît, et laquelle est la plus ancienne que nous ayons trouvée. Ce n\'est pas la même chose qu\'une date de première apparition, et nous ne l\'écrirons pas comme si ça l\'était. Or c\'est précisément la question du fact-checking d\'image : une photo de 2018 republiée comme une actualité reste le mode de désinformation le plus courant. </p>',
    'quand_nous_nous_trompons' => 'Quand nous nous trompons',
    'un_verdict_qui_change_ne_disparait_pas' => 'Un verdict qui change ne disparaît pas : l\'ancien reste publié, avec sa date et la raison du changement. Aucune correction ne peut être enregistrée sans motif écrit.',
];
