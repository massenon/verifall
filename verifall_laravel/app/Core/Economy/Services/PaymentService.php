<?php

declare(strict_types=1);

namespace App\Core\Economy\Services;

use App\Core\Economy\Providers\MomoProviderInterface;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\MotifMouvementUnite;
use App\Enums\OperateurMobileMoney;
use App\Enums\StatutPaiement;
use App\Models\Payment;
use App\Models\UnitPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Recharges Mobile Money.
 *
 * Deux règles gouvernent tout le reste :
 *
 *   1. LE MONTANT VIENT DU SERVEUR. Le client n'envoie qu'un identifiant de
 *      pack ; le prix et le nombre d'unités sont lus dans la configuration. Un
 *      client qui enverrait « 10 000 unités » n'obtiendrait rien de plus.
 *   2. UN PAIEMENT NE SE DÉNOUE QU'UNE FOIS. La référence de l'opérateur est
 *      unique en base et la transition d'état est verrouillée : un rappel
 *      rejoué — parce que l'opérateur n'a pas reçu notre accusé, ou parce
 *      qu'un attaquant l'a intercepté — ne crédite jamais deux fois.
 */
final class PaymentService
{
    public function __construct(
        private readonly MomoProviderInterface $operateur,
        private readonly EconomyService $economie,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Ouvre une demande et la transmet à l'opérateur.
     */
    public function initier(User $utilisateur, string $pack, string $numero): Payment
    {
        /*
         | Le pack est lu EN BASE, et seulement s'il est en vente.
         |
         | Le client n'envoie qu'un code ; le prix et la quantité restent ici.
         | C'est ce qui empêche d'acheter soixante unités au prix de dix en
         | modifiant un formulaire — et c'est aussi ce qui permet au porteur du
         | projet de changer sa grille sans qu'on redéploie.
         */
        $definition = UnitPack::where('code', $pack)->where('is_active', true)->first();

        if ($definition === null) {
            throw new RuntimeException('Pack inconnu ou retiré de la vente : '.$pack);
        }

        $operateur = OperateurMobileMoney::depuisNumero($numero);

        if ($operateur === null) {
            throw new RuntimeException('Ce numéro ne correspond à aucun opérateur connu.');
        }

        $paiement = Payment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $utilisateur->id,
            'provider' => $operateur->value,
            'pack' => $pack,
            // Figés ICI, côté serveur, au moment de la demande : changer la
            // grille demain ne doit pas changer le montant d'un paiement
            // déjà ouvert.
            'amount_fcfa' => $definition->price_fcfa,
            'units' => $definition->units,
            'status' => StatutPaiement::EnAttente->value,
            'phone_encrypted' => $numero,
        ]);

        $reference = $this->operateur->demanderPaiement($paiement, $numero);

        $paiement->forceFill(['provider_ref' => $reference])->save();

        return $paiement;
    }

    /**
     * Dénoue une demande à la réception du rappel.
     *
     * @return bool true si l'état a changé, false si le rappel était un doublon
     */
    public function denouer(Payment $paiement, StatutPaiement $issue, ?string $motif = null): bool
    {
        if ($issue === StatutPaiement::EnAttente) {
            throw new RuntimeException('« En attente » n\'est pas une issue.');
        }

        return DB::transaction(function () use ($paiement, $issue, $motif): bool {
            /*
             | La ligne est relue SOUS VERROU. Deux rappels arrivant en même
             | temps liraient sinon tous deux « en attente » et créditeraient
             | tous deux — c'est le scénario exact du double crédit.
             */
            $verrouille = Payment::whereKey($paiement->getKey())->lockForUpdate()->firstOrFail();

            // Déjà tranché : un rappel rejoué ne rouvre pas un état final.
            if ($verrouille->status->estFinal()) {
                return false;
            }

            $verrouille->forceFill([
                'status' => $issue->value,
                'failure_reason' => $motif,
                'settled_at' => now(),
            ])->save();

            if ($issue === StatutPaiement::Reussi) {
                $utilisateur = $verrouille->user;

                if ($utilisateur === null) {
                    // Le compte a disparu entre-temps — supprimé, par exemple.
                    // Le paiement reste tracé, mais il n'y a personne à créditer.
                    return true;
                }

                $this->economie->crediter(
                    $utilisateur,
                    // Le nombre d'unités vient de la LIGNE, figée à
                    // l'initiation, jamais du rappel de l'opérateur.
                    $verrouille->units,
                    MotifMouvementUnite::Recharge,
                    'Recharge '.$verrouille->units.' unités · '
                        .number_format($verrouille->amount_fcfa, 0, ',', ' ').' FCFA',
                    $verrouille,
                );
            }

            $paiement->refresh();

            return true;
        });
    }

    /**
     * Marque les demandes abandonnées.
     *
     * Sans cela, une roue tournerait indéfiniment sur l'écran de quelqu'un qui
     * a fermé la demande sur son téléphone.
     */
    public function expirerLesAbandonnees(): int
    {
        $expirees = 0;

        $anciennes = Payment::where('status', StatutPaiement::EnAttente->value)
            ->where('created_at', '<=', now()->subMinutes(
                (int) config('economy.momo.reconcile_after_minutes', 10),
            ))
            ->get();

        foreach ($anciennes as $paiement) {
            // On REDEMANDE avant de conclure : un rappel HTTP se perd, et
            // conclure à l'échec sans vérifier priverait de ses unités
            // quelqu'un qui a réellement payé.
            $resultat = $this->operateur->interroger($paiement);

            if ($resultat->statut === StatutPaiement::Reussi) {
                $this->denouer($paiement, StatutPaiement::Reussi);

                continue;
            }

            if ($this->denouer($paiement, StatutPaiement::Expire, 'Aucune confirmation de l\'opérateur')) {
                $expirees++;
            }
        }

        return $expirees;
    }

    /**
     * Ajustement manuel par un administrateur.
     *
     * Toujours audité, et le motif est OBLIGATOIRE : créditer des unités sans
     * encaissement est le geste le plus sensible du back-office.
     */
    public function ajusterParAdmin(User $cible, User $administrateur, int $unites, string $motif): void
    {
        if (trim($motif) === '') {
            throw new RuntimeException('Un ajustement manuel exige un motif.');
        }

        $unites > 0
            ? $this->economie->crediter($cible, $unites, MotifMouvementUnite::AjustementAdmin, $motif)
            : $this->economie->debiter($cible, abs($unites), MotifMouvementUnite::AjustementAdmin, $motif);

        $this->audit->record(
            AuditLogService::MODIFICATION, 'ai_unit', $administrateur,
            cible: $cible,
            apres: ['unites' => $unites, 'motif' => $motif],
            cibleLibelle: 'Ajustement d\'unités : '.$cible->name,
        );
    }
}
