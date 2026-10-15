<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Community\Services\ModerationDuForum;
use App\Core\Identity\Services\AuditLogService;
use App\Models\ForumCategory;
use App\Models\Thread;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * « Forum Citoyen » — la modération à l'initiative du modérateur.
 *
 * ## Ce que cet écran n'est pas
 *
 * Ce n'est pas la file de signalements : `Staff\SignalementsContenu` traite ce
 * que les citoyens portent à la modération. Ici, un modérateur PARCOURT le
 * forum et agit sur ce qu'il lit — souvent avant que quiconque ait signalé
 * quoi que ce soit. Les deux écrans sont complémentaires, et les confondre
 * ferait passer à côté de ce que personne ne signale.
 *
 * ## Verrouiller n'est pas supprimer
 *
 * La matrice sépare `forum.moderate` de `forum.delete`, et l'écran s'y tient.
 * Un sujet verrouillé reste LISIBLE : on voit ce qui a été dit, et qu'un terme
 * y a été mis. La suppression fait disparaître la discussion entière, réponses
 * de bonne foi comprises — d'où la confirmation qui annonce leur nombre.
 *
 * ## Pas de dépôt, et la raison est mesurée
 *
 * `Thread` compte HUIT lecteurs — le service d'animation, la réputation, l'API
 * mobile, quatre écrans citoyens. Le critère 1 de `CLAUDE.md` (lecture
 * partagée web/API) est donc réuni, et un dépôt serait dû.
 *
 * L'écrire ici en ne l'utilisant que sur cet écran donnerait un NEUVIÈME
 * lecteur, avec un neuvième style. Unifier les huit est un chantier à part,
 * dont la taille est maintenant mesurée et consignée au journal du lot 8.
 * En attendant, les ÉCRITURES passent toutes par `ModerationDuForum` — c'est
 * là que vivent les invariants, et c'est ce qui compte le plus.
 */
