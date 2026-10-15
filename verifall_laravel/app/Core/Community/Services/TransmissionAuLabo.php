<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Jobs\AnalyserVerification;
use App\Models\Thread;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;

/**
 * Du forum au laboratoire, et retour.
 *
 * C'est la charnière du produit : une rumeur signalée par un citoyen devient
 * une vérification, et le verdict revient s'afficher sur le sujet. Sans ce
 * chemin, le forum ne serait qu'un espace de commentaires de plus.
 *
 * Deux règles :
 *
 *   · La transmission est GRATUITE pour l'auteur du signalement. Lui faire
 *     payer des unités pour rendre service à tout le monde serait absurde :
 *     c'est la plateforme qui décide de vérifier, pas lui qui commande.
 *   · Un sujet n'est transmis QU'UNE FOIS. Deux analyses de la même rumeur
 *     coûteraient deux fois, et pourraient rendre deux verdicts différents sur
 *     le même fil.
 */
final class TransmissionAuLabo
{
    public function __construct(
        private readonly SoumissionVerificationService $soumissions,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Envoie un sujet à l'analyse.
     *
     * Rend la vérification existante si le sujet a déjà été transmis : du point
     * de vue de qui clique, le résultat voulu est atteint.
     */
    public function transmettre(User $demandeur, Thread $sujet): Verification
    {
        return DB::transaction(function () use ($demandeur, $sujet): Verification {
            $sujet->refresh();

            if ($sujet->verification_id !== null) {
                return Verification::findOrFail($sujet->verification_id);
            }

            /*
             | Le LIEN signalé prime sur le corps du message quand il existe :
             | c'est lui la pièce à vérifier, le texte n'en est que le résumé.
             */
            $type = $sujet->source_url !== null ? TypeContenu::Lien : TypeContenu::Texte;
            $affirmation = $sujet->source_url ?? $sujet->title;

            $verification = $this->soumissions->soumettreSansDebit(
                type: $type,
                affirmation: $affirmation,
                contexte: $sujet->title.' — '.strip_tags($sujet->body),
            );

            /*
             | La TRAÇABILITÉ de l'escalade est écrite ici, sur le fil, et pas
             | seulement dans le journal d'audit : le journal est une preuve,
             | pas une source d'affichage. Sans ces deux colonnes, on ne peut
             | répondre ni à « depuis combien de temps attend-on ce verdict ? »
             | ni à « ses escalades aboutissent-elles ? » — qui est la matière
             | première de la réputation citoyenne.
             */
            $sujet->forceFill([
                'verification_id' => $verification->id,
                'escalated_by' => $demandeur->id,
                'escalated_at' => now(),
            ])->save();

            AnalyserVerification::dispatch($verification->id);

            $this->audit->record(
                AuditLogService::CREATION, 'forum', $demandeur,
                cible: $sujet,
                apres: ['verification' => $verification->uuid],
                cibleLibelle: 'Transmission au labo : '.$sujet->title,
            );

            return $verification;
        });
    }

    /**
     * Qui a le droit d'escalader un sujet en fact-check ?
     *
     * L'AUTEUR du sujet et les modérateurs, et personne d'autre. Le raisonnement
     * a deux branches :
     *
     *   · ouvrir le bouton à tout le monde ferait de chaque fil une file
     *     d'analyses — le laboratoire est gratuit pour le demandeur, il ne l'est
     *     pas pour la plateforme, et une rumeur populaire serait escaladée
     *     cinquante fois avant que la première analyse ne rende son verdict ;
     *   · le réserver aux seuls modérateurs, comme c'était le cas, revient à
     *     enterrer le mécanisme central du produit dans un bandeau que 99 % des
     *     visiteurs ne voient jamais. Celui qui a signalé la rumeur est
     *     précisément celui qui veut savoir.
     *
     * Un sujet déjà transmis ne se ré-escalade pas : deux analyses de la même
     * rumeur pourraient rendre deux verdicts sur le même fil.
     */
    public function peutEscalader(User $demandeur, Thread $sujet): bool
    {
        if ($sujet->verification_id !== null) {
            return false;
        }

        return $sujet->user_id === $demandeur->id || $demandeur->can('forum.moderate');
    }

    /**
     * Appose le verdict officiel de la plateforme sur un sujet.
     *
     * Distinct de l'avis de la communauté, et il le reste à l'écran : une
     * majorité qui se trompe reste une majorité qui se trompe.
     */
    public function apposerLeVerdict(User $agent, Thread $sujet, VerdictFactCheck $verdict): Thread
    {
        $sujet->forceFill([
            'official_verdict' => $verdict->value,
            'verified_by' => $agent->id,
            'verified_at' => now(),
        ])->save();

        $this->audit->record(
            AuditLogService::PUBLICATION, 'forum', $agent,
            cible: $sujet,
            apres: ['verdict' => $verdict->value],
            cibleLibelle: 'Verdict officiel « '.$verdict->libelle().' » : '.$sujet->title,
        );

        return $sujet->refresh();
    }
}
