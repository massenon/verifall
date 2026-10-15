<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /*
     | HasApiTokens : sans lui, createToken() n'existe pas et le client mobile
     | Flutter ne peut pas s'authentifier, alors même que des routes
     | auth:sanctum seraient déclarées.
     |
     | HasRoles : sans lui, les middlewares role: et permission: — déjà aliasés
     | dans bootstrap/app.php — resteraient inopérants sur ce modèle.
     */
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /*
     | La suppression d'un compte est une demande RGPD, pas un DELETE : elle
     | ouvre un délai de rétractation de 30 jours pendant lequel la ligne doit
     | survivre — sans quoi une seconde d'hésitation coûterait tout. L'effacement
     | définitif, lui, prend la forme d'une ANONYMISATION : les données
     | personnelles disparaissent, la ligne reste pour ne pas rompre ce qui
     | s'y rattache.
     */
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'profession',
        'city',
        'bio',
        'password',
    ];

    /*
     | Volontairement HORS $fillable, et ce n'est pas un oubli :
     |   units, streak_days ............. l'économie ne s'écrit que par
     |                                    EconomyService, de façon transactionnelle ;
     |   xp ............................. par JournalDXp, et par lui seul.
     |                                    Cette ligne disait « EconomyService »
     |                                    comme les unités : c'était FAUX. Quatre
     |                                    services appelaient increment('xp')
     |                                    directement, sans journal — on savait
     |                                    combien de points un compte avait,
     |                                    jamais d'où ils venaient. Corrigé le
     |                                    2 septembre 2026, table `xp_logs` ;
     |   otp_verified_at ................ l'activation d'un compte ne peut pas
     |                                    venir d'une requête utilisateur ;
     |   suspended_at / _reason / _by ... par SuspensionDeCompte, qui ferme les
     |                                    sessions, journalise et prévient dans
     |                                    le même geste. Une requête capable de
     |                                    poser cet état au passage d'un autre
     |                                    formulaire serait une porte dérobée
     |                                    dans la modération ;
     |   two_factor_* ................... écrits par TwoFactorService seul ;
     |   last_login_at / last_login_ip_hash ... posés par le flux de connexion.
     */

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            // Données personnelles directement identifiantes : chiffrées au
            // repos (SECURITE_RGPD §5). Une fuite de la base ne livre alors ni
            // les téléphones, ni les secrets 2FA.
            'phone' => 'encrypted',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',

            'otp_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'tour_done_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',

            // Réglages de confort, écrits par PreferencesService seul — d'où
            // leur absence de $fillable.
            'notification_preferences' => 'array',

            'units' => 'integer',
            'xp' => 'integer',
            'suspended_at' => 'datetime',
            'streak_days' => 'integer',
            'streak_best' => 'integer',
            // Une DATE et non un horodatage : la série se compte en jours.
            'streak_last_day' => 'date',
            'referral_visits' => 'integer',
        ];
    }

    /**
     * Journal des consentements — accords ET retraits, dans l'ordre.
     *
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Archives de portabilité demandées par ce compte.
     *
     * @return HasMany<DataExport, $this>
     */
    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    /**
     * L'identifiant public est l'UUID : aucune URL n'expose l'auto-incrément.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Guard de référence pour les rôles et permissions.
     *
     * L'application expose DEUX guards d'authentification (`web` citoyen,
     * `staff` back-office) mais UN SEUL jeu de rôles : « Admin » désigne la
     * même chose des deux côtés. Sans cette surcharge, spatie chercherait des
     * permissions déclarées pour le guard `staff`, n'en trouverait aucune, et
     * refuserait un administrateur pourtant légitime — un 403 incompréhensible.
     *
     * Dupliquer les 106 permissions par guard aurait été l'autre voie : deux
     * fois plus de lignes à maintenir pour une distinction qui n'existe pas
     * dans le métier.
     */
    protected function getDefaultGuardName(): string
    {
        return 'web';
    }

    /**
     * Le compte est-il activé ?
     *
     * Un compte dont l'OTP n'a jamais été validé existe en base mais ne doit
     * pouvoir rien faire : sans cela, une adresse dont on n'a pas la maîtrise
     * suffirait à créer un compte utilisable.
     */
    public function estActive(): bool
    {
        return $this->otp_verified_at !== null;
    }

    /**
     * Le compte est-il suspendu ?
     *
     * ## Volontairement SÉPARÉ de `estActive()`
     *
     * Le réflexe serait d'ajouter `&& ! $this->estSuspendu()` à la méthode
     * ci-dessus : un seul appel, tous les appelants couverts d'un coup. Ce
     * serait une erreur, et pour une raison qui n'est pas théorique.
     *
     * « Non activé » et « suspendu » n'appellent pas la même réponse :
     *
     *   · un compte dont l'OTP n'a jamais été validé doit être refusé **sans
     *     qu'on révèle son existence** — sinon le formulaire de connexion
     *     devient un annuaire des inscrits ;
     *   · un compte suspendu doit être refusé **en le disant**. Taire la
     *     suspension enverrait la personne réinitialiser un mot de passe qui
     *     n'est pas en cause, échouer encore, puis écrire au support en colère.
     *
     * Les fondre ferait disparaître cette distinction dans un booléen, et le
     * second cas hériterait silencieusement du comportement du premier.
     */
    public function estSuspendu(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteurDeLaSuspension(): BelongsTo
    {
        return $this->belongsTo(self::class, 'suspended_by');
    }

    /**
     * L'identité a-t-elle été vérifiée ?
     *
     * Le prototype affiche un badge « Identité vérifiée (KYC) » figé. Il est ici
     * adossé à une date réelle : une page qui affirme une vérification que rien
     * ne soutient est pire qu'une page qui ne l'affiche pas.
     */
    public function identiteVerifiee(): bool
    {
        return $this->kyc_verified_at !== null;
    }

    /**
     * Nom d'affichage court — « Adderly D. » dans le prototype.
     *
     * Le patronyme complet n'apparaît pas dans l'interface citoyenne : c'est le
     * réglage de discrétion retenu par la maquette, on le respecte.
     */
    public function nomAffichage(): string
    {
        $initiale = $this->last_name !== null && $this->last_name !== ''
            ? mb_strtoupper(mb_substr($this->last_name, 0, 1)).'.'
            : '';

        return trim(($this->first_name ?? $this->name).' '.$initiale);
    }

    /** Initiales de l'avatar — « AD ». */
    public function initiales(): string
    {
        $prenom = mb_substr((string) ($this->first_name ?? $this->name), 0, 1);
        $nom = mb_substr((string) $this->last_name, 0, 1);

        return mb_strtoupper($prenom.$nom);
    }

    /**
     * @return HasMany<Referral, $this>
     */
    public function parrainages(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * @return BelongsToMany<Badge, $this>
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')->withPivot('unlocked_at');
    }

    /**
     * Les cours dont ce compte est le formateur (lot 7).
     *
     * `courses.instructor_id` existait depuis la migration du catalogue, mais
     * sans relation dans ce sens : on pouvait aller du cours au formateur,
     * jamais l'inverse. L'écran « Mentors » a besoin du second sens — « que
     * porte cette personne ? » est la seule question qu'on se pose sur un
     * mentor, et un `where('instructor_id', ...)` recopié à chaque appelant
     * l'aurait rendue impossible à compter par `withCount()`.
     *
     * @return HasMany<Course, $this>
     */
    public function coursEnseignes(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}
