<?php

declare(strict_types=1);

namespace App\Livewire\Staff\TableauDeBord;

use App\Core\Application\Services\CentreDePilotage;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Une section analytique du tableau de bord, chargée À PART.
 *
 * ## Pourquoi un composant par section
 *
 * Trois propriétés de la directive (§50-51) tiennent à ce découpage :
 *
 *   · **Chargement progressif.** `#[Lazy]` rend d'abord un squelette, puis la
 *     section se calcule quand elle entre à l'écran. Les agrégats du bas de
 *     page ne retardent pas l'affichage des alertes.
 *   · **Échec partiel.** Une section qui lève rend « Ce bloc n'a pas pu être
 *     chargé » ; ses voisines ne le savent même pas.
 *   · **Rechargement ciblé.** Changer de période remonte les sections (clé
 *     neuve), sans recalculer les files d'attente du parent.
 *
 * ## Les propriétés sont verrouillées, et le droit est revérifié
 *
 * `#[Locked]` : une section ne change pas de nature depuis le navigateur. Et le
 * droit de la voir est relu ICI, pas seulement chez le parent — une requête
 * Livewire se forge sans passer par la page qui l'a rendue.
 */
#[Lazy]
final class SectionAnalytique extends Component
{
    #[Locked]
    public string $section = 'kpi';

    #[Locked]
    public string $periode = '30d';

    #[Locked]
    public string $du = '';

    #[Locked]
    public string $au = '';

    public function placeholder(): View
    {
        return view('livewire.staff.tableau-de-bord.squelette', ['section' => $this->section]);
    }

    public function render(CentreDePilotage $pilotage): View
    {
        $membre = auth('staff')->user();
        $periode = $pilotage->periode($this->periode, $this->du ?: null, $this->au ?: null);

        if ($membre === null || ! $pilotage->peutVoir($membre, $this->section)) {
            return view('livewire.staff.tableau-de-bord.section-masquee');
        }

        $donnees = $pilotage->tenter(
            $this->section,
            fn (): array => $pilotage->section($this->section, $membre, $periode),
        );

        return view('livewire.staff.tableau-de-bord.section-'.$this->section, [
            'd' => $donnees ?? [],
            'enErreur' => $donnees === null,
            'periodeCalculee' => $periode,
        ]);
    }
}
