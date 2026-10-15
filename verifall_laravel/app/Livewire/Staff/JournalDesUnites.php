<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Economy\Services\EconomyService;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\MotifMouvementUnite;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Historiques des Unités IA » — le journal de tous les comptes.
 *
 * ## Ce que cet écran répare
 *
 * `MotifMouvementUnite::AjustementAdmin` existe dans l'énumération depuis
 * l'origine. **Rien ne l'émettait.** Le produit déclarait donc un geste —
 * corriger à la main le solde d'un compte — qu'aucune interface ne permettait
 * de poser : il fallait `php artisan tinker` en production.
 *
 * Ce n'est pas une commodité. C'est le seul recours quand un paiement aboutit
 * sans crédit, et les CGU §6 s'y engagent par écrit : « le membre saisit la
 * plateforme, qui procède au crédit ou au remboursement ». La clause existait,
 * le moyen de la tenir non.
 *
 * ## Un mouvement inverse, jamais une réécriture
 *
 * `UnitLog` le dit dans son propre en-tête : « un mouvement corrigé se corrige
 * par un mouvement inverse, comme en comptabilité ». L'écran n'offre donc ni
 * modification ni suppression de ligne — seulement l'ajout d'un mouvement, qui
 * passe par `EconomyService` comme tous les autres et laisse sa trace.
 *
 * ## Lecture seule par défaut
 *
 * `ai_unit.view` ouvre la page ; `ai_unit.adjust` seul débloque le formulaire.
 * La matrice sépare déjà les deux verbes — l'écran s'y tient plutôt que de
 * confondre « consulter le journal » et « écrire dedans ».
 *
 * ## UN SEUL écran pour deux entrées du prototype (arbitré au lot 10)
 *
 * Le menu du prototype porte « Historiques des Unités IA » sous FACT-CHECKING
 * et « Logs Unités IA » sous SÉCURITÉ. Deux entrées, une seule table : les
 * construire toutes les deux aurait donné deux écrans identiques, donc deux
 * habitudes, donc un des deux qu'on finit par ne plus relever. Le lot 2 a
 * tranché deux doublons de la même façon.
 *
 * La seconde entrée est donc retirée du menu — mais son DROIT est reconnu ici :
 * `security.unit_logs` ouvre la page au même titre que `ai_unit.view`. Un
 * responsable sécurité, qui porte le premier sans le second, y accède en
 * lecture ; le formulaire d'ajustement reste derrière `ai_unit.adjust`.
 */
