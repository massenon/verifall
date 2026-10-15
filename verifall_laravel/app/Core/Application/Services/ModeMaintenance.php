<?php

declare(strict_types=1);

namespace App\Core\Application\Services;

use App\Core\Application\Reglages\Reglages;
use App\Core\Identity\Services\AuditLogService;
use App\Models\MaintenanceWatcher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Le mode maintenance, tenu par l'application et NON par `artisan down`.
 *
 * ## Pourquoi pas la maintenance de Laravel
 *
 * `php artisan down` pose un fichier et coupe TOUT, back-office compris. Trois
 * conséquences, chacune rédhibitoire ici :
 *
 *   1. **on ne peut plus rouvrir depuis l'administration** — il faut un accès
 *      SSH, que l'équipe éditoriale n'a pas ;
 *   2. **le formulaire « prévenez-moi » ne peut pas fonctionner** : aucune route
 *      ne répond, donc aucun POST ;
 *   3. le décompte n'a nulle part où lire sa date de fin.
 *
 * Le mode vit donc dans les réglages, et un middleware l'applique. Le personnel
 * et la page de maintenance elle-même en sont exemptés — sans quoi on
 * s'enfermerait dehors.
 *
 * ## La fin annoncée est une PROMESSE, pas une minuterie
 *
 * `maintenance_ends_at` n'éteint rien toute seule. Une maintenance qui se
 * rouvrirait au chronomètre rouvrirait un site à moitié migré si l'opération a
 * pris dix minutes de plus — et c'est exactement le moment où il ne faut pas
 * laisser entrer.
 *
 * La date sert à deux choses : afficher un décompte honnête, et permettre à la
 * page de dire « nous avons du retard » plutôt que d'afficher un compteur à
 * zéro qui ment. **La réouverture reste un geste humain.**
 *
 * ## Prévenir n'est pas automatique non plus
 *
 * Les avertis sont notifiés à la RÉOUVERTURE, par le même geste. Les prévenir
 * à l'heure annoncée les enverrait sur un site encore fermé.
 */
final class ModeMaintenance
{
    public const CLE_ACTIVE = 'maintenance_active';

    public const CLE_DEBUT = 'maintenance_started_at';

    public const CLE_FIN = 'maintenance_ends_at';

    public const CLE_MESSAGE = 'maintenance_message';

    public function __construct(
        private readonly Reglages $reglages,
        private readonly AuditLogService $journal,
    ) {}

    public function estActif(): bool
    {
        try {
            // `actif()` et non `get()` : les réglages sont stockés en CHAÎNES,
            // et `(bool) '0'` vaut faux mais `(bool) 'false'` vaut vrai. Le
            // helper existe pour ne pas refaire cette erreur à chaque appelant.
            return $this->reglages->actif(self::CLE_ACTIVE);
        } catch (Throwable) {
            /*
             | UN DRAPEAU ILLISIBLE NE FERME PAS LE SITE.
             |
             | Ce test tourne sur CHAQUE requête, y compris celle d'un visiteur
             | anonyme sur l'accueil. Si la table `settings` est injoignable —
             | déploiement dont les migrations n'ont pas encore tourné, base
             | momentanément indisponible — laisser l'exception remonter
             | rendrait 500 sur tout le site.
             |
             | Le défaut sûr est donc « pas de maintenance » : au pire on sert
             | un site qu'on croyait fermé, au lieu de fermer un site qui allait
             | bien. La suite complète l'a montré en rendant 500 sur l'accueil
             | avant que ce filet n'existe.
             */
            return false;
        }
    }

    public function debut(): ?CarbonImmutable
    {
        return $this->date(self::CLE_DEBUT);
    }

    /** La fin ANNONCÉE. Elle n'éteint rien — voir le bloc de documentation. */
    public function finAnnoncee(): ?CarbonImmutable
    {
        return $this->date(self::CLE_FIN);
    }

    /**
     * L'heure annoncée est-elle dépassée ?
     *
     * La page le dit alors franchement plutôt que d'afficher un décompte à zéro.
     * Un compteur figé sur « 00:00 » pendant vingt minutes fait rafraîchir en
     * boucle et donne l'impression d'un site cassé, pas d'une opération en cours.
     */
    public function enRetard(): bool
    {
        $fin = $this->finAnnoncee();

        return $fin !== null && $fin->isPast();
    }

