<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotStatistiquesDuTableauDeBord;
use App\Core\Application\Statistiques\PeriodeDAnalyse;
use App\Enums\StatutCopieExamen;
use App\Enums\StatutVerification;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Certificate;
use App\Models\ContentLike;
use App\Models\ContentVisit;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\HubArticle;
use App\Models\LessonCompletion;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use App\Models\VisitorHit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Les agrégats du centre de pilotage, en Eloquent.
 *
 * ## Aucune fonction de date propre à un moteur… sauf deux, isolées
 *
 * Les séries se groupent par `DATE(colonne)`, que MySQL et SQLite comprennent
 * tous deux, puis se replient en semaines ou en mois EN PHP. Seules l'heure du
 * jour et la durée moyenne exigent une expression propre au moteur : elles sont
 * isolées dans `heure()` et `dureeMoyenne()`, et c'est tout. Une expression
 * MySQL glissée ailleurs passerait en production et casserait en test — là où
 * on la découvrirait le plus tard.
 *
 * ## Le cache
 *
 * Deux minutes, et une VERSION dans la clé. Le pilote `database` ne connaît pas
 * les étiquettes : incrémenter la version invalide d'un geste tous les agrégats
 * de toutes les périodes, sans devoir connaître leurs clés.
 */
final class DepotStatistiquesDuTableauDeBordEloquent implements DepotStatistiquesDuTableauDeBord
{
    private const TTL_SECONDES = 120;

    private const CLE_VERSION = 'staff.pilotage.version';

    public function utilisateurs(PeriodeDAnalyse $periode): array
    {
        return $this->memoriser('utilisateurs', $periode, function () use ($periode): array {
            $citoyens = static fn (): Builder => User::query()
                ->role(UserRole::Citoyen->value)
                ->whereNull('anonymized_at');

            return [
                /*
                 | `last_login_at` ne garde que la DERNIÈRE connexion. Pour une
                 | période qui court jusqu'à maintenant, « connecté depuis le
                 | début » est donc exact. Pour une période passée, la valeur a
                 | pu être écrasée depuis : on rend `null` plutôt qu'un chiffre
                 | faux.
                 */
                'actifs' => $periode->seTermineMaintenant()
                    ? $citoyens()->where('last_login_at', '>=', $periode->debut)->count()
                    : null,
                'nouveaux' => $this->dans($citoyens(), 'users.created_at', $periode)->count(),
                'nouveaux_precedente' => $this->dans($citoyens(), 'users.created_at', $periode->precedente())->count(),
                'serie_nouveaux' => $this->serie($citoyens(), 'users.created_at', $periode),
            ];
        });
    }

    public function verifications(PeriodeDAnalyse $periode): array
    {
        return $this->memoriser('verifications', $periode, function () use ($periode): array {
            $soumises = fn (PeriodeDAnalyse $p): Builder => $this->dans(Verification::query(), 'verifications.created_at', $p);

            /** @var array<string, int> $parStatut */
            $parStatut = $soumises($periode)
                ->selectRaw('status, COUNT(*) as nombre')
                ->groupBy('status')
                ->pluck('nombre', 'status')
                ->map(static fn ($n): int => (int) $n)
                ->all();

            $enCours = array_sum(array_intersect_key($parStatut, array_flip([
                StatutVerification::EnFile->value,
                StatutVerification::EnCours->value,
                StatutVerification::EnAttenteDeReprise->value,
            ])));

            $moyenne = $soumises($periode)
                ->where('status', StatutVerification::Terminee->value)
                ->whereNotNull('completed_at')
                ->selectRaw($this->dureeMoyenne().' as moyenne')
                ->value('moyenne');

            /** @var array<string, int> $verdicts */
            $verdicts = $soumises($periode)
                ->join('analysis_reports', 'analysis_reports.verification_id', '=', 'verifications.id')
                ->whereNotNull('analysis_reports.verdict')
                ->selectRaw('analysis_reports.verdict as verdict, COUNT(*) as nombre')
                ->groupBy('analysis_reports.verdict')
                ->orderByDesc('nombre')
                ->pluck('nombre', 'verdict')
                ->map(static fn ($n): int => (int) $n)
                ->all();

            /** @var array<string, int> $types */
            $types = $soumises($periode)
                ->selectRaw('type, COUNT(*) as nombre')
                ->groupBy('type')
                ->orderByDesc('nombre')
                ->pluck('nombre', 'type')
                ->map(static fn ($n): int => (int) $n)
                ->all();

            return [
                'soumises' => array_sum($parStatut),
                'soumises_precedente' => $soumises($periode->precedente())->count(),
                'terminees' => $parStatut[StatutVerification::Terminee->value] ?? 0,
                'terminees_precedente' => $soumises($periode->precedente())
                    ->where('status', StatutVerification::Terminee->value)->count(),
                'en_cours' => $enCours,
                'revue_humaine' => $parStatut[StatutVerification::RevueHumaine->value] ?? 0,
                'echouees' => $parStatut[StatutVerification::Echouee->value] ?? 0,
                'duree_moyenne_secondes' => $moyenne === null ? null : (int) round((float) $moyenne),
                'serie' => $this->serie(Verification::query(), 'verifications.created_at', $periode),
                'verdicts' => $verdicts,
                'types' => $types,
            ];
        });
    }

