<?php

declare(strict_types=1);

namespace App\Core\Economy\Providers;

use App\Models\Payment;

/**
 * Contrat d'un fournisseur Mobile Money.
 *
 * Volontairement réduit à trois gestes, parce que c'est tout ce que le protocole
 * comporte réellement : demander un paiement, être rappelé, et pouvoir
 * réinterroger quand le rappel ne vient pas.
 *
 * Le troisième n'est pas un luxe : un rappel HTTP se perd, et sans
 * réinterrogation une personne aurait payé sans jamais être créditée.
 */
interface MomoProviderInterface
{
    /**
     * Demande le paiement à l'opérateur.
     *
     * Retourne la référence de l'opérateur, qui servira de clé d'idempotence au
     * moment du rappel.
     */
    public function demanderPaiement(Payment $paiement, string $numero): string;

    /**
     * Réinterroge l'état d'une demande restée sans réponse.
     */
    public function interroger(Payment $paiement): ResultatPaiement;

    /**
     * Le rappel reçu est-il authentique ?
     *
     * @param  array<string, mixed>  $charge
     */
    public function verifierSignature(array $charge, string $signature): bool;
}
