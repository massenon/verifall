<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Le menu du back-office, déclaré une fois et filtré par ce que la route exige.
 *
 * ## Le défaut que ceci corrige
 *
 * La barre latérale affichait **56 liens à tout le monde**. Quatre étaient
 * conditionnés ; les cinquante-deux autres promettaient à un mentor des écrans
 * que son rôle lui refuse — le clic répondait 403. Un menu qui promet ce que le
 * serveur interdit fait perdre confiance dans TOUT le menu : on cesse de savoir
 * ce qu'on a le droit de faire, et on clique pour voir.
 *
 * ## La garde n'est PAS déclarée ici, elle est LUE sur la route
 *
 * C'est le point qui compte. Le réflexe serait d'écrire une permission à côté de
 * chaque entrée. Ce serait une seconde source de vérité, et elle divergerait —
 * comme a divergé la liste des courriels câblés, corrigée trois fois avant
 * d'être remplacée par une règle.
 *
 * Chaque entrée ne déclare que sa ROUTE. La visibilité se calcule en relisant le
 * middleware `can:` de cette route et en l'évaluant pour la personne connectée.
 * Le menu montre donc, par construction, exactement ce que le serveur autorise :
 * changer la garde d'une route change le menu, sans que personne n'ait à y
 * penser.
 *
 * Les deux formes de garde sont gérées :
 *   · `can:permission` — une permission spatie ;
 *   · `can:ability,App\Models\Machin` — une Policy.
 *
 * Une route qui en porte plusieurs les exige TOUTES, comme le fait le routeur.
 *
 * ## Une entrée sans garde est un défaut, pas une commodité
 *
 * `estGardee()` le dit, et un test l'exige. Une route de back-office sans
 * `can:` est accessible à quiconque franchit la porte du personnel — y compris
 * un compte dont le seul rôle est « Mentor ». Le tableau de bord est la seule
 * exception, et elle est nommée.
 */
final class MenuDuBackOffice
{
    /**
     * Les groupes, dans l'ordre d'affichage.
     *
     * Chaque entrée : `route => [icône, libellé]`. Rien d'autre — surtout pas
     * une permission, voir le bloc ci-dessus.
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    public const GROUPES = [
        /*
         | Ordre, libellés et icônes Lucide de la console premium
         | (`resources/prototype-premium/assets/js/app.js`, arbitrage n° 176).
         | Les gardes, elles, restent lues sur les routes : changer de libellé ne
         | change aucun droit.
         |
         | Sans intitulé : le tableau de bord seul, avant le premier groupe.
         */
        '' => [
            'staff.dashboard' => ['layout-dashboard', 'Tableau de bord'],
        ],

        'Fact-checking' => [
            // `staff.verifications` est l'écran des signalements CITOYENS ;
            // les contenus signalés du forum sont en Communauté.
            'staff.verifications' => ['flame', 'Signalements'],
            'staff.knowledge' => ['file-check', 'Fact-checks'],
            'staff.trusted-sources' => ['shield-check', 'Sources vérifiées'],
            'staff.source-categories' => ['tags', 'Catégories de sources'],
            'staff.tipline' => ['message-circle', 'Tipline WhatsApp'],
            // Doublon voulu avec « Journaux » — voir DOUBLONS_ASSUMES.
            'staff.unit-journal' => ['zap', 'Unités IA'],
            'staff.unit-packs' => ['package', 'Packs d’unités'],
            'staff.tools' => ['wrench', 'Répertoire des outils'],
        ],

        'Académie' => [
            'staff.courses' => ['library', 'Tous les cours'],
            'staff.course-categories' => ['folder-tree', 'Catégories'],
            'staff.question-bank' => ['list-checks', 'Banque de questions'],
            'staff.exams' => ['file-check-2', 'Examens'],
            'staff.exam-grading' => ['pen-line', 'Correction'],
            'staff.course-enrolment' => ['user-plus', 'Inscriptions aux cours'],
            'staff.exam-enrolment' => ['clipboard-list', 'Inscriptions aux examens'],
            'staff.certificates' => ['award', 'Certificats'],
            'staff.certificate-template' => ['scroll-text', 'Modèle de certificat'],
            'staff.course-units' => ['badge-dollar-sign', 'Ventes premium'],
            'staff.exam-stats' => ['chart-pie', 'Statistiques globales'],
        ],

        'Espace mentor' => [
            'staff.live-classes' => ['video', 'Live classes'],
            'staff.my-content' => ['folder-open', 'Mes contenus'],
            'staff.my-students' => ['users', 'Mes apprenants'],
            'staff.progression' => ['trending-up', 'Progression'],
            'staff.mentor-analytics' => ['chart-line', 'Ma performance'],
        ],

        'Hub d’infos' => [
            'staff.hub-news' => ['newspaper', 'Actualités'],
            'staff.hub-portals' => ['landmark', 'Portails officiels'],
            'staff.hub-jobs' => ['briefcase', 'Offres d’emploi'],
        ],

        'Communauté' => [
            'staff.forum' => ['messages-square', 'Forum citoyen'],
            'staff.forum-categories' => ['folder', 'Catégories du forum'],
            'staff.content-reports' => ['flag', 'Contenus signalés'],
            'staff.moderation-keywords' => ['list-filter', 'Mots surveillés'],
            'staff.posts' => ['pen-tool', 'Articles du blog'],
            'staff.blog-categories' => ['folder', 'Catégories du blog'],
            'staff.newsletter' => ['mail', 'Infolettres'],
            'staff.faq' => ['circle-help', 'FAQ'],
            'staff.testimonials' => ['quote', 'Témoignages'],
            'staff.partners' => ['handshake', 'Partenaires'],
            'staff.contact-messages' => ['inbox', 'Messages reçus'],
        ],

