<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Garde le thème choisi avec le compte. Appelé en arrière-plan, sans effet visible. */
final class ThemeDeLaConsoleController extends Controller
{
    public function __invoke(Request $requete): JsonResponse
    {
        $donnees = $requete->validate(['theme' => ['required', 'in:light,dark']]);

        /** @var User $membre */
        $membre = Auth::guard('staff')->user();
        $membre->forceFill(['console_theme' => $donnees['theme']])->save();

        return response()->json(['theme' => $donnees['theme']]);
    }
}
