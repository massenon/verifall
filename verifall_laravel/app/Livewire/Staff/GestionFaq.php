<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Faq;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Gestion FAQ » — l'écran que le menu promettait sans le livrer.
 *
 * ## Le défaut, signalé et retrouvé tel quel
 *
 * L'entrée « Gestion FAQ » du menu d'administration pointait sur
 * `route('public.faq')` : on cliquait pour gérer la FAQ et on atterrissait sur
 * la page publique, en lecture, sans rien pouvoir modifier.
 *
 * C'est la même famille que les deux défauts du lot 2 (`Live Classes` et
 * `Répertoire des outils` menaient à l'espace citoyen) — et comme eux, le
 * correctif n'est pas un `href` : l'écran n'existait pas, et les dix-sept
 * questions vivaient dans `lang/{fr,en}/faq.php`, déroulées à la main dans le
 * gabarit.
 *
 * ## Les deux langues sont tenues ENSEMBLE
 *
 * Le site expose un sélecteur FR/EN. Une question existe donc en deux lignes,
 * reliées par `group_key`. Le formulaire édite les deux d'un coup : les
 * séparer garantirait qu'on corrige la française et qu'on oublie l'anglaise —
 * et une FAQ à moitié corrigée est pire qu'une FAQ pas corrigée, parce qu'on la
 * croit à jour.
 *
 * La traduction anglaise peut rester vide : la page anglaise se replie alors
 * sur le français. Une FAQ incomplète vaut mieux qu'une FAQ trouée.
 */
