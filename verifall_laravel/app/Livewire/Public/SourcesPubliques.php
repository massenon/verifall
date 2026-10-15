<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Models\TrustedSource;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Les sources sur lesquelles s'appuie Vérif'All », publiées.
 *
 * ## Pourquoi cette page existe
 *
 * Parce qu'un verdict de fact-checking ne vaut que ce que valent ses sources,
 * et qu'une plateforme qui les garde pour elle demande une confiance qu'elle ne
 * justifie pas. Le référentiel décidait déjà, en silence, du crédit accordé à
 * un domaine dans chaque analyse ; le publier, c'est rendre vérifiable ce qui
 * était opaque.
 *
 * Elle complète {@see Methodologie}, qui dit COMMENT une affirmation devient un
 * verdict. Celle-ci dit SUR QUOI.
 *
 * ## Deux choses qu'elle n'affiche pas, et ce n'est pas un oubli
 *
 * **La crédibilité chiffrée.** Elle existe en base et pèse dans le score. Mais
 * sur les 386 sources reprises d'un répertoire tiers, elle vaut la valeur par
 * DÉFAUT de leur famille — aucune n'a été notée individuellement. Publier « 78 »
 * en face de deux cent soixante-quatre rédactions afficherait une précision
 * qui n'existe pas, et invitererait à discuter un chiffre que personne n'a
 * arbitré. La FAMILLE, elle, dit quelque chose de vrai : c'est elle qui est
 * montrée.
 *
 * **Les notes internes.** `notes` porte la provenance et les remarques de la
 * rédaction. C'est un carnet de travail, pas une publication.
 *
 * ## Le filtre est en mémoire, et c'est voulu
 *
 * Le dépôt rend les quatre cents fiches déjà en cache — les mêmes que celles
 * que le moteur consulte. Les refiltrer en base à chaque frappe ajouterait une
 * requête par caractère pour trier une collection qui tient dans un souffle, et
 * ferait diverger la page publique de ce que le moteur voit réellement.
 */
#[Layout('components.layouts.public', ['title' => "Nos sources · Vérif'All"])]
final class SourcesPubliques extends Component
{
    /** Recherche libre — sur le nom comme sur le domaine. */
    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** Famille retenue, vide pour toutes. */
    #[Url(as: 'famille', except: '')]
    public string $famille = '';

    public function render(DepotSourcesFiables $depot): View
    {
        $parFamille = $depot->parFamille();

        return view('public.nos-sources', [
            'familles' => $parFamille->keys(),
            'comptes' => $parFamille->map(static fn (Collection $s): int => $s->count()),
            'total' => $parFamille->flatten()->count(),
            'resultats' => $this->filtrer($parFamille),
        ]);
    }

    /**
     * Recliquer sur la famille active la relâche.
     *
     * Le geste attendu quand on veut « tout » revoir, et celui qu'emploie déjà
     * l'écran des outils citoyens.
     */
    public function choisirLaFamille(string $famille): void
    {
        $this->famille = $this->famille === $famille ? '' : $famille;
    }

    public function reinitialiser(): void
    {
        $this->recherche = '';
        $this->famille = '';
    }

    /**
     * @param  Collection<string, Collection<int, TrustedSource>>  $parFamille
     * @return Collection<string, Collection<int, TrustedSource>>
     */
    private function filtrer(Collection $parFamille): Collection
    {
        $terme = mb_strtolower(trim($this->recherche));

        return $parFamille
            ->when(
                $this->famille !== '',
                fn (Collection $familles): Collection => $familles->filter(
                    fn (Collection $sources, string $nom): bool => $nom === $this->famille,
                ),
            )
            ->map(static fn (Collection $sources): Collection => $terme === ''
                ? $sources
                : $sources->filter(static fn (TrustedSource $source): bool => str_contains(mb_strtolower($source->name), $terme)
                    || str_contains(mb_strtolower($source->domain), $terme))->values())
            // Une famille vidée par la recherche disparaît : afficher un titre
            // suivi de rien laisse croire à une panne.
            ->reject(static fn (Collection $sources): bool => $sources->isEmpty());
    }
}
