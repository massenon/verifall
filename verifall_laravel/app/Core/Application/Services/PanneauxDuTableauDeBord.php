<?php

declare(strict_types=1);

namespace App\Core\Application\Services;

use App\Enums\ThematiqueVerification;
use App\Enums\UserRole;
use App\Http\Middleware\CompterLeNavigateur;
use App\Models\AuditLog;
use App\Models\ContentReport;
use App\Models\CounterExpertise;
use App\Models\HubArticle;
use App\Models\User;
use App\Models\Verification;
use App\Models\VisitorHit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Les six panneaux que le prototype dessinait sans rien mesurer derrière.
 *
 * `compte-admin/dashboard.html` affiche dix panneaux. Six d'entre eux portaient
 * des nombres ÉCRITS EN DUR dans le HTML — « Général 427 », « 216 en attente »,
 * « Chrome 68 % ». Les transposer tels quels aurait produit un écran qui a
 * l'air de mesurer et qui n'observe rien : la pire chose qu'on puisse mettre
 * sur la première page après connexion.
 *
 * Chaque méthode ci-dessous répond à un panneau, et ne rend que ce qui est
 * réellement en base. Quand la base est vide, le panneau le DIT — il n'invente
 * pas une courbe.
 *
 * ## Séparé de `TableauDeBordPersonnel`, et pourquoi
 *
 * Celui-là répond à « qu'est-ce qui m'attend ? » : des compteurs d'attente,
 * jamais mis en cache, sur lesquels on agit tout de suite. Ceux-ci répondent à
 * « comment va la plateforme ? » : des agrégats sur des fenêtres de temps, plus
 * lourds, et qui supportent une minute de retard. Deux questions, deux régimes
 * de fraîcheur, deux services.
 */
final class PanneauxDuTableauDeBord
{
    private const TTL_SECONDES = 60;

    /** Deux semaines : assez pour voir une tendance, assez court pour tenir dans un graphe étroit. */
    private const FENETRE_JOURS = 14;

    /**
     * Panneau « Performance Articles » — Titre · Vues · Clics.
     *
     * « Clics » compte les départs vers `source_url`, pas les ouvertures de
     * l'article. C'est la seule mesure qui distingue un article LU d'un article
     * qui a réellement renvoyé quelqu'un à la preuve — et sur une plateforme de
     * fact-checking, c'est cette seconde chose qui compte.
     *
     * Le tri se fait sur les vues : c'est l'audience qu'on classe, et le taux
     * de clic se lit ensuite comme une qualité, pas comme un rang.
     *
     * @return Collection<int, HubArticle>
     */
    public function performanceDesArticles(int $combien = 5): Collection
    {
        return Cache::remember(
            'staff.tdb.articles',
            self::TTL_SECONDES,
            static fn (): Collection => HubArticle::query()
                ->where('is_published', true)
                ->orderByDesc('views')
                ->limit($combien)
                ->get(['uuid', 'slug', 'title', 'views', 'source_clicks']),
        );
    }

    /**
     * Panneau « Activité de Signalements » — une barre par jour sur deux semaines.
     *
     * Les jours SANS signalement sont rendus à zéro, et ce n'est pas un détail :
     * un graphe qui saute les jours vides transforme trois signalements espacés
     * d'une semaine en une courbe qui monte. La forme mentirait.
     *
     * @return list<array{jour: string, etiquette: string, nombre: int}>
     */
    public function activiteDesSignalements(): array
    {
        $debut = now()->subDays(self::FENETRE_JOURS - 1)->startOfDay();

        /** @var array<string, int> $parJour */
        $parJour = ContentReport::query()
            ->where('created_at', '>=', $debut)
            // `selectRaw` puis `pluck` sur des ALIAS, et non `pluck(DB::raw(…))` :
            // `pluck()` attend des noms de colonnes, pas des expressions. La
            // forme brute fonctionne par accident et ne se vérifie pas.
            ->selectRaw('DATE(created_at) as jour, COUNT(*) as nombre')
            ->groupBy('jour')
            ->orderBy('jour')
            ->pluck('nombre', 'jour')
            ->map(static fn ($n): int => (int) $n)
            ->all();

        $serie = [];

        for ($rang = 0; $rang < self::FENETRE_JOURS; $rang++) {
            $jour = $debut->copy()->addDays($rang);
            $cle = $jour->toDateString();

            $serie[] = [
                'jour' => $cle,
                'etiquette' => $jour->translatedFormat('d/m'),
                'nombre' => $parJour[$cle] ?? 0,
            ];
        }

        return $serie;
    }

    /**
     * Panneau « Nouveaux Citoyens ».
     *
     * Les comptes anonymisés sont écartés : après une demande de suppression
     * RGPD, il ne reste qu'un identifiant technique. L'afficher dans une liste
     * de bienvenue serait absurde, et rappellerait à l'écran quelqu'un qui a
     * précisément demandé à disparaître.
     *
     * @return Collection<int, User>
     */
    public function nouveauxCitoyens(int $combien = 10): Collection
    {
        return User::query()
            ->role(UserRole::Citoyen->value)
            ->whereNull('anonymized_at')
            ->latest('created_at')
            ->limit($combien)
            ->get(['id', 'uuid', 'name', 'username', 'city', 'email_verified_at', 'created_at']);
    }

    /**
     * Panneau « Journal d'Audit ».
     *
     * `actor_label` plutôt que la relation : le journal conserve le libellé de
     * l'auteur AU MOMENT DU GESTE. Un compte supprimé depuis ne doit pas
     * effacer la trace de ce qu'il a fait — c'est tout l'intérêt d'un journal
     * d'audit.
     *
     * @return Collection<int, AuditLog>
     */
    public function journalDAudit(int $combien = 6): Collection
    {
        return AuditLog::query()
            ->latest('created_at')
            ->limit($combien)
            ->get(['uuid', 'actor_label', 'action', 'module', 'target_label', 'created_at']);
    }

