<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Application\Contracts\DepotFicheDuCompte;
use App\Enums\StatutCopieExamen;
use App\Enums\StatutDevoir;
use App\Enums\StatutInscription;
use App\Enums\StatutPaiement;
use App\Enums\StatutVerification;
use App\Enums\UserRole;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Models\Thread;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use App\Models\XpLog;
use App\Support\DataTables\RequeteDataTables;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * La fiche d'un compte : sa carte d'identité, ses onglets, et les lignes de
 * chaque onglet telles que DataTables les reçoit.
 *
 * ## Une seule fiche pour les citoyens ET les mentors
 *
 * Un mentor est un compte comme un autre : il a pu vérifier des contenus,
 * suivre des cours, gagner des points avant de recevoir son rôle. Une seconde
 * fiche recopierait la carte d'identité et les actions de suspension, et les
 * deux divergeraient au premier correctif. La fiche AJOUTE donc les onglets du
 * périmètre du personnel (cours encadrés, corrections, vérifications assignées,
 * faits rédigés) quand le compte consulté porte le rôle concerné — ou quand
 * il a un historique dans cet onglet : un ancien mentor rétrogradé a corrigé
 * des copies, et cette trace ne disparaît pas avec son rôle.
 *
 * ## Qui voit quoi
 *
 * 1. **La fiche** : `UserPolicy::view` (`user.view`), rejouée ici pour chaque
 *    source JSON — la route protège l'adresse, le service protège l'appel.
 * 2. **L'onglet Journal** : en plus, `AuditLogPolicy::viewAny`
 *    (`security.logs`). Consulter un compte n'est pas lire le journal
 *    d'audit, et la matrice sépare déjà les deux.
 * 3. **L'export** : NON proposé. La seule permission d'export de la matrice,
 *    `user.export`, porte sur la liste des comptes ; l'étendre à l'historique
 *    détaillé d'une personne serait une décision du porteur
 *    (`docs/DATATABLES_CONTRAT.md` §8). Une requête `export=1` reçoit 403.
 */
