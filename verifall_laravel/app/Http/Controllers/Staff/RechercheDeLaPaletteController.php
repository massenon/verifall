<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * La recherche dans les DONNÉES de la palette Ctrl K (comptes, cours).
 *
 * Chaque famille de résultats exige le droit de lister l'écran vers lequel elle
 * mène : la palette ne doit jamais montrer ce que l'écran refuserait. Le résultat
 * mène à la liste filtrée (`?q=`), pas à une fiche : une adresse n'est ainsi
 * jamais écrite dans une URL de fiche. Deux caractères minimum, 5 résultats par
 * famille, et le journal d'audit n'en garde rien (aucune donnée n'est ouverte).
 */
final class RechercheDeLaPaletteController extends Controller
{
    private const PAR_FAMILLE = 5;

    public function __invoke(Request $requete): JsonResponse
    {
        $q = trim((string) $requete->query('q', ''));
        $membre = Auth::guard('staff')->user();

        if ($membre === null || mb_strlen($q) < 2) {
            return response()->json(['resultats' => []]);
        }

        $motif = '%'.addcslashes($q, '%_\\').'%';
        $resultats = [];

        if ($membre->can('viewAny', User::class) && Route::has('staff.users')) {
            User::query()
                ->where(fn ($r) => $r->where('name', 'like', $motif)->orWhere('email', 'like', $motif))
                ->orderBy('name')->limit(self::PAR_FAMILLE)->get(['id', 'name', 'email'])
                ->each(function (User $u) use (&$resultats): void {
                    $resultats[] = ['libelle' => $u->name, 'groupe' => 'Compte · '.$u->email, 'url' => route('staff.users', ['q' => $u->email])];
                });
        }

        if ($membre->can('viewAny', Course::class) && Route::has('staff.courses')) {
            Course::query()->where('title', 'like', $motif)->orderBy('title')->limit(self::PAR_FAMILLE)->get(['id', 'title'])
                ->each(function (Course $c) use (&$resultats): void {
                    $resultats[] = ['libelle' => $c->title, 'groupe' => 'Cours', 'url' => route('staff.courses', ['q' => $c->title])];
                });
        }

        return response()->json(['resultats' => $resultats])->header('Cache-Control', 'no-store');
    }
}
