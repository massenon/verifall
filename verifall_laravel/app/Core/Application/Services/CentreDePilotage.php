<?php

declare(strict_types=1);

namespace App\Core\Application\Services;

use App\Core\Application\Contracts\DepotStatistiquesDuTableauDeBord;
use App\Core\Application\Statistiques\PeriodeDAnalyse;
use App\Enums\EtatMoteur;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Closure;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Le centre de pilotage : ce que le tableau de bord montre, et à qui.
 *
 * ## Ce que ce service décide — et que la vue ne décide pas
 *
 *   · QUI voit quelle section. La visibilité est lue sur la garde de la route
 *     de l'écran détaillé ({@see MenuDuBackOffice::autorise()}) : on ne montre
 *     pas l'agrégat d'un domaine à quelqu'un que l'écran de ce domaine refuse.
 *   · CE QU'EST une variation. Pas de pourcentage sans dénominateur : « +∞ % »
 *     ou « +100 % » après une période vide seraient des chiffres exacts et
 *     trompeurs.
 *   · LE NIVEAU d'une alerte. Critique, attention, info : la règle est ici, pas
 *     dans une couleur choisie par la vue.
 *
 * ## L'échec d'un widget n'est pas l'échec de la page
 *
 * `tenter()` enveloppe chaque calcul : une exception est RAPPORTÉE (journal,
 * gestionnaire d'erreurs) puis remplacée par `null`, que la vue rend en
 * « Ce bloc n'a pas pu être chargé ». Les autres blocs s'affichent.
 */
final class CentreDePilotage
{
    /**
     * Les sections analytiques et l'écran dont elles résument le domaine.
     *
     * Une section est visible si TOUTES ses routes sont autorisées. L'Académie
     * exige en plus l'écran Utilisateurs : ses chiffres portent sur tous les
     * apprenants de la plateforme, et un mentor n'a de vision que sur les siens.
     */
    private const GARDES_DES_SECTIONS = [
        'kpi' => [],
        'fact-checking' => ['staff.verifications'],
        'contenu' => [],
        'editorial' => [],
        'engagement' => ['staff.users'],
        'lms' => ['staff.users', 'staff.course-enrolment'],
        'activite' => [],
    ];

    public function __construct(
        private readonly DepotStatistiquesDuTableauDeBord $depot,
        private readonly TableauDeBordPersonnel $chiffres,
    ) {}

    public function periode(string $choix, ?string $du = null, ?string $au = null): PeriodeDAnalyse
    {
        return PeriodeDAnalyse::depuis($choix, $du, $au);
    }

    /**
     * Exécute un calcul de widget ; `null` s'il échoue.
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T|null
     */
    public function tenter(string $widget, Closure $calcul): mixed
    {
        try {
            return $calcul();
        } catch (Throwable $erreur) {
            report($erreur);
            logger()->warning('Tableau de bord : le bloc « '.$widget.' » a échoué.', ['message' => $erreur->getMessage()]);

            return null;
        }
    }

    public function peutVoir(User $membre, string $section): bool
    {
        if (! array_key_exists($section, self::GARDES_DES_SECTIONS)) {
            return false;
        }

        foreach (self::GARDES_DES_SECTIONS[$section] as $route) {
            if (! MenuDuBackOffice::autorise($membre, $route)) {
                return false;
            }
        }

        return match ($section) {
            // Une bande de KPI sans aucun KPI visible n'est pas affichée.
            'kpi' => $this->peutVoirUnKpi($membre),
            'contenu', 'editorial' => MenuDuBackOffice::autorise($membre, 'staff.posts')
                || MenuDuBackOffice::autorise($membre, 'staff.hub-news'),
            default => true,
        };
    }

    /** Invalide les agrégats : le bouton « Actualiser ». */
    public function actualiser(): void
    {
        $this->depot->oublier();
    }

    /**
     * Les données d'une section, prêtes pour la vue.
     *
     * @return array<string, mixed>
     */
    public function section(string $section, User $membre, PeriodeDAnalyse $periode): array
    {
        return match ($section) {
            'kpi' => ['kpis' => $this->kpis($membre, $periode)],
            'fact-checking' => $this->factChecking($periode),
            'contenu' => $this->contenu($membre, $periode),
            'editorial' => $this->editorial($membre, $periode),
            'engagement' => $this->engagement($periode),
            'lms' => $this->lms($periode),
            'activite' => ['evenements' => $this->activiteRecente($membre)],
            default => [],
        };
    }

    // ------------------------------------------------------------------ KPI

    private function peutVoirUnKpi(User $membre): bool
    {
        return MenuDuBackOffice::autorise($membre, 'staff.users')
            || MenuDuBackOffice::autorise($membre, 'staff.verifications');
    }

    /**
     * Les cinq KPI prioritaires (§8), filtrés par droit.
     *
     * @return list<array<string, mixed>>
     */
    public function kpis(User $membre, PeriodeDAnalyse $periode): array
    {
        $kpis = [];
        $voitUtilisateurs = MenuDuBackOffice::autorise($membre, 'staff.users');
        $voitVerifications = MenuDuBackOffice::autorise($membre, 'staff.verifications');

        if ($voitUtilisateurs) {
            $u = $this->depot->utilisateurs($periode);

            $kpis[] = $this->kpi(
                cle: 'utilisateurs-actifs',
                libelle: 'Utilisateurs actifs',
                icone: 'group',
                valeur: $u['actifs'],
                precedente: null,
                periode: $periode,
                // Pas de micro-courbe : la seule série disponible est celle des
                // INSCRIPTIONS, et la dessiner sous « actifs » ferait lire autre chose.
                serie: null,
                note: $u['actifs'] === null
                    ? 'Non calculable sur une période passée : seule la dernière connexion est conservée.'
                    : sprintf('Citoyens connectés sur la période · %s nouveau%s', $this->nombre($u['nouveaux']), $u['nouveaux'] > 1 ? 'x' : ''),
                lien: 'staff.users',
                sansComparaison: 'Pas de référence : la dernière connexion écrase la précédente.',
            );
        }

        if ($voitVerifications) {
            $v = $this->depot->verifications($periode);

            $kpis[] = $this->kpi(
                cle: 'verifications',
                libelle: 'Vérifications réalisées',
                icone: 'fact_check',
                valeur: $v['soumises'],
                precedente: $v['soumises_precedente'],
                periode: $periode,
                serie: $v['serie'],
                note: sprintf('%s terminée%s', $this->nombre($v['terminees']), $v['terminees'] > 1 ? 's' : ''),
                lien: 'staff.verifications',
            );

            $enAttente = $this->depot->verificationsEnAttente();

            $kpis[] = $this->kpi(
                cle: 'en-attente',
                libelle: 'Signalements en attente',
                icone: 'pending_actions',
                valeur: $enAttente,
                precedente: null,
                periode: $periode,
                serie: null,
                note: 'En file, en analyse ou en revue humaine — maintenant.',
                lien: 'staff.verifications',
                sens: 'baisse',
                sansComparaison: 'Instantané : indépendant de la période.',
            );

            $taux = $this->taux($v['terminees'], $v['soumises']);
            $tauxPrecedent = $this->taux($v['terminees_precedente'], $v['soumises_precedente']);

            $kpis[] = [
                ...$this->kpi(
                    cle: 'taux-resolution',
                    libelle: 'Taux de résolution',
                    icone: 'task_alt',
                    valeur: $taux,
                    precedente: null,
                    periode: $periode,
                    serie: null,
                    note: $taux === null
                        ? 'Aucune vérification soumise sur la période.'
                        : 'Part des vérifications soumises menées à terme.',
                    lien: 'staff.verifications',
                    format: 'pourcent',
                    sansComparaison: 'Pas de référence sur la période précédente.',
                ),
                // Un taux se compare en POINTS, pas en pourcentage d'un pourcentage.
                'variation' => $taux !== null && $tauxPrecedent !== null ? round($taux - $tauxPrecedent, 1) : null,
                'unite_variation' => 'pts',
            ];
        }

        if ($voitUtilisateurs) {
            $visites = $this->depot->visites($periode);

            $kpis[] = $this->kpi(
                cle: 'visites',
                libelle: 'Pages vues',
                icone: 'visibility',
                valeur: $visites['pages_vues'],
                precedente: $visites['pages_vues_precedente'],
                periode: $periode,
                serie: $visites['serie'],
                note: 'Site public, robots exclus. Sessions : non mesurées.',
                lien: null,
            );
        }

        return $kpis;
    }

    /**
     * @param  list<int>|null  $serie
     * @return array<string, mixed>
     */
    private function kpi(
        string $cle,
        string $libelle,
        string $icone,
        int|float|null $valeur,
        int|float|null $precedente,
        PeriodeDAnalyse $periode,
        ?array $serie,
        ?string $note,
        ?string $lien,
        string $format = 'nombre',
        string $sens = 'hausse',
        ?string $sansComparaison = null,
    ): array {
        $variation = $valeur !== null && $precedente !== null ? $this->variation($valeur, $precedente) : null;

        return [
            'cle' => $cle,
            'libelle' => $libelle,
            'icone' => $icone,
            'valeur' => $valeur,
            'format' => $format,
            'variation' => $variation,
            'unite_variation' => '%',
            // « hausse » : monter est bon (vérifications). « baisse » : monter
            // est mauvais (dossiers en attente). La couleur en dépend.
            'sens' => $sens,
            'comparaison' => $variation === null && $precedente !== null && (int) $precedente === 0
                ? 'Période précédente vide : pas de variation calculable.'
                : ($sansComparaison !== null && $precedente === null ? $sansComparaison : $periode->libelleComparaison()),
            'serie' => $serie !== null && array_sum($serie) > 0 ? $serie : null,
            'note' => $note,
            'lien' => $lien,
        ];
    }

    /** Variation relative en %, `null` si la référence est nulle. */
    public function variation(int|float $actuel, int|float $precedent): ?float
    {
        if ((float) $precedent === 0.0) {
            return null;
        }

        return round(($actuel - $precedent) * 100 / $precedent, 1);
    }

    /** Un taux en %, `null` sans dénominateur. */
    public function taux(int|float $numerateur, int|float $denominateur): ?float
    {
        return (float) $denominateur === 0.0 ? null : round($numerateur * 100 / $denominateur, 1);
    }

    // -------------------------------------------------------- ALERTES (§11)

    /**
     * Classe ce qui ne va pas en trois niveaux.
     *
     * @param  array<string, int>|null  $aTraiter
     * @param  list<array{niveau: string, texte: string}>|null  $avertissements
     * @param  array<string, mixed>|null  $sante
     * @return array{critique: list<array<string, mixed>>, attention: list<array<string, mixed>>, info: list<array<string, mixed>>}
     */
    public function alertes(User $membre, ?array $aTraiter, ?array $avertissements, ?array $sante): array
    {
        $alertes = ['critique' => [], 'attention' => [], 'info' => []];

        foreach ($avertissements ?? [] as $avertissement) {
            $niveau = $avertissement['niveau'] === 'grave' ? 'critique' : 'attention';
            $alertes[$niveau][] = ['titre' => 'Configuration', 'texte' => $avertissement['texte'], 'route' => null];
        }

        /*
         | L'infrastructure : planificateur muet, file bloquée, courriels en
         | échec. Chacune de ces pannes arrête le produit EN SILENCE en
         | hébergement mutualisé — elles sont donc critiques par nature.
         */
        foreach ($sante['infrastructure'] ?? [] as $ligne) {
            if (($ligne['alerte'] ?? false) === true) {
                $alertes['critique'][] = [
                    'titre' => $ligne['libelle'],
                    'texte' => trim($ligne['valeur'].($ligne['detail'] !== null ? ' — '.$ligne['detail'] : '')),
                    'route' => null,
                ];
            }
        }

        if (($sante['moteurs']['simule'] ?? false) === false) {
            foreach ($sante['moteurs']['moteurs'] ?? [] as $moteur) {
                if (($moteur['etat'] ?? null) === EtatMoteur::Injoignable) {
                    $alertes['critique'][] = [
                        'titre' => 'Moteur injoignable',
                        'texte' => $moteur['libelle'].' refuse notre clé : ses analyses échouent.',
                        'route' => null,
                    ];
                }
            }
        }

        if ($aTraiter !== null) {
            $files = [
                ['interrompues', 'critique', 'Analyses interrompues', 'Une panne technique a coupé l’analyse : le demandeur attend.', 'staff.verifications'],
                ['arbitrages', 'attention', 'Arbitrages humains', 'Les moteurs se contredisent : un humain doit trancher.', 'staff.verifications'],
                ['contestations', 'attention', 'Verdicts contestés', 'Quelqu’un dit que nous nous sommes trompés.', null],
                ['kyc', 'attention', 'Identités à examiner', 'Des pièces d’identité attendent une décision.', 'staff.kyc'],
                ['signalements', 'info', 'Contenus signalés', 'Des contenus du forum attendent la modération.', 'staff.content-reports'],
                ['messages', 'info', 'Messages de contact', 'Reçus ou pris en charge sans être terminés.', 'staff.contact-messages'],
            ];

            foreach ($files as [$cle, $niveau, $titre, $texte, $route]) {
                $nombre = (int) ($aTraiter[$cle] ?? 0);

                if ($nombre === 0) {
                    continue;
                }

                $alertes[$niveau][] = [
                    'titre' => $titre.' · '.$this->nombre($nombre),
                    'texte' => $texte,
                    'route' => $route !== null && MenuDuBackOffice::autorise($membre, $route) ? $route : null,
                ];
            }
        }

        return $alertes;
    }

    // ------------------------------------------------ ACTIONS RAPIDES (§10)

    /**
     * Les raccourcis que CETTE personne peut réellement suivre.
     *
     * @return list<array{libelle: string, icone: string, route: string, primaire: bool}>
     */
    public function actionsRapides(User $membre): array
    {
        $candidates = [
            ['Traiter les signalements', 'fact_check', 'staff.verifications'],
            ['Gérer les utilisateurs', 'manage_accounts', 'staff.users'],
            ['Créer un cours', 'school', 'staff.courses'],
            ['Publier un article', 'edit_note', 'staff.posts'],
            ['Voir les alertes', 'shield', 'staff.alerts'],
            ['Voir les logs', 'history_edu', 'staff.audit'],
        ];

        $actions = [];

        foreach ($candidates as [$libelle, $icone, $route]) {
            if (MenuDuBackOffice::autorise($membre, $route)) {
                // Une seule action primaire par zone (§29) : la première permise.
                $actions[] = ['libelle' => $libelle, 'icone' => $icone, 'route' => $route, 'primaire' => $actions === []];
            }
        }

        return $actions;
    }

    // ---------------------------------------------- FACT-CHECKING (§12)

    /**
     * @return array<string, mixed>
     */
    private function factChecking(PeriodeDAnalyse $periode): array
    {
        $v = $this->depot->verifications($periode);
        $totalVerdicts = array_sum($v['verdicts']);

        $verdicts = [];

        foreach ($v['verdicts'] as $valeur => $nombre) {
            $verdict = VerdictFactCheck::tryFrom((string) $valeur);

            $verdicts[] = [
                'libelle' => $verdict?->libelle() ?? (string) $valeur,
                'couleur' => $verdict?->couleurBarre() ?? '#64748B',
                'nombre' => $nombre,
                'part' => $this->taux($nombre, $totalVerdicts) ?? 0.0,
            ];
        }

        $types = [];

        foreach ($v['types'] as $valeur => $nombre) {
            $type = TypeContenu::tryFrom((string) $valeur);

            $types[] = [
                'libelle' => $type?->libelle() ?? (string) $valeur,
                'icone' => $type?->icone() ?? 'description',
                'nombre' => $nombre,
                'part' => $this->taux($nombre, $v['soumises']) ?? 0.0,
            ];
        }

        return [
            'stats' => $v,
            'variation' => $this->variation($v['soumises'], $v['soumises_precedente']),
            'duree' => $this->duree($v['duree_moyenne_secondes']),
            'etiquettes' => array_column($periode->tranches(), 'etiquette'),
            'verdicts' => $verdicts,
            'total_verdicts' => $totalVerdicts,
            'types' => $types,
        ];
    }

    // ------------------------------------------- CONTENU ET ÉDITORIAL (§13-19)

    /**
     * @return array<string, mixed>
     */
    private function contenu(User $membre, PeriodeDAnalyse $periode): array
    {
        ['blog' => $blog, 'actualites' => $actualites] = $this->editorialVisible($membre, $periode);

        $lignes = [];

        foreach ([['Articles de blog', 'edit_note', $blog, 'staff.posts'], ['Actualités', 'newspaper', $actualites, 'staff.hub-news']] as [$libelle, $icone, $d, $route]) {
            if ($d !== null) {
                $lignes[] = [
                    'libelle' => $libelle, 'icone' => $icone, 'route' => $route,
                    'total' => $d['total'], 'publies' => $d['publies'], 'publies_periode' => $d['publies_periode'],
                    'vues' => $d['vues'], 'variation_vues' => $this->variation($d['vues'], $d['vues_precedente']),
                ];
            }
        }

        /*
         | Faits vérifiés et cours : volumes publiés seulement. Leur date de
         | publication n'est pas horodatée de façon homogène et leurs visites ne
         | sont pas suivies — `null` partout ailleurs, « non mesuré » à l'écran.
         */
        $volumes = $this->chiffres->volumes();

        foreach ([['Faits vérifiés', 'verified', 'faits', 'staff.knowledge'], ['Cours', 'school', 'cours', 'staff.courses']] as [$libelle, $icone, $cle, $route]) {
            $lignes[] = [
                'libelle' => $libelle, 'icone' => $icone,
                'route' => MenuDuBackOffice::autorise($membre, $route) ? $route : null,
                'total' => null, 'publies' => $volumes[$cle], 'publies_periode' => null,
                'vues' => null, 'variation_vues' => null,
            ];
        }

        return ['lignes' => $lignes];
    }

    /**
     * Blog et actualités, chacun `null` si l'écran de gestion est refusé.
     *
     * @return array{blog: array<string, mixed>|null, actualites: array<string, mixed>|null}
     */
    private function editorialVisible(User $membre, PeriodeDAnalyse $periode): array
    {
        $e = $this->depot->editorial($periode);

        return [
            'blog' => MenuDuBackOffice::autorise($membre, 'staff.posts') ? $e['blog'] : null,
            'actualites' => MenuDuBackOffice::autorise($membre, 'staff.hub-news') ? $e['actualites'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorial(User $membre, PeriodeDAnalyse $periode): array
    {
        $e = $this->editorialVisible($membre, $periode);
        $performants = $this->depot->contenusPerformants($periode);

        $cartes = [];

        foreach (['blog' => 'Blog', 'actualites' => 'Actualités'] as $cle => $titre) {
            $d = $e[$cle];

            if ($d === null) {
                continue;
            }

            $cartes[$cle] = [
                'titre' => $titre,
                'donnees' => $d,
                'variation_vues' => $this->variation($d['vues'], $d['vues_precedente']),
                /*
                 | CTR : UNIQUEMENT là où les clics existent, et en cumul — la
                 | colonne n'est pas horodatée. Pour le blog, aucun clic n'est
                 | compté : le taux est `null`, affiché « non mesuré ».
                 */
                'ctr_cumul' => $d['clics_cumul'] !== null ? $this->taux($d['clics_cumul'], $d['vues_cumul']) : null,
            ];
        }

        $voitBlog = $e['blog'] !== null;
        $voitActus = $e['actualites'] !== null;

        $lignes = array_values(array_filter(
            $performants['lignes'],
            static fn (array $l): bool => ($l['type'] === 'blog' && $voitBlog) || ($l['type'] === 'actualite' && $voitActus),
        ));

        foreach ($lignes as &$ligne) {
            $ligne['ctr_cumul'] = $ligne['clics_cumul'] !== null ? $this->taux($ligne['clics_cumul'], $ligne['vues_cumul']) : null;
            $ligne['route_publique'] = $ligne['type'] === 'blog' ? 'public.blog.show' : 'public.fact';
            $ligne['route_gestion'] = $ligne['type'] === 'blog' ? 'staff.posts' : 'staff.hub-news';
        }
        unset($ligne);

        return [
            'cartes' => $cartes,
            'etiquettes' => array_column($periode->tranches(), 'etiquette'),
            'classement' => $performants['classement'],
            'lignes' => $lignes,
        ];
    }

    // --------------------------------------------------- ENGAGEMENT (§20)

    /**
     * @return array<string, mixed>
     */
    private function engagement(PeriodeDAnalyse $periode): array
    {
        $u = $this->depot->utilisateurs($periode);
        $e = $this->depot->engagement($periode);
        $v = $this->depot->verifications($periode);

        $mesures = [
            ['Utilisateurs actifs', $u['actifs'], null, 'Dernière connexion dans la période.'],
            ['Nouveaux utilisateurs', $u['nouveaux'], $u['nouveaux_precedente'], null],
            ['Sessions', null, null, 'Non mesuré : la table des sessions ne garde que les sessions ouvertes.'],
            ['Vérifications', $v['soumises'], $v['soumises_precedente'], null],
            ['Sujets du forum', $e['sujets'], $e['sujets_precedente'], null],
            ['Réponses (commentaires)', $e['reponses'], $e['reponses_precedente'], null],
            ['Réactions', $e['reactions'], $e['reactions_precedente'], 'Mentions « j’aime » sur le forum.'],
            ['Leçons terminées', $e['lecons_terminees'], $e['lecons_terminees_precedente'], 'Progression LMS.'],
        ];

        return [
            'mesures' => array_map(fn (array $m): array => [
                'libelle' => $m[0],
                'valeur' => $m[1],
                'variation' => $m[1] !== null && $m[2] !== null ? $this->variation($m[1], $m[2]) : null,
                'note' => $m[3],
            ], $mesures),
            'serie_nouveaux' => $u['serie_nouveaux'],
            'etiquettes' => array_column($periode->tranches(), 'etiquette'),
        ];
    }

    // ---------------------------------------------------------- LMS (§21)

    /**
     * @return array<string, mixed>
     */
    private function lms(PeriodeDAnalyse $periode): array
    {
        $l = $this->depot->lms($periode);

        return [
            'stats' => $l,
            'taux_reussite' => $this->taux((int) $l['examens_reussis'], (int) $l['examens_corriges']),
            'variation_inscriptions' => $this->variation((int) $l['inscriptions'], (int) $l['inscriptions_precedente']),
            'variation_termines' => $this->variation((int) $l['cours_termines'], (int) $l['cours_termines_precedente']),
            'variation_certificats' => $this->variation((int) $l['certificats'], (int) $l['certificats_precedente']),
            'etiquettes' => array_column($periode->tranches(), 'etiquette'),
        ];
    }

    // ------------------------------------------------ ACTIVITÉ RÉCENTE (§22)

    /**
     * La frise : les derniers événements des sources que cette personne a le
     * droit de voir, fusionnés et datés.
     *
     * @return list<array<string, mixed>>
     */
    public function activiteRecente(User $membre, int $limite = 8): array
    {
        $sources = [
            // source => [route qui en garde la lecture, icône, libellé d'événement, ton]
            'inscriptions' => ['staff.users', 'person_add', 'Nouvel utilisateur', 'info'],
            'verifications_soumises' => ['staff.verifications', 'report', 'Nouveau signalement', 'warn'],
            'verifications_terminees' => ['staff.verifications', 'task_alt', 'Vérification terminée', 'pub'],
            'faits' => ['staff.knowledge', 'verified', 'Fait publié', 'pub'],
            'actualites' => ['staff.hub-news', 'newspaper', 'Actualité publiée', 'pub'],
            'blog' => ['staff.posts', 'edit_note', 'Article publié', 'pub'],
            'certificats' => ['staff.certificates', 'workspace_premium', 'Certificat délivré', 'premium'],
            'audit' => ['staff.audit', 'shield', 'Action tracée', 'draft'],
        ];

        $permises = array_keys(array_filter(
            $sources,
            static fn (array $s): bool => MenuDuBackOffice::autorise($membre, $s[0]),
        ));

        $evenements = array_slice($this->depot->evenementsRecents($permises, $limite), 0, $limite);

        return array_map(static function (array $e) use ($sources): array {
            [$route, $icone, $evenement, $ton] = $sources[$e['source']];

            /** @var Carbon $quand */
            $quand = $e['quand'];

            return [
                'icone' => $icone,
                'evenement' => $evenement,
                'titre' => $e['titre'],
                'acteur' => $e['acteur'],
                'detail' => $e['detail'],
                'quand' => $quand,
                'route' => $route,
                'ton' => $ton,
            ];
        }, $evenements);
    }

    // -------------------------------------------------------------- outils

    private function nombre(int|float $valeur): string
    {
        return number_format((float) $valeur, 0, ',', ' ');
    }

    private function duree(?int $secondes): ?string
    {
        if ($secondes === null) {
            return null;
        }

        return match (true) {
            $secondes < 60 => $secondes.' s',
            $secondes < 3600 => round($secondes / 60).' min',
            $secondes < 86400 => round($secondes / 3600, 1).' h',
            default => round($secondes / 86400, 1).' j',
        };
    }
}
