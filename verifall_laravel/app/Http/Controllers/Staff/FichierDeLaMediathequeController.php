<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une image de la médiathèque. Le lien est signé et temporaire ; on revérifie
 * en plus que la personne connectée peut voir CE fichier (le sien, ou `media.delete`).
 */
final class FichierDeLaMediathequeController extends Controller
{
    public function __invoke(MediaFile $media): StreamedResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_unless($membre !== null && $membre->can('media.view'), 403);
        abort_unless($membre->can('media.delete') || $media->user_id === $membre->id, 403);
        abort_unless(Storage::disk(MediaFile::DISQUE)->exists($media->chemin), 404);

        return Storage::disk(MediaFile::DISQUE)->response($media->chemin, null, [
            'Content-Type' => $media->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
