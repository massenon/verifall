<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Lms\Services\GabaritCertificat as ServiceGabarit;
use App\Models\Certificate;
use App\Models\Setting;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Certificat Template » — voir le document avant de le délivrer.
 *
 * ## Ce que cet écran fait, et ce qu'il ne duplique pas
 *
 * Il **n'a pas de formulaire**. Les réglages du certificat se saisissent aux
 * Paramètres, onglet « Certificats », avec les soixante autres — et ils y
 * restent. Un second formulaire pour les mêmes six clés, c'est deux endroits où
 * écrire la même valeur, donc un endroit où l'on oublie de la changer.
 *
 * Ce qui manquait n'était pas la saisie : c'était **le rendu**. On réglait une
 * couleur, on déposait une signature, on enregistrait — et l'on découvrait le
 * résultat en délivrant un vrai diplôme à une vraie personne. Cet écran montre
 * le document tel qu'il sortira, sur un exemple.
 *
 * ## L'exemple n'est PAS enregistré
 *
 * Le certificat affiché est construit en mémoire, jamais persisté : il ne porte
 * pas d'`id`, n'apparaît dans aucune liste, et son code — délibérément
 * reconnaissable — ne validera jamais rien sur la page publique de
 * vérification. Créer une ligne réelle pour un aperçu polluerait la table des
 * diplômes, et un aperçu oublié en base finirait par ressembler à une fraude.
 */
#[Layout('components.layouts.staff', ['title' => "Certificat Template · Vérif'All"])]
final class GabaritCertificat extends Component
{
    public function mount(): void
    {
        /*
         | Le droit lu est celui des RÉGLAGES, pas celui des certificats.
         |
         | Cet écran ne délivre ni ne révoque rien : il montre l'effet d'une
         | configuration. C'est la même responsabilité que l'onglet où ces
         | valeurs se saisissent, et lui demander `certificate.view` laisserait
         | croire qu'on y voit les diplômes des gens.
         */
        $this->authorize('viewAny', Setting::class);
    }

    public function render(ServiceGabarit $gabarit): View
    {
        $exemple = $this->exemple();

        return view('livewire.staff.gabarit-certificat', [
            'certificat' => $exemple,
            'habillage' => $gabarit->habillage(),
            'qrCode' => $gabarit->qrCodeDataUri($exemple),
        ]);
    }

    /**
     * Un certificat d'exemple, en mémoire et jamais enregistré.
     *
     * Les valeurs sont volontairement PARLANTES plutôt que réalistes : un nom
     * inventé mais plausible ferait douter, en le voyant dans une capture
     * d'écran, qu'il s'agit bien d'un exemple.
     */
    private function exemple(): Certificate
    {
        $certificat = new Certificate;

        $certificat->forceFill([
            'code' => 'VERIF-EXEMPLE-0000',
            'holder_name' => 'Nom du titulaire',
            'course_title' => 'Reconnaître une image sortie de son contexte',
            'issued_at' => now(),
            'revoked_at' => null,
        ]);

        return $certificat;
    }
}
