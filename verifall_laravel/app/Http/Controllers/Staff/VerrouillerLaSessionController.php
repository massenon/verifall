<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Identity\Services\VerrouDeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST /admin/verrouiller — depuis le menu du profil, Ctrl L, ou après 15 min
 * sans geste (console.js). POST seulement : un lien GET se déclencherait au
 * préchargement du navigateur.
 */
final class VerrouillerLaSessionController
{
    public function __invoke(Request $requete, VerrouDeSession $verrou): RedirectResponse
    {
        $verrou->verrouiller($requete, (string) $requete->input('raison', 'manuel'));

        return redirect()->route('staff.verrou');
    }
}
