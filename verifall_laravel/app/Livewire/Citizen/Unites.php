<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Economy\Providers\FakeMomoProvider;
use App\Core\Economy\Services\PaymentService;
use App\Core\Identity\Services\SecurityLogService;
use App\Enums\StatutPaiement;
use App\Http\Controllers\Webhooks\MomoWebhookController;
use App\Models\Payment;
use App\Models\UnitPack;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Mes unités » — page unites.html du prototype.
 *
 * Le client n'envoie qu'un IDENTIFIANT DE PACK. Ni le prix ni le nombre
 * d'unités ne transitent par le navigateur : les lire de là suffirait à se
 * créditer ce qu'on veut.
 */
#[Layout('components.layouts.citizen', ['title' => "Mes unités · Vérif'All"])]
final class Unites extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'type', except: '')]
    public string $sens = '';

    public int $parPage = 10;

    // ------------------------------------------------------------ achat
    public bool $modaleAchat = false;

    /** Identifiant du pack — la seule chose que le client choisit. */
    public string $pack = 'p25';

    public string $numero = '';

    /** UUID de la demande en cours, s'il y en a une. */
    public ?string $paiementEnCours = null;

    public function render(UnitLogRepositoryInterface $journal): View
    {
        $utilisateur = $this->utilisateur();

        return view('livewire.citizen.unites', [
            'utilisateur' => $utilisateur,
            'mouvements' => $journal->historique($utilisateur, $this->recherche, $this->sens, $this->parPage),
            // La grille vient de la base : le porteur du projet la change
            // depuis le back-office, sans déploiement.
            'packs' => UnitPack::enVente()->get(),
            'paiement' => $this->paiement(),
            'simulable' => ! app()->isProduction() && config('economy.momo.driver') === 'fake',
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'sens', 'parPage'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Prochaine dotation mensuelle — le 1ᵉʳ du mois suivant.
     *
     * @return array{date: string, jours: int}
     */
    #[Computed]
    public function prochaineDotation(): array
    {
        $prochaine = now()->addMonthNoOverflow()->startOfMonth();

        return [
            'date' => '1ᵉʳ '.$prochaine->translatedFormat('F Y'),
            // diffInDays arrondit vers le bas : on compte les jours entamés,
            // sinon « dans 0 jour » s'afficherait la veille au soir.
            'jours' => (int) ceil(now()->diffInDays($prochaine, absolute: true)) ?: 1,
        ];
    }

    // ============================================================ achat

    public function ouvrirAchat(): void
    {
        $this->modaleAchat = true;
        $this->reset(['numero']);
        $this->resetErrorBag();
    }

    public function fermerAchat(): void
    {
        $this->reset(['modaleAchat', 'numero']);
        $this->resetErrorBag();
    }

    public function choisirPack(string $pack): void
    {
        // Un pack inconnu — ou retiré de la vente — vient d'un client
        // modifié : on ignore plutôt que de deviner.
        if (UnitPack::where('code', $pack)->where('is_active', true)->exists()) {
            $this->pack = $pack;
        }
    }

    public function payer(PaymentService $paiements): void
    {
        $this->validate([
            // 8 chiffres nationaux, avec ou sans indicatif et espaces.
            'numero' => ['required', 'string', 'regex:/^(\+?229)?[\s0-9]{8,14}$/'],
        ], [
            'numero.required' => 'Entrez votre numéro Mobile Money.',
            'numero.regex' => 'Ce numéro ne ressemble pas à un numéro béninois.',
        ]);

        try {
            $paiement = $paiements->initier($this->utilisateur(), $this->pack, $this->numero);
        } catch (RuntimeException $erreur) {
            $this->addError('numero', $erreur->getMessage());

            return;
        }

        $this->paiementEnCours = $paiement->uuid;
        $this->modaleAchat = false;

        $this->toastInfo('Validez la demande sur votre téléphone.');
    }

    /**
     * Interroge l'état de la demande — l'écran d'attente appelle ceci toutes
     * les 3 secondes.
     */
    public function rafraichirPaiement(): void
    {
        $paiement = $this->paiement();

        if ($paiement === null) {
            return;
        }

        if ($paiement->status === StatutPaiement::Reussi) {
            $this->paiementEnCours = null;
            $this->dispatch('solde-change', solde: $this->utilisateur()->fresh()->units);
            $this->toastSuccess($paiement->units.' unités créditées. Bonne vérification !');

            return;
        }

        if ($paiement->status->estFinal()) {
            $this->paiementEnCours = null;
            $this->toastError('Paiement non abouti. '.($paiement->failure_reason ?? 'Vous pouvez réessayer.'));

            return;
        }

        if ($paiement->aExpire()) {
            // Une roue qui tourne indéfiniment laisse croire que le paiement va
            // aboutir : au-delà du délai, on le dit.
            $this->paiementEnCours = null;
            $this->toastWarning('Aucune confirmation reçue. Réessayez.');
        }
    }

    public function abandonnerPaiement(): void
    {
        $this->paiementEnCours = null;
    }

    /**
     * Bouton « Simuler le paiement » — développement uniquement.
     *
     * Le contrôleur du rappel est invoqué DIRECTEMENT, avec une charge
     * réellement signée : la vérification de signature, la recherche du
     * paiement et la transition verrouillée sont donc bel et bien exercées.
     *
     * Pas d'appel HTTP vers nous-mêmes, en revanche : `artisan serve` est
     * mono-processus, et la requête sortante ne peut être servie qu'une fois la
     * requête courante terminée. Le crédit arrivait donc APRÈS le rendu, et
     * l'écran continuait d'afficher « en attente » alors que le paiement était
     * passé — exactement l'impression qu'on cherche à éviter.
     */
    public function simulerPaiement(
        FakeMomoProvider $simulateur,
        MomoWebhookController $rappel,
        PaymentService $paiements,
        SecurityLogService $securite,
    ): void {
        abort_if(app()->isProduction(), 403);

        $paiement = $this->paiement();

        if ($paiement === null || ! $paiement->estEnAttente()) {
            return;
        }

        $charge = ['reference' => $paiement->provider_ref, 'status' => 'succeeded'];

        $requete = Request::create(
            uri: '/webhooks/momo/'.$paiement->provider->value,
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $simulateur->signer($charge)],
            content: (string) json_encode($charge),
        );

        $rappel($requete, $paiement->provider->value, $simulateur, $paiements, $securite);

        $this->rafraichirPaiement();
    }

    // ========================================================== export

    /**
     * Export CSV de l'historique filtré — bouton du prototype.
     */
    public function exporterCsv(UnitLogRepositoryInterface $journal): StreamedResponse
    {
        $lignes = $journal->pourExport($this->utilisateur(), $this->recherche, $this->sens);

        return response()->streamDownload(function () use ($lignes): void {
            $sortie = fopen('php://output', 'wb');

            // BOM UTF-8 : sans lui, Excel casse les accents.
            fwrite($sortie, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($sortie, ['Réf.', 'Libellé', 'Montant', 'Type', 'Solde après', 'Date'], ';');

            foreach ($lignes as $ligne) {
                fputcsv($sortie, [
                    $ligne->reference(),
                    // Le libellé TRADUIT, comme à l'écran : quelqu'un qui
                    // exporte ce qu'il vient de lire doit retrouver les mêmes
                    // mots dans son tableur.
                    $ligne->libelleAffiche(),
                    $ligne->delta,
                    $ligne->estCredit() ? 'Crédit' : 'Débit',
                    $ligne->balance_after,
                    $ligne->created_at?->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($sortie);
        }, 'mes-unites-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function paiement(): ?Payment
    {
        if ($this->paiementEnCours === null) {
            return null;
        }

        // Borné au propriétaire : un uuid deviné ne doit renseigner sur
        // aucune demande d'autrui.
        return Payment::where('uuid', $this->paiementEnCours)
            ->where('user_id', $this->utilisateur()->id)
            ->first();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
