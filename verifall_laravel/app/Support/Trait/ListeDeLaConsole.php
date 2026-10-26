<?php

declare(strict_types=1);

namespace App\Support\Trait;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Le minimum de toute liste de la console : recherche, lignes par page, pagination,
 * remise à zéro. Pour une liste construite EN MÉMOIRE (catalogue, dossier, agrégat) ;
 * une liste Eloquent pagine avec `->paginate($this->lignesParPage())`.
 *
 * Le nombre de lignes est borné à ce que `<x-premium.barre-de-liste>` propose : une
 * valeur forgée dans l'adresse (`?par=100000`) retombe sur 10.
 */
trait ListeDeLaConsole
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'par', except: 10)]
    public int $parPage = 10;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingParPage(): void
    {
        $this->resetPage();
    }

    public function reinitialiserLaListe(): void
    {
        $this->reset('recherche');
        $this->parPage = 10;
        $this->resetPage();

        if (method_exists($this, 'reinitialiserLesFiltres')) {
            $this->reinitialiserLesFiltres();
        }
    }

    protected function lignesParPage(): int
    {
        return in_array($this->parPage, [10, 25, 50, 100], true) ? $this->parPage : 10;
    }

    /**
     * Filtre par texte libre puis pagine une liste en mémoire.
     *
     * @param  array<int, mixed>|Collection<int, mixed>  $lignes
     * @param  callable(mixed): string  $texte  ce que la recherche parcourt, pour une ligne
     */
    protected function paginerLaListe(array|Collection $lignes, callable $texte): LengthAwarePaginator
    {
        $q = mb_strtolower(trim($this->recherche));
        $lignes = collect($lignes)
            ->when($q !== '', fn (Collection $c): Collection => $c->filter(fn (mixed $l): bool => str_contains(mb_strtolower($texte($l)), $q)))
            ->values();

        $parPage = $this->lignesParPage();
        $courante = $this->getPage();

        return new LengthAwarePaginator(
            $lignes->forPage($courante, $parPage)->values()->all(),
            $lignes->count(),
            $parPage,
            $courante,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }
}