    /**
     * Panneau « Navigateurs — Analyse des visiteurs ».
     *
     * Alimenté par {@see CompterLeNavigateur}, qui ne
     * retient que la FAMILLE du navigateur et la date. Aucune adresse IP,
     * aucun identifiant, aucun cookie, aucun tiers : ce panneau ne peut pas
     * servir à suivre quelqu'un, par construction.
     *
     * @return array{total: int, familles: list<array{nom: string, hits: int, part: float}>}
     */
    public function navigateurs(): array
    {
        return Cache::remember('staff.tdb.navigateurs', self::TTL_SECONDES, static function (): array {
            $depuis = now()->subDays(self::FENETRE_JOURS - 1)->toDateString();

            /** @var array<string, int> $brut */
            $brut = VisitorHit::query()
                ->where('day', '>=', $depuis)
                ->selectRaw('browser, SUM(hits) as total')
                ->groupBy('browser')
                ->orderByDesc('total')
                ->pluck('total', 'browser')
                ->map(static fn ($n): int => (int) $n)
                ->all();

            $total = array_sum($brut);

            return [
                'total' => $total,
                'familles' => array_map(
                    static fn (string $nom, int $hits): array => [
                        'nom' => $nom,
                        'hits' => $hits,
                        // Le total ne peut pas être nul ici : si `$brut` est
                        // vide, `array_map` ne s'exécute pas.
                        'part' => round($hits * 100 / $total, 1),
                    ],
                    array_keys($brut),
                    array_values($brut),
                ),
            ];
        });
    }

    /**
     * Panneau « Top Thématiques ».
     *
     * La liste est CLOSE — six thèmes, {@see ThematiqueVerification} — et les
     * thèmes à zéro ne sont pas affichés : un panneau « Top » qui liste six
     * lignes dont quatre à zéro n'est plus un classement.
     *
     * Les vérifications sans thème sont comptées en « Général » : elles datent
     * d'avant la classification, et les écarter ferait mentir le total.
     *
     * @return list<array{theme: ThematiqueVerification, nombre: int}>
     */
    public function topThematiques(): array
    {
        return Cache::remember('staff.tdb.thematiques', self::TTL_SECONDES, static function (): array {
            /** @var array<string, int> $brut */
            $brut = Verification::query()
                ->selectRaw('theme, COUNT(*) as nombre')
                ->groupBy('theme')
                ->pluck('nombre', 'theme')
                ->map(static fn ($n): int => (int) $n)
                ->all();

            $compte = [];

            foreach ($brut as $valeur => $nombre) {
                $theme = ThematiqueVerification::tryFrom((string) $valeur)
                    ?? ThematiqueVerification::General;

                $compte[$theme->value] = ($compte[$theme->value] ?? 0) + $nombre;
            }

            arsort($compte);

            return array_map(
                static fn (string $valeur, int $nombre): array => [
                    'theme' => ThematiqueVerification::from($valeur),
                    'nombre' => $nombre,
                ],
                array_keys($compte),
                array_values($compte),
            );
        });
    }

    /**
     * Panneau « Verdicts contestés » — la file des contre-expertises ouvertes.
     *
     * ## Pourquoi ce panneau existe, et pourquoi il est en haut
     *
     * Une contestation dit qu'on s'est peut-être trompés. C'est le retour le
     * plus précieux qu'une plateforme de fact-checking reçoive, et le plus
     * facile à enterrer : il arrive par un formulaire, il n'a pas d'échéance,
     * et personne ne se plaint si on l'oublie — sauf la personne qui avait
     * raison.
     *
     * Sans cette file, la table `counter_expertises` serait une table
     * qu'on ÉCRIT et que personne ne LIT. Le chantier a déjà refusé cela deux
     * fois, et la règle vaut ici aussi.
     *
     * @return Collection<int, CounterExpertise>
     */
    public function contreExpertises(int $combien = 5): Collection
    {
        return CounterExpertise::query()
            ->with(['verification:id,uuid,claim', 'demandeur:id,uuid,name'])
            ->enAttente()
            // Les plus ANCIENNES d'abord : une file de contestations se traite
            // dans l'ordre d'arrivée, sinon les demandes gênantes descendent.
            ->oldest('created_at')
            ->limit($combien)
            ->get();
    }

    /**
     * Panneau « Dernières Activités de Fact-Checking » — Sujet · Statut · Assigné à.
     *
     * `with('assignee')` : sans ce chargement, la colonne « Assigné à » ferait
     * une requête par ligne. C'est exactement le genre de N+1 qu'un tableau de
     * bord multiplie, puisqu'il est la page la plus ouverte du back-office.
     *
     * @return Collection<int, Verification>
     */
    public function activitesDeFactChecking(int $combien = 8): Collection
    {
        return Verification::query()
            ->with('assignee:id,uuid,name')
            ->latest('created_at')
            ->limit($combien)
            /*
             | `id` EN PREMIER, et ce n'est pas décoratif : `reference()` compose
             | « VF-000123 » à partir de la clé primaire. Sans elle dans le
             | `select`, l'attribut est absent, `(string) null` vaut '' et
             | CHAQUE ligne affichait « VF-000000 » — la même référence, fausse,
             | sur l'écran où l'on confie du travail. Trouvé au navigateur : la
             | page se rendait sans la moindre erreur.
             */
            ->get(['id', 'uuid', 'claim', 'type', 'status', 'theme', 'assigned_to', 'assigned_at', 'created_at']);
    }
}
