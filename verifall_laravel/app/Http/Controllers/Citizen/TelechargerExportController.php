<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Models\DataExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement de l'archive de portabilité.
 *
 * TROIS conditions, et aucune n'est redondante :
 *
 *   · la signature de l'URL, qui prouve que le lien vient de nous et n'a pas
 *     été fabriqué ;
 *   · la session, car un lien signé finit dans une boîte mail, et une boîte
 *     mail se compromet ;
 *   · le PROPRIÉTAIRE, car un lien transféré ne doit pas livrer les données de
 *     quelqu'un d'autre à qui se trouve connecté.
 *
 * Le fichier n'est jamais accessible par une URL de stockage : il vit sur le
 * disque privé et ne passe que par ici.
 */
final class TelechargerExportController
{
    public function __invoke(Request $request, string $export): StreamedResponse
    {
        $archive = DataExport::where('uuid', $export)->firstOrFail();

        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null || $archive->user_id !== $utilisateur->id, 403);

        // Expirée ou jamais construite : 404 plutôt qu'un fichier vide, qui
        // laisserait croire à un export réussi mais lacunaire.
        abort_unless($archive->estTelechargeable(), 404);
        abort_unless(Storage::disk('local')->exists((string) $archive->path), 404);

        $archive->forceFill(['downloaded_at' => now()])->save();

        return Storage::disk('local')->download(
            (string) $archive->path,
            'verifall-mes-donnees-'.now()->format('Y-m-d').'.zip',
        );
    }
}
