<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Community\Services\FiltreDeMots;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\ActionMotClef;
use App\Models\ModerationKeyword;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Str;

/**
 * « Mots surveillés » — la liste que consulte le filtre de modération.
 *
 * ## Pourquoi cet écran, et pas un fichier de configuration
 *
 * Parce que la liste doit se modifier au rythme de l'actualité. Une rumeur
 * nouvelle apparaît un mardi matin ; attendre un déploiement pour la
 * surveiller revient à ne pas la surveiller.
 *
 * ## Rien ne se supprime en silence
 *
 * Un mot se DÉSACTIVE. La ligne demeure, avec sa note et son compteur : c'est
 * ainsi qu'on peut répondre, six mois plus tard, à « pourquoi ce mot
 * était-il bloqué ? » La suppression définitive existe, mais elle est
 * journalisée comme le reste.
 *
 * ## Le compteur de déclenchements
 *
 * Il est là pour permettre l'ÉLAGAGE. Une liste de mots surveillés qui grossit
 * sans jamais être relue finit par bloquer des choses que plus personne
 * n'assume, et personne n'ose retirer une entrée dont on ignore si elle sert.
 */
#[Layout('components.layouts.staff', ['title' => "Mots surveillés · Vérif'All"])]
final class MotsSurveilles extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'block' | 'flag' */
    #[Url(as: 'action', except: '')]
    public string $filtreAction = '';

    public bool $formulaireOuvert = false;

    public ?string $enEdition = null;

    public string $terme = '';

    public string $action = 'flag';

    public string $note = '';

    public bool $actif = true;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreAction(): void
    {
        $this->resetPage();
    }

    public function ouvrirFormulaire(): void
    {
        $this->reset(['enEdition', 'terme', 'note']);
        $this->action = ActionMotClef::Signaler->value;
        $this->actif = true;
        $this->resetErrorBag();
        $this->formulaireOuvert = true;
    }

    public function modifier(string $uuid): void
    {
        $mot = ModerationKeyword::query()->where('uuid', $uuid)->first();

        if ($mot === null) {
            return;
        }

        $this->enEdition = $mot->uuid;
        $this->terme = $mot->term;
        $this->action = $mot->action->value;
        $this->note = (string) $mot->note;
        $this->actif = $mot->is_active;
        $this->resetErrorBag();
        $this->formulaireOuvert = true;
    }

    public function fermerFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'enEdition', 'terme', 'note']);
        $this->resetErrorBag();
    }

    public function enregistrer(FiltreDeMots $filtre, AuditLogService $audit): void
    {
        $this->authorize('update', ModerationKeyword::class);

        $existant = $this->enEdition === null
            ? null
            : ModerationKeyword::query()->where('uuid', $this->enEdition)->first();

        $donnees = $this->validate([
            /*
             | UNIQUE. Le même mot déclaré deux fois avec deux actions
             | différentes produirait un comportement dépendant de l'ordre de
             | lecture, c'est-à-dire imprévisible.
             */
            'terme' => [
                'required', 'string', 'min:2', 'max:120',
                Rule::unique('moderation_keywords', 'term')->ignore($existant?->id),
            ],
            'action' => ['required', Rule::enum(ActionMotClef::class)],
            /*
             | La note est OBLIGATOIRE, contrairement à l'habitude.
             |
             | Sans elle, personne n'ose retirer une entrée six mois plus tard,
             | faute de savoir ce qu'elle visait. Une liste qu'on n'ose pas
             | élaguer finit par bloquer des choses que plus personne n'assume.
             */
            'note' => ['required', 'string', 'min:5', 'max:300'],
            'actif' => ['boolean'],
        ], attributes: [
            'terme' => 'mot ou expression',
            'note' => 'raison',
        ], messages: [
            'terme.unique' => 'Ce mot est déjà surveillé.',
            'note.required' => 'Dites pourquoi ce mot est surveillé : sans raison, personne n’osera le retirer plus tard.',
        ]);

        $avant = $existant?->only(['term', 'action', 'is_active']);

        if ($existant === null) {
            $mot = ModerationKeyword::query()->create([
                'uuid' => (string) Str::uuid(),
                'term' => $donnees['terme'],
                'action' => $donnees['action'],
                'note' => $donnees['note'],
                'is_active' => $donnees['actif'],
                'created_by' => auth('staff')->id(),
            ]);
        } else {
            $existant->forceFill([
                'term' => $donnees['terme'],
                'action' => $donnees['action'],
                'note' => $donnees['note'],
                'is_active' => $donnees['actif'],
            ])->save();

            $mot = $existant->refresh();
        }

        /*
         | Le cache est vidé TOUT DE SUITE. Une équipe qui ajoute un mot doit le
         | voir agir dans la minute, pas au bout du délai de cache : c'est le
         | jour d'une rumeur qui monte qu'on s'en sert.
         */
        $filtre->oublier();

        $audit->record(
            action: $existant === null ? 'moderation_keyword.create' : 'moderation_keyword.update',
            module: 'moderation',
            acteur: auth('staff')->user(),
            cible: $mot,
            avant: $avant,
            apres: $mot->only(['term', 'action', 'is_active']),
            cibleLibelle: $mot->term,
        );

        $this->fermerFormulaire();
        $this->toastSuccess('Mot surveillé enregistré.');
    }

    /**
     * Bascule l'activité d'un mot.
     *
     * Le geste courant, et le seul qui devrait servir : on désactive plutôt que
     * de supprimer, pour garder la trace et la raison.
     */
    public function basculer(string $uuid, FiltreDeMots $filtre, AuditLogService $audit): void
    {
        $this->authorize('update', ModerationKeyword::class);

        $mot = ModerationKeyword::query()->where('uuid', $uuid)->first();

        if ($mot === null) {
            return;
        }

        $avant = $mot->is_active;
        $mot->forceFill(['is_active' => ! $avant])->save();

        $filtre->oublier();

        $audit->record(
            action: 'moderation_keyword.toggle',
            module: 'moderation',
            acteur: auth('staff')->user(),
            cible: $mot,
            avant: ['is_active' => $avant],
            apres: ['is_active' => ! $avant],
            cibleLibelle: $mot->term,
        );

        $this->toastSuccess($avant ? 'Mot désactivé.' : 'Mot réactivé.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', ModerationKeyword::class);

        return view('livewire.staff.mots-surveilles', [
            'mots' => $this->lignes(),
            'actions' => ActionMotClef::cases(),
            'compteurs' => $this->compteurs(),
        ]);
    }

    /** @return LengthAwarePaginator<int, ModerationKeyword> */
    private function lignes(): LengthAwarePaginator
    {
        return ModerationKeyword::query()
            ->with('auteur:id,name')
            ->when($this->recherche !== '', fn ($r) => $r->where('term', 'like', '%'.$this->recherche.'%'))
            ->when($this->filtreAction !== '', fn ($r) => $r->where('action', $this->filtreAction))
            /*
             | Les plus DÉCLENCHÉS en tête. C'est l'ordre utile : ce sont eux
             | qu'il faut relire, parce que ce sont eux qui agissent réellement
             | sur ce que les gens publient.
             */
            ->orderByDesc('hits')
            ->orderBy('term')
            ->paginate(25);
    }

    /** @return array<string, int> */
    private function compteurs(): array
    {
        return [
            'total' => ModerationKeyword::query()->count(),
            'actifs' => ModerationKeyword::query()->where('is_active', true)->count(),
            'bloquants' => ModerationKeyword::query()
                ->where('is_active', true)
                ->where('action', ActionMotClef::Bloquer->value)->count(),
            'declenchements' => (int) ModerationKeyword::query()->sum('hits'),
        ];
    }
}