    public function verificationsEnAttente(): int
    {
        return Verification::query()
            ->whereIn('status', [
                StatutVerification::EnFile->value,
                StatutVerification::EnCours->value,
                StatutVerification::EnAttenteDeReprise->value,
                StatutVerification::RevueHumaine->value,
            ])
            ->count();
    }

    public function visites(PeriodeDAnalyse $periode): array
    {
        return $this->memoriser('visites', $periode, function () use ($periode): array {
            $somme = static fn (PeriodeDAnalyse $p): int => (int) VisitorHit::query()
                ->where('day', '>=', $p->debut->toDateString())
                ->where('day', '<=', $p->fin->subSecond()->toDateString())
                ->sum('hits');

            $serie = null;

            // Le compteur est JOURNALIER : il n'existe aucune ventilation par
            // heure, et en inventer une serait dessiner une courbe plate.
            if ($periode->granularite() !== 'heure') {
                $tranches = $periode->tranches();
                $serie = array_fill(0, count($tranches), 0);

                $parJour = VisitorHit::query()
                    ->where('day', '>=', $periode->debut->toDateString())
                    ->where('day', '<=', $periode->fin->subSecond()->toDateString())
                    ->selectRaw('day as jour, SUM(hits) as total')
                    ->groupBy('day')
                    ->pluck('total', 'jour');

                foreach ($parJour as $jour => $total) {
                    $rang = $this->rangDuJour((string) $jour, $tranches);

                    if ($rang !== null) {
                        $serie[$rang] += (int) $total;
                    }
                }
            }

            return [
                'pages_vues' => $somme($periode),
                'pages_vues_precedente' => $somme($periode->precedente()),
                'serie' => $serie,
            ];
        });
    }

