<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\NiveauCours;
use App\Enums\StatutInscription;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Catalogue de cours » — transposition de compte-user/catalogue.html.
 *
 * Une carte, quatre états : pas inscrit, inscrit, en cours, terminé. Le
 * prototype le dit explicitement — « Un seul composant carte, 4 états » — et
 * c'est le bon découpage : le libellé du bouton dit à quoi s'attendre, et
 * « Commencer » sur un cours à moitié fait ferait croire à une progression
 * perdue.
 */
#[Layout('components.layouts.citizen', ['title' => "Catalogue de cours · Vérif'All"])]
final class Catalogue extends Component
{
    /** Combien de cours proposer à quelqu'un qui n'a rien commencé. */
    private const RECOMMANDATIONS = 2;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    #[Url(as: 'niveau', except: '')]
    public string $filtreNiveau = '';

    /** '', 'free' ou 'premium' — les trois choix du prototype. */
    #[Url(as: 'prix', except: '')]
    public string $filtrePrix = '';

    /**
     * Code ISO d'une langue, ou '' pour toutes.
     *
     * `courses.language` était saisie depuis le lot 14 et **rien ne s'en
     * servait** : on demandait au mentor de renseigner un champ qui n'agissait
     * nulle part.
     */
    #[Url(as: 'langue', except: '')]
    public string $filtreLangue = '';

    public function render(): View
    {
        return view('livewire.citizen.catalogue', [
            'cours' => $this->cours(),
            'categories' => $this->categories(),
            'niveaux' => NiveauCours::cases(),
            'langues' => $this->langues(),
            'recommandes' => $this->recommandes(),
            'reprise' => $this->reprise(),
            'progression' => $this->progression(),
        ]);
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'categorie', 'filtreNiveau', 'filtrePrix']);
    }

    public function choisirCategorie(string $categorie): void
    {
        $this->categorie = $this->categorie === $categorie ? '' : $categorie;
    }

    /** Un filtre est-il actif ? Décide de l'état vide à montrer. */
    public function filtresActifs(): bool
    {
        return $this->recherche !== '' || $this->categorie !== ''
            || $this->filtreNiveau !== '' || $this->filtrePrix !== ''
            // La langue compte comme les trois autres : sans elle, filtrer sur
            // « Anglais » laissait le bloc « Recommandés » proposer des cours
            // français juste en dessous de la liste filtrée.
            || $this->filtreLangue !== '';
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
                    ->orWhere('description', 'like', $terme)
                    ->orWhere('category', 'like', $terme));
            })
            ->when($this->categorie !== '', fn (Builder $q) => $q->where('category', $this->categorie))
            ->when($this->filtreNiveau !== '', fn (Builder $q) => $q->where('level', $this->filtreNiveau))
            ->when($this->filtreLangue !== '', fn (Builder $q) => $q->where('language', $this->filtreLangue))
            /*
             | « Gratuit » se lit sur le prix EFFECTIF, pas sur le prix affiché :
             | un cours premium offert ce mois-ci doit apparaître sous le filtre
             | Gratuit, sinon le filtre contredit la carte juste à côté.
             */
            ->when($this->filtrePrix === 'free', fn (Builder $q) => $q->where(
                fn (Builder $sous) => $sous->where('cost_units', 0)->orWhere('free_until', '>', now())
            ))
            ->when($this->filtrePrix === 'premium', fn (Builder $q) => $q->where('cost_units', '>', 0)
                ->where(fn (Builder $sous) => $sous->whereNull('free_until')->orWhere('free_until', '<=', now())))
            ->orderBy('id')
            ->get();
    }

    /**
     * Les langues réellement présentes au catalogue.
     *
     * ## Une seule langue = aucun sélecteur
     *
     * La vue n'affiche le filtre qu'à partir de DEUX entrées. Un menu déroulant
     * dont tous les choix mènent au même résultat n'aide personne : il occupe
     * une place, invite à un clic, et ne change rien. Aujourd'hui les neuf cours
     * sont en français ; le filtre apparaîtra tout seul le jour où un cours
     * anglophone sera publié, sans qu'on ait à y revenir.
     *
     * @return list<string> codes ISO, triés
     */
    private function langues(): array
    {
        return Course::where('is_published', true)
            ->whereNotNull('language')
            ->distinct()
            ->orderBy('language')
            ->pluck('language')
            ->all();
    }

    /**
     * Les catégories réellement présentes.
     *
     * Le prototype code ses trois pastilles en dur ; les lire depuis le
     * catalogue évite qu'une quatrième catégorie créée par un mentor reste
     * invisible, et qu'une pastille survive au dernier cours qui la portait.
     *
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

    /**
     * Les inscriptions de la personne, indexées par cours.
     *
     * Chargées EN UNE FOIS : interroger l'inscription depuis chaque carte
     * ferait une requête par cours affiché.
     *
     * @return Collection<int, Enrollment>
     */
    #[Computed]
    public function inscriptions(): Collection
    {
        return Enrollment::where('user_id', $this->utilisateur()->id)
            ->get()
            ->keyBy('course_id');
    }

    /** L'inscription à ce cours, s'il y en a une. */
    public function inscription(Course $cours): ?Enrollment
    {
        return $this->inscriptions->get($cours->id);
    }

    /**
     * Le cours à reprendre — la bannière « Continuer » du prototype.
     *
     * Le plus récemment touché, pas le premier de la liste : c'est celui qu'on
     * a en tête en revenant.
     */
    private function reprise(): ?Enrollment
    {
        return Enrollment::with('course')
            ->where('user_id', $this->utilisateur()->id)
            ->whereIn('status', [StatutInscription::Inscrit->value, StatutInscription::EnCours->value])
            ->orderByDesc('last_activity_at')
            ->first();
    }

    /**
     * Deux cours à découvrir.
     *
     * Le prototype filtre sur l'état « NOT_STARTED », que sa propre fonction ne
     * renvoie jamais — elle rend « NOT_ENROLLED ». Le bloc « Recommandé pour
     * vous » y est donc toujours vide, donc toujours masqué. L'intention est
     * limpide ; la transposer telle quelle aurait produit du code mort.
     *
     * @return Collection<int, Course>
     */
    private function recommandes(): Collection
    {
        if ($this->filtresActifs()) {
            return new Collection;
        }

        return Course::where('is_published', true)
            ->whereNotIn('id', $this->inscriptions->keys())
            ->withCount('lessons')
            ->orderByDesc('rating')
            ->limit(self::RECOMMANDATIONS)
            ->get();
    }

    /**
     * L'encart de progression, en tête de page.
     *
     * Le pourcentage est la MOYENNE des avancements, pas le rapport des cours
     * terminés : quelqu'un à 90 % sur deux cours n'a rien « terminé », et lui
     * afficher 0 % serait décourageant autant qu'inexact.
     *
     * @return array{pourcentage: int, termines: int, en_cours: int}
     */
    private function progression(): array
    {
        $inscriptions = $this->inscriptions;

        return [
            'pourcentage' => $inscriptions->isEmpty() ? 0 : (int) round($inscriptions->avg('progress')),
            'termines' => $inscriptions->where('status', StatutInscription::Termine)->count(),
            'en_cours' => $inscriptions->whereIn('status', [
                StatutInscription::Inscrit, StatutInscription::EnCours,
            ])->count(),
        ];
    }

    /** La prochaine leçon d'un cours — cible du bouton « Continuer ». */
    public function prochaineLecon(Course $cours): ?string
    {
        $lecon = app(ProgressionAcademie::class)->prochaineLecon($this->utilisateur(), $cours);

        return $lecon?->uuid;
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
