<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\PositionPreuve;
use App\Enums\VerdictFactCheck;

/**
 * Empêche un rapport de se contredire lui-même.
 *
 * Un rapport qui titre « FAUX » et dont la synthèse commence par « cette
 * information est exacte » détruit la confiance plus sûrement qu'une erreur
 * franche : le lecteur ne sait plus quoi croire, et il a raison.
 *
 * Trois niveaux sont confrontés, parce qu'ils sont produits séparément et
 * peuvent diverger :
 *
 *   1. le VERDICT, calculé par le scoring ;
 *   2. la SYNTHÈSE, rédigée — aujourd'hui côté serveur, demain par un modèle ;
 *   3. les DÉTAILS : ce que chaque moteur a répondu, et ce que disent les
 *      preuves.
 *
 * Le contrôle est utile dès maintenant, mais il prend tout son sens le jour où
 * la synthèse sera écrite par un modèle de langage : c'est exactement là qu'un
 * rapport commencera à dire autre chose que son propre verdict.
 */
final class CoherenceRapport
{
    /**
     * Le verdict est-il compatible avec ce que disent les moteurs ?
     *
     * @param  list<VerdictMoteur>  $resultats
     */
    public function verdictSuitLesMoteurs(VerdictFactCheck $verdict, array $resultats): bool
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

    /**
     * La synthèse dit-elle la même chose que le verdict ?
     *
     * On cherche l'affirmation CONTRAIRE, pas la confirmation : une synthèse
     * peut légitimement ne pas répéter le mot du verdict — « les sources
     * concordent » vaut « vrai ». En revanche, trouver « cette information est
     * fausse » sous un verdict « VRAI » est une contradiction, quelle que soit
     * la formulation du reste.
     */
    public function syntheseSuitLeVerdict(VerdictFactCheck $verdict, ?string $synthese): bool
    {
        if ($synthese === null || trim($synthese) === '') {
            return true;
        }

        $texte = mb_strtolower($synthese);

        $contraires = match ($verdict) {
            VerdictFactCheck::Vrai => [
                'information est fausse', 'est un faux', 'sont fausses',
                'contredisent cette information', 'aucune source ne confirme',
            ],
            VerdictFactCheck::Faux => [
                'information est exacte', 'information est vraie',
                'confirment cette information', 'est authentique',
            ],
            // « Trompeur » et « non vérifiable » ne nient rien : aucune
            // formulation ne peut les contredire frontalement.
            default => [],
        };

        foreach ($contraires as $expression) {
            if (str_contains($texte, $expression)) {
                return false;
            }
        }

        return true;
    }

    /**
     * AU MOINS UN raisonnement de moteur s'accorde-t-il avec le verdict ?
     *
     * C'est le contrôle du TEXTE AFFICHÉ, là où `syntheseSuitLeVerdict`
     * contrôle la phrase serveur. Les deux comptent : le rapport montre les
     * deux, et un Hero « FAUX » au-dessus d'un raisonnement de moteur qui
     * conclut à l'authenticité est exactement la dissonance que la plateforme
     * est censée rendre impossible.
     *
     * On cherche un texte QUI TIENT, pas l'unanimité. Les moteurs ont le droit
     * de diverger — c'est même le fonctionnement prévu ; celui dont le texte
     * est affiché, en revanche, doit s'accorder avec le verdict retenu. D'où
     * l'existence et non l'universalité : un seul raisonnement soutenant le
     * verdict suffit à ce que le rapport ait une explication à afficher.
     *
     * Aucun texte du tout : rien à confronter, la phrase serveur seule fera
     * l'affaire — elle est composée depuis le verdict, elle ne peut pas le
     * contredire.
     *
     * @param  list<VerdictMoteur>  $resultats
     */
    public function unRaisonnementSAppuieSurLeVerdict(VerdictFactCheck $verdict, array $resultats): bool
    {
        $textes = array_values(array_filter(
            $resultats,
            static fn (VerdictMoteur $r): bool => ! $r->echoue && trim($r->raisonnement) !== '',
        ));

        if ($textes === []) {
            return true;
        }

        foreach ($textes as $resultat) {
            if ($this->syntheseSuitLeVerdict($verdict, $resultat->raisonnement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Les preuves penchent-elles du côté du verdict ?
     *
     * Tolérante à dessein : un dossier contient normalement des sources des
     * deux bords — c'est même le signe d'une vérification honnête. On ne
     * s'alarme que d'un renversement NET, où les preuves contraires écrasent
     * les autres.
     *
     * @param  list<PositionPreuve>  $positions
     */
    public function preuvesSuiventLeVerdict(VerdictFactCheck $verdict, array $positions): bool
    {
        if (in_array($verdict, [VerdictFactCheck::NonVerifiable, VerdictFactCheck::Trompeur], true)) {
            return true;
        }

        $confirment = count(array_filter($positions, fn ($p) => $p === PositionPreuve::Confirme));
        $contredisent = count(array_filter($positions, fn ($p) => $p === PositionPreuve::Contredit));

        // Aucune preuve orientée : rien à confronter.
        if ($confirment + $contredisent === 0) {
            return true;
        }

        [$pour, $contre] = $verdict === VerdictFactCheck::Vrai
            ? [$confirment, $contredisent]
            : [$contredisent, $confirment];

        // Le renversement doit être franc — deux fois plus de preuves
        // contraires — pour valoir alerte.
        return $contre <= $pour * 2;
    }

    /**
     * Contrôle d'ensemble.
     *
     * @param  list<VerdictMoteur>  $resultats
     * @param  list<PositionPreuve>  $positions
     * @return string|null motif d'incohérence, ou null si tout concorde
     */
    public function incoherence(
        VerdictFactCheck $verdict,
        ?string $synthese,
        array $resultats,
        array $positions,
    ): ?string {
        if (! $this->verdictSuitLesMoteurs($verdict, $resultats)) {
            return 'Le verdict ne suit pas la majorité des moteurs d\'analyse.';
        }

        if (! $this->syntheseSuitLeVerdict($verdict, $synthese)) {
            return 'La synthèse contredit le verdict retenu.';
        }

        /*
         | Le texte que le rapport AFFICHERA (« Pourquoi ce résultat ? »)
         | passé lui aussi. La phrase serveur est composée depuis le verdict :
         | elle ne peut pas le contredire. Le raisonnement d'un moteur, lui,
         | est écrit par un modèle qui peut divaguer — et il occupe l'essentiel
         | de la page. Aucun texte qui le tienne : rien à montrer, et le
         | dossier va à l'arbitrage plutôt que de publier un rapport muet sur
         | son propre verdict.
         */
        if (! $this->unRaisonnementSAppuieSurLeVerdict($verdict, $resultats)) {
            return 'Aucun raisonnement des moteurs ne s\'accorde avec le verdict retenu.';
        }

        if (! $this->preuvesSuiventLeVerdict($verdict, $positions)) {
            return 'Les sources consultées contredisent le verdict retenu.';
        }

        return null;
    }
}
