<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Marketing\Services\SuiviDesVisites;
use App\Models\HubArticle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Une brève du Hub — transposition de compte-user/hub-article.html.
 */
#[Layout('components.layouts.citizen', ['title' => "Hub opportunités · Vérif'All"])]
final class ArticleHub extends Component
{
    public string $slug = '';

    public function mount(string $article): void
    {
        $this->slug = $article;

        $breve = $this->article();

        /*
         | Compteur de lectures incrémenté au chargement initial seulement.
         | Le placer dans `render()` le ferait grimper à chaque rafraîchissement
         | de composant Livewire — un clic sur un filtre suffirait à gonfler les
         | statistiques, et personne ne s'en apercevrait.
         */
        /*
         | Une visite, pas un chargement. Le service écarte les robots, les
         | aperçus de partage, les préchargements du navigateur et les
         | rafraîchissements — voir SuiviDesVisites.
         */
        app(SuiviDesVisites::class)->enregistrer($breve, request());
    }

    public function render(): View
    {
        return view('livewire.citizen.article-hub', [
            'breve' => $this->article(),
            'voisines' => $this->voisines(),
        ]);
    }

    #[Computed(persist: true)]
    public function articleCharge(): HubArticle
    {
        return HubArticle::with('verifiedFact')
            ->where('slug', $this->slug)
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
            ->where('category', $this->article()->category)
            ->whereKeyNot($this->article()->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}
