<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\Marketing\Seo\Referencement;
use App\Core\Marketing\Services\SuiviDesVisites;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Un article du blog. */
#[Layout('components.layouts.public')]
final class ArticleBlog extends Component
{
    public string $slug = '';

    public function mount(string $post): void
    {
        $this->slug = $post;

        /*
         | Une visite, pas un chargement. Le service écarte les robots, les
         | aperçus de partage, les préchargements du navigateur et les
         | rafraîchissements — voir SuiviDesVisites.
         */
        app(SuiviDesVisites::class)->enregistrer($this->article(), request());
    }

    public function render(): View
    {
        $article = $this->article();

        return view('livewire.public.article-blog', [
            'article' => $article,
            'voisins' => $this->voisins(),
            'tempsDeLecture' => $this->tempsDeLecture($article),
        ])->layoutData([
            'seo' => app(Referencement::class)->pourArticleDeBlog($article),
        ]);
    }

    /**
     * Le temps de lecture, en minutes.
     *
     * Le prototype l'écrit en dur (« 4 min »). Ici il se calcule sur le texte
     * réel, à 200 mots par minute — la moyenne admise en lecture d'écran. Une
     * minute au minimum : « 0 min de lecture » ne veut rien dire.
     */
    private function tempsDeLecture(BlogPost $article): int
    {
        $mots = str_word_count(strip_tags((string) $article->body));

        return max(1, (int) ceil($mots / 200));
    }

    #[Computed(persist: true)]
    public function articleCharge(): BlogPost
    {
        return BlogPost::with(['category', 'author:id,name'])
            ->where('slug', $this->slug)
            ->where('is_published', true)
            ->firstOrFail();
    }

    private function article(): BlogPost
    {
        return $this->articleCharge();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    private function voisins(): Collection
    {
        // `author` chargé d'avance : la carte d'un voisin affiche sa signature,
        // et trois requêtes de plus par page passeraient inaperçues sans
        // `preventLazyLoading`.
        return BlogPost::with('author:id,name')
            ->where('is_published', true)
            ->whereKeyNot($this->article()->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}
