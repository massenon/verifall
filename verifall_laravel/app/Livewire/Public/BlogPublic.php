<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Blog public.
 *
 * Comme les actualités, il lit la base. Un blog dont les articles sont figés
 * dans le gabarit ne se met jamais à jour, et le premier vrai article oblige à
 * réécrire la page.
 */
#[Layout('components.layouts.public', ['title' => "Blog · Vérif'All"])]
final class BlogPublic extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    public function render(): View
    {
        return view('livewire.public.blog', [
            'articles' => $this->articles(),
            'aLaUne' => $this->aLaUne(),
            'categories' => $this->categories(),
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'categorie'], true)) {
            $this->resetPage();
        }
    }

    /** Recliquer sur la rubrique active la relâche — comme partout ailleurs. */
    public function choisirCategorie(string $slug): void
    {
        $this->categorie = $this->categorie === $slug ? '' : $slug;
        $this->resetPage();
    }

    public function filtresActifs(): bool
    {
        return $this->recherche !== '' || $this->categorie !== '';
    }

    /**
     * L'article mis en avant — masqué dès qu'un filtre est actif, sinon il
     * montrerait un résultat qui ne correspond pas à la recherche.
     */
    private function aLaUne(): ?BlogPost
    {
        if ($this->filtresActifs()) {
            return null;
        }

        return BlogPost::with(['category', 'author'])
            ->where('is_published', true)
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, BlogPost>
     */
    private function articles(): LengthAwarePaginator
    {
        $aLaUne = $this->aLaUne();

        return BlogPost::query()
            ->with(['category', 'author'])
            ->where('is_published', true)
            ->when($aLaUne !== null, fn (Builder $q) => $q->whereKeyNot($aLaUne->id))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                    ->orWhere('excerpt', 'like', $terme));
            })
            ->when($this->categorie !== '', fn (Builder $q) => $q->whereHas(
                'category', fn (Builder $c) => $c->where('slug', $this->categorie)
            ))
            ->orderByDesc('published_at')
            ->paginate(9);
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    private function categories(): Collection
    {
        return BlogCategory::orderBy('position')->orderBy('name')->get();
    }
}
