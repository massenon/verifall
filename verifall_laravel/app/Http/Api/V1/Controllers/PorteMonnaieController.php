<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Economy\Services\PaymentService;
use App\Http\Api\V1\Resources\MouvementUniteResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\PageCurseur;
use App\Models\UnitPack;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Le solde d'unités, les packs, la recharge Mobile Money et le relevé.
 *
 * LE CLIENT NE CRÉDITE JAMAIS. Il envoie un CODE DE PACK et un numéro ; le
 * prix et la quantité sont lus en base par `PaymentService`. C'est ce qui
 * empêche d'acheter soixante unités au prix de dix en modifiant une requête —
 * et c'est aussi pourquoi la réponse ne comporte aucun champ que le client
 * pourrait croire modifiable.
 *
 * LE CRÉDIT ARRIVE PAR RAPPEL DE L'OPÉRATEUR, jamais au retour de
 * l'application. Le téléphone qui revient de l'application Mobile Money ne
 * sait pas si le paiement a abouti : il relit le solde, il ne le décide pas.
 */
final class PorteMonnaieController
{
    /**
     * `GET /me/wallet`
     *
     * Solde et grille tarifaire dans la même réponse : l'écran « Unités » les
     * affiche ensemble, et deux appels pour un écran qu'on ouvre en attendant
     * un taxi, c'est une seconde de trop.
     */
    public function afficher(Request $requete): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        return Enveloppe::donnee([
            'solde' => $utilisateur->units,
            'packs' => UnitPack::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (UnitPack $pack): array => [
                    'code' => $pack->code,
                    'libelle' => $pack->label,
                    'unites' => $pack->units,
                    'prix_fcfa' => $pack->price_fcfa,
                    'mis_en_avant' => $pack->is_featured,
                ])->values(),
        ]);
    }

    /**
     * `POST /me/wallet/topup`
     *
     * Ouvre une demande auprès de l'opérateur et rend de quoi la suivre. Le
     * solde N'EST PAS modifié ici : il ne le sera qu'au rappel signé de
     * l'opérateur, ou à la réconciliation planifiée quand ce rappel se perd.
     */
    public function recharger(Request $requete, PaymentService $paiements): JsonResponse
    {
        $donnees = $requete->validate([
            'pack' => ['required', 'string', 'max:20'],
            // Le numéro détermine l'opérateur : c'est le serveur qui le
            // déduit, jamais le client — un client qui choisirait son
            // opérateur pourrait viser celui dont la vérification de signature
            // est la plus permissive.
            'numero' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        try {
            $paiement = $paiements->initier(
                $this->utilisateur($requete),
                $donnees['pack'],
                $donnees['numero'],
            );
        } catch (RuntimeException $refus) {
            return Enveloppe::erreur(CodeErreur::ActionImpossible, $refus->getMessage());
        }

        return Enveloppe::donnee([
            'uuid' => $paiement->uuid,
            'statut' => $paiement->status->value,
            'statut_libelle' => $paiement->status->libelle(),
            'operateur' => $paiement->provider,
            'montant_fcfa' => $paiement->amount_fcfa,
            'unites' => $paiement->units,
            // Le numéro chiffré, la référence opérateur et la raison d'échec
            // ne sortent pas : ce sont des données de rapprochement interne.
        ], statut: 201);
    }

    /**
     * `GET /me/wallet/transactions` — le relevé.
     *
     * Chaque ligne porte le solde tel qu'il était après l'opération. C'est le
     * relevé de compte du citoyen : il se lit comme il a été écrit.
     */
    public function mouvements(Request $requete, UnitLogRepositoryInterface $journal): JsonResponse
    {
        // Par le DÉPÔT et non par Eloquent : c'est là que le bornage au
        // propriétaire est écrit une fois pour toutes. Le refaire ici serait
        // exactement l'endroit où on l'oublierait un jour.
        $page = $journal->historiqueParCurseur(
            $this->utilisateur($requete),
            PageCurseur::taille($requete),
        )->withQueryString();

        return Enveloppe::page($page, MouvementUniteResource::collection($page->items()), [
            'solde' => $this->utilisateur($requete)->units,
        ]);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
