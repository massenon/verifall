<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;

/**
 * Le sujet d'un courriel, surchargeable depuis l'administration.
 *
 * ## Devenu une FAÇADE, le 2 septembre 2026
 *
 * Ce service ne servait qu'une chose : l'objet. Le corps vivait dans un gabarit
 * Blade, hors de portée de l'administration. Tout est passé à
 * {@see RenduDeGabarit}, qui sert aussi le titre,
 * le corps, le bouton et l'interrupteur.
 *
 * Cette classe reste, et délègue. Neuf envois l'appellent ; les réécrire dans le
 * même mouvement aurait mêlé un changement d'architecture à neuf modifications
 * de comportement, et rendu illisible le jour où l'un d'eux se serait mis à
 * mal partir. Ils migreront un par un.
 *
 * `CLES` est conservée pour la même raison, et pointe désormais sur le
 * catalogue : deux listes de clés qui coexistent finissent toujours par
 * diverger.
 */
final class GabaritsDEmail
{
    /**
     * @deprecated Employer CatalogueDesCourriels::libelles(). Conservée le temps
     *             que les neuf appelants migrent.
     *
     * @return array<string, string>
     */
    public static function cles(): array
    {
        return CatalogueDesCourriels::libelles();
    }

    public function __construct(private readonly RenduDeGabarit $rendu) {}

    /**
     * L'objet à employer : celui de l'administration, ou celui du code.
     *
     * `$defaut` est TOUJOURS calculé par l'appelant, même quand une surcharge
     * existe : il porte souvent une traduction ou une variable, et le résoudre
     * paresseusement obligerait chaque appelant à passer une fermeture pour un
     * gain nul.
     */
    public function sujet(string $cle, string $defaut): string
    {
        return $this->rendu->sujet($cle, $defaut);
    }

    /** Vide le cache. Appelé à chaque écriture du back-office. */
    public function oublier(): void
    {
        $this->rendu->oublier();
    }
}