#[Layout('components.layouts.staff', ['title' => "Historiques des Unités IA · Vérif'All"])]
final class JournalDesUnites extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après un ajustement. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'journal-des-unites-modifie';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'credit' | 'debit' */
    #[Url(as: 'sens', except: '')]
    public string $filtreSens = '';

    #[Url(as: 'motif', except: '')]
    public string $filtreMotif = '';

    public bool $formulaireOuvert = false;

    // — le formulaire d'ajustement
    public string $compte = '';

    #[Locked]
    public ?string $compteTrouve = null;

    public string $sensAjustement = 'credit';

    public string $unites = '';

    public string $justification = '';

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreSens', 'filtreMotif');
    }

    public function mount(): void
    {
        $lecteur = Auth::guard('staff')->user();

        // L'UN OU L'AUTRE : le fact-checking par `ai_unit.view`, la sécurité par
        // `security.unit_logs`. Deux entrées du prototype, un seul écran.
        abort_unless(
            ($lecteur?->can('ai_unit.view') ?? false) || ($lecteur?->can('security.unit_logs') ?? false),
            403,
        );
    }

    public function render(UnitLogRepositoryInterface $journal): View
    {
        return view('livewire.staff.journal-des-unites', [
            'indicateurs' => $journal->totauxGlobaux(),
            'motifs' => MotifMouvementUnite::cases(),
            'peutAjuster' => $this->peutAjuster(),
        ]);
    }

    // ————————————————————————————————————————————————— l'ajustement

    public function ouvrirAjustement(): void
    {
        abort_unless($this->peutAjuster(), 403);

        $this->reset('compte', 'compteTrouve', 'unites', 'justification');
        $this->sensAjustement = 'credit';
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->resetValidation();
    }

    /**
     * Retrouve le compte visé, et l'affiche AVANT que le mouvement soit posé.
     *
     * Écrire une adresse et cliquer « créditer » sans jamais voir de qui il
     * s'agit, c'est la façon la plus simple de créditer le mauvais compte —
     * `paul@` et `paule@` se ressemblent, et l'erreur ne se voit qu'après.
     */
    public function updatedCompte(string $valeur): void
    {
        $valeur = trim($valeur);

        $this->compteTrouve = $valeur === '' ? null : $this->chercherLeCompte($valeur)?->uuid;
    }

    public function ajuster(EconomyService $economie, AuditLogService $audit): void
    {
        abort_unless($this->peutAjuster(), 403);

        $donnees = $this->validate();

        $utilisateur = $this->chercherLeCompte(trim($donnees['compte']));

        if ($utilisateur === null) {
            $this->addError('compte', 'Aucun compte ne correspond.');

            return;
        }

        $unites = (int) $donnees['unites'];
        $soldeAvant = (int) $utilisateur->units;

        try {
            $mouvement = $donnees['sensAjustement'] === 'credit'
                ? $economie->crediter($utilisateur, $unites, MotifMouvementUnite::AjustementAdmin, $donnees['justification'])
                : $economie->debiter($utilisateur, $unites, MotifMouvementUnite::AjustementAdmin, $donnees['justification']);
        } catch (SoldeInsuffisant) {
            /*
             | Rattrapée plutôt que laissée remonter en 500 : un solde
             | insuffisant n'est pas une panne, c'est une réponse. Le message
             | donne le solde réel, sans quoi la personne retente au hasard.
             */
            $this->addError('unites', 'Solde insuffisant : ce compte ne porte que '.$soldeAvant.' unité(s).');

            return;
        }

        $audit->record(
            action: 'ai_unit.adjust',
            module: 'fact-checking',
            acteur: Auth::guard('staff')->user(),
            cible: $utilisateur,
            avant: ['units' => $soldeAvant],
            apres: [
                'units' => (int) $utilisateur->refresh()->units,
                'delta' => (int) $mouvement->delta,
                // La justification est DANS le journal d'audit, pas seulement
                // dans le libellé du mouvement : c'est elle qu'on cherchera si
                // l'ajustement est contesté.
                'justification' => $donnees['justification'],
            ],
            cibleLibelle: $utilisateur->name.' <'.$utilisateur->email.'>',
        );

        $this->fermerLeTiroir();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Ajustement enregistré : nouveau solde '.$utilisateur->units.' unité(s).');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'compte' => ['required', 'string', 'max:255'],
            'sensAjustement' => ['required', Rule::in(['credit', 'debit'])],
            /*
             | Plafonné à 10 000. Sans borne haute, une faute de frappe
             | — un zéro de trop — crédite cent fois ce qu'on voulait, et la
             | correction demande un second mouvement que personne ne pense à
             | faire tout de suite.
             */
            'unites' => ['required', 'integer', 'min:1', 'max:10000'],
            /*
             | Obligatoire, et longue. C'est la seule pièce qui explique, six
             | mois plus tard, pourquoi ce compte a reçu 50 unités. Un champ
             | facultatif ici finirait vide dans neuf cas sur dix.
             */
            'justification' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'compte' => 'compte',
            'sensAjustement' => 'sens',
            'unites' => 'nombre d\'unités',
            'justification' => 'justification',
        ];
    }

    private function peutAjuster(): bool
    {
        return Auth::guard('staff')->user()?->can('ai_unit.adjust') ?? false;
    }

    /**
     * Le compte visé, par adresse ou par UUID.
     *
     * Jamais par nom : deux personnes peuvent s'appeler pareil, et se tromper
     * de destinataire est ici irréversible — le mouvement inverse laisse deux
     * lignes au journal de quelqu'un qui n'y était pour rien.
     */
    private function chercherLeCompte(string $valeur): ?User
    {
        return User::query()
            ->where('email', $valeur)
            ->orWhere('uuid', $valeur)
            ->first();
    }
}
