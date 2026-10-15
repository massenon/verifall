<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Lms\Services\GabaritCertificat;
use App\Models\Verification;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Le QR de contrôle d'un rapport imprimé.
 *
 * ## À quoi il sert, et pourquoi il pointe sur la page PUBLIQUE
 *
 * Un rapport sur papier ne prouve rien par lui-même : n'importe qui peut
 * composer la même page dans un traitement de texte. Ce qui lui donne valeur,
 * c'est de pouvoir remonter à l'original en une seconde, sans rien installer
 * et sans compte.
 *
 * Il mène donc à `public.report`, jamais à `citizen.report`. Celui qui scanne
 * la feuille — un confrère, un employeur, un parent à qui l'on montre le
 * démenti d'une rumeur — n'a pas de session sur la plateforme, et l'espace
 * citoyen lui renverrait un écran de connexion. Le QR serait décoratif.
 *
 * C'est exactement le raisonnement de {@see GabaritCertificat},
 * dont ce service reprend le rendu SVG.
 */
final class QrCodeRapport
{
    /**
     * Le QR, en SVG prêt à insérer.
     *
     * SVG et non PNG : il est destiné à l'impression, où une image matricielle
     * de soixante-dix pixels devient un carré flou qu'aucun téléphone ne lit.
     */
    public function svg(Verification $verification, int $taille = 70): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($taille, 0), new SvgImageBackEnd));

        return $writer->writeString($this->adresse($verification));
    }

    /**
     * Le QR, s'il y a quelque chose à contrôler.
     *
     * `null` tant que le rapport n'est pas PUBLIÉ : imprimer un code qui mène à
     * une page introuvable vaut moins que ne rien imprimer du tout. Le lecteur
     * en conclurait que la plateforme ne répond pas.
     */
    public function siPublie(Verification $verification, int $taille = 70): ?string
    {
        return $verification->report?->published_at === null
            ? null
            : $this->svg($verification, $taille);
    }

    /** L'adresse que porte le code. */
    public function adresse(Verification $verification): string
    {
        return route('public.report', ['verification' => $verification->uuid]);
    }
}
