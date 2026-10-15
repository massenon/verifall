<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\FactChecking\Services\JournalDesCorrections;
use App\Core\Marketing\Seo\Referencement;
use App\Core\Marketing\Services\SuiviDesVisites;
use App\Models\HubArticle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Une vérification publiée, en accès libre.
 *
 * Publique et sans compte : un démenti que seuls les inscrits peuvent lire ne
 * dément rien. C'est la page qui circulera en réponse à la rumeur.
 */
#[Layout('components.layouts.public')]
final class ArticlePublic extends Component
{
    public string $slug = '';

    public function mount(string $article): void
    {
        $this->slug = $article;

        // Incrémenté au MONTAGE : dans `render()`, un simple rafraîchissement
        // de composant gonflerait le compteur.
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

        return view('livewire.public.article', [
            'article' => $article,
            'voisines' => $this->voisines(),
            /*
             | L'historique des corrections de CETTE vérification.
             |
             | Affiché sur la page elle-même et pas seulement dans le journal :
             | quelqu'un qui arrive ici par un partage ne consultera jamais
             | `/corrections` de lui-même, et c'est précisément la personne qui
             | doit savoir que le verdict a changé.
             */
            'corrections' => app(JournalDesCorrections::class)->historiqueDe($article),
        ])->layoutData([
            'seo' => app(Referencement::class)->pourFaitVerifie($article),
        ]);
    }

    #[Computed(persist: true)]
    public function articleCharge(): HubArticle
    {
        return HubArticle::where('slug', $this->slug)
            ->where('is_published', true)
            ->firstOrFail();
    }

    private function article(): HubArticle
    {
        return $this->articleCharge();
    }

    /**
     * @return Collection<int, HubArticle>
     */
    private function voisines(): Collection
    {
        return HubArticle::where('is_published', true)
            ->whereKeyNot($this->article()->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}
