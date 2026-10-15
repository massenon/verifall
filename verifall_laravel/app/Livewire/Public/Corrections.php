<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\FactChecking\Services\JournalDesCorrections;
use App\Models\VerdictRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Le journal public des corrections.
 *
 * La page la plus inconfortable du site, et la plus nécessaire : elle
 * n'affiche que les fois où la plateforme s'est trompée. C'est ce qui la rend
 * crédible — une rédaction qui ne publie jamais de correction n'est pas
 * infaillible, elle est opaque.
 *
 * Le composant ne calcule rien : il pagine ce que le journal lui rend.
 */
#[Layout('components.layouts.public', ['title' => "Journal des corrections · Vérif'All"])]
final class Corrections extends Component
{
    use WithPagination;

    /**
     * Quinze par page.
     *
     * Assez pour que le journal se lise d'un trait quand il est court, assez
     * peu pour qu'aucune page ne devienne illisible quand il s'allonge.
     */
    private const PAR_PAGE = 15;

    public function render(JournalDesCorrections $journal): View
    {
        return view('livewire.public.corrections', [
            'revisions' => $this->revisions($journal),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, VerdictRevision>
     */
    private function revisions(JournalDesCorrections $journal): LengthAwarePaginator
    {
        return $journal->journal(self::PAR_PAGE);
    }
}
