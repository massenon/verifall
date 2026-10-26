<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Reglages\CatalogueDesReglages;
use App\Core\Application\Reglages\ReglageDefini;
use App\Core\Application\Reglages\Reglages;
use App\Core\Identity\Services\AuditLogService;
use App\Core\Marketing\Seo\DonneesGoogle;
use App\Models\Setting;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * « Paramètres » — dix onglets, une soixantaine de réglages.
 *
 * ## Ce que cet écran remplace
 *
 * Rien. Il n'existait pas. Changer le bonus de bienvenue, l'adresse
 * d'expédition des courriels ou une clé d'API demandait un développeur et un
 * déploiement — pour des décisions qui appartiennent au porteur du projet.
 *
 * ## Les trois règles qui gouvernent le traitement des secrets
 *
 * Elles ne sont pas dans cette classe mais dans le service `Reglages` ; il faut
 * les connaître pour lire ce qui suit.
 *
 *   1. **Chiffré au repos.** Un mot de passe SMTP en clair, c'est un mot de
 *      passe SMTP dans chaque sauvegarde de la base.
 *   2. **Jamais renvoyé au navigateur.** Le champ arrive VIDE, toujours. Un
 *      formulaire qui le pré-remplit l'expédie au client à chaque affichage :
 *      le champ est masqué à l'écran, la valeur est dans la source.
 *   3. **Vide veut dire « je n'y touche pas ».** Conséquence de la deuxième :
 *      sans cette règle, ouvrir puis enregistrer l'écran effacerait toutes les
 *      clés.
 *
 * ## Pourquoi les champs ne sont pas déclarés un par un
 *
 * Ils viennent de `CatalogueDesReglages`. Soixante propriétés publiques
 * demanderaient soixante règles de validation et soixante blocs de gabarit —
 * quatre endroits à tenir à jour par réglage, dont le quatrième s'oublie
 * toujours. Ici, ajouter un réglage, c'est ajouter une ligne au catalogue.
 */