#[Layout('components.layouts.staff', ['title' => "Forum citoyen · Vérif'All"])]
final class ForumCitoyen extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'rubrique', except: '')]
    public string $filtreCategorie = '';

    /** '' | 'verrouilles' | 'epingles' | 'escalades' | 'sans-verdict' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $aSupprimer = null;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCategorie(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCategorie', 'filtreEtat');
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('forum.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.forum-citoyen', [
            'sujets' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'categories' => $this->categories(),
            'peutModerer' => $this->peut('forum.moderate'),
            'peutSupprimer' => $this->peut('forum.delete'),
            'sujetVise' => $this->aSupprimer !== null ? $this->sujet($this->aSupprimer) : null,
        ]);
    }

    // ————————————————————————————————————————————————— modération

    public function basculerLeVerrou(string $uuid, ModerationDuForum $moderation, AuditLogService $audit): void
    {
        abort_unless($this->peut('forum.moderate'), 403);

        $sujet = $this->sujet($uuid);
        $verrouille = $moderation->basculerLeVerrou($sujet);

        $audit->record(
            action: $verrouille ? 'forum.lock' : 'forum.unlock',
            module: 'communaute',
            acteur: Auth::guard('staff')->user(),
            cible: $sujet,
            avant: ['is_locked' => ! $verrouille],
            apres: ['is_locked' => $verrouille],
            cibleLibelle: $sujet->title,
        );

        // Le message dit ce que le verrou NE FAIT PAS : le sujet reste lisible.
        // Sans cela, « verrouillé » se confond avec « masqué ».
        $this->toastSuccess($verrouille
            ? 'Sujet verrouillé : plus aucune réponse, mais la discussion reste lisible.'
            : 'Sujet rouvert aux réponses.');
    }

    public function basculerLEpingle(string $uuid, ModerationDuForum $moderation, AuditLogService $audit): void
    {
        abort_unless($this->peut('forum.moderate'), 403);

        $sujet = $this->sujet($uuid);
        $epingle = $moderation->basculerLEpingle($sujet);

        $audit->record(
            action: $epingle ? 'forum.pin' : 'forum.unpin',
            module: 'communaute',
            acteur: Auth::guard('staff')->user(),
            cible: $sujet,
            avant: ['is_pinned' => ! $epingle],
            apres: ['is_pinned' => $epingle],
            cibleLibelle: $sujet->title,
        );

        $this->toastSuccess($epingle ? 'Sujet épinglé en tête du forum.' : 'Sujet désépinglé.');
    }

    /**
     * Transmet un sujet au fact-checking.
     */
    public function escalader(string $uuid, ModerationDuForum $moderation, AuditLogService $audit): void
    {
        abort_unless($this->peut('forum.moderate'), 403);

        $sujet = $this->sujet($uuid);
        $demandeur = Auth::guard('staff')->user();

        if ($demandeur === null) {
            return;
        }

        try {
            $moderation->escalader($sujet, $demandeur);
        } catch (RuntimeException $erreur) {
            /*
             | Deux refus : sujet déjà tranché, sujet déjà escaladé. Escalader
             | deux fois écraserait la date d'origine — donc l'ancienneté de la
             | demande, seule chose qui ordonne la file du fact-checking.
             */
            $this->toastError($erreur->getMessage());

            return;
        }

        $audit->record(
            action: 'forum.escalate',
            module: 'communaute',
            acteur: $demandeur,
            cible: $sujet,
            apres: ['escalated_at' => $sujet->escalated_at?->toIso8601String()],
            cibleLibelle: $sujet->title,
        );

        // « Transmis » et non « vérifié » : escalader, c'est demander.
        $this->toastSuccess('Sujet transmis au fact-checking. Le verdict reste à rendre.');
    }

    // ————————————————————————————————————————————————— suppression

    public function demanderLaSuppression(string $uuid): void
    {
        abort_unless($this->peut('forum.delete'), 403);

        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(ModerationDuForum $moderation, AuditLogService $audit): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        abort_unless($this->peut('forum.delete'), 403);

        $sujet = $this->sujet($this->aSupprimer);

        $audit->record(
            action: 'forum.delete',
            module: 'communaute',
            acteur: Auth::guard('staff')->user(),
            cible: $sujet,
            avant: [
                'title' => $sujet->title,
                'auteur' => $sujet->author?->email,
                // Ce qui disparaît avec : c'est ce qu'on cherchera si la
                // suppression est contestée.
                'reponses' => $sujet->replies()->count(),
            ],
            cibleLibelle: $sujet->title,
        );

        $emportees = $moderation->supprimerLeSujet($sujet);

        $this->aSupprimer = null;
        $this->toastSuccess($emportees === 0
            ? 'Sujet supprimé.'
            : 'Sujet supprimé — '.$emportees.' réponse(s) sont parties avec lui.');
    }

    // ————————————————————————————————————————————————— lectures

    private function peut(string $permission): bool
    {
        return Auth::guard('staff')->user()?->can($permission) ?? false;
    }

    /**
     * @return Collection<int, ForumCategory>
     */
    private function categories(): Collection
    {
        return ForumCategory::query()->orderBy('position')->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, Thread>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Thread::query()
            ->with(['author:id,uuid,name,email', 'category:id,uuid,name,slug'])
            ->withCount('replies')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('body', 'like', $terme));
            })
            ->when($this->filtreCategorie !== '', function (Builder $requete): void {
                $requete->whereHas('category', fn (Builder $q) => $q->where('uuid', $this->filtreCategorie));
            })
            ->when($this->filtreEtat === 'verrouilles', fn (Builder $q) => $q->where('is_locked', true))
            ->when($this->filtreEtat === 'epingles', fn (Builder $q) => $q->where('is_pinned', true))
            ->when($this->filtreEtat === 'escalades', fn (Builder $q) => $q
                ->whereNotNull('escalated_at')->whereNull('official_verdict'))
            ->when($this->filtreEtat === 'sans-verdict', fn (Builder $q) => $q->whereNull('official_verdict'))
            /*
             | Les épinglés d'abord, puis la dernière activité. Un modérateur
             | cherche ce qui BOUGE, pas ce qui a été créé : un sujet ancien qui
             | reçoit vingt réponses en une heure est ce qu'il doit voir en
             | premier.
             */
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->paginate(20);
    }

    /**
     * @return array{sujets: int, verrouilles: int, escalades: int, tranches: int}
     */
    private function indicateurs(): array
    {
        return [
            'sujets' => Thread::query()->count(),
            'verrouilles' => Thread::query()->where('is_locked', true)->count(),
            /*
             | Escaladés SANS verdict : c'est la file d'attente réelle du
             | fact-checking côté forum. Compter tous les escaladés y mêlerait
             | ceux qui ont déjà reçu leur réponse.
             */
            'escalades' => Thread::query()
                ->whereNotNull('escalated_at')
                ->whereNull('official_verdict')
                ->count(),
            'tranches' => Thread::query()->whereNotNull('official_verdict')->count(),
        ];
    }

    private function sujet(string $uuid): Thread
    {
        return Thread::query()->with(['author', 'category'])->where('uuid', $uuid)->firstOrFail();
    }
}
