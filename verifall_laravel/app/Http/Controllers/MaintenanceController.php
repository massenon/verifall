<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Application\Services\ModeMaintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Le formulaire « prévenez-moi » de la page de maintenance.
 *
 * ## Pourquoi un contrôleur et pas un composant Livewire
 *
 * Livewire a besoin de son point d'entrée `/livewire/update`, qui passerait par
 * le middleware de maintenance. L'exempter ouvrirait une porte pour un
 * formulaire de trois lignes. Un POST ordinaire n'a besoin de rien.
 *
 * ## L'adresse n'est pas conservée en clair au-delà de son usage
 *
 * L'IP est hachée, jamais stockée telle quelle : elle ne sert qu'à repérer un
 * abus, et une page publique qui collecte des IP en clair est une base de
 * données de visiteurs que personne n'a demandée.
 */
final class MaintenanceController extends Controller
{
    public function __invoke(Request $request, ModeMaintenance $maintenance): RedirectResponse
    {
        /*
         | `email:rfc` et NON `email:rfc,dns`.
         |
         | La règle `dns` interroge le résolveur pour vérifier que le domaine
         | existe. Sur cette page-ci, c'est la dépendance à ne pas ajouter :
         | elle s'affiche justement quand l'infrastructure est dégradée, et un
         | résolveur lent ou injoignable ferait attendre plusieurs secondes puis
         | refuser une adresse parfaitement valable.
         |
         | Le coût du renoncement est faible : une adresse à domaine inexistant
         | produira un courriel non délivré, ce que le transport signale déjà.
         */
        $donnees = $request->validate(
            ['email' => ['required', 'email:rfc', 'max:255']],
            [],
            ['email' => 'adresse e-mail'],
        );

        $maintenance->prevenirMoi(
            $donnees['email'],
            Hash::make((string) $request->ip()),
        );

        /*
         | Retour à la page, avec un drapeau de session plutôt qu'un paramètre
         | d'adresse : recharger ne doit pas réafficher « c'est noté » à
         | quelqu'un qui revient une heure plus tard.
         */
        return redirect()->route('maintenance')->with('maintenance_prevenu', true);
    }
}
