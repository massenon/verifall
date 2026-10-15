<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Models\Course;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Répertoire des outils » — transposition de compte-user/outils.html.
 *
 * Un annuaire d'outils externes, groupé par catégorie, avec une recherche et
 * des pastilles de filtre. Rien n'y est calculé : la valeur de cette page tient
 * entièrement à l'exactitude de ses liens.
 *
 * La recherche est faite en base et non dans le navigateur — contrairement au
 * prototype, qui masquait des cartes déjà présentes dans la page. À quinze
 * fiches la différence est invisible ; à cent cinquante, envoyer tout
 * l'annuaire pour en montrer trois est un gaspillage sur une connexion mobile.
 */
#[Layout('components.layouts.citizen', ['title' => "Répertoire des outils · Vérif'All"])]
final class Outils extends Component
{
    /**
     * Les catégories, dans l'ordre du prototype.
     *
     * LA CLÉ EST LA VALEUR STOCKÉE EN BASE, et elle ne se traduit sous aucun
     * prétexte : c'est elle qui filtre. La valeur, elle, n'est plus le libellé
     * mais le SUFFIXE DE SA CLÉ DE LANGUE — le libellé s'obtient par
     * `__('cit_outils.categorie.<suffixe>')`.
     *
     * Le détour paraît indirect ; il évite le piège qui guettait ici. Libellé
     * et valeur de filtre étaient la même chaîne, si bien que traduire
     * l'affichage aurait traduit le filtre, et cassé la recherche en base sans
     * qu'aucune erreur ne le signale.
     *
     * Les libellés ne recopient pas toujours la clé, faute de place dans la
     * pastille : « Médias & métadonnées » s'affiche « Métadonnées ».
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'Recherche' => 'recherche',
        'Web & domaines' => 'web_domaines',
        'Archives' => 'archives',
        'Médias & métadonnées' => 'medias',
        'Intelligence artificielle' => 'ia',
        'Géolocalisation' => 'geolocalisation',
        'Fact-checking' => 'fact_checking',
    ];

    /** Icône d'en-tête de chaque groupe — celle de son premier outil. */
    private const ICONES = [
        'Recherche' => 'image_search',
        'Web & domaines' => 'language',
        'Archives' => 'history',
        'Médias & métadonnées' => 'photo_camera',
        'Intelligence artificielle' => 'smart_toy',
        'Géolocalisation' => 'public',
        'Fact-checking' => 'fact_check',
    ];

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    public function render(): View
    {
        $outils = $this->outils();

        return view('livewire.citizen.outils', [
            'recommandes' => $this->recommandes(),
            'parCategorie' => $outils->groupBy('category'),
            'nombreTotal' => Tool::publiees()->count(),
            'nombreAffiche' => $outils->count(),
            'icones' => self::ICONES,
            'coursOsint' => $this->coursOsint(),
        ]);
    }

    public function filtrer(string $categorie): void
    {
        // Recliquer sur la pastille active la relâche : c'est ce que fait le
        // prototype, et c'est le geste attendu quand on veut « tout » revoir.
        $this->categorie = $this->categorie === $categorie ? '' : $categorie;
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'categorie']);
    }

    /**
     * Les outils correspondant à la recherche et au filtre.
     *
     * @return Collection<int, Tool>
     */
    private function outils(): Collection
    {
        $terme = mb_strtolower(trim($this->recherche));

        return Tool::publiees()
            ->when($this->categorie !== '', fn ($q) => $q->where('category', $this->categorie))
            ->when($terme !== '', function ($q) use ($terme): void {
                // La recherche porte aussi sur les mots-clés : « domaine » doit
                // trouver Whois, dont le titre ne contient pas le mot.
                /*
                 | Sans `whereRaw`, et pour deux raisons.
                 |
                 | La version précédente écrivait `LOWER('.$colonne.') LIKE ?`.
                 | Elle était SÛRE — le terme était lié, seule la colonne venait
                 | d'une liste figée — mais elle coûtait deux choses :
                 |
                 |  · `LOWER()` sur une colonne empêche MySQL d'employer son
                 |    index, et n'apporte rien : la collation `utf8mb4_unicode_ci`
                 |    compare déjà sans tenir compte de la casse, et SQLite fait
                 |    de même sur l'ASCII ;
                 |  · le motif « concaténer dans du SQL brut » reste sûr tant que
                 |    la liste est figée, et cesse de l'être le jour où quelqu'un
                 |    la rend dynamique. Ne pas l'écrire évite d'avoir à le
                 |    vérifier.
                 */
                $q->where(function ($sous) use ($terme): void {
                    foreach (['title', 'description', 'keywords'] as $colonne) {
                        $sous->orWhere($colonne, 'like', '%'.$terme.'%');
                    }
                });
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * La sélection mise en avant — masquée dès qu'on cherche ou qu'on filtre.
     *
     * Garder une sélection figée au-dessus de résultats filtrés donnerait
     * l'impression que le filtre n'a pas pris.
     *
     * @return Collection<int, Tool>
     */
    private function recommandes(): Collection
    {
        if ($this->recherche !== '' || $this->categorie !== '') {
            return new Collection;
        }

        return Tool::publiees()->where('is_recommended', true)->orderBy('sort_order')->get();
    }

    /**
     * Le cours vers lequel renvoie le bandeau de bas de page.
     *
     * Le prototype pointe « OSINT Fondamentaux » en dur. On le retrouve par son
     * titre : s'il n'est pas publié, le bandeau disparaît plutôt que d'offrir
     * un lien mort.
     */
    private function coursOsint(): ?Course
    {
        return Course::where('is_published', true)
            ->where('title', 'like', 'OSINT%')
            ->first();
    }
}