        'Utilisateurs' => [
            'staff.users' => ['users', 'Utilisateurs'],
            'staff.mentors' => ['presentation', 'Mentors'],
            'staff.kyc' => ['id-card', 'Vérification KYC'],
            'staff.roles' => ['key-round', 'Rôles et permissions'],
            'staff.referrals' => ['gift', 'Parrainages'],
        ],

        'Système' => [
            'staff.settings' => ['settings', 'Réglages'],
            'staff.email-templates' => ['mails', 'Modèles de courriels'],
            'staff.campaigns' => ['megaphone', 'Campagnes'],
            'staff.badges' => ['medal', 'Badges'],
            'staff.maintenance-mode' => ['construction', 'Mode maintenance'],
            'staff.backups' => ['database-backup', 'Sauvegardes'],
            'staff.cron' => ['timer', 'Tâches planifiées'],
        ],

        'Journaux' => [
            'staff.audit' => ['scroll', 'Journal d’audit'],
            'staff.email-logs' => ['mail-check', 'Courriels'],
            'staff.unit-journal' => ['receipt', 'Unités'],
            'staff.alerts' => ['shield-alert', 'Alertes d’intrusion'],
        ],
    ];

    /**
     * Les routes qui ont DROIT à deux entrées, et pourquoi.
     *
     * ## Un doublon n'est pas forcément un défaut
     *
     * J'ai d'abord retiré celui-ci en le prenant pour une négligence. Il est
     * délibéré, et `SecuriteEtMaintenanceTest` le documentait déjà : le
     * prototype porte « Historiques des Unités IA » en fact-checking et « Logs
     * Unités IA » en sécurité. **Deux écrans identiques donneraient deux
     * habitudes**, donc un des deux qu'on ne relève plus ; une seule page servie
     * par deux portes évite cela.
     *
     * Les autres doublons du menu, eux, étaient bien des négligences — deux
     * chemins vers les réglages, deux vers les opportunités du Hub — et ont été
     * retirés au lot 10.
     *
     * @var array<string, string>
     */
    public const DOUBLONS_ASSUMES = [
        'staff.unit-journal' => 'Deux portes, un écran : le responsable sécurité et le gestionnaire d’unités y arrivent chacun par son groupe.',
    ];

    /**
     * Les seules entrées qu'on accepte de voir sans garde.
     *
     * Le tableau de bord est la porte : la refuser reviendrait à connecter
     * quelqu'un sur une page vide. Ses PANNEAUX, eux, sont conditionnés un par
     * un — c'est là que se joue la confidentialité, pas sur l'adresse.
     *
     * @var list<string>
     */
    public const SANS_GARDE_ASSUMEE = ['staff.dashboard'];

    /**
     * Le menu tel que cette personne peut le parcourir.
     *
     * Les groupes vides ne sont pas rendus : un intitulé « EXAMENS » suivi de
     * rien se lit comme une panne.
     *
     * @return array<string, list<array{route: string, icone: string, libelle: string}>>
     */
    public static function pour(?User $membre): array
    {
        if ($membre === null) {
            return [];
        }

        $visible = [];

        foreach (self::GROUPES as $groupe => $entrees) {
            $retenues = [];

            foreach ($entrees as $route => [$icone, $libelle]) {
                if (! self::autorise($membre, $route)) {
                    continue;
                }

                $retenues[] = ['route' => $route, 'icone' => $icone, 'libelle' => $libelle];
            }

            if ($retenues !== []) {
                $visible[$groupe] = $retenues;
            }
        }

        return $visible;
    }

    /**
     * Cette personne peut-elle atteindre cette route ?
     *
     * On relit le middleware `can:` de la route et on l'évalue. Aucune
     * permission n'est écrite ici : c'est ce qui empêche le menu de diverger de
     * la garde réelle.
     */
    public static function autorise(User $membre, string $route): bool
    {
        $gardes = self::gardes($route);

        if ($gardes === []) {
            /*
             | Pas de garde : on affiche. C'est délibéré et c'est le comportement
             | du routeur — une route sans `can:` est ouverte à tout le personnel.
             | Le test `MenuDuBackOffice` refuse toute entrée dans ce cas, sauf
             | celles de SANS_GARDE_ASSUMEE : le menu n'est pas l'endroit où
             | corriger une route mal gardée, il ne fait que la refléter.
             */
            return true;
        }

        foreach ($gardes as [$capacite, $modele]) {
            $autorise = $modele !== null
                ? Gate::forUser($membre)->allows($capacite, $modele)
                : $membre->can($capacite);

            // Le routeur exige TOUTES les gardes ; le menu fait de même.
            if (! $autorise) {
                return false;
            }
        }

        return true;
    }

    /** Une route de menu porte-t-elle au moins une garde ? */
    public static function estGardee(string $route): bool
    {
        return self::gardes($route) !== [];
    }

    /**
     * Les `can:` d'une route, décomposés.
     *
     * @return list<array{0: string, 1: string|null}> [capacité, modèle éventuel]
     */
    private static function gardes(string $route): array
    {
        $declaration = Route::getRoutes()->getByName($route);

        if ($declaration === null) {
            return [];
        }

        $gardes = [];

        foreach ($declaration->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                continue;
            }

            // `can:ability` ou `can:ability,App\Models\Machin`
            $morceaux = explode(',', substr($middleware, 4));

            $gardes[] = [trim($morceaux[0]), isset($morceaux[1]) ? trim($morceaux[1]) : null];
        }

        return $gardes;
    }
}
