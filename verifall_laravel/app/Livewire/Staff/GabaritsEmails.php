<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Database\Seeders\GabaritsDEmailSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * « Gabarits d'emails » — le texte de tout ce que la plateforme envoie.
 *
 * ## Ce que l'écran laissait faire, et ce qu'il promettait
 *
 * Il ne permettait de changer QUE l'objet. Le corps vivait dans un gabarit
 * Blade, hors de portée. Un écran nommé « Gabarits d'emails » qui ne laisse pas
 * toucher au corps promet plus qu'il ne tient — et le rédacteur qui vient y
 * corriger une formulation repart en écrivant au développeur.
 *
 * Il porte désormais l'objet, le titre, le corps, le libellé du bouton, et
 * l'interrupteur.
 *
 * ## La liste reste FERMÉE — pas de création, pas de suppression
 *
 * C'est le seul point sur lequel cet écran ne suit pas le CRUD habituel, et
 * c'est délibéré : une ligne dont la clé ne correspond à aucun envoi est un
 * réglage que personne ne lit, sur un écran qui laisse croire qu'on ajoute un
 * courriel en ajoutant une ligne. Le catalogue est en code
 * ({@see CatalogueDesCourriels}), parce que c'est le code qui envoie.
 *
 * Ce que l'écran offre à la place : **réinitialiser** une ligne, qui est
 * l'équivalent utile de la suppression sans son piège.
 *
 * ## La colonne « câblé »
 *
 * Plusieurs gabarits sont rédigeables alors que rien ne les expédie encore.
 * L'écran le DIT, ligne par ligne. Un texte qu'on peut soigner sans que rien ne
 * l'envoie est exactement le piège que ce chantier a corrigé quatre fois ; le
 * cacher ici l'aurait recréé sur l'écran qui prétend tout lister.
 *
 * ## Les marqueurs sont vérifiés à l'enregistrement
 *
 * `{{ prenom }}` qu'aucun envoi ne remplit, ou `{{ prénom }}` accentué, partent
 * tels quels dans toutes les boîtes. L'écran refuse donc tout marqueur absent
 * de la liste déclarée — c'est la garde ; le rendu, lui, a un filet qui les
 * retire, mais un filet ne remplace pas une garde.
 */
