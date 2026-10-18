<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Console premium — Actions rapides
|--------------------------------------------------------------------------
| Les gestes fréquents, à un clic de n'importe quel écran (bouton vert de la
| barre du haut, touche « A »). Chaque action ne déclare que sa ROUTE : comme
| le menu, elle n'apparaît que si la garde `can:` de la route laisse passer la
| personne connectée (`MenuDuBackOffice::autorise`). L'ordre est celui de
| l'affichage ; une même liste sert l'administrateur et le mentor, chacun ne
| voyant que ce qui lui est ouvert.
|
| [route, icône Lucide, libellé, aide]
*/

return [
    'actions_rapides' => [
        ['staff.verifications', 'flame', 'Traiter un signalement', 'File triée par échéance'],
        ['staff.knowledge', 'file-check', 'Rédiger un fact-check', 'Verdict, sources, démenti'],
        ['staff.exam-grading', 'pen-line', 'Corriger des copies', 'Copies en attente'],
        ['staff.live-classes', 'video', 'Programmer un live', 'Sessions et inscrits'],
        ['staff.courses', 'library', 'Créer ou modifier un cours', 'Catalogue et constructeur'],
        ['staff.question-bank', 'list-checks', 'Ajouter des questions', 'Banque de questions'],
        ['staff.my-content', 'upload', 'Déposer un contenu', 'Vidéo, PDF ou image'],
        ['staff.progression', 'send', 'Relancer les inactifs', 'Apprenants à 14 jours'],
        ['staff.posts', 'square-pen', 'Écrire un article', 'Blog de la communauté'],
        ['staff.hub-news', 'newspaper', 'Publier une actualité', 'Hub d’infos'],
        ['staff.forum', 'messages-square', 'Modérer le forum', 'Discussions et réponses'],
        ['staff.users', 'coins', 'Créditer des unités', 'À un citoyen, journalisé'],
        ['staff.kyc', 'id-card', 'Examiner un dossier KYC', 'Pièces en attente'],
        ['staff.email-templates', 'mails', 'Modifier un courriel', 'Objet, texte, bouton'],
        ['staff.backups', 'database-backup', 'Sauvegarder la base', 'Sauvegarde complète'],
    ],

    // Au-delà, la liste ne serait plus « rapide ».
    'actions_rapides_max' => 8,
];
