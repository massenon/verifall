<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Application\Contracts\DepotExamens;
use App\Models\Exam;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Statistiques Globales » — un examen par ligne, ce qu'il produit.
 *
 * ## Lecture seule, et c'est tout ce que la matrice accorde
 *
 * `exam_stat => ['view']`. Un seul verbe, et l'écran n'en invente pas : aucun
 * bouton, aucune écriture. Un tableau de bord qui modifie ce qu'il mesure finit
 * par mesurer ses propres modifications.
 *
 * ## Les agrégats sont calculés à la LECTURE, jamais stockés
 *
 * `DepotExamens::statistiques()` rend chaque `Exam` porteur de
 * `sessions_count` et `average_score`, calculés par la requête. Le contrat
 * l'explique : une colonne de plus sur `exams` aurait dérivé au premier
 * recalcul oublié — et un taux de réussite faux est pire qu'un taux absent,
 * parce qu'on le cite.
 *
 * ## Ce que l'écran refuse d'afficher
 *
 * Les indicateurs des deux dépôts rendent `null` — et non zéro — quand la
 * mesure n'a pas de sens : `taux_reussite` sans copie corrigée,
 * `delai_moyen_jours` sans correction faite. La vue affiche alors un tiret.
 *
 * « 0 % de réussite » sur une plateforme qui n'a encore corrigé personne est un
 * chiffre INVENTÉ, et c'est celui qu'on retient. Les deux contrats ont pris
 * cette précaution ; l'écran ne la défait pas en remplaçant `null` par 0.
 */
#[Layout('components.layouts.staff', ['title' => "Statistiques des examens · Vérif'All"])]
final class StatistiquesExamens extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth('staff')->user()?->can('exam_stat.view') ?? false, 403);
    }

    public function render(DepotExamens $examens, DepotCopiesExamen $copies): View
    {
        return view('livewire.staff.statistiques-examens', [
            'lignes' => $this->lignes($examens),
            'examens' => $examens->indicateurs(),
            'correction' => $copies->indicateurs(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Exam>
     */
    private function lignes(DepotExamens $depot): LengthAwarePaginator
    {
        return $depot->statistiques($this->recherche);
    }
}
