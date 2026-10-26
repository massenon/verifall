<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotFicheDuCompte;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ConsultationHub;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\HistoriqueConnexion;
use App\Models\KycSubmission;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Models\Thread;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use App\Models\XpLog;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Implémentation Eloquent de la fiche d'un compte.
 *
 * Chaque onglet se décrit en quatre points, et rien d'autre ne varie :
 *
 *   · sa requête de BASE — les lignes de CE compte, jamais d'un autre ;
 *   · la correspondance clé publique → colonne SQL de ses tris ;
 *   · sa recherche et ses filtres ;
 *   · les colonnes lues et les relations chargées d'avance.
 *
 * La pagination, les deux compteurs et le départage sont communs.
 */
final class DepotFicheDuCompteEloquent implements DepotFicheDuCompte
{
    /**
     * Clé publique → colonne SQL, par onglet. La seule table de
     * correspondance : ce qui n'y figure pas n'atteint jamais `orderBy()`.
     */
    private const TRIS_SQL = [
        'verifications' => ['affirmation' => 'claim', 'type' => 'type', 'statut' => 'status', 'cout' => 'cost_units', 'creeLe' => 'created_at'],
        'cours' => ['statut' => 'status', 'progression' => 'progress', 'unites' => 'paid_units', 'inscritLe' => 'created_at', 'derniereActivite' => 'last_activity_at', 'termineLe' => 'completed_at'],
        'quiz' => ['score' => 'score', 'reussi' => 'passed', 'duree' => 'duration_s', 'passeLe' => 'created_at'],
        'devoirs' => ['statut' => 'status', 'note' => 'grade', 'remisLe' => 'created_at', 'corrigeLe' => 'reviewed_at'],
        'examens' => ['tentative' => 'attempt_no', 'statut' => 'status', 'score' => 'score_percent', 'reussi' => 'passed', 'soumisLe' => 'submitted_at'],
        'certificats' => ['code' => 'code', 'cours' => 'course_title', 'delivreLe' => 'issued_at'],
        'unites' => ['date' => 'created_at', 'mouvement' => 'delta', 'solde' => 'balance_after', 'libelle' => 'label'],
        'points' => ['date' => 'created_at', 'mouvement' => 'delta', 'solde' => 'balance_after', 'libelle' => 'label'],
        'paiements' => ['date' => 'created_at', 'pack' => 'pack', 'montant' => 'amount_fcfa', 'unites' => 'units', 'statut' => 'status'],
        'forum' => ['sujet' => 'title', 'reponses' => 'replies_count', 'creeLe' => 'created_at'],
        'journal' => ['date' => 'created_at', 'action' => 'action', 'module' => 'module'],
        'connexions' => ['date' => 'created_at', 'resultat' => 'resultat', 'espace' => 'espace'],
        'lectures-hub' => ['date' => 'created_at', 'rubrique' => 'rubrique', 'titre' => 'titre'],
        'verifications-assignees' => ['affirmation' => 'claim', 'statut' => 'status', 'assigneeLe' => 'assigned_at', 'creeLe' => 'created_at'],
        'cours-encadres' => ['titre' => 'title', 'publie' => 'is_published', 'inscrits' => 'enrollments_count', 'creeLe' => 'created_at'],
        'corrections-devoirs' => ['statut' => 'status', 'note' => 'grade', 'corrigeLe' => 'reviewed_at'],
        'corrections-examens' => ['statut' => 'status', 'score' => 'score_percent', 'assigneeLe' => 'assigned_at', 'corrigeLe' => 'graded_at'],
        'faits-rediges' => ['titre' => 'title', 'verdict' => 'verdict', 'publie' => 'is_published', 'reutilisations' => 'hits', 'creeLe' => 'created_at'],
    ];

    /**
     * Les colonnes réellement lues sur `users` pour la carte d'identité.
     *
     * Pas de `select *` : la ligne porte le secret 2FA chiffré, les codes de
     * secours, le hachage du mot de passe, le téléphone chiffré et l'empreinte
     * de la dernière adresse IP. Aucun n'est affiché.
     */
    private const COLONNES_DU_COMPTE = [
        'id', 'uuid', 'name', 'first_name', 'last_name', 'email', 'profession', 'city', 'locale',
        'units', 'xp', 'streak_days', 'streak_best', 'referral_code',
        'email_verified_at', 'otp_verified_at', 'kyc_verified_at',
        'suspended_at', 'suspension_reason', 'suspended_by',
        'two_factor_confirmed_at', 'last_login_at', 'deletion_requested_at', 'created_at',
    ];

