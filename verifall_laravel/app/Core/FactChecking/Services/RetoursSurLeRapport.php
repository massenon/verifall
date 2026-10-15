<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\Notifications\ContreExpertiseDemandee;
use App\Core\FactChecking\Notifications\ContreExpertiseTranchee;
use App\Core\Identity\Services\AuditLogService;
use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Enums\PreferenceNotification;
use App\Enums\StatutContreExpertise;
use App\Models\CounterExpertise;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerificationFeedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ce que le lecteur d'un rapport peut nous renvoyer : un avis, ou une contestation.
 *
 * Les deux boutons de l'aside n'écrivaient nulle part dans le prototype. Ils
 * n'ont pas le même poids et ne sont pas traités de la même façon :
 *
 *   · **L'avis** (« utile / pas utile ») est un signal agrégé. Il se change,
 *     ne prévient personne, et ne demande aucune décision.
 *   · **La contre-expertise** dit que nous nous sommes peut-être trompés. Elle
 *     ouvre une file, prévient des humains, et se solde par une issue explicite.
 */
final class RetoursSurLeRapport
{
    /**
     * Qui doit être prévenu qu'un verdict est contesté.
     *
     * La permission, jamais le rôle : la matrice peut évoluer, et une liste
     * bâtie sur « Modérateur » se désynchroniserait le jour où un rôle change
     * de périmètre.
     */
    private const PERMISSION_ARBITRE = 'signalement.verdict';

    /** Assez pour expliquer, trop court pour qu'on y colle un dossier entier. */
    public const MOTIF_MAXIMUM = 1000;

    public function __construct(
        private readonly AuditLogService $journal,
        private readonly ExpediteurDeCourriels $courriels,
    ) {}

    /**
     * Enregistre l'avis d'utilité. Le vote se CHANGE, il ne s'additionne pas.
     *
     * Sans l'unicité, un pouce cliqué douze fois compterait douze avis, et
     * l'indicateur mesurerait l'agacement plutôt que la satisfaction.
     */
    public function noter(Verification $verification, User $lecteur, bool $utile): VerificationFeedback
    {
        return VerificationFeedback::updateOrCreate(
            ['verification_id' => $verification->getKey(), 'user_id' => $lecteur->getKey()],
            ['useful' => $utile],
        );
    }

    /** L'avis déjà donné par cette personne, s'il y en a un. */
    public function avisDe(Verification $verification, User $lecteur): ?VerificationFeedback
    {
        return VerificationFeedback::query()
            ->where('verification_id', $verification->getKey())
            ->where('user_id', $lecteur->getKey())
            ->first();
    }

    /**
     * Ouvre une contre-expertise.
     *
     * ## Une seule demande ouverte à la fois, par rapport
     *
     * Verrou de transaction, et non un simple `exists()` : deux envois du
     * formulaire à la même seconde — un double-clic suffit — créeraient deux
     * demandes identiques, et deux journalistes reprendraient le même dossier
     * sans le savoir.
     *
     * Une NOUVELLE demande reste possible une fois la première tranchée : un
     * rapport corrigé peut l'être mal.
     *
     * @throws RuntimeException si une demande est déjà en attente, ou si le
     *                          motif est vide
     */
    public function demanderUneContreExpertise(
        Verification $verification,
        User $demandeur,
        string $motif,
    ): CounterExpertise {
        $motif = trim($motif);

        if ($motif === '') {
            throw new RuntimeException(
                'Expliquez en quelques mots ce qui vous paraît faux : une demande sans motif '
                .'ne peut pas être instruite.'
            );
        }

        $demande = DB::transaction(function () use ($verification, $demandeur, $motif): CounterExpertise {
            $enCours = CounterExpertise::query()
                ->where('verification_id', $verification->getKey())
                ->enAttente()
                ->lockForUpdate()
                ->exists();

            if ($enCours) {
                throw new RuntimeException(
                    'Une contre-expertise est déjà en cours sur ce rapport. '
                    .'Vous serez prévenu dès qu’un journaliste l’aura reprise.'
                );
            }

            return CounterExpertise::create([
                'uuid' => (string) Str::uuid(),
                'verification_id' => $verification->getKey(),
                'user_id' => $demandeur->getKey(),
                'reason' => Str::limit($motif, self::MOTIF_MAXIMUM, ''),
                'status' => StatutContreExpertise::EnAttente->value,
            ]);
        });

        $this->prevenirLesArbitres($demande, $verification);

        $this->journal->record(
            action: 'verification.contre_expertise',
            module: 'factchecking',
            acteur: $demandeur,
            cible: $verification,
            apres: ['contre_expertise' => $demande->uuid],
        );

        return $demande;
    }

