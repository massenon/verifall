<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\MessageBloque;
use App\Core\Community\Services\Sondages;
use App\Enums\UserRole;
use App\Models\ContentLike;
use App\Models\ForumCategory;
use App\Models\HubArticle;
use App\Models\Thread;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Forum citoyen — transposition de compte-user/forum.html.
 *
 * On y signale des rumeurs. L'avis de la communauté s'affiche en barres, et le
 * verdict de Vérif'All — quand il existe — le surplombe sans s'y mêler : une
 * majorité qui se trompe reste une majorité qui se trompe.
 */
#[Layout('components.layouts.citizen', ['title' => "Forum · Vérif'All"])]
final class Forum extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    /** Composer ouvert ? Le prototype le déplie au clic. */
    public bool $composerOuvert = false;

    public string $titre = '';

    public string $corps = '';

    public string $categorieChoisie = '';

    /** Signalement de fake news : fait apparaître le champ « source ». */
    public bool $signalementFakeNews = false;

    public string $lienSignale = '';

    /*
    |--------------------------------------------------------------------------
    | Le sondage, posé DÈS la création du sujet
    |--------------------------------------------------------------------------
    | Il pouvait déjà s'ajouter depuis la page d'un sujet, une fois celui-ci
    | publié. En pratique, presque personne n'y revenait : la question qu'on
    | veut poser est présente à l'esprit au moment où l'on écrit, pas dix
    | minutes plus tard sur un autre écran.
    */
    public bool $avecSondage = false;

    public string $questionSondage = '';

    /**
     * Les réponses proposées.
     *
     * Deux cases visibles d'emblée, parce qu'un sondage en exige deux au
     * minimum : les afficher vides évite d'avoir à cliquer « ajouter » avant de
     * pouvoir commencer.
     *
     * @var list<string>
     */
    public array $optionsSondage = ['', ''];

    public function render(): View
    {
        return view('livewire.citizen.forum', [
            'sujets' => $this->sujets(),
            'categories' => $this->categories(),
            'statistiques' => $this->statistiques(),
            'tendances' => $this->tendances(),
            'contributeurs' => $this->contributeurs(),
            'dernieresVerifications' => $this->dernieresVerifications(),
        ]);
    }

    /**
     * Les trois chiffres de l'en-tête.
     *
     * Le prototype les écrit en dur (« 1 284 discussions · 8 421 membres »).
     * Comptés ici — un forum qui annonce huit mille membres le jour de son
     * ouverture ne trompe personne longtemps, et discrédite le reste.
     *
     * @return array{discussions: int, membres: int, verifications: int}
     */
    private function statistiques(): array
    {
        return [
            'discussions' => Thread::count(),
            'membres' => User::role(UserRole::Citoyen->value)->count(),
            'verifications' => Thread::whereNotNull('official_verdict')->count(),
        ];
    }

    /**
     * « Rumeurs en tendance » — les sujets les plus discutés.
     *
     * @return Collection<int, Thread>
     */
    private function tendances(): Collection
    {
        /*
         | On trie sur la COLONNE dénormalisée, sans `withCount`.
         |
         | `withCount('replies')` ajoute au SELECT un alias nommé lui aussi
         | `replies_count` — et `threads` porte DÉJÀ une colonne de ce nom
         | (`create_community`, ligne 78). La clause ORDER BY devenait donc
         | ambiguë : MySQL 8 lève `ER_NON_UNIQ_ERROR` et la page du forum
         | rendait 500 en production.
         |
         | SQLite, qui pilote la suite de tests, accepte l'ambiguïté. Le défaut
         | ne pouvait donc apparaître qu'en production — la pire des places.
         */
        return Thread::query()
            ->orderByDesc('replies_count')
            ->orderByDesc('last_activity_at')
            ->limit(3)
            ->get();
    }

    /**
     * « Contributeurs » — classés par expérience réellement acquise.
     *
     * @return Collection<int, User>
     */
    private function contributeurs(): Collection
    {
        return User::role(UserRole::Citoyen->value)
            ->where('xp', '>', 0)
            ->orderByDesc('xp')
            ->limit(3)
            ->get(['id', 'name', 'xp']);
    }

    /**
     * « Dernières vérifications » — les VERDICTS du laboratoire.
     *
     * Distincts des avis de la communauté, et c'est tout l'objet de ce bloc :
     * le forum discute, le laboratoire tranche.
     *
     * @return Collection<int, HubArticle>
     */
    private function dernieresVerifications(): Collection
    {
        return HubArticle::where('is_published', true)
            ->whereNotNull('verdict')
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    public function updated(string $propriete): void
    {
        if ($propriete === 'categorie') {
            $this->resetPage();
        }
    }

    public function choisirCategorie(string $slug): void
    {
        $this->categorie = $this->categorie === $slug ? '' : $slug;
        $this->resetPage();
    }

    public function ajouterUneOption(): void
    {
        if (count($this->optionsSondage) >= Sondages::OPTIONS_MAXIMUM) {
            return;
        }

        $this->optionsSondage[] = '';
    }

    /**
     * Retire une réponse, sans jamais descendre sous le minimum.
     *
     * Laisser retirer la dernière case produirait un formulaire sans réponse
     * possible, et un bouton « ajouter » comme seule issue.
     */
    public function retirerUneOption(int $index): void
    {
        if (count($this->optionsSondage) <= Sondages::OPTIONS_MINIMUM) {
            return;
        }

        unset($this->optionsSondage[$index]);

        // Réindexé : Livewire sérialise un tableau à trous en objet, et les
        // champs cessent alors de se lier correctement.
        $this->optionsSondage = array_values($this->optionsSondage);
    }

    public function basculerComposer(): void
    {
        $this->composerOuvert = ! $this->composerOuvert;
        $this->resetErrorBag();
    }

    public function publier(AnimationDuForum $forum, Sondages $sondages): void
    {
        $this->validate([
            'titre' => ['required', 'string', 'min:10', 'max:180'],
            'corps' => ['required', 'string', 'min:20', 'max:5000'],
            'categorieChoisie' => ['nullable', 'string'],
            // Une « source signalée » qui n'est pas une URL n'est pas une
            // source : elle afficherait un lien mort comme une preuve.
            'lienSignale' => [$this->signalementFakeNews ? 'required' : 'nullable', 'url:http,https', 'max:500'],
            'questionSondage' => [$this->avecSondage ? 'required' : 'nullable', 'string', 'min:5', 'max:300'],
            'optionsSondage' => [$this->avecSondage ? 'required' : 'nullable', 'array'],
            'optionsSondage.*' => ['nullable', 'string', 'max:120'],
        ], [
            'titre.min' => 'Donnez un titre explicite : au moins 10 caractères.',
            'corps.min' => 'Décrivez un peu : au moins 20 caractères.',
            'lienSignale.required' => 'Un signalement sans lien ne peut pas être vérifié.',
            'lienSignale.url' => 'Ce lien ne semble pas valide.',
            'questionSondage.required' => 'Posez la question de votre sondage.',
            'questionSondage.min' => 'Posez une question complète.',
        ]);

        $categorie = $this->categorieChoisie !== ''
            ? ForumCategory::where('slug', $this->categorieChoisie)->first()
            : null;

        /*
         | UN BLOCAGE N'EST PAS UNE PANNE.
         |
         | Le filtre de modération lève quand un mot interdit est présent. Sans
         | ce `try`, l'exception remontait jusqu'à l'écran : la personne voyait
         | une page d'erreur, perdait son texte, et n'apprenait rien de ce qui
         | s'était passé.
         |
         | Le message reste GÉNÉRIQUE, et le brouillon est conservé : on la
         | remet devant son texte pour qu'elle le reformule.
         */
        try {
            $sujet = $forum->publier(
                $this->utilisateur(),
                $this->titre,
                $this->corps,
                $categorie,
                $this->signalementFakeNews ? $this->lienSignale : null,
            );
        } catch (MessageBloque $blocage) {
            $this->addError('corps', $blocage->getMessage());

            return;
        }

        /*
         | LE SONDAGE VIENT APRÈS LE SUJET, et il ne peut pas en être autrement :
         | un sondage appartient à un fil, donc le fil doit exister.
         |
         | Si sa création échoue — deux réponses identiques, question trop
         | courte — le SUJET RESTE PUBLIÉ. Annuler la publication parce que le
         | sondage est mal formé ferait perdre un texte déjà écrit pour un
         | accessoire ; on le dit, et la personne ajoute son sondage depuis la
         | page du sujet.
         */
        if ($this->avecSondage) {
            try {
                $sondages->creer($this->utilisateur(), $sujet, $this->questionSondage, $this->optionsSondage);
            } catch (ValidationException $erreur) {
                $this->reinitialiserLeComposer();
                $this->resetPage();

                $this->toastInfo(
                    'Votre sujet est publié, mais le sondage n’a pas pu être créé : '
                    .implode(' ', array_merge(...array_values($erreur->errors())))
                );

                return;
            }
        }

        $this->reinitialiserLeComposer();
        $this->resetPage();

        $this->toastSuccess('Votre sujet est publié.');
    }

    /**
     * Remet le composeur à blanc, sondage compris.
     *
     * Les deux cases vides sont RÉTABLIES : sans cela, la personne qui
     * enchaîne un second sujet retrouverait ses réponses précédentes.
     */
    private function reinitialiserLeComposer(): void
    {
        $this->reset([
            'titre', 'corps', 'lienSignale', 'signalementFakeNews', 'composerOuvert',
            'avecSondage', 'questionSondage',
        ]);

        $this->optionsSondage = ['', ''];
    }

    public function aimer(string $slug, AnimationDuForum $forum): void
    {
        $sujet = Thread::where('slug', $slug)->firstOrFail();

        $forum->aimer($this->utilisateur(), $sujet);

        unset($this->mesJaime);
    }

    /**
     * Les sujets que la personne a déjà aimés.
     *
     * Chargés EN UNE FOIS : interroger depuis chaque carte ferait une requête
     * par sujet affiché.
     *
     * @return list<int>
     */
    #[Computed]
    public function mesJaime(): array
    {
        return ContentLike::where('user_id', $this->utilisateur()->id)
            ->where('likeable_type', (new Thread)->getMorphClass())
            ->pluck('likeable_id')
            ->all();
    }

    public function aAime(Thread $sujet): bool
    {
        return in_array($sujet->id, $this->mesJaime, true);
    }

    /**
     * @return LengthAwarePaginator<int, Thread>
     */
    private function sujets(): LengthAwarePaginator
    {
        return Thread::query()
            ->with(['author:id,name', 'category:id,name,slug'])
            ->when($this->categorie !== '', fn (Builder $q) => $q->whereHas(
                'category', fn (Builder $c) => $c->where('slug', $this->categorie)
            ))
            // Épinglés d'abord, puis l'activité la plus récente : un fil vivant
            // se trouve en haut, c'est là qu'on le cherche.
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->paginate(10);
    }

    /**
     * @return Collection<int, ForumCategory>
     */
    private function categories(): Collection
    {
        return ForumCategory::where('is_active', true)->orderBy('position')->orderBy('name')->get();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