final readonly class FicheDuCompte
{
    /**
     * Les onglets, dans l'ordre d'affichage.
     *
     * `roles` : les rôles qui font apparaître l'onglet même vide. `null` =
     * l'onglet de l'activité de tout compte, toujours présent.
     */
    public const ONGLETS = [
        'verifications' => ['libelle' => 'Vérifications', 'roles' => null],
        'cours' => ['libelle' => 'Cours', 'roles' => null],
        'quiz' => ['libelle' => 'Quiz', 'roles' => null],
        'devoirs' => ['libelle' => 'Devoirs', 'roles' => null],
        'examens' => ['libelle' => 'Examens', 'roles' => null],
        'certificats' => ['libelle' => 'Certificats', 'roles' => null],
        'unites' => ['libelle' => 'Unités IA', 'roles' => null],
        'points' => ['libelle' => 'Points', 'roles' => null],
        'paiements' => ['libelle' => 'Paiements', 'roles' => null],
        'forum' => ['libelle' => 'Forum', 'roles' => null],
        'journal' => ['libelle' => 'Journal', 'roles' => null],
        'verifications-assignees' => ['libelle' => 'Vérifications assignées', 'roles' => [UserRole::Admin, UserRole::Moderateur, UserRole::Mentor]],
        'cours-encadres' => ['libelle' => 'Cours encadrés', 'roles' => [UserRole::Admin, UserRole::Mentor]],
        'corrections-devoirs' => ['libelle' => 'Corrections de devoirs', 'roles' => [UserRole::Admin, UserRole::Mentor]],
        'corrections-examens' => ['libelle' => 'Corrections d’examens', 'roles' => [UserRole::Admin, UserRole::Mentor]],
        'faits-rediges' => ['libelle' => 'Faits rédigés', 'roles' => [UserRole::Admin, UserRole::Moderateur, UserRole::Mentor]],
    ];

    public function __construct(private DepotFicheDuCompte $depot) {}

    /**
     * Les filtres rapides de chaque onglet : leur libellé, et leurs SEULES
     * valeurs acceptées avec le texte de chacune. La vue affiche ces options,
     * la requête est confrontée à leurs clés — une seule source pour les deux.
     *
     * @return array<string, array{libelle: string, options: array<string, string>}>
     */
    public static function optionsDesFiltres(string $onglet): array
    {
        $depuisEnum = static function (array $cas): array {
            $options = [];
            foreach ($cas as $un) {
                $options[(string) $un->value] = $un->libelle();
            }

            return $options;
        };

        $publie = ['publie' => ['libelle' => 'Publication', 'options' => ['oui' => 'Publié', 'non' => 'Brouillon']]];

        return match ($onglet) {
            'verifications', 'verifications-assignees' => ['statut' => ['libelle' => 'Statut', 'options' => $depuisEnum(StatutVerification::cases())]],
            'cours' => ['statut' => ['libelle' => 'Statut', 'options' => $depuisEnum(StatutInscription::cases())]],
            'quiz' => ['reussite' => ['libelle' => 'Résultat', 'options' => ['reussi' => 'Réussi', 'echoue' => 'Échoué']]],
            'devoirs', 'corrections-devoirs' => ['statut' => ['libelle' => 'Statut', 'options' => $depuisEnum(StatutDevoir::cases())]],
            'examens', 'corrections-examens' => ['statut' => ['libelle' => 'Statut', 'options' => $depuisEnum(StatutCopieExamen::cases())]],
            'certificats' => ['statut' => ['libelle' => 'Statut', 'options' => ['valide' => 'Valide', 'revoque' => 'Révoqué']]],
            'unites', 'points' => ['sens' => ['libelle' => 'Sens', 'options' => ['credit' => 'Crédits', 'debit' => 'Débits']]],
            'paiements' => ['statut' => ['libelle' => 'Statut', 'options' => $depuisEnum(StatutPaiement::cases())]],
            'cours-encadres', 'faits-rediges' => $publie,
            default => [],
        };
    }

    /**
     * Les mêmes filtres, sous la forme que lit `RequeteDataTables`.
     *
     * @return array<string, list<string>|'date'>
     */
    public static function filtresAutorises(string $onglet): array
    {
        $autorises = [];

        foreach (self::optionsDesFiltres($onglet) as $cle => $filtre) {
            $autorises[$cle] = array_map('strval', array_keys($filtre['options']));
        }

        return $autorises;
    }

    /** Le compte à afficher, ou null s'il n'existe plus (supprimé entre-temps). */
    public function compte(string $uuid): ?User
    {
        return $this->depot->compte($uuid);
    }

    /**
     * Les onglets que CE membre du personnel voit sur CE compte, avec leur
     * nombre de lignes.
     *
     * @return list<array{cle: string, libelle: string, nombre: int}>
     */
    public function onglets(User $acteur, User $compte): array
    {
        $porte = Gate::forUser($acteur);
        $roles = $compte->getRoleNames()->all();

        $candidats = [];
        foreach (self::ONGLETS as $cle => $definition) {
            if ($cle === 'journal' && ! $porte->allows('viewAny', AuditLog::class)) {
                continue;
            }
            $candidats[] = $cle;
        }

        $nombres = $this->depot->compteurs($compte, $candidats);
        $onglets = [];

        foreach ($candidats as $cle) {
            $rolesRequis = self::ONGLETS[$cle]['roles'];
            $parRole = $rolesRequis === null || array_intersect(array_column($rolesRequis, 'value'), $roles) !== [];

            if (! $parRole && ($nombres[$cle] ?? 0) === 0) {
                continue;
            }

            $onglets[] = ['cle' => $cle, 'libelle' => self::ONGLETS[$cle]['libelle'], 'nombre' => $nombres[$cle] ?? 0];
        }

        return $onglets;
    }

    /**
     * La carte d'identité, en textes prêts à afficher.
     *
     * @return array<string, mixed>
     */
    public function identite(User $compte): array
    {
        $dossier = $this->depot->dernierDossierKyc($compte);
        $roles = [];
        $personnel = false;

        foreach ($compte->getRoleNames() as $nom) {
            $roles[] = UserRole::tryFrom($nom)?->label() ?? $nom;
            $personnel = $personnel || $nom !== UserRole::Citoyen->value;
        }

        return [
            'nom' => (string) $compte->name,
            'initiales' => $compte->initiales() !== '' ? $compte->initiales() : '?',
            'email' => (string) $compte->email,
            'profession' => $compte->profession,
            'ville' => $compte->city,
            'roles' => $roles !== [] ? $roles : [UserRole::Citoyen->label()],
            'personnel' => $personnel,
            'statut' => match (true) {
                $compte->estSuspendu() => 'suspendu',
                $compte->estActive() => 'actif',
                default => 'non_active',
            },
            'suspension' => $compte->estSuspendu() ? [
                'depuis' => $this->date($compte->suspended_at),
                'par' => $compte->auteurDeLaSuspension?->name,
                'motif' => $compte->suspension_reason,
            ] : null,
            'emailVerifie' => $this->date($compte->email_verified_at),
            'otpVerifie' => $this->date($compte->otp_verified_at),
            'deuxFacteurs' => $personnel ? ($compte->two_factor_confirmed_at !== null ? 'Active' : 'Enrôlement à venir') : null,
            'inscritLe' => $this->date($compte->created_at),
            'derniereConnexion' => $compte->last_login_at !== null
                ? $compte->last_login_at->translatedFormat('j M Y à H:i').' ('.$compte->last_login_at->diffForHumans().')'
                : null,
            'unites' => (int) $compte->units,
            'points' => (int) $compte->xp,
            'serie' => (int) $compte->streak_days,
            'meilleureSerie' => (int) $compte->streak_best,
            'kyc' => match (true) {
                $compte->kyc_verified_at !== null => 'Vérifiée le '.$this->date($compte->kyc_verified_at),
                $dossier !== null => $dossier->status->libelle().' — déposé le '.$this->date($dossier->created_at),
                default => 'Aucun dossier',
            },
            'suppressionDemandee' => $this->date($compte->getAttribute('deletion_requested_at')),
        ];
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(string $onglet, RequeteDataTables $requete, User $acteur, User $compte): array
    {
        $porte = Gate::forUser($acteur);
        $porte->authorize('view', $compte);

        if (! array_key_exists($onglet, self::ONGLETS)) {
            throw new AuthorizationException('Onglet inconnu.');
        }

        if ($onglet === 'journal') {
            $porte->authorize('viewAny', AuditLog::class);
        }

        if ($requete->export) {
            throw new AuthorizationException("L'historique d'un compte ne s'exporte pas depuis la fiche.");
        }

        $page = $this->depot->pourOnglet($onglet, $compte, $requete);

        $lignes = [];
        foreach ($page->lignes as $modele) {
            $lignes[] = $this->ligne($onglet, $modele, $compte);
        }

        return [
            'draw' => $requete->draw,
            'recordsTotal' => $page->total,
            'recordsFiltered' => $page->filtres,
            'data' => $lignes,
            'exportTronque' => false,
        ];
    }

    // ═══════════════════════════════════════════════════════════ les lignes

    /**
     * Une ligne, champ par champ. Des TEXTES seulement — ni HTML, ni `id`, ni
     * clé étrangère : le navigateur échappe à l'affichage.
     *
     * `ton` guide la couleur du badge (ok, attente, alerte, neutre), jamais le
     * sens de la donnée.
     *
     * @return array<string, mixed>
     */
    private function ligne(string $onglet, Model $m, User $compte): array
    {
        return match (true) {
            $m instanceof Verification => [
                'affirmation' => $m->apercu(140),
                'auteur' => $m->user_id === null ? 'Invité' : ($m->user?->name ?? '—'),
                'type' => $m->type->libelle(),
                'theme' => $m->theme?->libelle(),
                'statut' => $m->status->libelle(),
                'ton' => match ($m->status) {
                    StatutVerification::Terminee => 'ok',
                    StatutVerification::Echouee => 'alerte',
                    default => 'attente',
                },
                // `verdict` est NULLABLE dès qu'un rapport est MIXTE (contrat
                // V-8) — `?->verdict->libelle()` plantait tel quel sur un
                // rapport à plusieurs affirmations contradictoires.
                'verdict' => $m->report?->estMixte()
                    ? $m->report->composition_status->libelle()
                    : $m->report?->verdict?->libelle(),
                'cout' => (int) $m->cost_units,
                'rembourse' => $m->refunded_at !== null,
                'assigneeLe' => $this->date($m->assigned_at),
                'creeLe' => $this->date($m->created_at, true),
            ],

            $m instanceof Enrollment => [
                'cours' => $m->course?->title ?? 'Cours supprimé',
                'statut' => $m->status->libelle(),
                'ton' => $m->status === StatutInscription::Termine ? 'ok' : 'attente',
                'progression' => (int) $m->progress,
                'unites' => (int) $m->paid_units,
                'inscritLe' => $this->date($m->created_at),
                'derniereActivite' => $this->date($m->last_activity_at),
                'termineLe' => $this->date($m->completed_at),
            ],

            $m instanceof QuizAttempt => [
                'quiz' => $m->quiz?->title ?? 'Quiz supprimé',
                'cours' => $m->quiz?->course?->title,
                'score' => (int) $m->score,
                'seuil' => $m->quiz?->pass_score,
                'reussi' => $m->passed ? 'Réussi' : 'Échoué',
                'ton' => $m->passed ? 'ok' : 'alerte',
                'duree' => $this->duree((int) $m->duration_s),
                'passeLe' => $this->date($m->created_at, true),
            ],

            $m instanceof AssignmentSubmission => [
                'apprenant' => $m->user?->name ?? '—',
                'devoir' => $m->assignment?->title ?? 'Devoir supprimé',
                'cours' => $m->assignment?->course?->title,
                'statut' => $m->status->libelle(),
                'ton' => match ($m->status) {
                    StatutDevoir::Valide => 'ok',
                    StatutDevoir::ARevoir => 'alerte',
                    default => 'attente',
                },
                'note' => $m->grade,
                'correcteur' => $m->reviewer?->name,
                'remisLe' => $this->date($m->created_at),
                'corrigeLe' => $this->date($m->reviewed_at),
            ],

            $m instanceof ExamAttempt => [
                'candidat' => $m->user?->name ?? '—',
                'examen' => $m->exam?->title ?? 'Examen supprimé',
                'tentative' => (int) $m->attempt_no,
                'statut' => $m->status->libelle(),
                'ton' => match ($m->status) {
                    StatutCopieExamen::Corrigee => 'ok',
                    StatutCopieExamen::Abandonnee => 'alerte',
                    default => 'attente',
                },
                'score' => $m->score_percent,
                'reussi' => $m->passed === null ? null : ($m->passed ? 'Réussi' : 'Échoué'),
                'correcteur' => $m->correcteur?->name,
                'soumisLe' => $this->date($m->submitted_at),
                'assigneeLe' => $this->date($m->assigned_at),
                'corrigeLe' => $this->date($m->graded_at),
            ],

            $m instanceof Certificate => [
                'code' => (string) $m->code,
                'cours' => (string) $m->course_title,
                'delivreLe' => $this->date($m->issued_at),
                'statut' => $m->estValide() ? 'Valide' : 'Révoqué',
                'ton' => $m->estValide() ? 'ok' : 'alerte',
            ],

            $m instanceof UnitLog, $m instanceof XpLog => [
                'date' => $this->date($m->created_at, true),
                'mouvement' => ($m->delta > 0 ? '+' : '').$m->delta,
                'ton' => $m->delta > 0 ? 'ok' : ($m->delta < 0 ? 'alerte' : 'neutre'),
                'solde' => (int) $m->balance_after,
                'motif' => $m->reason->libelle(),
                'libelle' => (string) $m->label,
            ],

            $m instanceof Payment => [
                'date' => $this->date($m->created_at, true),
                'operateur' => $m->provider->libelle(),
                'pack' => (string) $m->pack,
                'montant' => (int) $m->amount_fcfa,
                'unites' => (int) $m->units,
                'statut' => $m->status->libelle(),
                'ton' => match ($m->status) {
                    StatutPaiement::Reussi => 'ok',
                    StatutPaiement::EnAttente => 'attente',
                    default => 'alerte',
                },
                'regleLe' => $this->date($m->settled_at, true),
            ],

            $m instanceof Thread => [
                'sujet' => (string) $m->title,
                'categorie' => $m->category?->name,
                'reponses' => (int) $m->replies_count,
                'verdict' => $m->official_verdict?->libelle(),
                'verrouille' => (bool) $m->is_locked,
                'creeLe' => $this->date($m->created_at),
            ],

            $m instanceof AuditLog => [
                'date' => $this->date($m->created_at, true),
                'action' => (string) $m->action,
                'module' => (string) $m->module,
                'acteur' => $m->actor_label ?? '—',
                'cible' => $m->target_label,
                'role' => $this->roleDansLaTrace($m, $compte),
            ],

            $m instanceof Course => [
                'titre' => (string) $m->title,
                'niveau' => $m->level?->libelle(),
                'publie' => $m->is_published ? 'Publié' : 'Brouillon',
                'ton' => $m->is_published ? 'ok' : 'neutre',
                'inscrits' => (int) $m->getAttribute('enrollments_count'),
                'creeLe' => $this->date($m->created_at),
            ],

            $m instanceof VerifiedFact => [
                'titre' => (string) $m->title,
                'verdict' => $m->verdict->libelle(),
                'publie' => $m->is_published ? 'Publié' : 'Brouillon',
                'ton' => $m->is_published ? 'ok' : 'neutre',
                'reutilisations' => (int) $m->hits,
                'creeLe' => $this->date($m->created_at),
            ],

            default => throw new LogicException("Ligne sans forme pour l'onglet {$onglet}."),
        };
    }

    private function roleDansLaTrace(AuditLog $trace, User $compte): string
    {
        $acteur = $trace->actor_id !== null && (int) $trace->actor_id === (int) $compte->id;
        $cible = $trace->target_type === User::class && $trace->target_uuid === $compte->uuid;

        return match (true) {
            $acteur && $cible => 'Acteur et cible',
            $acteur => 'Acteur',
            default => 'Cible',
        };
    }

    private function date(mixed $date, bool $heure = false): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->translatedFormat($heure ? 'j M Y H:i' : 'j M Y');
    }

    private function duree(int $secondes): string
    {
        return $secondes >= 60
            ? intdiv($secondes, 60).' min '.str_pad((string) ($secondes % 60), 2, '0', STR_PAD_LEFT).' s'
            : $secondes.' s';
    }
}
