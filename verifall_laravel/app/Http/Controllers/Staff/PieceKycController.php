<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Models\KycSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une pièce d'identité à l'examinateur, et à personne d'autre.
 *
 * C'est le fichier le plus sensible de la plateforme : le scan d'une carte
 * d'identité. Il n'a **aucune URL de stockage** — il vit sur le disque privé et
 * ne passe que par ici, derrière quatre conditions dont aucune n'est redondante :
 *
 *   · la **signature à durée limitée**, qui prouve que le lien vient de nous et
 *     n'a pas été fabriqué — et qui périme, pour qu'une adresse copiée dans un
 *     historique de navigateur ne serve pas six mois plus tard ;
 *   · la **session du personnel**, car un lien signé se transmet ;
 *   · l'**habilitation `kyc.review`**, car tout le personnel n'a pas à voir les
 *     pièces d'identité des gens ;
 *   · la **présence du fichier**, car un dossier purgé n'a plus rien à montrer.
 *
 * ## `inline` et non `download`
 *
 * L'examinateur compare une photo à un formulaire : il la regarde, il ne
 * l'archive pas. Un téléchargement laisserait des copies de cartes d'identité
 * dans le dossier « Téléchargements » de chaque poste — exactement ce que la
 * purge à trente jours cherche à éviter côté serveur.
 *
 * ## `X-Robots-Tag` et `no-store`
 *
 * Le lien est signé et derrière une session : aucun robot ne devrait le voir.
 * Les en-têtes le disent quand même, et interdisent la mise en cache par un
 * proxy d'entreprise ou le navigateur.
 */
final class PieceKycController
{
    public function __invoke(Request $requete, KycSubmission $dossier, string $piece): StreamedResponse
    {
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null || $membre->cannot('kyc.review'), 403);

        $chemins = $dossier->pieces();

        // 404 et non 403 : un rôle de pièce inventé ne doit pas renseigner sur
        // ce que le dossier contient.
        abort_unless(isset($chemins[$piece]), 404);
        abort_unless(Storage::disk('local')->exists($chemins[$piece]), 404);

        return Storage::disk('local')->response(
            $chemins[$piece],
            null,
            [
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ],
        );
    }
}
