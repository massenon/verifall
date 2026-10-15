<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\VerdictFactCheck;

/**
 * Agrège les avis des moteurs en un verdict.
 *
 * La règle centrale n'est pas de trancher, c'est de savoir NE PAS trancher.
 * Un verdict n'est retenu que s'il devance nettement le suivant et dépasse un
 * plancher de confiance. Sinon on l'annonce : contradiction, arbitrage humain.
 *
 * Publier un faux consensus sur une plateforme de fact-checking serait
 * exactement ce qu'elle combat.
 */
final class FactCheckScoringService
{
    /**
     * @param  list<VerdictMoteur>  $resultats
     */
    public function decider(array $resultats): DecisionFactCheck
    {
        $retenus = array_values(array_filter(
            $resultats,
            static fn (VerdictMoteur $r): bool => $r->compteDansLeScore(),
        ));

        $minimum = (int) config('factcheck.decision.moteurs_minimum', 2);

        if (count($retenus) < $minimum) {
            /*
             | Moins de deux avis exploitables : on ne fabrique pas un verdict
             | avec ça. Le rapport sort quand même — un écran vide serait pire —
             | mais il dit franchement qu'il est partiel, et les unités sont
             | remboursées.
             */
            return DecisionFactCheck::partielle(count($retenus));
        }

        $scores = $this->scoresParVerdict($retenus);
        arsort($scores);

        $verdicts = array_keys($scores);
        $premier = $verdicts[0];
        $scorePremier = $scores[$premier];
        $scoreSecond = count($verdicts) > 1 ? $scores[$verdicts[1]] : 0.0;

        $ecart = $scorePremier - $scoreSecond;
        $ecartMinimum = (float) config('factcheck.decision.ecart_minimum', 25);
        $scoreMinimum = (float) config('factcheck.decision.score_minimum', 60);

        if ($ecart >= $ecartMinimum && $scorePremier >= $scoreMinimum) {
            $verdict = VerdictFactCheck::from((string) $premier);

            return DecisionFactCheck::tranchee(
                $verdict,
                $this->fiabilite($scorePremier, $retenus, $verdict),
                $scores,
            );
        }

        /*
         | Contradiction. Le verdict penche vers « Trompeur » quand les deux
         | thèses en présence sont « Vrai » et « Faux » : c'est la signature
         | d'une affirmation partiellement exacte, présentée de travers. Dans
         | les autres cas, on reconnaît qu'on ne sait pas.
         */
        $these = array_slice($verdicts, 0, 2);
        $partiellementVraie = in_array(VerdictFactCheck::Vrai->value, $these, true)
            && in_array(VerdictFactCheck::Faux->value, $these, true);

        $verdict = $partiellementVraie ? VerdictFactCheck::Trompeur : VerdictFactCheck::NonVerifiable;

        return DecisionFactCheck::contradictoire(
            $verdict,
            $this->fiabilite($scorePremier, $retenus, $verdict),
            $scores,
        );
    }

    /**
     * L'indice de FIABILITÉ — qui n'est pas la part de consensus.
     *
     * ## Ce que le rapport affichait, et pourquoi c'était faux
     *
     * Le chiffre sous « Indice de fiabilité » était `$scorePremier`, c'est-à-dire
     * la part du poids total qui penche du côté du verdict retenu. Deux moteurs
     * d'accord donnent 100 % — même quand l'un annonce 95 % de confiance et
     * l'autre 98 %. La page promettait donc une certitude absolue là où les
     * moteurs eux-mêmes gardaient une réserve, sur un produit dont tout le
     * propos est de ne pas surpromettre.
     *
     * ## Les deux grandeurs, et pourquoi il faut les deux
     *
     *   · Le CONSENSUS dit si les moteurs sont d'accord entre eux. Trois
     *     moteurs unanimes mais peu sûrs : consensus 100, et ce n'est pas une
     *     garantie.
     *   · La CONFIANCE dit à quel point chacun s'engage sur son propre avis.
     *     Un moteur seul à 99 % n'est pas un recoupement.
     *
     * Ni l'une ni l'autre ne suffit. Leur PRODUIT, lui, ne peut être élevé que
     * si les deux le sont : il faut que les moteurs soient d'accord ET sûrs.
     * C'est la seule composition qui ne mente dans aucun des deux sens.
     *
     * La confiance retenue est celle des moteurs qui ont voté POUR le verdict,
     * pondérée par leur poids au barème. Y mêler les dissidents ferait baisser
     * l'indice à cause d'avis qui, précisément, n'ont pas été suivis — on
     * mesure la solidité de la conclusion, pas la moyenne du désaccord.
     *
     * @param  list<VerdictMoteur>  $retenus
     */
    private function fiabilite(float $consensus, array $retenus, VerdictFactCheck $verdict): int
    {
        $poids = 0.0;
        $somme = 0.0;

        foreach ($retenus as $resultat) {
            if ($resultat->verdict !== $verdict) {
                continue;
            }

            $p = $resultat->moteur->poids();
            $poids += $p;
            $somme += $p * $resultat->confiance;
        }

        /*
         | Aucun moteur n'a voté pour le verdict retenu : c'est le cas d'une
         | contradiction rabattue sur « Trompeur » ou « Non vérifiable », deux
         | verdicts que personne n'a énoncés tels quels. Le consensus seul fait
         | alors foi — il n'y a pas de confiance à pondérer.
         */
        if ($poids <= 0.0) {
            return (int) round($consensus);
        }

        return (int) round($consensus / 100 * ($somme / $poids));
    }

    /**
     * Score normalisé 0-100 par verdict.
     *
     * @param  list<VerdictMoteur>  $retenus
     * @return array<string, float>
     */
    private function scoresParVerdict(array $retenus): array
    {
        $bruts = [];
        $total = 0.0;

        foreach ($retenus as $resultat) {
            $poids = $resultat->moteur->poids() * ($resultat->confiance / 100);
            $cle = $resultat->verdict?->value ?? VerdictFactCheck::NonVerifiable->value;

            $bruts[$cle] = ($bruts[$cle] ?? 0.0) + $poids;
            $total += $poids;
        }

        if ($total <= 0.0) {
            return [VerdictFactCheck::NonVerifiable->value => 0.0];
        }

        // Normalisation : sans elle, le score dépendrait du NOMBRE de moteurs
        // interrogés, et une analyse plus large paraîtrait toujours plus sûre.
        return array_map(static fn (float $brut): float => $brut / $total * 100, $bruts);
    }

    /**
     * Contrôle de cohérence avant publication.
     *
     * Le verdict doit être compatible avec ce que disent majoritairement les
     * preuves. Un rapport dont les sources contredisent son propre verdict
     * détruirait la confiance plus sûrement qu'une erreur franche.
     *
     * @param  list<VerdictMoteur>  $resultats
     */
    public function verdictCoherent(VerdictFactCheck $verdict, array $resultats): bool
    {
        // Ces deux-là n'affirment rien de tranché : rien à contredire.
        if (in_array($verdict, [VerdictFactCheck::NonVerifiable, VerdictFactCheck::Trompeur], true)) {
            return true;
        }

        $pour = 0;
        $contre = 0;

        foreach ($resultats as $resultat) {
            if (! $resultat->compteDansLeScore()) {
                continue;
            }

            $resultat->verdict === $verdict ? $pour++ : $contre++;
        }

        return $pour >= $contre;
    }
}
