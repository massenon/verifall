<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\GabaritCertificat;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le certificat de quelqu'un — transposition de compte-user/certificat.html.
 *
 * Borné à son TITULAIRE. Le code public sert à faire vérifier le diplôme par un
 * tiers ; cette page-ci, qui porte les actions de téléchargement et de partage,
 * n'a pas à s'ouvrir sur un code deviné.
 */
#[Layout('components.layouts.citizen', ['title' => "Mon certificat · Vérif'All"])]
final class Certificat extends Component
{
    public string $code = '';

    public function mount(string $certificate): void
    {
        $this->code = $certificate;

        $this->certificat();
    }

    public function render(GabaritCertificat $gabarit): View
    {
        $certificat = $this->certificat();

        return view('livewire.citizen.certificat', [
            'certificat' => $certificat,
            /*
             | L'habillage vient du même service que la page publique de
             | vérification : c'est ce qui garantit que le recruteur voit le
             | document que le titulaire a imprimé. Deux lectures séparées des
             | réglages finiraient par diverger.
             */
            'habillage' => $gabarit->habillage(),
            // Pas de QR sur un certificat révoqué : il mènerait à une page qui
            // dit « révoqué », ce que la page affiche déjà, en plus clair.
            'qrCode' => $certificat->estValide() ? $gabarit->qrCodeDataUri($certificat) : null,
        ]);
    }

    private function certificat(): Certificate
    {
        return Certificate::with('course')
            ->where('code', $this->code)
            ->where('user_id', $this->utilisateur()->id)
            ->firstOrFail();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
