<?php

declare(strict_types=1);

namespace App\Core\Economy\Providers;

use App\Models\Payment;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Simulateur d'opérateur Mobile Money.
 *
 * Aucun contrat opérateur n'est signé : ce fournisseur tient la place des vrais
 * en reproduisant le PROTOCOLE complet — demande, attente, rappel signé,
 * réinterrogation. Le jour où MTN et Moov arrivent, seule cette classe est
 * remplacée ; le service, le rappel et l'écran ne bougent pas.
 *
 * Il refuse de fonctionner en production : un simulateur qui crédite des unités
 * sans qu'aucun franc n'ait été encaissé n'a rien à y faire.
 */
final class FakeMomoProvider implements MomoProviderInterface
{
    public function demanderPaiement(Payment $paiement, string $numero): string
    {
        $this->refuserEnProduction();

        return 'FAKE-'.Str::upper(Str::random(12));
    }

    /**
     * Le simulateur ne décide jamais tout seul : il répond « en attente » et
     * laisse la personne déclencher le rappel depuis le bouton de simulation.
     *
     * C'est fidèle au vrai protocole, où l'issue dépend d'une validation sur le
     * téléphone, et cela évite qu'un test devienne dépendant du hasard.
     */
    public function interroger(Payment $paiement): ResultatPaiement
    {
        $this->refuserEnProduction();

        return ResultatPaiement::enAttente();
    }

    /**
     * Signature HMAC, calculée exactement comme le fera le vrai fournisseur.
     *
     * @param  array<string, mixed>  $charge
     */
    public function verifierSignature(array $charge, string $signature): bool
    {
        $secret = config('economy.momo.webhook_secret');

        // Pas de secret configuré : on refuse TOUT. Accepter par défaut
        // ouvrirait le crédit d'unités à qui devine l'URL du rappel.
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $attendue = hash_hmac('sha256', $this->corpsCanonique($charge), $secret);

        // Comparaison à temps constant : une comparaison ordinaire laisse
        // deviner la signature octet par octet.
        return hash_equals($attendue, $signature);
    }

    /**
     * Signe une charge — utilisé par le bouton de simulation et par les tests.
     *
     * @param  array<string, mixed>  $charge
     */
    public function signer(array $charge): string
    {
        $secret = config('economy.momo.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('PAYMENT_WEBHOOK_SECRET est vide : impossible de signer.');
        }

        return hash_hmac('sha256', $this->corpsCanonique($charge), $secret);
    }

    /**
     * Représentation stable de la charge.
     *
     * Les clés sont triées : deux JSON équivalents mais ordonnés différemment
     * donneraient sinon deux signatures différentes, et un rappel légitime
     * serait rejeté.
     *
     * @param  array<string, mixed>  $charge
     */
    private function corpsCanonique(array $charge): string
    {
        ksort($charge);

        return (string) json_encode($charge, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function refuserEnProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Le simulateur Mobile Money crédite des unités sans encaissement : '
                .'il est interdit en production. Configurez MOMO_DRIVER.'
            );
        }
    }
}
