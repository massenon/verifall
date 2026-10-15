<?php

declare(strict_types=1);

namespace App\Core\Gamification\Services;

use App\Enums\MotifGainXp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Le premier réflexe de fact-checking, demandé à l'arrivée.
 *
 * Une seule question, posée une seule fois. Elle n'évalue personne : elle fait
 * vivre en trente secondes le geste que la plateforme enseigne — ne pas cliquer,
 * aller voir à la source.
 *
 * La question et ses réponses viennent du prototype et n'en bougent pas. Ce
 * n'est pas une donnée mesurée, c'est du contenu éditorial, au même titre que
 * n'importe quel texte d'écran.
 */
final class QuizDAccueil
{
    /** Récompense annoncée dans la modale — les deux doivent rester d'accord. */
    public const XP = 50;

    /** Délai après « Me rappeler plus tard ». */
    public const HEURES_DE_REPIT = 1;

    /** La bonne réponse : vérifier à la source, et signaler. */
    public const BONNE_REPONSE = 2;

    public function __construct(private readonly JournalDXp $journalXp) {}

    public function question(): string
    {
        return 'Un message WhatsApp annonce une « aide de l\'État » contre vos '
            .'informations bancaires. Que faites-vous ?';
    }

    /**
     * @return list<string>
     */
    public function reponses(): array
    {
        return [
            'Je clique pour vérifier si je suis éligible',
            'Je le transfère à mes proches par précaution',
            'Je vérifie sur les canaux officiels et je le signale sur Vérif\'All',
        ];
    }

    /** Faut-il ouvrir la modale à cette visite ? */
    public function aPoser(User $utilisateur): bool
    {
        if ($utilisateur->onboarding_quiz_done_at !== null) {
            return false;
        }

        $repit = $utilisateur->onboarding_quiz_snoozed_at;

        return $repit === null || $repit->addHours(self::HEURES_DE_REPIT)->isPast();
    }

    /**
     * Enregistre la réponse.
     *
     * L'XP n'est crédité qu'à la bonne réponse ET une seule fois : le quiz
     * reste ouvert après une erreur, sans quoi se tromper fermerait la porte
     * de l'apprentissage au moment précis où il commence.
     */
    public function repondre(User $utilisateur, int $choix): bool
    {
        $juste = $choix === self::BONNE_REPONSE;

        if (! $juste || $utilisateur->onboarding_quiz_done_at !== null) {
            return $juste;
        }

        DB::transaction(function () use ($utilisateur): void {
            $this->journalXp->crediter($utilisateur, self::XP, MotifGainXp::QuizDAccueil);
            $utilisateur->forceFill(['onboarding_quiz_done_at' => now()])->save();
        });

        return true;
    }

    /** « Me rappeler plus tard » — un délai, pas un refus. */
    public function reporter(User $utilisateur): void
    {
        $utilisateur->forceFill(['onboarding_quiz_snoozed_at' => now()])->save();
    }

    public function commentaire(bool $juste): string
    {
        return $juste
            ? 'Exact ! Aucune institution ne demande vos informations bancaires '
                .'par WhatsApp. +'.self::XP.' XP'
            : 'Attention : c\'est exactement le piège. La bonne réponse est de '
                .'vérifier sur les canaux officiels et de signaler.';
    }
}