    public function editorial(PeriodeDAnalyse $periode): array
    {
        return $this->memoriser('editorial', $periode, fn (): array => [
            'blog' => $this->unTypeDeContenu(new BlogPost, $periode, avecClics: false),
            'actualites' => $this->unTypeDeContenu(new HubArticle, $periode, avecClics: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function unTypeDeContenu(BlogPost|HubArticle $modele, PeriodeDAnalyse $periode, bool $avecClics): array
    {
        $morph = $modele->getMorphClass();
        $requete = static fn (): Builder => $modele->newQuery();

        $visites = fn (PeriodeDAnalyse $p): Builder => $this->dans(
            ContentVisit::query()->where('visitable_type', $morph)->where('is_bot', false),
            'visited_at',
            $p,
        );

        $plusVu = $visites($periode)
            ->selectRaw('visitable_id, COUNT(*) as nombre')
            ->groupBy('visitable_id')
            ->orderByDesc('nombre')
            ->first();

        $top = null;

        if ($plusVu !== null) {
            $article = $requete()->whereKey($plusVu->getAttribute('visitable_id'))->first(['id', 'title', 'slug']);

            if ($article !== null) {
                $top = [
                    'titre' => (string) $article->getAttribute('title'),
                    'slug' => (string) $article->getAttribute('slug'),
                    'vues' => (int) $plusVu->getAttribute('nombre'),
                ];
            }
        }

        $plusVuCumul = $requete()->where('is_published', true)->where('views', '>', 0)
            ->orderByDesc('views')->first(['title', 'slug', 'views']);

        return [
            'total' => $requete()->count(),
            'publies' => $requete()->where('is_published', true)->count(),
            'publies_periode' => $this->dans($requete()->where('is_published', true), 'published_at', $periode)->count(),
            'vues' => $visites($periode)->count(),
            'vues_precedente' => $visites($periode->precedente())->count(),
            'visiteurs' => (int) $visites($periode)->selectRaw('COUNT(DISTINCT visitor_hash) as nombre')->value('nombre'),
            'serie_vues' => $this->serie(
                ContentVisit::query()->where('visitable_type', $morph)->where('is_bot', false),
                'visited_at',
                $periode,
            ),
            'top_periode' => $top,
            'vues_cumul' => (int) $requete()->where('is_published', true)->sum('views'),
            /*
             | Les clics n'existent que pour les actualités : `source_clicks`
             | compte les départs vers la preuve citée. Le blog n'a aucun lien
             | suivi — `null`, et non zéro.
             |
             | Et ils sont CUMULÉS : la colonne s'incrémente sans horodatage.
             | Aucune ventilation par période n'est donc possible.
             */
            'clics_cumul' => $avecClics ? (int) $requete()->where('is_published', true)->sum('source_clicks') : null,
            'plus_vu_cumul' => $plusVuCumul === null ? null : [
                'titre' => (string) $plusVuCumul->getAttribute('title'),
                'slug' => (string) $plusVuCumul->getAttribute('slug'),
                'vues' => (int) $plusVuCumul->getAttribute('views'),
            ],
            // Aucune de ces trois mesures n'est collectée aujourd'hui.
            'clics_periode' => null,
            'partages' => null,
            'temps_moyen' => null,
        ];
    }

    public function contenusPerformants(PeriodeDAnalyse $periode, int $limite = 8): array
    {
        return $this->memoriser('contenus-'.$limite, $periode, function () use ($periode, $limite): array {
            $modeles = [
                (new BlogPost)->getMorphClass() => ['blog', BlogPost::class],
                (new HubArticle)->getMorphClass() => ['actualite', HubArticle::class],
            ];

            $classement = $this->dans(
                ContentVisit::query()->whereIn('visitable_type', array_keys($modeles))->where('is_bot', false),
                'visited_at',
                $periode,
            )
                ->selectRaw('visitable_type, visitable_id, COUNT(*) as vues, COUNT(DISTINCT visitor_hash) as visiteurs')
                ->groupBy('visitable_type', 'visitable_id')
                ->orderByDesc('vues')
                ->limit($limite)
                ->get();

            if ($classement->isNotEmpty()) {
                $lignes = [];

                foreach ($classement->groupBy('visitable_type') as $morph => $groupe) {
                    [$type, $classe] = $modeles[(string) $morph];

                    $articles = $classe::query()
                        ->whereKey($groupe->pluck('visitable_id')->all())
                        ->get()
                        ->keyBy('id');

                    foreach ($groupe as $rang) {
                        $article = $articles->get($rang->getAttribute('visitable_id'));

                        if ($article instanceof Model) {
                            $lignes[] = $this->ligneDeContenu(
                                $type,
                                $article,
                                (int) $rang->getAttribute('vues'),
                                (int) $rang->getAttribute('visiteurs'),
                            );
                        }
                    }
                }

                usort($lignes, static fn (array $a, array $b): int => $b['vues_periode'] <=> $a['vues_periode']);

                return ['classement' => 'vues', 'lignes' => $lignes];
            }

            /*
             | AUCUNE visite sur la période : un classement par vues serait un
             | classement de zéros. On montre alors ce qui a été publié en
             | dernier, et l'écran dit que ce n'est pas un palmarès.
             */
            $recents = [];

            foreach ($modeles as [$type, $classe]) {
                foreach ($classe::query()->where('is_published', true)->latest('published_at')->limit($limite)->get() as $article) {
                    $recents[] = $this->ligneDeContenu($type, $article, 0, 0);
                }
            }

            usort($recents, static fn (array $a, array $b): int => strcmp((string) $b['publie_le'], (string) $a['publie_le']));

            return ['classement' => 'recents', 'lignes' => array_slice($recents, 0, $limite)];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function ligneDeContenu(string $type, Model $article, int $vues, int $visiteurs): array
    {
        $publieLe = $article->getAttribute('published_at');

        return [
            'type' => $type,
            'titre' => (string) $article->getAttribute('title'),
            'slug' => (string) $article->getAttribute('slug'),
            'publie' => (bool) $article->getAttribute('is_published'),
            'publie_le' => $publieLe instanceof \DateTimeInterface ? $publieLe->format('Y-m-d H:i:s') : null,
            'vues_periode' => $vues,
            'visiteurs_periode' => $visiteurs,
            'vues_cumul' => (int) $article->getAttribute('views'),
            'clics_cumul' => $type === 'actualite' ? (int) $article->getAttribute('source_clicks') : null,
        ];
    }

    public function engagement(PeriodeDAnalyse $periode): array
    {
        return $this->memoriser('engagement', $periode, function () use ($periode): array {
            $mesures = [
                'sujets' => [static fn (): Builder => Thread::query(), 'threads.created_at'],
                'reponses' => [static fn (): Builder => Reply::query(), 'replies.created_at'],
                'reactions' => [static fn (): Builder => ContentLike::query(), 'content_likes.created_at'],
                'lecons_terminees' => [static fn (): Builder => LessonCompletion::query(), 'lesson_completions.completed_at'],
            ];

            $resultat = [];

            foreach ($mesures as $cle => [$requete, $colonne]) {
                $resultat[$cle] = $this->dans($requete(), $colonne, $periode)->count();
                $resultat[$cle.'_precedente'] = $this->dans($requete(), $colonne, $periode->precedente())->count();
            }

            return $resultat;
        });
    }

    public function lms(PeriodeDAnalyse $periode): array
    {
        return $this->memoriser('lms', $periode, function () use ($periode): array {
            $copies = fn (): Builder => $this->dans(
                ExamAttempt::query()->where('status', StatutCopieExamen::Corrigee->value),
                'submitted_at',
                $periode,
            );

            return [
                'apprenants_actifs' => (int) $this->dans(LessonCompletion::query(), 'completed_at', $periode)
                    ->selectRaw('COUNT(DISTINCT user_id) as nombre')->value('nombre'),
                'inscriptions' => $this->dans(Enrollment::query(), 'created_at', $periode)->count(),
                'inscriptions_precedente' => $this->dans(Enrollment::query(), 'created_at', $periode->precedente())->count(),
                'cours_termines' => $this->dans(Enrollment::query(), 'completed_at', $periode)->count(),
                'cours_termines_precedente' => $this->dans(Enrollment::query(), 'completed_at', $periode->precedente())->count(),
                'examens_soumis' => $this->dans(ExamAttempt::query()->whereNotNull('submitted_at'), 'submitted_at', $periode)->count(),
                'examens_corriges' => $copies()->count(),
                'examens_reussis' => $copies()->where('passed', true)->count(),
                'certificats' => $this->dans(Certificate::query()->whereNull('revoked_at'), 'issued_at', $periode)->count(),
                'certificats_precedente' => $this->dans(Certificate::query()->whereNull('revoked_at'), 'issued_at', $periode->precedente())->count(),
                'serie_inscriptions' => $this->serie(Enrollment::query(), 'created_at', $periode),
                'serie_completions' => $this->serie(Enrollment::query(), 'completed_at', $periode),
            ];
        });
    }

    public function evenementsRecents(array $sources, int $parSource = 5): array
    {
        $evenements = [];

        foreach (array_unique($sources) as $source) {
            $lignes = match ($source) {
                'inscriptions' => User::query()->role(UserRole::Citoyen->value)->whereNull('anonymized_at')
                    ->latest('created_at')->limit($parSource)->get(['id', 'uuid', 'name', 'city', 'created_at'])
                    ->map(static fn (User $u): array => [
                        'quand' => $u->created_at, 'titre' => $u->name, 'acteur' => null,
                        'detail' => $u->city, 'cle' => $u->uuid,
                    ]),

                'verifications_soumises' => Verification::query()->latest('created_at')->limit($parSource)
                    ->get(['id', 'uuid', 'type', 'status', 'claim', 'created_at'])
                    ->map(static fn (Verification $v): array => [
                        'quand' => $v->created_at, 'titre' => filled($v->claim) ? $v->apercu(90) : $v->type->libelle(),
                        'acteur' => null, 'detail' => $v->reference().' · '.$v->status->libelle(), 'cle' => $v->uuid,
                    ]),

                'verifications_terminees' => Verification::query()->whereNotNull('completed_at')
                    ->where('status', StatutVerification::Terminee->value)
                    ->latest('completed_at')->limit($parSource)
                    ->get(['id', 'uuid', 'type', 'claim', 'completed_at'])
                    ->map(static fn (Verification $v): array => [
                        'quand' => $v->completed_at, 'titre' => filled($v->claim) ? $v->apercu(90) : $v->type->libelle(),
                        'acteur' => null, 'detail' => $v->reference(), 'cle' => $v->uuid,
                    ]),

                'faits' => VerifiedFact::query()->where('is_published', true)->latest('created_at')->limit($parSource)
                    ->get(['uuid', 'title', 'verdict', 'created_at'])
                    ->map(static fn (VerifiedFact $f): array => [
                        'quand' => $f->created_at, 'titre' => $f->title, 'acteur' => null,
                        'detail' => $f->verdict->libelle(), 'cle' => $f->uuid,
                    ]),

                'actualites' => HubArticle::query()->where('is_published', true)->whereNotNull('published_at')
                    ->latest('published_at')->limit($parSource)->get(['slug', 'title', 'published_at'])
                    ->map(static fn (HubArticle $a): array => [
                        'quand' => $a->published_at, 'titre' => $a->title, 'acteur' => null, 'detail' => null, 'cle' => $a->slug,
                    ]),

                'blog' => BlogPost::query()->where('is_published', true)->whereNotNull('published_at')
                    ->latest('published_at')->limit($parSource)->get(['slug', 'title', 'published_at'])
                    ->map(static fn (BlogPost $a): array => [
                        'quand' => $a->published_at, 'titre' => $a->title, 'acteur' => null, 'detail' => null, 'cle' => $a->slug,
                    ]),

                'certificats' => Certificate::query()->whereNull('revoked_at')->whereNotNull('issued_at')
                    ->latest('issued_at')->limit($parSource)->get(['uuid', 'holder_name', 'course_title', 'issued_at'])
                    ->map(static fn (Certificate $c): array => [
                        'quand' => $c->issued_at, 'titre' => (string) $c->course_title, 'acteur' => $c->holder_name,
                        'detail' => null, 'cle' => $c->uuid,
                    ]),

                'audit' => AuditLog::query()->latest('created_at')->limit($parSource)
                    ->get(['uuid', 'actor_label', 'action', 'module', 'target_label', 'created_at'])
                    ->map(static fn (AuditLog $t): array => [
                        'quand' => $t->created_at, 'titre' => (string) $t->action, 'acteur' => $t->actor_label,
                        'detail' => trim($t->module.($t->target_label ? ' — '.$t->target_label : '')), 'cle' => $t->uuid,
                    ]),

                default => collect(),
            };

            foreach ($lignes as $ligne) {
                // Une ligne sans date ne se place pas sur une frise.
                if ($ligne['quand'] === null) {
                    continue;
                }

                $evenements[] = ['source' => $source] + $ligne;
            }
        }

        usort($evenements, static fn (array $a, array $b): int => $b['quand'] <=> $a['quand']);

        return $evenements;
    }

    public function oublier(): void
    {
        Cache::forever(self::CLE_VERSION, ((int) Cache::get(self::CLE_VERSION, 0)) + 1);
    }

    /**
     * @template T of array
     *
     * @param  \Closure(): T  $calcul
     * @return T
     */
    private function memoriser(string $agregat, PeriodeDAnalyse $periode, \Closure $calcul): array
    {
        $cle = sprintf('staff.pilotage.v%d.%s.%s', (int) Cache::get(self::CLE_VERSION, 0), $agregat, $periode->cle());

        return Cache::remember($cle, self::TTL_SECONDES, $calcul);
    }

    /**
     * Borne une requête à la période, en intervalle semi-ouvert.
     *
     * @param  Builder<covariant Model>  $requete
     * @return Builder<covariant Model>
     */
    private function dans(Builder $requete, string $colonne, PeriodeDAnalyse $periode): Builder
    {
        return $requete
            ->where($colonne, '>=', $periode->debut)
            ->where($colonne, '<', $periode->fin);
    }

    /**
     * Une série de comptages, une valeur par tranche — les tranches vides à zéro.
     *
     * Les jours sans ligne sont rendus à ZÉRO et ce n'est pas un détail : une
     * courbe qui saute les jours vides transforme trois événements espacés
     * d'une semaine en une courbe qui monte.
     *
     * @param  Builder<covariant Model>  $requete
     * @return list<int>
     */
    private function serie(Builder $requete, string $colonne, PeriodeDAnalyse $periode): array
    {
        $tranches = $periode->tranches();
        $serie = array_fill(0, count($tranches), 0);

        if ($tranches === []) {
            return [];
        }

        $parHeure = $periode->granularite() === 'heure';
        $expression = $parHeure
            ? $this->heure($colonne, $requete->getModel()->getConnection()->getDriverName())
            : 'DATE('.$colonne.')';

        $lignes = $this->dans(clone $requete, $colonne, $periode)
            ->toBase()
            ->selectRaw($expression.' as cle, COUNT(*) as nombre')
            ->groupBy('cle')
            ->pluck('nombre', 'cle');

        foreach ($lignes as $cle => $nombre) {
            $rang = $parHeure
                ? $this->rangDeLHeure((int) $cle, $tranches)
                : $this->rangDuJour((string) $cle, $tranches);

            if ($rang !== null) {
                $serie[$rang] += (int) $nombre;
            }
        }

        return $serie;
    }

    /**
     * @param  list<array{debut: CarbonImmutable, fin: CarbonImmutable, etiquette: string}>  $tranches
     */
    private function rangDuJour(string $jour, array $tranches): ?int
    {
        $date = CarbonImmutable::parse(substr($jour, 0, 10))->startOfDay();

        foreach ($tranches as $rang => $tranche) {
            if ($date->gte($tranche['debut']->startOfDay()) && $date->lt($tranche['fin'])) {
                return $rang;
            }
        }

        return null;
    }

    /**
     * @param  list<array{debut: CarbonImmutable, fin: CarbonImmutable, etiquette: string}>  $tranches
     */
    private function rangDeLHeure(int $heure, array $tranches): ?int
    {
        foreach ($tranches as $rang => $tranche) {
            if ((int) $tranche['debut']->format('G') === $heure) {
                return $rang;
            }
        }

        return null;
    }

    private function heure(string $colonne, string $pilote): string
    {
        return match ($pilote) {
            'sqlite' => "CAST(strftime('%H', {$colonne}) AS INTEGER)",
            'pgsql' => "EXTRACT(HOUR FROM {$colonne})",
            default => "HOUR({$colonne})",
        };
    }

    /** Durée moyenne entre la soumission et la fin de l'analyse, en secondes. */
    private function dureeMoyenne(): string
    {
        return match ((new Verification)->getConnection()->getDriverName()) {
            'sqlite' => 'AVG((julianday(verifications.completed_at) - julianday(verifications.created_at)) * 86400.0)',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (verifications.completed_at - verifications.created_at)))',
            default => 'AVG(TIMESTAMPDIFF(SECOND, verifications.created_at, verifications.completed_at))',
        };
    }
}
