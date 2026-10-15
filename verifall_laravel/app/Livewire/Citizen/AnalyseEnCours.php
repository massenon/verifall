<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Enums\StatutVerification;
use App\Models\Verification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Le témoin « une analyse tourne » — visible partout dans l'espace citoyen.
 *
 * ## Ce qui manquait
 *
 * Le suivi existait, et il était honnête : `verification-en-cours.blade.php`
 * interroge le serveur toutes les trois secondes et affiche les étapes réelles
 * du pipeline. Mais il n'existait **que sur cet écran**. Dès qu'on partait
 * lire le forum ou parcourir le catalogue — ce qu'on fait naturellement pendant
 * une attente de plusieurs dizaines de secondes — plus aucun signe.
 *
 * Une analyse est payée en unités. Ne plus savoir si elle tourne, c'est ne plus
 * savoir si l'on a payé pour rien.
 *
 * ## Le drapeau de session, et pourquoi il n'est pas une optimisation
 *
 * Ce composant est monté par le gabarit citoyen : il s'exécute donc sur les
 * vingt-cinq écrans, à chaque affichage. Lui faire interroger la base à chaque
 * fois coûterait une requête par page **pour n'afficher presque jamais rien** —
 * la très grande majorité des pages sont consultées sans analyse en cours.
 *
 * D'où l'ordre : le drapeau de session d'abord, la base ensuite. Sans drapeau,
 * on ne requête pas et on ne rend rien.
 *
 * Le drapeau n'est PAS la vérité : il est posé au lancement, il peut donc
 * survivre à une analyse déjà finie. C'est la base qui tranche, et le drapeau
 * qu'on efface quand elle dit que tout est terminé. L'inverse — se fier au
 * drapeau seul — afficherait un témoin qui ne s'éteint jamais.
 *
 * ## Le rythme
 *
 * Dix secondes, contre trois sur l'écran dédié. La différence est voulue : là
 * -bas on REGARDE la progression, ici on veut seulement savoir que ça tourne
 * encore. Trois secondes sur tous les écrans multiplierait les requêtes sans
 * rien apprendre de plus.
 *
 * Et le sondage n'est attaché qu'au rendu ACTIF : une page sans analyse en
 * cours ne rend qu'un conteneur vide, sans `wire:poll`.
 */
final class AnalyseEnCours extends Component
{
    /**
     * La clé de session posée au lancement d'une analyse.
     *
     * Publique et constante : `Citizen\Verifier` la pose, ce composant la lit.
     * Deux chaînes écrites à la main dans deux fichiers finiraient par ne plus
     * coïncider, et le témoin ne s'allumerait plus — sans que rien ne le dise.
     */
    public const CLE_SESSION = 'verifall.analyse_en_cours';

    public function render(): View
    {
        return view('livewire.citizen.analyse-en-cours', [
            'verification' => $this->verificationEnCours(),
        ]);
    }

    /**
     * L'analyse en cours de cette personne, ou `null`.
     *
     * Rend la PLUS RÉCENTE : lancer une seconde vérification avant la fin de la
     * première est possible, et c'est celle qu'on vient de demander qu'on
     * s'attend à voir tourner.
     */
    private function verificationEnCours(): ?Verification
    {
        if (! session()->has(self::CLE_SESSION)) {
            return null;
        }

        $utilisateur = Auth::guard('web')->user();

        if ($utilisateur === null) {
            return null;
        }

        $verification = Verification::query()
            ->where('user_id', $utilisateur->id)
            ->whereIn('status', StatutVerification::valeursEnCours())
            ->latest('id')
            ->first();

        if ($verification === null) {
            /*
             | Plus rien ne tourne : on éteint le drapeau, sinon chaque page
             | referait cette requête jusqu'à la fin de la session — c'est-à-dire
             | précisément le coût que le drapeau existe pour éviter.
             */
            session()->forget(self::CLE_SESSION);
        }

        return $verification;
    }
}