#[Layout('components.layouts.staff', ['title' => "Gabarits d'emails · Vérif'All"])]
final class GabaritsEmails extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    public string $sujet = '';

    public string $titre = '';

    public string $corps = '';

    public string $libelleDuBouton = '';

    public bool $actif = true;

    public function mount(): void
    {
        abort_unless($this->peutGerer(), 403);
    }

    public function render(): View
    {
        return view('livewire.staff.gabarits-emails', [
            'gabarits' => $this->lignes(),
            'peutGerer' => $this->peutGerer(),
            'variablesDuGabarit' => $this->enEdition !== null
                ? array_map(
                    static fn (string $v): string => '{'.'{ '.$v.' }'.'}',
                    CatalogueDesCourriels::variables($this->enEdition),
                )
                : [],
            'evenementEnEdition' => $this->enEdition !== null
                ? CatalogueDesCourriels::evenement($this->enEdition)
                : null,
        ]);
    }

    public function ouvrirEdition(string $cle): void
    {
        abort_unless($this->peutGerer(), 403);
        abort_unless(CatalogueDesCourriels::existe($cle), 404);

        $gabarit = EmailTemplate::query()->where('key', $cle)->first();

        $this->enEdition = $cle;
        $this->sujet = (string) ($gabarit?->subject ?? '');
        $this->titre = (string) ($gabarit?->heading ?? '');
        $this->corps = (string) ($gabarit?->body ?? '');
        $this->libelleDuBouton = (string) ($gabarit?->cta_label ?? '');
        $this->actif = $gabarit?->is_active ?? CatalogueDesCourriels::evenement($cle)['parDefautActif'];

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->reset(['sujet', 'titre', 'corps', 'libelleDuBouton']);
        $this->resetValidation();
    }

    public function enregistrer(RenduDeGabarit $rendu, AuditLogService $audit): void
    {
        if ($this->enEdition === null) {
            return;
        }

        abort_unless($this->peutGerer(), 403);

        $cle = $this->enEdition;

        $donnees = $this->validate([
            'sujet' => ['required', 'string', 'min:5', 'max:200'],
            'titre' => ['required', 'string', 'min:3', 'max:200'],
            'corps' => ['required', 'string', 'min:20'],
            'libelleDuBouton' => ['nullable', 'string', 'max:80'],
        ], attributes: [
            'sujet' => 'objet du courriel',
            'titre' => 'titre du message',
            'corps' => 'contenu',
            'libelleDuBouton' => 'libellé du bouton',
        ]);

        if (($inconnu = $this->marqueurInconnu($cle, $donnees)) !== null) {
            /*
             | Une erreur de champ plutôt qu'un toast : le marqueur fautif est
             | DANS un texte que la personne est en train d'écrire, et l'erreur
             | doit s'afficher contre ce texte-là.
             */
            $this->addError('corps', sprintf(
                'Le marqueur « %s » n’est pas fourni par cet envoi. Marqueurs disponibles : %s.',
                $inconnu,
                implode(', ', array_map(static fn (string $v): string => '{{ '.$v.' }}', CatalogueDesCourriels::variables($cle))),
            ));

            return;
        }

        $gabarit = EmailTemplate::query()->firstOrNew(['key' => $cle]);
        $avant = $gabarit->exists
            ? $gabarit->only(['subject', 'heading', 'body', 'cta_label', 'is_active'])
            : null;

        $gabarit->forceFill([
            'uuid' => $gabarit->uuid ?? (string) Str::uuid(),
            'key' => $cle,
            'subject' => $donnees['sujet'],
            'heading' => $donnees['titre'],
            'body' => $donnees['corps'],
            'cta_label' => $donnees['libelleDuBouton'] !== '' ? $donnees['libelleDuBouton'] : null,
            // Un envoi verrouillé reste allumé quoi qu'on écrive : le rendu
            // l'impose de toute façon, et enregistrer le contraire ferait
            // mentir la ligne.
            'is_active' => CatalogueDesCourriels::estVerrouille($cle) ? true : $this->actif,
            'variables' => CatalogueDesCourriels::variables($cle),
            'updated_by' => $this->acteur()->id,
        ])->save();

        $audit->record(
            action: 'email_template.update',
            module: 'system',
            acteur: $this->acteur(),
            cible: $gabarit,
            avant: $avant,
            apres: $gabarit->only(['subject', 'heading', 'body', 'cta_label', 'is_active']),
            cibleLibelle: CatalogueDesCourriels::evenement($cle)['libelle'],
        );

        // Sans cet oubli, la correction ne partirait qu'au bout de trente
        // minutes — le genre de défaut qu'on met une journée à croire.
        $rendu->oublier();

        $this->fermerLeTiroir();
        $this->toastSuccess('Gabarit enregistré. Il s’applique aux prochains envois.');
    }

    /**
     * Rend une ligne à son texte d'origine.
     *
     * ## Réécrire, et non supprimer
     *
     * Tant que seul l'OBJET était surchargeable, « pas de surcharge » se disait
     * par l'absence de ligne, et réinitialiser voulait dire supprimer. Ce n'est
     * plus tenable depuis que la ligne porte AUSSI le corps : la supprimer
     * laisserait le courriel sans texte du tout jusqu'au prochain semis.
     *
     * La ligne existe donc toujours, et c'est `updated_by` qui dit si un être
     * humain y a touché. Réinitialiser réécrit le texte d'origine et efface
     * cette signature.
     */
    public function reinitialiser(string $cle, RenduDeGabarit $rendu, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);
        abort_unless(CatalogueDesCourriels::existe($cle), 404);

        $defaut = GabaritsDEmailSeeder::texteParDefaut($cle);
        $gabarit = EmailTemplate::query()->where('key', $cle)->first();

        if ($gabarit === null || $defaut === null) {
            $this->toastWarning('Ce gabarit est déjà à son texte d’origine.');

            return;
        }

        $avant = $gabarit->only(['subject', 'heading', 'body', 'cta_label', 'is_active']);

        $gabarit->forceFill([
            'subject' => $defaut['sujet'],
            'heading' => $defaut['titre'],
            'body' => $defaut['corps'],
            'cta_label' => $defaut['bouton'] ?? null,
            'is_active' => CatalogueDesCourriels::evenement($cle)['parDefautActif'],
            // La signature s'efface : la ligne redevient « texte d'origine ».
            'updated_by' => null,
        ])->save();

        $audit->record(
            action: 'email_template.reset',
            module: 'system',
            acteur: $this->acteur(),
            cible: $gabarit,
            avant: $avant,
            apres: $gabarit->only(['subject', 'heading', 'body', 'cta_label', 'is_active']),
            cibleLibelle: CatalogueDesCourriels::evenement($cle)['libelle'],
        );

        $rendu->oublier();

        $this->fermerLeTiroir();
        $this->toastSuccess('Gabarit revenu à son texte d’origine.');
    }

    /** Allume ou éteint un envoi, depuis la liste. */
    public function basculer(string $cle, RenduDeGabarit $rendu, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);
        abort_unless(CatalogueDesCourriels::existe($cle), 404);

        if (CatalogueDesCourriels::estVerrouille($cle)) {
            // Un code à usage unique qu'un réglage peut couper est un compte
            // qu'on ne peut plus créer.
            $this->toastWarning('Cet envoi ne peut pas être éteint : il est indispensable au fonctionnement du compte.');

            return;
        }

        $gabarit = EmailTemplate::query()->firstOrNew(['key' => $cle]);
        $etait = $gabarit->is_active ?? CatalogueDesCourriels::evenement($cle)['parDefautActif'];

        $gabarit->forceFill([
            'uuid' => $gabarit->uuid ?? (string) Str::uuid(),
            'key' => $cle,
            'is_active' => ! $etait,
            'updated_by' => $this->acteur()->id,
        ])->save();

        $audit->record(
            action: $etait ? 'email_template.disable' : 'email_template.enable',
            module: 'system',
            acteur: $this->acteur(),
            cible: $gabarit,
            cibleLibelle: CatalogueDesCourriels::evenement($cle)['libelle'],
        );

        $rendu->oublier();

        $this->toastSuccess($etait ? 'Envoi éteint.' : 'Envoi allumé.');
    }

    /**
     * Le premier marqueur écrit qui n'est pas fourni par l'envoi, ou `null`.
     *
     * @param  array<string, string>  $donnees
     */
    private function marqueurInconnu(string $cle, array $donnees): ?string
    {
        $autorises = CatalogueDesCourriels::variables($cle);
        $texte = implode(' ', [$donnees['sujet'], $donnees['titre'], $donnees['corps'], $donnees['libelleDuBouton'] ?? '']);

        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/u', $texte, $trouves);

        foreach ($trouves[1] as $marqueur) {
            if (! in_array($marqueur, $autorises, true)) {
                return $marqueur;
            }
        }

        return null;
    }

    /**
     * La liste, catalogue en tête et surcharges par-dessus.
     *
     * @return list<array{cle: string, libelle: string, declencheur: string, variables: list<string>, sujet: string, porte: bool, actif: bool, verrouille: bool, modifieLe: ?string, modifiePar: ?string, personnalise: bool}>
     */
    private function lignes(): array
    {
        $surcharges = EmailTemplate::query()
            ->with('auteurDeLaModification:id,name')
            ->get()
            ->keyBy('key');

        $lignes = [];

        foreach (CatalogueDesCourriels::EVENEMENTS as $cle => $evenement) {
            /** @var EmailTemplate|null $gabarit */
            $gabarit = $surcharges->get($cle);

            $lignes[] = [
                'cle' => $cle,
                'libelle' => $evenement['libelle'],
                'declencheur' => $evenement['declencheur'],
                // Les marqueurs sont composés ICI, pas dans le gabarit :
                // écrire `{{ '{{ '.$v.' }}' }}` en Blade fait fermer l'écho sur
                // le `}}` intérieur, et la vue ne compile plus. Troisième piège
                // Blade rencontré sur ce chantier.
                'variables' => array_map(
                    static fn (string $v): string => '{'.'{ '.$v.' }'.'}',
                    $evenement['variables'],
                ),
                'sujet' => (string) ($gabarit?->subject ?? '—'),
                'porte' => $evenement['porte'],
                'actif' => $gabarit?->is_active ?? $evenement['parDefautActif'],
                'verrouille' => $evenement['verrouille'],
                'modifieLe' => $gabarit?->updated_at?->format('d/m/Y H:i'),
                'modifiePar' => $gabarit?->auteurDeLaModification?->name,
                // La ligne existe TOUJOURS depuis qu'elle porte le corps :
                // c'est la signature qui dit si quelqu'un y a touché.
                'personnalise' => $gabarit?->updated_by !== null,
            ];
        }

        return $lignes;
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('system.email_template') ?? false;
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
