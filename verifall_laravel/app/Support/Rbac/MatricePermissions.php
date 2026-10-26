<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Enums\UserRole;

/**
 * Matrice des permissions — source unique de vérité du RBAC.
 *
 * ⚠️ DÉRIVÉE, NON SPÉCIFIÉE. Le document de référence
 * `docs/kb/ROLE_ET_PERMISSIONS.md` n'a pas été fourni : cette matrice a été
 * reconstruite à partir des 46 pages de la sidebar admin et des 12 pages
 * mentor du prototype, plus les exemples de RBAC_ROLES_PERMISSIONS. Elle
 * appelle une validation métier avant d'être considérée comme définitive.
 *
 * Convention `<module>.<action>` : un module par entrée de sidebar, des actions
 * explicites plutôt qu'un « manage » fourre-tout — « qui peut publier ? » doit
 * pouvoir se répondre sans lire de code.
 *
 * Les Policies restent le contrôle définitif : masquer un bouton n'est pas une
 * sécurité.
 */
final class MatricePermissions
{
    /**
     * Permissions groupées par module, dans l'ordre de la sidebar admin.
     *
     * @return array<string, list<string>>
     */
    public static function parModule(): array
    {
        return [
            // ---------------------------------------------- FACT-CHECKING
            'signalement' => ['view', 'assign', 'verdict', 'publish', 'delete'],
            /*
             | `create` ajouté : le prototype porte un bouton « + Nouveau fact
             | check », et sans ce droit la base de faits ne peut être
             | alimentée que par la console. La matrice héritée listait les
             | actions sur un fait EXISTANT et avait oublié sa naissance.
             */
            'knowledge_base' => ['view', 'create', 'update', 'publish', 'delete', 'export'],
            'trusted_source' => ['view', 'create', 'update', 'delete'],
            'ai_unit' => ['view', 'adjust'],
            /*
             | La grille tarifaire n'était pas dans la matrice héritée : elle
             | vivait dans un fichier de configuration, donc hors de portée de
             | quiconque n'écrit pas de code. Elle a maintenant son écran, donc
             | ses droits. Pas de `manage` fourre-tout : « qui peut retirer un
             | pack de la vente ? » doit se répondre sans lire de code.
             */
            'unit_pack' => ['view', 'create', 'update', 'delete'],
            // Lecture seule : il n'existe ni création, ni modification,
            // ni suppression d'un parrainage. Déclarer ces verbes ici
            // laisserait croire qu'ils existent quelque part.
            'referral' => ['view'],
            // Un seul verbe : voir et modifier les réglages relèvent du
            // même niveau de responsabilité. Il n'y a ni création ni
            // suppression — un réglage existe parce qu'il est au catalogue.
            'setting' => ['manage'],
            'tool' => ['view', 'create', 'update', 'delete'],

            // ------------------------------------------------ ACADÉMIE LMS
            'course' => ['view', 'create', 'update', 'publish', 'archive', 'delete'],
            'lesson' => ['view', 'create', 'update', 'delete'],
            'quiz' => ['view', 'create', 'update', 'delete'],
            'live_class' => ['view', 'create', 'update', 'delete'],
            'certificate' => ['view', 'issue', 'revoke'],
            'lms_category' => ['view', 'manage'],

            // ----------------------------------------------------- EXAMENS
            /*
             | `publish`, `archive` et `assign` ajoutés avec le domaine Examens.
             | Les deux premiers portent la barre d'actions groupées de
             | `examens.html` ; le troisième est l'assignation d'une copie à un
             | correcteur, qui n'est pas la même chose que corriger : répartir
             | la charge est une décision de coordination, noter est un acte
             | pédagogique.
             */
            'exam' => ['view', 'create', 'update', 'publish', 'archive', 'assign', 'grade', 'delete'],
            'exam_stat' => ['view'],
            /*
             | La banque de questions est un module à elle : `questions.html`
             | est une entrée de sidebar distincte des quiz, et ses questions
             | servent plusieurs examens. La ranger sous `quiz` aurait donné à
             | qui édite un quiz le droit de réécrire le fonds commun.
             */
            'question_bank' => ['view', 'create', 'update', 'publish', 'delete'],

            // -------------------------------------------------- ENRÔLEMENT
            /*
             | `convene` : envoyer une convocation n'est ni créer ni supprimer
             | une inscription — c'est engager la plateforme auprès d'un
             | candidat, à une date. `update` couvre l'arbitrage d'un report et
             | l'émargement.
             */
            'enrolment' => ['view', 'create', 'update', 'convene', 'delete'],

            // ---------------------------------------------------- APPRENANTS
            // Absent de la sidebar admin, central côté mentor (etudiants,
            // progression) : le module existe donc pour porter cette portée.
            'student' => ['view', 'progress'],

            // ------------------------------------------- HUB D'INFOS BÉNIN
            'hub_article' => ['view', 'create', 'update', 'publish', 'delete'],
            'hub_portal' => ['view', 'manage'],
            'hub_job' => ['view', 'manage'],

            // --------------------------------------------------- COMMUNAUTÉ
            'forum' => ['view', 'moderate', 'delete'],
            /*
             | Les SIGNALEMENTS DE CONTENU — sujets, réponses, notes de
             | communauté. À ne pas confondre avec `signalement`, qui désigne
             | une affirmation soumise à vérification : ici il s'agit de
             | contenus du forum qu'un citoyen porte à la modération.
             |
             | Deux verbes. Pas de `delete` : un signalement se TRAITE, retenu
             | ou rejeté, et la trace demeure. L'effacer ferait perdre le fait
             | qu'une décision a été prise, et empêcherait de reconnaître un
             | signaleur abusif.
             */
            'content_report' => ['view', 'handle'],
            'forum_category' => ['view', 'manage'],

            // --------------------------------------------------------- BLOG
            'post' => ['view', 'create', 'update', 'publish', 'delete'],
            'blog_category' => ['view', 'manage'],

            // ------------------------------------------- RESSOURCES & COM
            'partner' => ['view', 'manage'],
            'faq' => ['view', 'manage'],
            // Médiathèque : voir et déposer ; supprimer le sien (le droit `delete` couvre celui des autres).
            'media' => ['view', 'upload', 'delete'],
            'testimonial' => ['view', 'manage'],
            'newsletter' => ['view', 'send'],
            /*
             | La boîte de contact. `view` donne accès à des adresses e-mail et
             | à des messages qui peuvent nommer des personnes : elle n'est pas
             | ouverte au modérateur, dont le périmètre est le forum.
             */
            'contact_message' => ['view', 'handle'],
            'campaign' => ['view', 'manage'],

            // -------------------------------------------------- UTILISATEURS
            'user' => ['view', 'create', 'update', 'suspend', 'export', 'delete'],
            'mentor' => ['view', 'manage'],
            'kyc' => ['view', 'review'],
            'role' => ['view', 'manage'],

            // ------------------------------------------------ CONFIG SYSTÈME
            'system' => ['settings', 'email_template', 'badge', 'certificate_template'],

            // ------------------------------------------------------ SÉCURITÉ
            'security' => ['logs', 'alerts', 'email_logs', 'unit_logs'],

            // --------------------------------------------------- MAINTENANCE
            /*
             | `mode` s'ajoute le 3 septembre 2026, et c'est la PREMIÈRE
             | permission créée depuis quatorze lots — la matrice couvrait
             | jusqu'ici tout ce qu'on écrivait.
             |
             | Elle ne se confond avec aucune des deux autres : `backup` sauvegarde
             | la base, `cron` regarde des tâches planifiées, `mode` FERME LE SITE
             | à tout le monde. Le rattacher à l'une des deux aurait donné ce
             | pouvoir à qui n'a demandé qu'à lancer une sauvegarde.
             */
            'maintenance' => ['backup', 'cron', 'mode'],
        ];
    }

