<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Suspendre un compte, et le rétablir.
 *
 * ## Une suspension qui ne suspend pas n'est pas une suspension
 *
 * Poser une date en base ne bloque rien : la personne reste connectée, continue
 * de publier, et découvre la sanction en lisant son courriel — s'il part. Le
 * geste n'est complet que s'il fait les quatre choses ensemble :
 *
 *   1. poser l'état,
 *   2. **fermer les sessions ouvertes**,
 *   3. journaliser,
 *   4. prévenir la personne.
 *
 * Les trois premières sont dans la transaction ; la quatrième après, pour la
 * raison habituelle — `Mail::queue()` écrit dans `jobs`, qui participerait au
 * `commit`, et un rollback laisserait un courriel annonçant une suspension qui
 * n'a pas eu lieu.
 *
 * ## Le motif est OBLIGATOIRE, et c'est une décision
 *
 * Le service refuse une suspension sans motif. Une sanction sans raison lisible
 * n'est pas contestable : elle se transforme en message au support, puis en
 * défiance envers une plateforme dont le métier est justement d'expliquer ses
 * verdicts. Le rendre facultatif « pour les cas évidents » garantirait qu'il
 * finisse vide dans les cas qui ne le sont pas.
 *
 * ## Le courriel ne s'éteint pas
 *
 * `compte_suspendu` et `compte_retabli` sont **verrouillés** au catalogue. Une
 * sanction dont on n'informe pas la personne n'est pas une sanction : de son
 * point de vue, c'est une panne — et elle passera son temps à la signaler.
 */
final class SuspensionDeCompte
{
    /** Au-delà, le motif ne tient plus dans le courriel ni dans la colonne. */
    public const MOTIF_MAXIMUM = 500;

    public function __construct(
        private readonly AuditLogService $journal,
        private readonly ExpediteurDeCourriels $courriels,
    ) {}

    /**
     * @throws RuntimeException si le motif est vide, ou si la cible est l'acteur
     */
    public function suspendre(User $cible, User $acteur, string $motif): User
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new RuntimeException('Une suspension sans motif ne se conteste pas : le motif est obligatoire.');
        }

        if ($cible->id === $acteur->id) {
            /*
             | Se suspendre soi-même n'est pas une erreur théorique : c'est la
             | ligne du dessus dans une liste triée, et le geste est
             | irréversible depuis un compte qu'on vient de fermer.
             */
            throw new RuntimeException('On ne suspend pas son propre compte.');
        }

        if ($cible->estSuspendu()) {
            // Déjà suspendu : ne pas réécrire la date, sinon « depuis quand »
            // devient faux à chaque clic.
            return $cible;
        }

        $avant = $cible->only(['suspended_at', 'suspension_reason']);

        DB::transaction(function () use ($cible, $acteur, $motif): void {
            $cible->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => mb_substr($motif, 0, self::MOTIF_MAXIMUM),
                'suspended_by' => $acteur->id,
            ])->save();

            $this->fermerLesSessions($cible);
        });

        $this->journal->record(
            action: 'user.suspend',
            module: 'moderation',
            acteur: $acteur,
            cible: $cible,
            avant: $avant,
            apres: $cible->only(['suspended_at', 'suspension_reason']),
            cibleLibelle: (string) $cible->email,
        );

        $this->courriels->envoyer(
            $cible,
            'compte_suspendu',
            ['prenom' => $cible->name, 'motif' => $motif],
            route('public.contact'),
        );

        return $cible;
    }

    public function retablir(User $cible, User $acteur): User
    {
        if (! $cible->estSuspendu()) {
            return $cible;
        }

        $avant = $cible->only(['suspended_at', 'suspension_reason']);

        $cible->forceFill([
            'suspended_at' => null,
            // Le motif s'efface AVEC la suspension : le garder ferait porter au
            // compte rétabli la trace d'une sanction levée. L'historique de la
            // décision reste au journal d'audit, qui est fait pour ça.
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();

        $this->journal->record(
            action: 'user.unsuspend',
            module: 'moderation',
            acteur: $acteur,
            cible: $cible,
            avant: $avant,
            apres: $cible->only(['suspended_at', 'suspension_reason']),
            cibleLibelle: (string) $cible->email,
        );

        $this->courriels->envoyer(
            $cible,
            'compte_retabli',
            ['prenom' => $cible->name],
            route('citizen.dashboard'),
        );

        return $cible;
    }

    /**
     * Ferme toutes les sessions ouvertes de ce compte.
     *
     * Sans cela, la personne reste connectée jusqu'à son prochain retour : elle
     * continue de publier pendant des heures sur un compte officiellement
     * suspendu, et la modération croit le problème réglé.
     *
     * Le pilote de session est `database` ; le middleware `CompteActif` rattrape
     * de toute façon la requête suivante, mais l'attendre laisserait une fenêtre
     * ouverte sur les onglets déjà chargés.
     */
    private function fermerLesSessions(User $cible): void
    {
        DB::table('sessions')->where('user_id', $cible->id)->delete();
    }
}
