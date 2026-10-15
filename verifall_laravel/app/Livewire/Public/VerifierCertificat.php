<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\Lms\Services\GabaritCertificat;
use App\Models\Certificate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Vérification publique d'un certificat — verify-certificate.html.
 *
 * Un diplôme que personne ne peut contrôler ne vaut rien : c'est cette page qui
 * donne sa valeur à tout le reste de l'Académie. Elle est donc PUBLIQUE, sans
 * compte ni session.
 *
 * Deux précautions :
 *
 *   · La réponse ne dit QUE ce qu'un recruteur a besoin de savoir — le nom du
 *     titulaire, l'intitulé, la date, la validité. Rien sur le compte, ni sur
 *     les notes obtenues.
 *   · Le formulaire est LIMITÉ EN CADENCE. Sans cela, la page devient un
 *     oracle : on y énumère des codes jusqu'à en trouver un qui existe.
 */
/*
 | Gabarit AUTONOME : le prototype donne à cette page son propre en-tête réduit
 | plutôt que le menu complet du site. Quelqu'un qui arrive par un QR code
 | imprimé sur un diplôme a une seule question ; six sorties de navigation
 | avant la réponse, c'est cinq de trop.
 */
#[Layout('components.layouts.certificat', ['title' => "Vérifier un certificat · Vérif'All"])]
final class VerifierCertificat extends Component
{
    /** Le code peut venir d'un lien imprimé sur le diplôme. */
    #[Url(as: 'code', except: '')]
    public string $code = '';

    public bool $cherche = false;

    public bool $tropDeTentatives = false;

    public function mount(): void
    {
        // Un code passé dans l'URL se vérifie tout de suite : demander de
        // recliquer sur « Vérifier » n'apporte rien.
        $this->cherche = trim($this->code) !== '';
    }

    public function render(GabaritCertificat $gabarit): View
    {
        $certificat = $this->cherche ? $this->certificat() : null;

        return view('livewire.public.verifier-certificat', [
            'certificat' => $certificat,
            /*
             | Le DOCUMENT, en plus du relevé de faits (lot 4).
             |
             | Les deux répondent à deux questions différentes, et le recruteur
             | se les pose l'une après l'autre. Le relevé répond à « ce code
             | existe-t-il ? » ; le document répond à « est-ce bien celui que je
             | tiens entre les mains ? ». Sans lui, il n'y a rien à comparer à la
             | feuille imprimée qu'on vient de lui remettre.
             |
             | Même service que l'écran du titulaire : c'est ce qui garantit que
             | les deux rendus coïncident.
             */
            'habillage' => $gabarit->habillage(),
            'qrCode' => $certificat?->estValide() === true ? $gabarit->qrCodeDataUri($certificat) : null,
        ]);
    }

    public function verifier(): void
    {
        $cle = 'verif-certificat:'.sha1((string) request()->ip());

        if (RateLimiter::tooManyAttempts($cle, 20)) {
            $this->tropDeTentatives = true;

            return;
        }

        RateLimiter::hit($cle, 300);

        $this->tropDeTentatives = false;
        $this->cherche = true;
    }

    private function certificat(): ?Certificate
    {
        $code = mb_strtoupper(trim($this->code));

        if ($code === '') {
            return null;
        }

        return Certificate::where('code', $code)->first();
    }
}