#[Layout('components.layouts.staff', ['title' => "Paramètres · Vérif'All"])]
final class Parametres extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    #[Url(as: 'onglet', except: 'general')]
    public string $onglet = CatalogueDesReglages::GROUPE_GENERAL;

    /**
     * Les valeurs du formulaire, indexées par clé de réglage.
     *
     * Un tableau et non soixante propriétés : voir l'en-tête de classe.
     *
     * @var array<string, string|bool|null>
     */
    public array $valeurs = [];

    /**
     * Les fichiers en attente de dépôt, indexés par clé.
     *
     * @var array<string, mixed>
     */
    public array $fichiers = [];

    /** Les clés secrètes qui ont DÉJÀ une valeur — pour l'afficher à l'écran. */
    #[Locked]
    public array $secretsRenseignes = [];

    public function mount(Reglages $reglages): void
    {
        $this->authorize('viewAny', Setting::class);
        $this->chargerLesValeurs($reglages);
    }

    public function render(): View
    {
        return view('livewire.staff.parametres', [
            'groupes' => CatalogueDesReglages::groupes(),
            'reglages' => CatalogueDesReglages::duGroupe($this->onglet),
        ]);
    }

    /** @var array<string, array{ok: bool, message: string}> */
    public array $essaiGoogle = [];

    /** Essaie les accès ENREGISTRÉS (pas ceux en cours de saisie) : on enregistre d'abord, puis on teste. */
    public function testerGoogle(DonneesGoogle $google): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('system.settings') ?? false, 403);

        $this->essaiGoogle = $google->tester();
    }

    public function changerDOnglet(string $groupe): void
    {
        if (! array_key_exists($groupe, CatalogueDesReglages::groupes())) {
            return;
        }

        $this->onglet = $groupe;
        $this->essaiGoogle = [];
        $this->resetValidation();
    }

    public function enregistrer(Reglages $reglages, AuditLogService $audit): void
    {
        $this->authorize('update', Setting::class);

        $definitions = CatalogueDesReglages::duGroupe($this->onglet);

        $this->validate(
            $this->reglesDeLOnglet($definitions),
            [],
            $this->libellesDeLOnglet($definitions),
        );

        $membre = Auth::guard('staff')->user();
        $modifies = [];

        foreach ($definitions as $definition) {
            $valeur = $definition->type->estUnFichier()
                ? $this->deposerLeFichier($definition)
                : $this->valeurSaisie($definition);

            // Un fichier non redéposé rend `null` : on ne touche pas à l'ancien.
            if ($definition->type->estUnFichier() && $valeur === null) {
                continue;
            }

            if ($reglages->set($definition->cle, $valeur, $membre)) {
                $modifies[] = $definition->cle;
            }
        }

        /*
         | Le journal enregistre les CLÉS modifiées, jamais leurs valeurs.
         |
         | Consigner l'avant et l'après reviendrait à recopier dans le journal
         | d'audit exactement les secrets qu'on chiffre par ailleurs — et le
         | journal, lui, se consulte depuis un écran.
         */
        if ($modifies !== []) {
            $audit->record(
                action: 'settings.update',
                module: 'parametres',
                acteur: $membre,
                apres: ['groupe' => $this->onglet, 'cles' => $modifies],
                cibleLibelle: CatalogueDesReglages::groupes()[$this->onglet]['libelle'],
            );
        }

        $this->chargerLesValeurs($reglages);
        $this->fichiers = [];

        $this->toastSuccess($modifies === []
            ? 'Aucune modification à enregistrer.'
            : count($modifies).' réglage(s) enregistré(s).');
    }

    /**
     * Les valeurs saisies, en respectant la règle du secret.
     */
    private function valeurSaisie(ReglageDefini $definition): ?string
    {
        $brute = $this->valeurs[$definition->cle] ?? null;

        if ($definition->type->controle() === 'checkbox') {
            return $brute ? '1' : '0';
        }

        return $brute === null ? null : (string) $brute;
    }

    /**
     * Dépose un fichier et rend son chemin public — ou `null` si rien de neuf.
     *
     * ## Le nom du fichier n'est JAMAIS celui fourni
     *
     * Un nom d'origine peut contenir des séparateurs de chemin, une double
     * extension (`logo.php.png`), ou simplement écraser un fichier existant en
     * portant le même nom. On engendre donc un nom aléatoire, et on ne garde de
     * l'original que l'extension — elle-même contrainte par `mimes`, qui
     * inspecte le CONTENU et non l'étiquette.
     */
    private function deposerLeFichier(ReglageDefini $definition): ?string
    {
        $fichier = $this->fichiers[$definition->cle] ?? null;

        if ($fichier === null) {
            return null;
        }

        $extension = mb_strtolower($fichier->getClientOriginalExtension());
        $nom = Str::uuid()->toString().'.'.$extension;

        /*
         | Disque `public` et non `private` : ces fichiers SONT publics par
         | nature — un logo, un favicon, une image de partage. Les servir par
         | route signée ajouterait une latence et casserait la mise en cache
         | par le navigateur, pour protéger ce qu'on affiche de toute façon.
         |
         | Le fond et la signature de certificat font exception et mériteront
         | le disque privé le jour où le PDF sera engendré côté serveur.
         */
        $chemin = $fichier->storeAs('reglages', $nom, 'public');

        return 'storage/'.$chemin;
    }

    /**
     * @param  list<ReglageDefini>  $definitions
     * @return array<string, array<int, string>>
     */
    private function reglesDeLOnglet(array $definitions): array
    {
        $regles = [];

        foreach ($definitions as $definition) {
            $champ = $definition->type->estUnFichier()
                ? 'fichiers.'.$definition->cle
                : 'valeurs.'.$definition->cle;

            $regles[$champ] = $definition->regles();
        }

        return $regles;
    }

    /**
     * @param  list<ReglageDefini>  $definitions
     * @return array<string, string>
     */
    private function libellesDeLOnglet(array $definitions): array
    {
        $libelles = [];

        foreach ($definitions as $definition) {
            $champ = $definition->type->estUnFichier()
                ? 'fichiers.'.$definition->cle
                : 'valeurs.'.$definition->cle;

            $libelles[$champ] = mb_strtolower($definition->libelle);
        }

        return $libelles;
    }

    private function chargerLesValeurs(Reglages $reglages): void
    {
        $this->valeurs = [];
        $this->secretsRenseignes = [];

        foreach (CatalogueDesReglages::tous() as $definition) {
            if ($definition->type->estUnFichier()) {
                // Le chemin actuel sert à l'aperçu, pas à la saisie.
                $this->valeurs[$definition->cle] = $reglages->get($definition->cle);

                continue;
            }

            if ($definition->secret) {
                /*
                 | VIDE, toujours. C'est ici que se joue la deuxième règle : la
                 | valeur ne descend jamais vers le navigateur. On note
                 | seulement qu'elle EXISTE, pour que l'écran puisse le dire.
                 */
                $this->valeurs[$definition->cle] = '';
                $this->secretsRenseignes[$definition->cle] = $reglages->secretRenseigne($definition->cle);

                continue;
            }

            $this->valeurs[$definition->cle] = $definition->type->controle() === 'checkbox'
                ? $reglages->actif($definition->cle)
                : $reglages->pourAffichage($definition->cle);
        }
    }
}
