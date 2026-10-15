<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Models\HubArticle;
use App\Models\HubJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Hub opportunités » — transposition de compte-user/hub-infos.html.
 *
 * Trois rubriques : les ACTUALITÉS vérifiées, les OPPORTUNITÉS BÉNIN, les
 * OPPORTUNITÉS INTERNATIONALES. Séparer les deux dernières n'est pas cosmétique :
 * quelqu'un qui cherche un emploi à Cotonou et quelqu'un qui cherche une bourse
 * à l'étranger ne lisent pas la même liste, et les mélanger oblige chacun à
 * trier ce qui ne le concerne pas.
 *
 * Les portails officiels ne sont pas affichés SUR CET ÉCRAN. La mise en retrait
 * était « décidée, pas un abandon », et les données ont été conservées pour que
 * le retour ne coûte rien — c'est exactement ce qui s'est produit.
 *
 * Ils ont leur propre rubrique depuis le 1er septembre 2026 :
 * `citizen.portals`, atteignable par le menu du compte et fermée par défaut
 * derrière le réglage `hub_portals_actif`. Elle ne revient pas ici : un
 * annuaire d'adresses officielles et un fil d'actualités ne se parcourent pas
 * de la même façon, et les mêler ferait trier.
 */
#[Layout('components.layouts.citizen', ['title' => "Hub opportunités · Vérif'All"])]
final class HubInfos extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    /** Filtre de nature appliqué aux deux listes d'opportunités. */
    #[Url(as: 'type', except: '')]
    public string $filtreType = '';

    public function render(): View
    {
        return view('livewire.citizen.hub-infos', [
            'breves' => $this->breves(),
            'aLaUne' => $this->aLaUne(),
            'categories' => $this->categories(),
            'opportunitesBenin' => $this->opportunites(PorteeOpportunite::Benin),
            'opportunitesInternationales' => $this->opportunites(PorteeOpportunite::International),
            'types' => $this->typesDisponibles(),
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'categorie', 'filtreType'], true)) {
            $this->resetPage();
        }
    }

    public function choisirCategorie(string $nom): void
    {
        $this->categorie = $this->categorie === $nom ? '' : $nom;
        $this->resetPage();
    }

    public function choisirType(string $type): void
    {
        $this->filtreType = $this->filtreType === $type ? '' : $type;
    }

    /**
     * Un filtre est-il actif, pour les ACTUALITÉS ?
     *
     * La nature n'en fait pas partie : elle ne filtre que les opportunités, et
     * la faire compter ici masquerait la brève à la une sans raison.
     */
    public function filtresActifs(): bool
    {
        return $this->recherche !== '' || $this->categorie !== '';
    }

    /**
     * Un filtre est-il actif, pour les OPPORTUNITÉS ?
     *
     * Distingue « rien ne correspond à votre recherche » de « rien n'est
     * publié ». Le second message, affiché sur une recherche infructueuse,
     * affirmait une contre-vérité : que la plateforme n'avait rien vérifié.
     */
    public function filtresOpportunitesActifs(): bool
    {
        return $this->recherche !== '' || $this->filtreType !== '';
    }

    /** Efface tout, depuis l'état vide. */
    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'categorie', 'filtreType']);
        $this->resetPage();
    }

    /**
     * La brève mise en avant : la plus récente, hors filtre.
     *
     * Masquée dès qu'un filtre est actif — la garder en tête d'une liste
     * filtrée montrerait un résultat qui ne correspond pas à la recherche.
     */
    private function aLaUne(): ?HubArticle
    {
        if ($this->filtresActifs()) {
            return null;
        }

        return HubArticle::where('is_published', true)
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, HubArticle>
     */
    private function breves(): LengthAwarePaginator
    {
        $aLaUne = $this->aLaUne();

        return HubArticle::query()
            ->where('is_published', true)
            ->when($aLaUne !== null, fn (Builder $q) => $q->whereKeyNot($aLaUne->id))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                    ->orWhere('excerpt', 'like', $terme)
                    ->orWhere('source_name', 'like', $terme));
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

    /**
     * Opportunités vérifiées et encore ouvertes, pour une portée donnée.
     *
     * Trois filtres, chacun pour une raison distincte :
     *
     *   · `is_verified` — sur un hub béninois, la fausse offre qui réclame des
     *     « frais de dossier » est l'arnaque la plus répandue. Publier une
     *     opportunité non contrôlée reviendrait à la cautionner.
     *   · le DÉLAI DE GRÂCE — une offre reste visible une semaine après son
     *     échéance : les candidatures de la veille se traitent encore. La
     *     requête double la tâche planifiée plutôt que de s'y fier : si le
     *     planificateur n'est pas branché, l'écran reste juste.
     *   · le TYPE, quand la personne en a choisi un.
     *
     * @return Collection<int, HubJob>
     */
    private function opportunites(PorteeOpportunite $portee): Collection
    {
        $graceDepassee = now()->subDays(HubJob::JOURS_DE_GRACE)->toDateString();

        return HubJob::query()
            ->where('is_published', true)
            ->where('is_verified', true)
            ->where('scope', $portee->value)
            // OU, pas ET : une offre sans date limite reste ouverte, une offre
            // datée reste visible pendant son délai de grâce.
            ->where(fn (Builder $q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', $graceDepassee))
            ->when($this->filtreType !== '', fn (Builder $q) => $q->where('type', $this->filtreType))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                    ->orWhere('organisation', 'like', $terme));
            })
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline')
            ->limit(12)
            ->get();
    }

    /**
     * Les natures réellement présentes.
     *
     * Lues du contenu plutôt que codées en dur : proposer « Alternance » quand
     * aucune alternance n'est publiée mène à une liste vide, et une liste vide
     * derrière un filtre passe pour un bug.
     *
     * @return list<TypeOpportunite>
     */
    private function typesDisponibles(): array
    {
        $graceDepassee = now()->subDays(HubJob::JOURS_DE_GRACE)->toDateString();

        return HubJob::query()
            ->where('is_published', true)
            ->where('is_verified', true)
            // OU, pas ET : une offre sans date limite reste ouverte, une offre
            // datée reste visible pendant son délai de grâce.
            ->where(fn (Builder $q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', $graceDepassee))
            ->distinct()
            ->pluck('type')
            ->map(fn (TypeOpportunite $type): TypeOpportunite => $type)
            ->sortBy(fn (TypeOpportunite $type): string => $type->libelle())
            ->values()
            ->all();
    }
}
