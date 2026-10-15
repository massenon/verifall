<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\EtapePipeline;
use App\Enums\MoteurAnalyse;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La méthode de vérification, publiée.
 *
 * ## Pourquoi elle est calculée et non rédigée
 *
 * Parce qu'une page de méthodologie écrite à la main devient fausse le jour où
 * l'on change une pondération — et personne ne s'en aperçoit, puisque rien ne
 * casse. Ici, les étapes viennent de `EtapePipeline`, les poids de
 * `config/factcheck.weights`, les seuils de `config/factcheck.decision`. Si le
 * code change demain, cette page change avec lui.
 *
 * ## Ce que le composant ne fait pas
 *
 * Il ne décide rien : il ordonne et étiquette ce que le domaine expose déjà.
 * Les libellés sont ceux de `MoteurAnalyse::libelle()`, qui sont eux-mêmes
 * écrits pour un lecteur non technicien — les réécrire ici les ferait diverger
 * du rapport de vérification, où le citoyen les lit aussi.
 */
#[Layout('components.layouts.public', ['title' => "Notre méthodologie · Vérif'All"])]
final class Methodologie extends Component
{
    public function render(): View
    {
        return view('public.methodologie', [
            'etapes' => EtapePipeline::cases(),
            'moteurs' => $this->moteursParPoids(),
            'poidsMaximum' => $this->poidsMaximum(),
            'ecartMinimum' => (int) config('factcheck.decision.ecart_minimum', 25),
            'scoreMinimum' => (int) config('factcheck.decision.score_minimum', 60),
            'moteursMinimum' => (int) config('factcheck.decision.moteurs_minimum', 2),
            'delaiDeReprise' => (int) config('factcheck.runtime.reprise.delai_minutes', 12),
            'analysesParHeure' => (int) config('factcheck.runtime.analyses_par_heure', 20),
            // Les moteurs tournent-ils pour de vrai ? Le taire serait le seul
            // mensonge possible sur une page qui promet la méthode.
            'moteursSimules' => (bool) config('factcheck.fake_adapters', true),
        ]);
    }

    /**
     * Les moteurs, du plus lourd au plus léger.
     *
     * L'ordre EST l'information : il montre d'un coup d'œil qu'un fact-check
     * déjà publié par une rédaction pèse plus qu'un avis de modèle de langage.
     * Rendus dans l'ordre de déclaration de l'enum, ils ne diraient rien.
     *
     * @return list<MoteurAnalyse>
     */
    private function moteursParPoids(): array
    {
        $moteurs = MoteurAnalyse::cases();

        usort(
            $moteurs,
            static fn (MoteurAnalyse $a, MoteurAnalyse $b): int => $b->poids() <=> $a->poids()
                ?: strcmp($a->libelle(), $b->libelle()),
        );

        return $moteurs;
    }

    /**
     * Le poids le plus élevé, pour mettre les barres à l'échelle.
     *
     * Pas `1.0` en dur : si la configuration plafonnait un jour à 0,8, des
     * barres calibrées sur 1 rapetisseraient toutes sans raison visible.
     */
    private function poidsMaximum(): float
    {
        $poids = array_map(
            static fn (MoteurAnalyse $moteur): float => $moteur->poids(),
            MoteurAnalyse::cases(),
        );

        $maximum = $poids === [] ? 0.0 : max($poids);

        return $maximum > 0.0 ? $maximum : 1.0;
    }
}