    /**
     * Tranche une contre-expertise.
     *
     * L'issue est explicite — maintenu, corrigé, écartée — parce que « traitée »
     * ne dirait pas la seule chose que le demandeur attend : avions-nous tort ?
     *
     * @throws RuntimeException si la demande est déjà tranchée
     */
    public function trancher(
        CounterExpertise $demande,
        StatutContreExpertise $issue,
        User $arbitre,
        ?string $resolution = null,
    ): void {
        if (! $demande->status->estOuverte()) {
            throw new RuntimeException('Cette contre-expertise a déjà été tranchée.');
        }

        if ($issue->estOuverte()) {
            throw new RuntimeException('Choisissez une issue : maintenu, corrigé, ou écartée.');
        }

        $demande->forceFill([
            'status' => $issue->value,
            'handled_by' => $arbitre->getKey(),
            'handled_at' => now(),
            'resolution' => $resolution !== null ? trim($resolution) : null,
        ])->save();

        /*
         | Prévenir CELUI QUI A DEMANDÉ, systématiquement — y compris quand
         | l'issue nous donne tort, et surtout dans ce cas.
         |
         | Une contestation sans réponse enseigne qu'il ne sert à rien de
         | contester ; c'est le plus sûr moyen de ne plus jamais apprendre nos
         | erreurs. `demandeur` peut être nul si le compte a été supprimé
         | entre-temps : il n'y a alors personne à prévenir, et ce n'est pas
         | une anomalie.
         */
        $demande->demandeur?->notify(new ContreExpertiseTranchee($demande->fresh()));

        /*
         | ET par courriel, en plus de la notification interne.
         |
         | Une contestation se règle souvent en plusieurs jours : la personne
         | n'est pas devant l'écran quand la réponse tombe, et une pastille dans
         | une cloche qu'on rouvrira peut-être la semaine prochaine n'est pas une
         | réponse — c'est une réponse qu'on n'a pas encore lue.
         |
         | Sous la préférence `verification_results` : c'est exactement ce que
         | cet interrupteur désigne, et l'ignorer ici en ferait un réglage
         | décoratif.
         */
        if ($demande->demandeur !== null) {
            $this->courriels->envoyer(
                $demande->demandeur,
                'contre_expertise_tranchee',
                [
                    'prenom' => $demande->demandeur->name,
                    'sujet' => (string) ($demande->verification->claim ?? ''),
                    'decision' => $issue->libelle(),
                ],
                route('citizen.report', ['verification' => $demande->verification->uuid]),
                PreferenceNotification::ResultatsVerification,
            );
        }

        $this->journal->record(
            action: 'verification.contre_expertise.'.$issue->value,
            module: 'factchecking',
            acteur: $arbitre,
            cible: $demande->verification,
            apres: ['issue' => $issue->value, 'resolution' => $resolution],
        );
    }

    /**
     * Prévient les personnes habilitées à rendre un verdict.
     *
     * Pas de courriel ici, à la différence d'une assignation : une contestation
     * s'adresse à une ÉQUIPE, pas à quelqu'un en particulier. Écrire à tout le
     * monde ferait que personne ne se sent concerné, et userait l'attention
     * qu'on garde pour l'assignation nominative — qui, elle, part par courriel.
     */
    private function prevenirLesArbitres(CounterExpertise $demande, Verification $verification): void
    {
        User::query()
            ->permission(self::PERMISSION_ARBITRE)
            ->whereNull('anonymized_at')
            ->get()
            ->each(fn (User $arbitre) => $arbitre->notify(
                new ContreExpertiseDemandee($verification, $demande),
            ));
    }
}
