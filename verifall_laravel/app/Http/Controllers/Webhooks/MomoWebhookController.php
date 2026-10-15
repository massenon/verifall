<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Core\Economy\Providers\MomoProviderInterface;
use App\Core\Economy\Services\PaymentService;
use App\Core\Identity\Services\SecurityLogService;
use App\Enums\StatutPaiement;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rappel de l'opérateur Mobile Money.
 *
 * C'est la surface la plus exposée de l'application : une route publique dont
 * l'effet est de CRÉDITER DES UNITÉS. Quatre gardes, dans cet ordre :
 *
 *   1. signature HMAC — seul l'opérateur, qui détient le secret, peut la
 *      produire ;
 *   2. paiement connu — une référence inventée ne mène nulle part ;
 *   3. transition verrouillée en base — un rappel rejoué ne crédite pas deux
 *      fois ;
 *   4. montant lu sur NOTRE ligne, jamais dans la charge reçue — même signée,
 *      elle ne décide pas de ce qu'on doit.
 *
 * Chaque rejet est journalisé : une rafale de signatures invalides est une
 * tentative en cours, pas un incident isolé.
 */
final class MomoWebhookController
{
    public function __invoke(
        Request $requete,
        string $provider,
        MomoProviderInterface $operateur,
        PaymentService $paiements,
        SecurityLogService $securite,
    ): JsonResponse {
        $charge = $requete->json()->all();
        $signature = (string) $requete->header('X-Signature', '');

        if (! $operateur->verifierSignature($charge, $signature)) {
            $securite->record(
                SecurityLogService::UPLOAD_REFUSE, 'critical', null, null,
                ['contexte' => 'rappel momo', 'operateur' => $provider, 'raison' => 'signature invalide'],
            );

            // 401 et non 400 : la charge est peut-être valide, c'est
            // l'émetteur qui n'est pas prouvé.
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $reference = is_string($charge['reference'] ?? null) ? $charge['reference'] : '';
        $paiement = Payment::where('provider_ref', $reference)->first();

        if ($paiement === null) {
            $securite->record(
                SecurityLogService::UPLOAD_REFUSE, 'warning', null, null,
                ['contexte' => 'rappel momo', 'raison' => 'reference inconnue'],
            );

            return response()->json(['message' => 'Référence inconnue.'], 404);
        }

        $issue = ($charge['status'] ?? null) === 'succeeded'
            ? StatutPaiement::Reussi
            : StatutPaiement::Echoue;

        $motif = is_string($charge['reason'] ?? null) ? $charge['reason'] : null;

        $applique = $paiements->denouer($paiement, $issue, $motif);

        /*
         | 200 même sur un doublon, et c'est délibéré : l'opérateur qui rejoue
         | son rappel doit recevoir un accusé, sinon il continue de le rejouer
         | indéfiniment. Le champ `duplicate` dit ce qui s'est réellement passé.
         */
        return response()->json([
            'message' => $applique ? 'Traité.' : 'Déjà traité.',
            'duplicate' => ! $applique,
        ]);
    }
}
