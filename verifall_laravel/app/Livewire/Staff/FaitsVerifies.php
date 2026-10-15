<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Core\FactChecking\Services\GestionDesFaitsVerifies;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\VerifiedFact;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fact checks — saisie et publication de la base des faits tranchés.
 *
 * C'est le seul écran par lequel un humain alimente la table qui court-circuite
 * douze moteurs payants. Tant qu'il n'existait pas, la base ne se remplissait
 * qu'à la console, et chaque rumeur déjà démentie se repayait.
 *
 * Le composant n'écrit rien lui-même : il assemble une saisie et la remet à
 * `GestionDesFaitsVerifies`, qui purifie, calcule l'empreinte, complète les
 * mots-clés et journalise. Ce qui vit ici est de l'ordre de l'écran — l'état
 * du tiroir, le format « une source par ligne », les filtres.
 */
#[Layout('components.layouts.staff', ['title' => "Fact checks · Vérif'All"])]
final class FaitsVerifies extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    /** Limite haute de l'import : au-delà, ce n'est plus une saisie relue. */
    private const LIGNES_IMPORT_MAXIMUM = 500;

    /** Événement qui fait recharger la table DataTables après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'faits-verifies-modifies';

    // ------------------------------------------------------------- filtres

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** Valeur d'enum, jamais un libellé : le filtre doit survivre à une retraduction. */
    #[Url(as: 'verdict', except: '')]
    public string $filtreVerdict = '';

    /** '' | 'publie' | 'brouillon' — les trois états que le dépôt sait filtrer. */
    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    // -------------------------------------------------------------- tiroir

    public bool $tiroirOuvert = false;

    /** 'creation' | 'edition' | 'apercu' */
    public string $mode = 'creation';

    public ?string $uuidEnCours = null;

    public string $affirmation = '';

    public string $verdict = 'false';

    /**
     * Le verdict tel qu'il était à l'ouverture du tiroir.
     *
     * Retenu pour savoir, à l'enregistrement, si l'on publie un verdict ou si
     * l'on en CORRIGE un. Les deux gestes n'engagent pas la plateforme de la
     * même façon : le second doit s'expliquer publiquement.
     */
    public string $verdictInitial = '';

    /**
     * Pourquoi le verdict change. PUBLIÉE sur `/corrections`.
     *
     * Obligatoire dès que le verdict bouge — au niveau de cet écran comme au
     * niveau du service. Une correction sans motif dit qu'on a changé d'avis
     * sans dire ce qu'on a appris.
     */
    public string $raisonDeLaCorrection = '';

    /** Une source par ligne, « url » ou « url | nom ». */
    public string $sourcesTexte = '';

    public string $motsCles = '';

    /** HTML produit par Quill ; purifié par le service AVANT persistance. */
    public string $analyse = '';

    /**
     * Vrai dès que l'équipe a touché aux mots-clés.
     *
     * Sans ce drapeau, corriger une coquille dans l'affirmation écraserait des
     * mots-clés choisis à la main — donc les synonymes sous lesquels la rumeur
     * circule vraiment, que la machine ne devine pas.
     */
    public bool $motsClesModifies = false;

    // --------------------------------------------------------- suppression

    public ?string $uuidASupprimer = null;

    public string $libelleASupprimer = '';

    // -------------------------------------------------------------- import

    public bool $importOuvert = false;

    public mixed $fichierImport = null;

    public function render(DepotFaitsVerifies $depot, GestionDesFaitsVerifies $gestion): View
    {
        return view('livewire.staff.faits-verifies', [
            'indicateurs' => $depot->indicateurs(),
            'verdicts' => VerdictFactCheck::cases(),
            // Affichés sous le champ : voir ce que la plateforme retiendra est
            // la seule façon de corriger un rapprochement avant qu'il ne serve.
            'motsClesProposes' => trim($this->affirmation) === ''
                ? ''
                : $gestion->motsClesSuggeres($this->affirmation),
        ]);
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'filtreVerdict', 'filtreStatut']);
    }

    // -------------------------------------------------------------- tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', VerifiedFact::class);

        $this->reinitialiserLeFormulaire();
        $this->mode = 'creation';
        $this->tiroirOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $fait = $this->faitOuEchoue($uuid);
        $this->authorize('update', $fait);

        $this->remplirDepuis($fait);
        $this->mode = 'edition';
        $this->uuidEnCours = $fait->uuid;
        $this->tiroirOuvert = true;
    }

    public function ouvrirApercu(string $uuid): void
    {
        $fait = $this->faitOuEchoue($uuid);
        $this->authorize('view', $fait);

        $this->remplirDepuis($fait);
        $this->mode = 'apercu';
        $this->uuidEnCours = $fait->uuid;
        $this->tiroirOuvert = true;
    }

    /**
     * Duplication : le tiroir s'ouvre PRÉ-REMPLI, rien n'est encore écrit.
     *
     * Dupliquer en base sans relecture produirait un second fait au même
     * verdict sous un titre presque identique — exactement ce qu'une base de
     * référence ne doit pas contenir.
     */
    public function dupliquer(string $uuid): void
    {
        $this->authorize('create', VerifiedFact::class);

        $fait = $this->faitOuEchoue($uuid);

        $this->remplirDepuis($fait);
        $this->affirmation = $fait->title.' (copie)';
        $this->mode = 'creation';
        $this->uuidEnCours = null;
        $this->tiroirOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->tiroirOuvert = false;
        $this->resetValidation();
    }

    /**
     * Les mots-clés suivent l'affirmation tant que personne ne les a repris.
     */
    public function updatedAffirmation(): void
    {
        if (! $this->motsClesModifies) {
            $this->motsCles = trim($this->affirmation) === ''
                ? ''
                : app(GestionDesFaitsVerifies::class)->motsClesSuggeres($this->affirmation);
        }
    }

    public function updatedMotsCles(): void
    {
        $this->motsClesModifies = true;
    }

    // ------------------------------------------------------- enregistrement

    /** Enregistre sans engager la plateforme : un brouillon reste interne. */
    public function enregistrer(GestionDesFaitsVerifies $gestion): void
    {
        $this->ecrire($gestion, publier: false);
    }

    /**
     * Geste DISTINCT d'enregistrer.
     *
     * Publier, c'est décider que ce verdict sortira à la place d'une analyse,
     * sans qu'aucun moteur ne soit consulté. On exige donc ce qu'un lecteur est
     * en droit d'attendre : une analyse, et au moins une source.
     */
    public function publier(GestionDesFaitsVerifies $gestion): void
    {
        $this->ecrire($gestion, publier: true);
    }

    private function ecrire(GestionDesFaitsVerifies $gestion, bool $publier): void
    {
        $edition = $this->mode === 'edition' && $this->uuidEnCours !== null;
        $fait = $edition ? $this->faitOuEchoue((string) $this->uuidEnCours) : null;

        $fait !== null
            ? $this->authorize('update', $fait)
            : $this->authorize('create', VerifiedFact::class);

        if ($publier) {
            // Vérifié AVANT la validation de forme : inutile de faire corriger
            // trois champs à quelqu'un qui n'a de toute façon pas ce droit.
            $fait !== null
                ? $this->authorize('publish', $fait)
                : $this->authorize('publish', VerifiedFact::class);
        }

        $donnees = $this->validate($this->regles($publier), $this->messages());

        $sources = $this->sourcesDepuisLeTexte($this->sourcesTexte);

        if ($publier && $sources === []) {
            throw ValidationException::withMessages([
                'sourcesTexte' => 'Publier un verdict sans source, c’est demander qu’on vous croie sur parole. Ajoutez au moins un lien vérifiable.',
            ]);
        }

        $auteur = $this->acteur();
        $verdict = VerdictFactCheck::from((string) $donnees['verdict']);

        if ($fait !== null) {
            $fait = $gestion->mettreAJour(
                $auteur, $fait, (string) $donnees['affirmation'], $verdict,
                $this->analyse, $sources, $this->motsCles, $this->raisonDeLaCorrection,
            );

            if ($publier && ! $fait->is_published) {
                $gestion->basculerLaPublication($auteur, $fait);
            }
        } else {
            $gestion->creer(
                $auteur, (string) $donnees['affirmation'], $verdict,
                $this->analyse, $sources, $this->motsCles, $publier,
            );
        }

        $this->tiroirOuvert = false;
        $this->uuidEnCours = null;

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess($publier
            ? 'Fact check publié — il répondra désormais sans consulter les moteurs.'
            : 'Brouillon enregistré. Il ne sortira pas tant qu’il n’est pas publié.');
    }

    /**
     * Publication depuis la ligne, sans rouvrir le tiroir.
     */
    public function basculerLaPublication(string $uuid, GestionDesFaitsVerifies $gestion): void
    {
        $fait = $this->faitOuEchoue($uuid);
        $this->authorize('publish', $fait);

        if (! $fait->is_published && $fait->nombreDeSources() === 0) {
            $this->toastError('Ce fact check n’a aucune source vérifiable : il ne peut pas être publié en l’état.');

            return;
        }

        $fait = $gestion->basculerLaPublication($this->acteur(), $fait);

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess($fait->is_published
            ? 'Fact check publié.'
            : 'Fact check dépublié — il n’est plus servi aux citoyens.');
    }

    // --------------------------------------------------------- suppression

    public function confirmerSuppression(string $uuid): void
    {
        $fait = $this->faitOuEchoue($uuid);
        $this->authorize('delete', $fait);

        $this->uuidASupprimer = $fait->uuid;
        $this->libelleASupprimer = $fait->title;
    }

    public function annulerSuppression(): void
    {
        $this->reset(['uuidASupprimer', 'libelleASupprimer']);
    }

    public function supprimer(GestionDesFaitsVerifies $gestion): void
    {
        if ($this->uuidASupprimer === null) {
            return;
        }

        $fait = $this->faitOuEchoue($this->uuidASupprimer);
        $this->authorize('delete', $fait);

        $gestion->supprimer($this->acteur(), $fait);

        $this->reset(['uuidASupprimer', 'libelleASupprimer']);

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Fact check supprimé.');
    }

    // ------------------------------------------------------ import / export

    public function ouvrirImport(): void
    {
        $this->authorize('create', VerifiedFact::class);

        $this->reset(['fichierImport']);
        $this->resetValidation();
        $this->importOuvert = true;
    }

    public function fermerImport(): void
    {
        $this->importOuvert = false;
        $this->reset(['fichierImport']);
    }

    /**
     * Import CSV — en BROUILLONS, jamais publiés.
     *
     * Un fichier ne relit rien : ce qui entre par là doit repasser devant un
     * humain avant d'engager la plateforme. Chaque ligne passe par le même
     * service que la saisie manuelle, donc par la même purification, la même
     * empreinte et le même journal d'audit.
     */
    public function importer(GestionDesFaitsVerifies $gestion): void
    {
        $this->authorize('create', VerifiedFact::class);

        $this->validate(
            ['fichierImport' => 'required|file|mimes:csv,txt|max:2048'],
            ['fichierImport.required' => 'Choisissez un fichier CSV à importer.'],
        );

        $chemin = $this->fichierImport->getRealPath();
        $flux = is_string($chemin) ? fopen($chemin, 'rb') : false;

        if ($flux === false) {
            $this->toastError('Le fichier n’a pas pu être lu. Réessayez, ou saisissez le fact check à la main.');

            return;
        }

        $auteur = $this->acteur();
        $crees = 0;
        $ignorees = 0;
        $ligne = 0;

        while (($cellules = fgetcsv($flux, 0, ';')) !== false) {
            $ligne++;

            if ($ligne > self::LIGNES_IMPORT_MAXIMUM) {
                $ignorees++;

                continue;
            }

            // En-tête toléré : les tableurs l'écrivent par défaut.
            if ($ligne === 1 && mb_strtolower(trim((string) ($cellules[0] ?? ''))) === 'affirmation') {
                continue;
            }

            $affirmation = trim((string) ($cellules[0] ?? ''));
            $verdict = $this->verdictDepuisLeFichier((string) ($cellules[1] ?? ''));

            if (mb_strlen($affirmation) < 5 || $verdict === null) {
                $ignorees++;

                continue;
            }

            $gestion->creer(
                $auteur,
                $affirmation,
                $verdict,
                trim((string) ($cellules[3] ?? '')),
                $this->sourcesDepuisLeTexte((string) ($cellules[2] ?? '')),
                trim((string) ($cellules[4] ?? '')),
                publier: false,
            );

            $crees++;
        }

        fclose($flux);

        $this->importOuvert = false;
        $this->reset(['fichierImport']);

        if ($crees > 0) {
            $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        }

        // Un rapport chiffré, y compris de ce qui a échoué : un import qui dit
        // seulement « terminé » laisse croire que tout est passé.
        $ignorees > 0
            ? $this->toastWarning(sprintf(
                '%d brouillon(s) créé(s), %d ligne(s) ignorée(s) — affirmation trop courte ou verdict inconnu.',
                $crees, $ignorees,
            ))
            : $this->toastSuccess(sprintf('%d brouillon(s) créé(s). Relisez-les avant publication.', $crees));
    }

    /**
     * Export CSV du périmètre filtré, tel qu'il est affiché.
     */
    public function exporter(DepotFaitsVerifies $depot, AuditLogService $audit): StreamedResponse
    {
        $this->authorize('viewAny', VerifiedFact::class);

        /*
         | Arbitrage du 14 septembre 2026 : le modérateur rédige et consulte
         | la base, il ne l'exporte pas — brouillons compris. `viewAny` seule
         | ne suffit donc pas ici, contrairement au reste de l'écran.
         */
        if (! $this->acteur()->can('knowledge_base.export')) {
            abort(403);
        }

        $lignes = $depot->lister(
            recherche: trim($this->recherche),
            verdict: VerdictFactCheck::tryFrom($this->filtreVerdict),
            statut: $this->filtreStatut,
            parPage: 1000,
        );

        $audit->record(
            action: 'knowledge_base.export',
            module: 'faits-verifies',
            acteur: $this->acteur(),
            apres: [
                'lignes' => $lignes->total(),
                'recherche' => $this->recherche,
                'verdict' => $this->filtreVerdict,
                'statut' => $this->filtreStatut,
            ],
            cibleLibelle: 'Base des faits vérifiés',
        );

        return response()->streamDownload(function () use ($lignes): void {
            $sortie = fopen('php://output', 'wb');

            // BOM UTF-8 : sans lui, Excel affiche « Ã© » à la place des accents.
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Affirmation', 'Verdict', 'Sources', 'Analyse', 'Mots-clés', 'Statut'], ';');

            foreach ($lignes as $fait) {
                fputcsv($sortie, [
                    $fait->title,
                    $fait->verdict->value,
                    implode("\n", array_map(
                        static fn (array $source): string => $source['url'].' | '.$source['name'],
                        $fait->sourcesRetenues(),
                    )),
                    strip_tags($fait->summary),
                    $fait->keywords,
                    $fait->is_published ? 'Publié' : 'Brouillon',
                ], ';');
            }

            fclose($sortie);
        }, 'fact-checks-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ------------------------------------------------------------- rouages

    /**
     * @return array<string, string>
     */
    private function regles(bool $publier): array
    {
        return [
            'affirmation' => 'required|string|min:5|max:255',
            'verdict' => 'required|string|in:'.implode(',', array_column(VerdictFactCheck::cases(), 'value')),
            'analyse' => $publier ? 'required|string|min:20' : 'nullable|string',
            'motsCles' => 'nullable|string|max:255',
            'sourcesTexte' => 'nullable|string',
            /*
             | La raison n'est exigée que lorsqu'un verdict CHANGE. L'imposer
             | à chaque enregistrement transformerait une exigence de
             | transparence en formalité qu'on remplit par « mise à jour », et
             | le journal public perdrait tout son sens.
             |
             | Vingt caractères : c'est la longueur en dessous de laquelle on
             | n'écrit pas ce qu'on a appris, on écrit « erreur ».
             */
            'raisonDeLaCorrection' => $this->correctionEnCours()
                ? 'required|string|min:20|max:1000'
                : 'nullable|string|max:1000',
        ];
    }

    /**
     * L'enregistrement en cours corrige-t-il un verdict déjà rendu ?
     *
     * Publier un premier verdict et en corriger un ne sont pas le même acte :
     * seul le second doit s'expliquer publiquement.
     */
    public function correctionEnCours(): bool
    {
        return $this->mode === 'edition'
            && $this->verdictInitial !== ''
            && $this->verdictInitial !== $this->verdict;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'affirmation.required' => 'Écrivez l’affirmation telle qu’elle circule : c’est elle qui sera rapprochée des signalements.',
            'affirmation.min' => 'Cinq caractères au minimum — une affirmation trop courte ne se rapproche de rien.',
            'analyse.required' => 'Une publication sans analyse ne dit pas POURQUOI le verdict est celui-là.',
            'analyse.min' => 'L’analyse est trop courte pour justifier un verdict rendu au nom de la plateforme.',
            'raisonDeLaCorrection.required' => 'Vous changez un verdict déjà rendu : dites POURQUOI. '
                .'Cette phrase sera publiée au journal des corrections, à côté de l’ancien verdict.',
            'raisonDeLaCorrection.min' => 'Vingt caractères au minimum : « erreur » n’explique rien à qui avait partagé l’ancien verdict.',
        ];
    }

    /**
     * Une source par ligne, « url » ou « url | nom ».
     *
     * Le format vit ici et non dans le service : c'est une commodité de saisie
     * propre à cet écran. Le service, lui, ne connaît que des couples.
     *
     * @return list<array{url: string, name: string}>
     */
    private function sourcesDepuisLeTexte(string $texte): array
    {
        $sources = [];

        foreach (preg_split('/\R/', $texte) ?: [] as $ligne) {
            $ligne = trim($ligne);

            if ($ligne === '') {
                continue;
            }

            [$url, $nom] = array_pad(array_map('trim', explode('|', $ligne, 2)), 2, '');

            if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $sources[] = ['url' => $url, 'name' => $nom];
        }

        return $sources;
    }

    /**
     * Accepte la valeur d'enum comme le libellé du tableur, DANS LES DEUX
     * LANGUES.
     *
     * Pas `libelle()` : il suit la langue de l'écran, alors que le fichier
     * importé, lui, ne change pas. L'import cessait donc d'accepter
     * « trompeur » dès qu'un membre du personnel avait consulté le site public
     * en anglais — un fichier accepté la veille refusé le lendemain, sans rien
     * pour l'expliquer.
     */
    private function verdictDepuisLeFichier(string $brut): ?VerdictFactCheck
    {
        $brut = mb_strtolower(trim($brut));

        foreach (VerdictFactCheck::cases() as $cas) {
            if (in_array($brut, $cas->libellesReconnus(), true)) {
                return $cas;
            }
        }

        return null;
    }

    private function remplirDepuis(VerifiedFact $fait): void
    {
        $this->resetValidation();

        $this->affirmation = $fait->title;
        $this->verdict = $fait->verdict->value;
        $this->verdictInitial = $fait->verdict->value;
        $this->raisonDeLaCorrection = '';
        $this->analyse = $fait->summary;
        $this->motsCles = $fait->keywords;
        // Repris tels quels : ils ont été choisis, ils ne se redevinent pas.
        $this->motsClesModifies = true;
        $this->sourcesTexte = implode("\n", array_map(
            static fn (array $source): string => trim($source['url'].' | '.$source['name'], ' |'),
            $fait->sourcesRetenues(),
        ));

        $this->rechargerLEditeur();
    }

    private function reinitialiserLeFormulaire(): void
    {
        $this->resetValidation();
        $this->reset([
            'affirmation', 'sourcesTexte', 'motsCles', 'analyse', 'motsClesModifies',
            'uuidEnCours', 'verdictInitial', 'raisonDeLaCorrection',
        ]);
        $this->verdict = VerdictFactCheck::Faux->value;

        $this->rechargerLEditeur();
    }

    /**
     * Recharge Quill depuis l'état du composant.
     *
     * L'hôte de l'éditeur est en `wire:ignore` : Livewire n'y touche plus une
     * fois monté. Sans cet événement, ouvrir un second fait afficherait
     * l'analyse du premier — et l'enregistrer l'écraserait par celle-ci.
     */
    private function rechargerLEditeur(): void
    {
        $this->dispatch('analyse-chargee', html: $this->analyse);
    }

    private function faitOuEchoue(string $uuid): VerifiedFact
    {
        $fait = app(DepotFaitsVerifies::class)->parUuid($uuid);

        abort_if($fait === null, 404);

        return $fait;
    }

    /**
     * L'acteur du back-office, jamais une session citoyenne.
     */
    private function acteur(): User
    {
        $acteur = Auth::guard('staff')->user();

        abort_if(! $acteur instanceof User, 403);

        return $acteur;
    }
}
