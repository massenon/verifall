<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Testimonial;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Témoignages » — la parole des citoyens, relue avant de paraître.
 *
 * ## Non publié par défaut, et c'est le seul choix défendable
 *
 * `testimonials.is_published` vaut FAUX à la création. Un témoignage est la
 * parole de quelqu'un, publiée sous le nom de la plateforme : il se relit.
 *
 * C'est l'inverse du réflexe habituel — on coche « publié » et on oublie —
 * mais l'erreur n'a pas la même conséquence dans les deux sens. Un témoignage
 * qui attend un jour de plus ne coûte rien ; un témoignage publié par
 * inadvertance engage la plateforme sur des propos qu'elle n'a pas relus.
 *
 * ## La note est NULLABLE, et surtout pas « 5 par défaut »
 *
 * Un témoignage sans note n'est pas un témoignage cinq étoiles. Mettre 5 par
 * défaut fabriquerait des avis élogieux que personne n'a donnés — et une
 * moyenne d'étoiles calculée dessus serait un chiffre inventé qui a l'air
 * mesuré.
 */
#[Layout('components.layouts.staff', ['title' => "Témoignages · Vérif'All"])]
final class Temoignages extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'publies' | 'en-attente' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $auteur = '';

    public string $qualite = '';

    public string $citation = '';

    public string $note = '';

    public string $ordre = '0';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('testimonial.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.temoignages', [
            'temoignages' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
        ]);
    }

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'auteur', 'qualite', 'citation', 'note');
        $this->ordre = (string) ((int) Testimonial::query()->max('sort_order') + 10);
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $temoignage = $this->temoignage($uuid);

        $this->enEdition = $uuid;
        $this->auteur = (string) $temoignage->author;
        $this->qualite = (string) $temoignage->role;
        $this->citation = (string) $temoignage->quote;
        $this->note = $temoignage->rating !== null ? (string) $temoignage->rating : '';
        $this->ordre = (string) $temoignage->sort_order;

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    public function enregistrer(AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $donnees = $this->validate();

        $temoignage = $this->enEdition !== null ? $this->temoignage($this->enEdition) : new Testimonial;

        $suivies = ['author', 'role', 'rating', 'sort_order', 'is_published'];
        $avant = $temoignage->exists ? $temoignage->only($suivies) : null;

        $temoignage->forceFill([
            'uuid' => $temoignage->uuid ?? (string) Str::uuid(),
            'author' => $donnees['auteur'],
            'role' => $donnees['qualite'] !== '' ? $donnees['qualite'] : null,
            'quote' => $donnees['citation'],
            // Vide veut dire « pas de note », jamais zéro ni cinq.
            'rating' => $donnees['note'] !== '' ? (int) $donnees['note'] : null,
            'sort_order' => (int) $donnees['ordre'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'testimonial.create' : 'testimonial.update',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $temoignage,
            avant: $avant,
            apres: $temoignage->only($suivies),
            cibleLibelle: $temoignage->author,
        );

        $nouveau = $avant === null;

        $this->fermerLeTiroir();
        $this->toastSuccess($nouveau
            ? 'Témoignage enregistré — il reste à publier après relecture.'
            : 'Témoignage mis à jour.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $temoignage = $this->temoignage($uuid);
        $publie = ! $temoignage->is_published;

        $temoignage->forceFill(['is_published' => $publie])->save();

        $audit->record(
            action: $publie ? 'testimonial.publish' : 'testimonial.unpublish',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $temoignage,
            avant: ['is_published' => ! $publie],
            apres: ['is_published' => $publie],
            cibleLibelle: $temoignage->author,
        );

        $this->toastSuccess($publie ? 'Témoignage publié.' : 'Témoignage retiré du site.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $audit): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        abort_unless($this->peutGerer(), 403);

        $temoignage = $this->temoignage($this->aSupprimer);

        $audit->record(
            action: 'testimonial.delete',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $temoignage,
            avant: $temoignage->only(['author', 'quote', 'is_published']),
            cibleLibelle: $temoignage->author,
        );

        $temoignage->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Témoignage supprimé.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'auteur' => ['required', 'string', 'min:2', 'max:160'],
            'qualite' => ['nullable', 'string', 'max:160'],
            'citation' => ['required', 'string', 'min:20', 'max:1000'],
            'note' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'auteur' => 'nom de la personne',
            'qualite' => 'qualité',
            'citation' => 'témoignage',
            'note' => 'note',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('testimonial.manage') ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, Testimonial>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Testimonial::query()
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('author', 'like', $terme)
                    ->orWhere('quote', 'like', $terme));
            })
            ->when($this->filtreEtat === 'publies', fn (Builder $q) => $q->where('is_published', true))
            ->when($this->filtreEtat === 'en-attente', fn (Builder $q) => $q->where('is_published', false))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * @return array{total: int, publies: int, en_attente: int, note_moyenne: ?float}
     */
    private function indicateurs(): array
    {
        $notes = Testimonial::query()->where('is_published', true)->whereNotNull('rating');

        return [
            'total' => Testimonial::query()->count(),
            'publies' => Testimonial::query()->where('is_published', true)->count(),
            'en_attente' => Testimonial::query()->where('is_published', false)->count(),
            /*
             | `null` tant qu'aucun témoignage publié ne porte de note — et non
             | zéro. Une moyenne sur zéro avis est un chiffre inventé, et c'est
             | celui qu'on cite.
             */
            'note_moyenne' => (clone $notes)->exists()
                ? round((float) (clone $notes)->avg('rating'), 1)
                : null,
        ];
    }

    private function temoignage(string $uuid): Testimonial
    {
        return Testimonial::query()->where('uuid', $uuid)->firstOrFail();
    }
}
