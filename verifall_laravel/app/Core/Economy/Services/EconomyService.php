<?php

declare(strict_types=1);

namespace App\Core\Economy\Services;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Enums\MotifMouvementUnite;
use App\Models\UnitLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Le solde d'unités — seul endroit de l'application qui a le droit d'y toucher.
 *
 * Trois garanties, toutes indispensables et toutes testées :
 *
 *   1. TRANSACTIONNEL. Le solde et sa ligne de journal sont écrits ensemble ou
 *      pas du tout. Un crédit sans trace est un solde inexplicable ; une trace
 *      sans crédit est une réclamation.
 *   2. VERROUILLÉ. La ligne du compte est verrouillée pendant l'opération. Sans
 *      cela, deux vérifications lancées simultanément liraient le même solde et
 *      n'en débiteraient qu'une seule — la seconde serait offerte.
 *   3. JAMAIS NÉGATIF. Un débit qui dépasse le solde est refusé, pas rogné :
 *      accepter en mettant à zéro ferait payer un service sans le facturer.
 */
final class EconomyService
{
    public function __construct(
        private readonly UnitLogRepositoryInterface $journal,
        private readonly ExpediteurDeCourriels $courriels,
    ) {}

    /**
     * Crédite un compte.
     */
    public function crediter(
        User $utilisateur,
        int $unites,
        MotifMouvementUnite $motif,
        ?string $libelle = null,
        ?Model $source = null,
    ): UnitLog {
        if ($unites <= 0) {
            throw new RuntimeException('Un crédit porte sur un nombre d\'unités strictement positif.');
        }

        return $this->mouvement($utilisateur, $unites, $motif, $libelle, $source);
    }

    /**
     * Débite un compte.
     *
     * @throws SoldeInsuffisant si le solde ne couvre pas la dépense
     */
    public function debiter(
        User $utilisateur,
        int $unites,
        MotifMouvementUnite $motif,
        ?string $libelle = null,
        ?Model $source = null,
    ): UnitLog {
        if ($unites <= 0) {
            throw new RuntimeException('Un débit porte sur un nombre d\'unités strictement positif.');
        }

        return $this->mouvement($utilisateur, -$unites, $motif, $libelle, $source);
    }

    /**
     * Le compte peut-il supporter cette dépense ?
     *
     * À utiliser pour AFFICHER un coût, jamais pour autoriser : entre la
     * question et la dépense, le solde peut avoir bougé. Seul `debiter()`
     * tranche, sous verrou.
     */
    public function peutPayer(User $utilisateur, int $unites): bool
    {
        return $utilisateur->units >= $unites;
    }

    /**
     * @param  int  $delta  signé : positif au crédit, négatif au débit
     */
    private function mouvement(
        User $utilisateur,
        int $delta,
        MotifMouvementUnite $motif,
        ?string $libelle,
        ?Model $source,
    ): UnitLog {
        $ligne = DB::transaction(function () use ($utilisateur, $delta, $motif, $libelle, $source): UnitLog {
            /*
             | lockForUpdate : le solde est relu DEPUIS LA BASE, verrouillé, à
             | l'intérieur de la transaction. L'instance passée en argument peut
             | dater de plusieurs requêtes ; s'y fier ferait perdre les
             | mouvements intervenus entre-temps.
             */
            $verrouille = User::whereKey($utilisateur->getKey())->lockForUpdate()->firstOrFail();

            $soldeApres = $verrouille->units + $delta;

            if ($soldeApres < 0) {
                throw new SoldeInsuffisant($verrouille->units, abs($delta));
            }

            $verrouille->forceFill(['units' => $soldeApres])->save();

            // L'instance de l'appelant est alignée : la laisser périmée
            // conduirait à afficher l'ancien solde juste après l'opération.
            $utilisateur->setAttribute('units', $soldeApres);

            return $this->journal->enregistrer([
                'uuid' => (string) Str::uuid(),
                'user_id' => $verrouille->id,
                'delta' => $delta,
                'balance_after' => $soldeApres,
                'reason' => $motif->value,
                // `libelleCanonique()` et non `libelle()` : ce qui part en base
                // ne doit pas dépendre de la langue de l'écran. Voir le bloc de
                // documentation de l'enum.
                'label' => $libelle ?? $motif->libelleCanonique(),
                'source_type' => $source !== null ? $source::class : null,
                'source_id' => $source?->getKey(),
            ]);
        });

        $this->prevenirDuCredit($utilisateur, $ligne, $motif);

        return $ligne;
    }

    /**
     * Prévient d'un CRÉDIT, hors transaction.
     *
     * ## Pourquoi seulement les crédits
     *
     * Un débit, c'est une vérification qu'on vient de lancer soi-même, écran
     * ouvert : la personne le sait déjà, et le lui écrire ferait un courriel par
     * usage du produit. Un crédit, lui, arrive souvent sans qu'on soit là — une
     * dotation mensuelle, un parrainage qui aboutit, un remboursement.
     *
     * ## Le bonus de bienvenue est EXCLU
     *
     * Il est annoncé par le courriel de bienvenue, qui part au même instant.
     * Deux messages en une seconde pour dire la même chose est la meilleure
     * façon d'apprendre à quelqu'un à nous filtrer.
     *
     * ## Hors transaction, comme la félicitation de fin de cours
     *
     * `Mail::queue()` écrit dans la table `jobs`, qui participe à la
     * transaction en cours : un rollback laisserait un courriel annonçant des
     * unités que le compte n'a jamais reçues.
     */
    private function prevenirDuCredit(User $utilisateur, UnitLog $ligne, MotifMouvementUnite $motif): void
    {
        if ($ligne->delta <= 0 || $motif === MotifMouvementUnite::Bienvenue) {
            return;
        }

        $this->courriels->envoyer(
            $utilisateur,
            'unites_creditees',
            [
                'prenom' => $utilisateur->name,
                'unites' => (int) $ligne->delta,
                'motif' => $motif->libelle(),
                'solde' => (int) $ligne->balance_after,
            ],
            route('citizen.units'),
        );
    }
}