    /** @return PageDeResultats<Model> */
    public function pourOnglet(string $onglet, User $compte, RequeteDataTables $requete): PageDeResultats
    {
        $base = $this->base($onglet, $compte);
        $total = (clone $base)->count();

        $filtree = clone $base;
        $this->filtrer($onglet, $filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            $colonne = self::TRIS_SQL[$onglet][$cle] ?? null;

            if ($colonne !== null) {
                $filtree->orderBy($colonne, $sens);
            }
        }

        $this->charger($onglet, $filtree);

        $lignes = $filtree
            // Départage stable : deux lignes de la même seconde ne changent pas
            // de page d'un rechargement à l'autre.
            ->orderByDesc($filtree->getModel()->getQualifiedKeyName())
            ->skip($requete->debut)
            ->take($requete->longueur)
            ->get();

        return new PageDeResultats(
            total: $total,
            filtres: $nombreFiltre,
            lignes: $lignes,
            tronque: $requete->export && $nombreFiltre > $requete->longueur,
        );
    }

    /**
     * @param  list<string>  $onglets
     * @return array<string, int>
     */
    public function compteurs(User $compte, array $onglets): array
    {
        $compteurs = [];

        foreach ($onglets as $onglet) {
            $compteurs[$onglet] = $this->base($onglet, $compte)->count();
        }

        return $compteurs;
    }

