<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\NiveauCours;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Tutoriels » — la vitrine publique de l'Académie.
 *
 * Le prototype tient une page de tutoriels distincte du catalogue, avec son
 * propre contenu figé. Créer une seconde bibliothèque pédagogique à côté des
 * cours produirait deux corpus à tenir à jour, et le jour où ils divergent
 * c'est la page publique qui ment — celle que voient les gens qui ne sont pas
 * encore inscrits.
 *
 * Cette page présente donc les COURS PUBLIÉS de l'Académie, sans exiger de
 * compte pour les découvrir. On ne cache pas ce qu'on propose d'apprendre ;
 * seule l'inscription ouvre les leçons.
 */
#[Layout('components.layouts.public', ['title' => "Tutoriels · Vérif'All"])]
final class TutorielsPublics extends Component
{
    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /**
     * Cases cochées dans le volet « Catégories ».
     *
     * Un tableau et non une chaîne : le prototype coche plusieurs cases  à la
     * fois, et chercher « Investigation OU Santé » est un besoin ordinaire.
     *
     * @var list<string>
     */
    #[Url(as: 'cat', except: [])]
    public array $categoriesChoisies = [];

    /**
     * Cases cochées dans le volet « Niveau ».
     *
     * @var list<string>
     */
    #[Url(as: 'niveau', except: [])]
    public array $niveauxChoisis = [];

    public function render(): View
    {
        return view('livewire.public.tutoriels', [
            'cours' => $this->cours(),
            'categories' => $this->categories(),
            'niveaux' => NiveauCours::cases(),
        ]);
    }

    public function filtresActifs(): bool
    {
        return $this->recherche !== ''
            || $this->categoriesChoisies !== []
            || $this->niveauxChoisis !== [];
    }

    /**
     * @return Collection<int, Course>
     */
    private function cours(): Collection
    {
        return Course::query()
            ->where('is_published', true)
            ->withCount('lessons')
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                    ->orWhere('description', 'like', $terme));
            })
            ->when($this->categoriesChoisies !== [],
                fn (Builder $q) => $q->whereIn('category', $this->categoriesChoisies))
            ->when($this->niveauxChoisis !== [],
                fn (Builder $q) => $q->whereIn('level', $this->niveauxChoisis))
            // Par ordre de publication : la note reste à zéro tant que personne
            // n'a noté, et trier sur une colonne vide donne un ordre au hasard.
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        return Course::where('is_published', true)
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }
}
