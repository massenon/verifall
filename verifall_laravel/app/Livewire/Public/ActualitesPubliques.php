<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\HubArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Actualités » publiques — les vérifications que l'équipe a publiées.
 *
 * Cette page LIT LA BASE. Le prototype y montre six brèves écrites en dur ;
 * les transposer telles quelles reviendrait à publier, sur un site de
 * fact-checking, des verdicts qui n'ont jamais été rendus. Tant que la
 * rédaction n'a rien publié, la page le dit.
 */
#[Layout('components.layouts.public', ['title' => "Actualités vérifiées · Vérif'All"])]
final class ActualitesPubliques extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    public function render(): View
    {
        return view('livewire.public.actualites', [
            'articles' => $this->articles(),
            'categories' => $this->categories(),
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'categorie'], true)) {
            $this->resetPage();
        }
    }

    public function choisirCategorie(string $nom): void
    {
        $this->categorie = $this->categorie === $nom ? '' : $nom;
        $this->resetPage();
    }

    public function filtresActifs(): bool
    {
        return $this->recherche !== '' || $this->categorie !== '';
    }

    /**
     * @return LengthAwarePaginator<int, HubArticle>
     */
    private function articles(): LengthAwarePaginator
    {
        return HubArticle::query()
            ->where('is_published', true)
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                    ->orWhere('excerpt', 'like', $terme));
            })
            ->when($this->categorie !== '', fn (Builder $q) => $q->where('category', $this->categorie))
            ->orderByDesc('published_at')
            ->paginate(9);
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        return HubArticle::where('is_published', true)
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }
}