    public function compte(string $uuid): ?User
    {
        return User::query()
            ->select(self::COLONNES_DU_COMPTE)
            ->with(['roles:id,name', 'auteurDeLaSuspension:id,name'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function dernierDossierKyc(User $compte): ?KycSubmission
    {
        return KycSubmission::query()
            // Métadonnées SEULES : ni chemins de pièces, ni empreinte du numéro.
            ->select(['id', 'uuid', 'user_id', 'document_type', 'status', 'rejection_reason', 'reviewed_at', 'created_at'])
            ->where('user_id', $compte->id)
            ->latest('id')
            ->first();
    }

    // ═══════════════════════════════════════════════════════════ les onglets

    /**
     * Les lignes de CE compte pour cet onglet — la borne de sécurité de la
     * fiche. Tout ce qui suit (recherche, filtres, tri) ne fait que restreindre.
     *
     * @return Builder<covariant Model>
     */
    private function base(string $onglet, User $compte): Builder
    {
        return match ($onglet) {
            'verifications' => Verification::query()->where('user_id', $compte->id),
            'cours' => Enrollment::query()->where('user_id', $compte->id),
            'quiz' => QuizAttempt::query()->where('user_id', $compte->id),
            'devoirs' => AssignmentSubmission::query()->where('user_id', $compte->id),
            'examens' => ExamAttempt::query()->where('user_id', $compte->id),
            'certificats' => Certificate::query()->where('user_id', $compte->id),
            'unites' => UnitLog::query()->where('user_id', $compte->id),
            'points' => XpLog::query()->where('user_id', $compte->id),
            'paiements' => Payment::query()->where('user_id', $compte->id),
            'forum' => Thread::query()->where('user_id', $compte->id),

            /*
             | Ce que le compte a FAIT, et ce qu'on lui a fait. La cible est
             | reconnue par son type ET son uuid : `target_uuid` seul pourrait
             | désigner un cours ou un article portant par hasard le même.
             | Les deux branches ont leur index (`actor_id, created_at` et
             | `target_type, target_uuid`).
             */
            'journal' => AuditLog::query()->where(static fn (Builder $ou) => $ou
                ->where('actor_id', $compte->id)
                ->orWhere(static fn (Builder $cible) => $cible
                    ->where('target_type', User::class)
                    ->where('target_uuid', $compte->uuid))),

            'connexions' => HistoriqueConnexion::query()->where('user_id', $compte->id),
            'lectures-hub' => ConsultationHub::query()->where('user_id', $compte->id),

            'verifications-assignees' => Verification::query()->where('assigned_to', $compte->id),
            'cours-encadres' => Course::query()->where('instructor_id', $compte->id),
            'corrections-devoirs' => AssignmentSubmission::query()->where('reviewed_by', $compte->id),

            // Assignée à ce correcteur, OU notée par lui : une copie réassignée
            // après correction reste une correction qu'il a faite.
            'corrections-examens' => ExamAttempt::query()->where(static fn (Builder $ou) => $ou
                ->where('assigned_to', $compte->id)
                ->orWhere('graded_by', $compte->id)),

            'faits-rediges' => VerifiedFact::query()->where('created_by', $compte->id),

            default => throw new InvalidArgumentException("Onglet inconnu : {$onglet}"),
        };
    }

    /**
     * Colonnes lues et relations chargées d'avance.
     *
     * @param  Builder<covariant Model>  $requete
     */
    private function charger(string $onglet, Builder $requete): void
    {
        // Les cours supprimés (soft delete) restent nommés : une inscription à
        // un cours retiré du catalogue fait toujours partie de l'historique.
        $cours = static fn ($q) => $q->withTrashed()->select(['id', 'title']);

        match ($onglet) {
            'verifications', 'verifications-assignees' => $requete
                ->select(['id', 'user_id', 'assigned_to', 'type', 'theme', 'status', 'claim', 'cost_units', 'refunded_at', 'assigned_at', 'completed_at', 'created_at'])
                ->with(['report:id,verification_id,verdict', 'user:id,name']),

            'cours' => $requete
                ->select(['id', 'user_id', 'course_id', 'status', 'progress', 'paid_units', 'started_at', 'last_activity_at', 'completed_at', 'created_at'])
                ->with(['course' => $cours]),

            'quiz' => $requete
                ->select(['id', 'user_id', 'quiz_id', 'score', 'passed', 'duration_s', 'created_at'])
                ->with(['quiz:id,course_id,title,pass_score', 'quiz.course' => $cours]),

            'devoirs', 'corrections-devoirs' => $requete
                ->select(['id', 'user_id', 'assignment_id', 'status', 'grade', 'reviewed_by', 'reviewed_at', 'created_at'])
                ->with(['assignment:id,course_id,title', 'assignment.course' => $cours, 'reviewer:id,name', 'user:id,name']),

            'examens', 'corrections-examens' => $requete
                ->select(['id', 'user_id', 'exam_id', 'attempt_no', 'status', 'score_percent', 'passed', 'assigned_to', 'assigned_at', 'submitted_at', 'graded_at', 'created_at'])
                ->with(['exam' => static fn ($q) => $q->withTrashed()->select(['id', 'title']), 'correcteur:id,name', 'user:id,name']),

            'certificats' => $requete
                ->select(['id', 'code', 'course_title', 'issued_at', 'revoked_at']),

            'unites', 'points' => $requete
                ->select(['id', 'delta', 'balance_after', 'reason', 'label', 'created_at']),

            // Ni `phone_encrypted`, ni `provider_ref`, ni `failure_reason` : ce
            // sont des données de l'opérateur, pas de l'historique du compte.
            'paiements' => $requete
                ->select(['id', 'provider', 'pack', 'amount_fcfa', 'units', 'status', 'settled_at', 'created_at']),

            'forum' => $requete
                ->select(['id', 'forum_category_id', 'title', 'replies_count', 'official_verdict', 'is_locked', 'created_at'])
                ->with(['category:id,name']),

            // Ni `ip_hash`, ni `user_agent`, ni le diff avant/après : la fiche
            // dit QUI a fait QUOI et QUAND. Le détail reste à l'écran « Logs
            // d'audit », sous la même permission.
            'journal' => $requete
                ->select(['id', 'actor_id', 'actor_label', 'action', 'module', 'target_type', 'target_uuid', 'target_label', 'created_at']),

            // Ni `ip_hash` : l'empreinte ne sert qu'à corréler, jamais à s'afficher.
            'connexions' => $requete
                ->select(['id', 'espace', 'resultat', 'methode', 'navigateur', 'systeme', 'ip_tronquee', 'pays', 'created_at']),

            'lectures-hub' => $requete
                ->select(['id', 'rubrique', 'titre', 'action', 'created_at']),

            'cours-encadres' => $requete
                ->select(['id', 'title', 'level', 'is_published', 'published_at', 'created_at'])
                ->withCount('enrollments'),

            'faits-rediges' => $requete
                ->select(['id', 'title', 'verdict', 'is_published', 'hits', 'created_at']),

            default => throw new InvalidArgumentException("Onglet inconnu : {$onglet}"),
        };
    }

    /**
     * Recherche et filtres rapides. Les valeurs ont déjà passé la liste
     * blanche de `FicheDuCompte::filtresAutorises()`.
     *
     * @param  Builder<covariant Model>  $requete
     */
    private function filtrer(string $onglet, Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;
        $motif = '%'.$criteres->recherche.'%';
        $cherche = $criteres->recherche !== '';

        switch ($onglet) {
            case 'verifications':
            case 'verifications-assignees':
                $requete->when($cherche, static fn (Builder $q) => $q->where('claim', 'like', $motif));
                $requete->when(isset($filtres['statut']), static fn (Builder $q) => $q->where('status', $filtres['statut']));
                break;

            case 'cours':
                // Sous-requête et non `whereHas` : le cours peut être supprimé (soft
                // delete), et son titre reste cherchable dans l'historique.
                $requete->when($cherche, static fn (Builder $q) => $q->whereIn('course_id', Course::withTrashed()->select('id')->where('title', 'like', $motif)));
                $requete->when(isset($filtres['statut']), static fn (Builder $q) => $q->where('status', $filtres['statut']));
                break;

            case 'quiz':
                $requete->when($cherche, static fn (Builder $q) => $q->whereHas('quiz', static fn (Builder $c) => $c->where('title', 'like', $motif)));
                match ($filtres['reussite'] ?? null) {
                    'reussi' => $requete->where('passed', true),
                    'echoue' => $requete->where('passed', false),
                    default => null,
                };
                break;

            case 'devoirs':
            case 'corrections-devoirs':
                $requete->when($cherche, static fn (Builder $q) => $q->whereHas('assignment', static fn (Builder $c) => $c->where('title', 'like', $motif)));
                $requete->when(isset($filtres['statut']), static fn (Builder $q) => $q->where('status', $filtres['statut']));
                break;

            case 'examens':
            case 'corrections-examens':
                $requete->when($cherche, static fn (Builder $q) => $q->whereIn('exam_id', Exam::withTrashed()->select('id')->where('title', 'like', $motif)));
                $requete->when(isset($filtres['statut']), static fn (Builder $q) => $q->where('status', $filtres['statut']));
                break;

            case 'certificats':
                $requete->when($cherche, static fn (Builder $q) => $q->where(static fn (Builder $ou) => $ou
                    ->where('code', 'like', $motif)->orWhere('course_title', 'like', $motif)));
                match ($filtres['statut'] ?? null) {
                    'valide' => $requete->whereNull('revoked_at'),
                    'revoque' => $requete->whereNotNull('revoked_at'),
                    default => null,
                };
                break;

            case 'unites':
            case 'points':
                $requete->when($cherche, static fn (Builder $q) => $q->where('label', 'like', $motif));
                match ($filtres['sens'] ?? null) {
                    'credit' => $requete->where('delta', '>', 0),
                    'debit' => $requete->where('delta', '<', 0),
                    default => null,
                };
                break;

            case 'paiements':
                $requete->when($cherche, static fn (Builder $q) => $q->where('pack', 'like', $motif));
                $requete->when(isset($filtres['statut']), static fn (Builder $q) => $q->where('status', $filtres['statut']));
                break;

            case 'forum':
                $requete->when($cherche, static fn (Builder $q) => $q->where('title', 'like', $motif));
                break;

            case 'journal':
                $requete->when($cherche, static fn (Builder $q) => $q->where(static fn (Builder $ou) => $ou
                    ->where('action', 'like', $motif)
                    ->orWhere('actor_label', 'like', $motif)
                    ->orWhere('target_label', 'like', $motif)));
                break;

            case 'connexions':
                $requete->when($cherche, static fn (Builder $q) => $q->where(static fn (Builder $ou) => $ou
                    ->where('navigateur', 'like', $motif)->orWhere('systeme', 'like', $motif)->orWhere('ip_tronquee', 'like', $motif)));
                break;

            case 'lectures-hub':
                $requete->when($cherche, static fn (Builder $q) => $q->where('titre', 'like', $motif));
                break;

            case 'cours-encadres':
                $requete->when($cherche, static fn (Builder $q) => $q->where('title', 'like', $motif));
                match ($filtres['publie'] ?? null) {
                    'oui' => $requete->where('is_published', true),
                    'non' => $requete->where('is_published', false),
                    default => null,
                };
                break;

            case 'faits-rediges':
                $requete->when($cherche, static fn (Builder $q) => $q->where('title', 'like', $motif));
                match ($filtres['publie'] ?? null) {
                    'oui' => $requete->where('is_published', true),
                    'non' => $requete->where('is_published', false),
                    default => null,
                };
                break;
        }
    }
}