    public function message(): string
    {
        $ecrit = trim((string) $this->reglages->get(self::CLE_MESSAGE, ''));

        return $ecrit !== '' ? $ecrit : self::MESSAGE_PAR_DEFAUT;
    }

    private const MESSAGE_PAR_DEFAUT =
        'Nous mettons Vérif’All à jour. Le service revient dès que l’opération est terminée.';

    /**
     * Ouvre la maintenance.
     *
     * @param  CarbonImmutable|null  $fin  l'heure ANNONCÉE, facultative
     *
     * @throws RuntimeException si la fin annoncée est déjà passée
     */
    public function activer(User $acteur, ?CarbonImmutable $fin = null, string $message = ''): void
    {
        if ($fin !== null && $fin->isPast()) {
            // Annoncer une fin déjà passée afficherait « en retard » dès la
            // première seconde : le message serait faux avant d'être lu.
            throw new RuntimeException('L’heure de fin annoncée est déjà passée.');
        }

        DB::transaction(function () use ($fin, $message): void {
            $this->reglages->set(self::CLE_ACTIVE, '1');
            $this->reglages->set(self::CLE_DEBUT, now()->toDateTimeString());
            $this->reglages->set(self::CLE_FIN, $fin?->toDateTimeString() ?? '');
            $this->reglages->set(self::CLE_MESSAGE, trim($message));
        });

        $this->journal->record(
            action: 'maintenance.enable',
            module: 'system',
            acteur: $acteur,
            apres: ['fin_annoncee' => $fin?->toDateTimeString(), 'message' => trim($message)],
            cibleLibelle: 'Mode maintenance',
        );
    }

    /**
     * Referme la maintenance, et rend la liste de ceux à prévenir.
     *
     * La notification n'est PAS envoyée ici : ce service ne sait pas expédier, et
     * lui donner ce rôle mêlerait la décision et son courrier. L'appelant met en
     * file — voir `Staff\Maintenance`.
     *
     * @return list<MaintenanceWatcher>
     */
    public function desactiver(User $acteur): array
    {
        $debut = $this->debut();

        DB::transaction(function (): void {
            $this->reglages->set(self::CLE_ACTIVE, '0');
            $this->reglages->set(self::CLE_FIN, '');
        });

        $this->journal->record(
            action: 'maintenance.disable',
            module: 'system',
            acteur: $acteur,
            apres: ['duree_minutes' => $debut?->diffInMinutes(now())],
            cibleLibelle: 'Mode maintenance',
        );

        /*
         | Ceux qui attendaient CELLE-CI, et qu'on n'a pas encore prévenus.
         |
         | Le filtre sur `waiting_since` évite d'écrire à quelqu'un inscrit lors
         | d'une maintenance précédente jamais notifiée : il recevrait « c'est
         | reparti » pour une interruption qu'il a oubliée depuis longtemps.
         */
        /** @var list<MaintenanceWatcher> */
        return MaintenanceWatcher::query()
            ->whereNull('notified_at')
            ->when($debut !== null, fn ($q) => $q->where('waiting_since', '>=', $debut))
            ->get()
            ->all();
    }

    /**
     * Enregistre une adresse à prévenir. Idempotent.
     *
     * Rafraîchir et resoumettre est le geste le plus naturel quand on attend :
     * la contrainte d'unicité fait qu'on ne s'inscrit pas trois fois.
     */
    public function prevenirMoi(string $email, ?string $empreinteIp = null): MaintenanceWatcher
    {
        $watcher = MaintenanceWatcher::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);

        $watcher->forceFill([
            'waiting_since' => $this->debut() ?? now(),
            // Réinscription pendant une NOUVELLE maintenance : on remet la
            // pendule à zéro, sinon la personne resterait « déjà prévenue ».
            'notified_at' => null,
            'ip_hash' => $empreinteIp,
        ])->save();

        return $watcher;
    }

    private function date(string $cle): ?CarbonImmutable
    {
        $valeur = trim((string) $this->reglages->get($cle, ''));

        return $valeur === '' ? null : CarbonImmutable::parse($valeur);
    }
}