#[Layout('components.layouts.staff', ['title' => "Gestion de la FAQ · Vérif'All"])]
final class GestionFaq extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'rubrique', except: '')]
    public string $filtreCategorie = '';

    /** La clé de groupe en cours d'édition — les deux langues à la fois. */
    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $categorie = '';

    public string $questionFr = '';

    public string $reponseFr = '';

    public string $questionEn = '';

    public string $reponseEn = '';

    public string $ordre = '0';

    public bool $publiee = true;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCategorie(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCategorie');
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('faq.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.gestion-faq', [
            'questions' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'categories' => $this->categoriesExistantes(),
            'peutGerer' => $this->peutGerer(),
            /*
             | Les clés de groupe qui ONT une version anglaise. En une requête
             | plutôt qu'une par ligne : la colonne « traduite » est ce qui dit
             | au rédacteur ce qui reste à faire, elle ne doit pas coûter vingt
             | requêtes pour le dire.
             */
            'traduites' => Faq::query()->where('locale', 'en')->pluck('group_key')->all(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'categorie', 'questionFr', 'reponseFr', 'questionEn', 'reponseEn');
        $this->ordre = (string) ((int) Faq::query()->max('sort_order') + 10);
        $this->publiee = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $cleDeGroupe): void
    {
        abort_unless($this->peutGerer(), 403);

        $lignes = Faq::query()->where('group_key', $cleDeGroupe)->get()->keyBy('locale');
        $fr = $lignes->get('fr');
        $en = $lignes->get('en');

        abort_if($fr === null && $en === null, 404);

        $reference = $fr ?? $en;

        $this->enEdition = $cleDeGroupe;
        $this->categorie = (string) $reference->category;
        $this->questionFr = (string) ($fr?->question ?? '');
        $this->reponseFr = (string) ($fr?->answer ?? '');
        $this->questionEn = (string) ($en?->question ?? '');
        $this->reponseEn = (string) ($en?->answer ?? '');
        $this->ordre = (string) $reference->sort_order;
        $this->publiee = (bool) $reference->is_published;

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

        $cle = $this->enEdition ?? Str::slug(Str::limit($donnees['questionFr'], 60, '')).'-'.Str::lower(Str::random(4));

        DB::transaction(function () use ($cle, $donnees): void {
            $this->ecrireLaLangue('fr', $cle, $donnees['questionFr'], $donnees['reponseFr'], $donnees);

            /*
             | L'anglais n'est écrit que s'il est rempli, et SUPPRIMÉ s'il est
             | vidé. Garder une traduction devenue vide laisserait une question
             | anglaise sans réponse — pire qu'un repli sur le français, qui au
             | moins dit quelque chose.
             */
            if (trim($donnees['questionEn']) !== '' && trim($donnees['reponseEn']) !== '') {
                $this->ecrireLaLangue('en', $cle, $donnees['questionEn'], $donnees['reponseEn'], $donnees);
            } else {
                Faq::query()->where('group_key', $cle)->where('locale', 'en')->delete();
            }
        });

        $audit->record(
            action: $this->enEdition === null ? 'faq.create' : 'faq.update',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: Faq::query()->where('group_key', $cle)->where('locale', 'fr')->first(),
            apres: ['group_key' => $cle, 'question' => $donnees['questionFr']],
            cibleLibelle: Str::limit($donnees['questionFr'], 60),
        );

        $nouvelle = $this->enEdition === null;

        $this->fermerLeTiroir();
        $this->toastSuccess($nouvelle ? 'Question ajoutée.' : 'Question mise à jour dans les deux langues.');
    }

    public function basculerLaPublication(string $cleDeGroupe, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $lignes = Faq::query()->where('group_key', $cleDeGroupe)->get();

        abort_if($lignes->isEmpty(), 404);

        $publiee = ! (bool) $lignes->first()->is_published;

        // Les deux langues basculent ENSEMBLE : publier la française et laisser
        // l'anglaise masquée ferait disparaître la question d'une version du
        // site sans raison lisible.
        Faq::query()->where('group_key', $cleDeGroupe)->update(['is_published' => $publiee]);

        $audit->record(
            action: $publiee ? 'faq.publish' : 'faq.unpublish',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $lignes->first(),
            avant: ['is_published' => ! $publiee],
            apres: ['is_published' => $publiee],
            cibleLibelle: Str::limit((string) $lignes->first()->question, 60),
        );

        $this->toastSuccess($publiee ? 'Question publiée.' : 'Question masquée de la FAQ publique.');
    }

    public function demanderLaSuppression(string $cleDeGroupe): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->aSupprimer = $cleDeGroupe;
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

        $premiere = Faq::query()->where('group_key', $this->aSupprimer)->first();

        if ($premiere === null) {
            $this->aSupprimer = null;

            return;
        }

        $audit->record(
            action: 'faq.delete',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $premiere,
            avant: ['group_key' => $this->aSupprimer, 'question' => $premiere->question],
            cibleLibelle: Str::limit((string) $premiere->question, 60),
        );

        // Toutes les langues partent : une traduction orpheline serait une
        // question anglaise dont la version française n'existe plus.
        Faq::query()->where('group_key', $this->aSupprimer)->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Question supprimée, dans toutes les langues.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'categorie' => ['nullable', 'string', 'max:60'],
            'questionFr' => ['required', 'string', 'min:5', 'max:500'],
            'reponseFr' => ['required', 'string', 'min:10', 'max:5000'],
            /*
             | L'anglais est facultatif — mais s'il y a une question, il faut
             | une réponse. Une question anglaise sans réponse serait affichée
             | vide, alors que l'absence des deux fait proprement replier la
             | page sur le français.
             */
            'questionEn' => ['nullable', 'string', 'max:500', 'required_with:reponseEn'],
            'reponseEn' => ['nullable', 'string', 'max:5000', 'required_with:questionEn'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
            'publiee' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'categorie' => 'rubrique',
            'questionFr' => 'question (français)',
            'reponseFr' => 'réponse (français)',
            'questionEn' => 'question (anglais)',
            'reponseEn' => 'réponse (anglais)',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function ecrireLaLangue(string $langue, string $cle, string $question, string $reponse, array $donnees): void
    {
        $ligne = Faq::query()->where('group_key', $cle)->where('locale', $langue)->first() ?? new Faq;

        $ligne->forceFill([
            'uuid' => $ligne->uuid ?? (string) Str::uuid(),
            'locale' => $langue,
            'group_key' => $cle,
            'category' => $donnees['categorie'] !== '' ? $donnees['categorie'] : null,
            'question' => trim($question),
            'answer' => trim($reponse),
            'sort_order' => (int) $donnees['ordre'],
            'is_published' => $donnees['publiee'],
        ])->save();
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('faq.manage') ?? false;
    }

    /**
     * @return list<string>
     */
    private function categoriesExistantes(): array
    {
        return Faq::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    /**
     * La liste est bornée au FRANÇAIS : une question par ligne, pas deux.
     *
     * Lister les deux langues doublerait chaque question à l'écran, et le
     * geste « modifier » ouvrirait de toute façon les deux. La colonne
     * « traduite » dit ce qui manque.
     *
     * @return LengthAwarePaginator<int, Faq>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Faq::query()
            ->where('locale', 'fr')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('question', 'like', $terme)
                    ->orWhere('answer', 'like', $terme));
            })
            ->when($this->filtreCategorie !== '',
                fn (Builder $q) => $q->where('category', $this->filtreCategorie))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20);
    }

    /**
     * @return array{total: int, publiees: int, traduites: int, sans_rubrique: int}
     */
    private function indicateurs(): array
    {
        $francaises = Faq::query()->where('locale', 'fr');

        return [
            'total' => (clone $francaises)->count(),
            'publiees' => (clone $francaises)->where('is_published', true)->count(),
            'traduites' => Faq::query()->where('locale', 'en')->count(),
            'sans_rubrique' => (clone $francaises)->whereNull('category')->count(),
        ];
    }
}