    /**
     * Liste plate `module.action`.
     *
     * @return list<string>
     */
    public static function toutes(): array
    {
        $permissions = [];

        foreach (self::parModule() as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * Permissions accordées à un rôle.
     *
     * @return list<string>
     */
    public static function pourRole(UserRole $role): array
    {
        return match ($role) {
            // L'administrateur détient tout, sans exception à maintenir.
            UserRole::Admin => self::toutes(),

            /*
             | MENTOR — production pédagogique, cantonnée à SES ressources.
             |
             | Il crée et corrige, mais ne publie pas : la publication d'un
             | cours engage la plateforme et reste à l'administrateur. Il ne
             | voit ni les utilisateurs, ni la sécurité, ni la configuration.
             */
            UserRole::Mentor => [
                'course.view', 'course.create', 'course.update',
                'lesson.view', 'lesson.create', 'lesson.update', 'lesson.delete',
                'quiz.view', 'quiz.create', 'quiz.update', 'quiz.delete',
                'live_class.view', 'live_class.create', 'live_class.update', 'live_class.delete',
                'exam.view', 'exam.create', 'exam.update', 'exam.grade',
                'exam_stat.view',
                // Il alimente le fonds commun de questions, mais ne le publie
                // pas : une question publiée est posée à tous les candidats.
                'question_bank.view', 'question_bank.create', 'question_bank.update',
                'certificate.view', 'certificate.issue',
                'enrolment.view',
                'student.view', 'student.progress',
                'lms_category.view',

                /*
                 | HUB D'INFOS ET COMMUNAUTÉ — le CRUD, sans la publication
                 | (arbitrage n° 177, décision D5 du 18 octobre 2026).
                 |
                 | Le mentor anime l'espace où ses apprenants se retrouvent :
                 | il crée, lit, modifie et supprime actualités, offres,
                 | portails, articles, FAQ, partenaires, témoignages, et il
                 | modère le forum. Comme pour un cours, il ne PUBLIE pas un
                 | article ni une actualité : la mise en ligne engage la
                 | plateforme et reste à l'administrateur.
                 |
                 | Les infolettres restent hors de son périmètre : `newsletter.view`
                 | ouvre la liste des ABONNÉS, des milliers d'adresses dont il n'a
                 | pas besoin (minimisation, RGPD art. 5.1.c), et `newsletter.send`
                 | est un envoi de masse qui ne se corrige pas.
                 */
                'hub_article.view', 'hub_article.create', 'hub_article.update', 'hub_article.delete',
                'hub_portal.view', 'hub_portal.manage',
                'hub_job.view', 'hub_job.manage',
                'forum.view', 'forum.moderate', 'forum.delete',
                'forum_category.view', 'forum_category.manage',
                'post.view', 'post.create', 'post.update', 'post.delete',
                'blog_category.view', 'blog_category.manage',
                'faq.view', 'faq.manage',
                'media.view', 'media.upload',
                'partner.view', 'partner.manage',
                'testimonial.view', 'testimonial.manage',
            ],

            /*
             | MODÉRATEUR — forum et signalements.
             |
             | Il peut rendre un verdict et modérer, mais ne SUPPRIME pas un
             | signalement : effacer une trace de désinformation doit rester
             | une décision d'administrateur, traçable.
             */
            UserRole::Moderateur => [
                'signalement.view', 'signalement.assign', 'signalement.verdict',
                /*
                 | Le modérateur RÉDIGE, il ne publie pas. C'est lui qui voit
                 | passer les rumeurs par les signalements : lui interdire de
                 | consigner un fait ferait perdre l'information entre le
                 | moment où elle est repérée et celui où un admin s'en saisit.
                 | Publier engage la plateforme — cela reste à l'admin.
                 */
                'knowledge_base.view', 'knowledge_base.create', 'knowledge_base.update',
                'trusted_source.view',
                'forum.view', 'forum.moderate', 'forum.delete',
                'forum_category.view',
                // Les signalements de contenu SONT son métier : c'est par eux
                // qu'un contenu problématique lui arrive.
                'content_report.view', 'content_report.handle',
                'hub_article.view',
                'student.view',
            ],

            // Le citoyen n'a aucune permission de back-office, par construction.
            UserRole::Citoyen => [],
        };
    }
}
