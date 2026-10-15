<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\Notifications\VerificationAssignee;
use App\Core\FactChecking\Notifications\VerificationRetiree;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Confie une vérification à un membre de l'équipe.
 *
 * La colonne « Assigné à » du prototype était décorative : rien ne portait la
 * prise en charge d'une analyse. Ce service la rend réelle, et impose les trois
 * choses qu'une assignation doit garantir.
 *
 * ## 1. On ne confie pas du travail à quelqu'un qui ne peut pas le faire
 *
 * Le destinataire doit détenir `signalement.verdict`. Assigner à un compte qui
 * recevra la notification puis butera sur un 403 est pire que ne rien assigner :
 * le dossier paraît pris en charge et ne l'est pas.
 *
 * ## 2. Une réassignation prévient LES DEUX personnes
 *
 * Reprendre un dossier à quelqu'un sans le lui dire, c'est le laisser travailler
 * sur une analyse qui ne lui appartient plus. Le précédent titulaire est donc
 * notifié du retrait.
 *
 * ## 3. La course entre deux assignations est tranchée en base
 *
 * `lockForUpdate()` dans une transaction. Sans cela, deux responsables cliquant
 * à la même seconde écrasent l'un l'autre : le dernier écrit gagne, le premier
 * croit avoir assigné, et le mentor du premier reçoit un courriel pour un
 * dossier qui ne lui est plus attribué.
 */
final class AssignationDeVerification
{
    /** La permission qu'il faut DÉTENIR pour se voir confier une vérification. */
    private const PERMISSION_REQUISE = 'signalement.verdict';

    public function __construct(private readonly AuditLogService $journal) {}

    /**
     * @throws RuntimeException si le destinataire ne peut pas traiter le dossier
     */
    public function confier(Verification $verification, User $destinataire, User $auteur): void
    {
        if (! $destinataire->can(self::PERMISSION_REQUISE)) {
            throw new RuntimeException(
                "Ce membre n'a pas le droit de rendre un verdict : lui confier ce dossier "
                .'le laisserait devant un refus d’accès.'
            );
        }

        /*
         | Deux informations DISTINCTES sortent de la transaction, et les
         | confondre est une erreur qu'on fait une fois : « il n'y avait pas de
         | titulaire précédent » n'est pas « rien n'a changé ». Un premier jet
         | ne rendait que le précédent, et renvoyait donc un courriel à chaque
         | fois qu'on réassignait quelqu'un à son propre dossier.
         *
         * @var array{change: bool, precedent: User|null} $issue
         */
        $issue = DB::transaction(function () use ($verification, $destinataire): array {
            /** @var Verification $verrouillee */
            $verrouillee = Verification::query()
                ->lockForUpdate()
                ->findOrFail($verification->getKey());

            $avant = $verrouillee->assignee;

            if ($verrouillee->assigned_to === $destinataire->getKey()) {
                return ['change' => false, 'precedent' => $avant];
            }

            $verrouillee->forceFill([
                'assigned_to' => $destinataire->getKey(),
                'assigned_at' => now(),
            ])->save();

            $verification->setRawAttributes($verrouillee->getAttributes(), sync: true);

            return ['change' => true, 'precedent' => $avant];
        });

        // Déjà chez cette personne : rien à annoncer. Lui écrire lui ferait
        // croire qu'un second dossier est arrivé.
        if (! $issue['change']) {
            return;
        }

        $precedent = $issue['precedent'];

        $destinataire->notify(new VerificationAssignee($verification, $auteur->name));

        if ($precedent !== null && $precedent->getKey() !== $destinataire->getKey()) {
            $precedent->notify(new VerificationRetiree($verification, $auteur->name, $destinataire->name));
        }

        $this->journal->record(
            action: 'verification.assign',
            module: 'factchecking',
            acteur: $auteur,
            cible: $verification,
            apres: [
                'assigned_to' => $destinataire->uuid,
                'assigned_to_label' => $destinataire->name,
                'precedent' => $precedent?->name,
            ],
        );
    }

    /**
     * Les membres à qui l'on peut confier une vérification.
     *
     * Filtré sur la PERMISSION et non sur le rôle : la matrice peut évoluer, et
     * une liste construite sur « Mentor ou Modérateur » se désynchroniserait
     * silencieusement le jour où un rôle change de périmètre.
     *
     * Le guard est `web` : tous les rôles du projet y sont semés, y compris
     * ceux du personnel — `User::getDefaultGuardName()` rend `'web'`.
     *
     * @return Collection<int, User>
     */
    public function destinatairesPossibles(): Collection
    {
        return User::query()
            ->permission(self::PERMISSION_REQUISE)
            ->whereNull('anonymized_at')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);
    }

    /**
     * Les rôles susceptibles de traiter un dossier, pour un message d'aide.
     *
     * @return list<UserRole>
     */
    public function rolesConcernes(): array
    {
        return [UserRole::Admin, UserRole::Moderateur];
    }
}
