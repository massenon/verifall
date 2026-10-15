<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Enums\StatutVerification;
use App\Models\Verification;
use App\Models\VerifiedFact;

/**
 * Cherche une réponse avant de payer pour en produire une.
 *
 * Une rumeur virale est signalée des centaines de fois. Relancer douze moteurs
 * payants à chaque signalement coûterait cher pour rendre douze fois la même
 * réponse — et ferait attendre chacun pour rien.
 *
 * Deux sources, dans cet ordre :
 *
 *   1. la BASE DE FAITS tranchés par l'équipe. Quand une rédaction a statué,
 *      aucun modèle n'a besoin d'en rediscuter, et la réponse est meilleure ;
 *   2. une ANALYSE RÉCENTE du même contenu. Au-delà de quelques jours on
 *      relance : une information peut être démentie la semaine suivante, et
 *      servir un verdict périmé serait pire que de ne rien servir.
 */
final class ReponseDejaConnue
{
    /** Au-delà, une analyse est réputée trop ancienne pour être resservie. */
    public const JOURS_DE_FRAICHEUR = 7;

    /**
     * Proximité minimale pour rapprocher deux formulations.
     *
     * 0,65 est délibérément haut. Un seuil bas rapprocherait deux rumeurs qui
     * partagent seulement leur vocabulaire — « le gouvernement annonce une
     * taxe » et « le gouvernement annonce une baisse » — et servirait le
     * verdict de l'une pour l'autre. Sur un fact-check, cette erreur-là est la
     * plus grave possible.
     */
    private const PROXIMITE_MINIMALE = 0.65;

    /**
     * Nombre minimal de mots significatifs partagés.
     *
     * Un ratio seul se laisse tromper par un fait aux mots-clés très courts :
     * deux mots partagés sur deux font 100 %. Il faut un faisceau, pas une
     * coïncidence.
     */
    private const MOTS_COMMUNS_MINIMUM = 3;

    public function __construct(
        private readonly EmpreinteContenu $empreintes,
        private readonly DepotFaitsVerifies $faits,
    ) {}

    /**
     * Un fait déjà tranché correspond-il ?
     */
    public function faitConnu(string $affirmation, ?string $empreinte = null): ?VerifiedFact
    {
        // Correspondance exacte : le cas le plus sûr, et le moins coûteux.
        if ($empreinte !== null) {
            $exact = $this->faits->parEmpreinte($empreinte);

            if ($exact !== null) {
                return $exact;
            }
        }

        /*
         | Rapprochement par mots-clés, calculé en PHP. Une recherche plein
         | texte serait plus fine mais dépendrait du moteur de base de données,
         | et devrait être réécrite à la première migration. La table des faits
         | est curée à la main : elle reste petite, le parcours est tenable.
         */
        $meilleur = null;
        $meilleureProximite = 0.0;

        foreach ($this->faits->publies() as $fait) {
            /*
             | Deux mesures, chacune pour ce qu'elle sait faire : la COUVERTURE
             | demande si les mots-clés curés par l'équipe sont présents, la
             | PROXIMITÉ compare deux formulations libres. Utiliser le
             | recouvrement mutuel sur des mots-clés pénaliserait la phrase pour
             | chaque mot qu'elle contient en plus.
             */
            $proximite = max(
                $this->empreintes->recouvrement($affirmation, $fait->keywords),
                $this->empreintes->recouvrement($affirmation, $fait->title),
            );

            /*
             | Deux conditions, pas une. Le RATIO seul se laisse tromper par un
             | fait aux mots-clés très courts : deux mots partagés sur deux font
             | 100 %. Le PLANCHER en nombre absolu écarte ce cas — il faut un
             | faisceau, pas une coïncidence.
             */
            $communs = count($this->empreintes->motsCommuns($affirmation, $fait->keywords.' '.$fait->title));

            if ($proximite > $meilleureProximite && $communs >= self::MOTS_COMMUNS_MINIMUM) {
                $meilleureProximite = $proximite;
                $meilleur = $fait;
            }
        }

        return $meilleureProximite >= self::PROXIMITE_MINIMALE ? $meilleur : null;
    }

    /**
     * Une analyse récente du même contenu existe-t-elle ?
     *
     * Seules les analyses TERMINÉES sont reprises : une analyse en arbitrage
     * humain n'a pas encore de verdict qui engage la plateforme, et une analyse
     * partielle ne mérite pas d'être resservie.
     */
    public function analyseRecente(string $empreinte, ?int $saufVerificationId = null): ?Verification
    {
        return Verification::where('content_fingerprint', $empreinte)
            ->where('status', StatutVerification::Terminee->value)
            ->when($saufVerificationId !== null, fn ($q) => $q->where('id', '!=', $saufVerificationId))
            // Jamais une analyse remboursée : elle était incomplète, c'est
            // précisément pourquoi elle a été remboursée.
            ->whereNull('refunded_at')
            ->where('completed_at', '>=', now()->subDays(self::JOURS_DE_FRAICHEUR))
            ->whereHas('report')
            ->latest('completed_at')
            ->first();
    }
}
