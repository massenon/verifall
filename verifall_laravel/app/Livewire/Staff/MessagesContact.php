<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotMessagesContact;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\NatureMessageContact;
use App\Enums\StatutMessageContact;
use App\Models\ContactMessage;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Messages » — la boîte de réception du formulaire de contact.
 *
 * ## Pourquoi cet écran existe
 *
 * Parce que sans lui, la table se remplit et personne ne la lit. C'est le
 * défaut exact qu'on venait de corriger côté public : le formulaire du
 * prototype remerciait sans rien envoyer. Le remplacer par un formulaire qui
 * écrit dans une table que personne n'ouvre ne change rien pour celui qui
 * écrit.
 *
 * ## Ce qu'il ne fait pas
 *
 * **Aucune suppression.** Un message se traite, il ne s'efface pas : sur une
 * plateforme de fact-checking, celui qui écrit signale parfois une intox, et
 * effacer la trace c'est perdre à la fois le signalement et la possibilité de
 * dire ce qui en a été fait.
 *
 * **Aucune réponse depuis l'écran.** Le courriel de notification porte un
 * `Reply-To` : on répond depuis son client mail, où l'on a sa signature et son
 * historique. Bâtir un client de messagerie ici serait un projet à part, et un
 * moins bon.
 */
#[Layout('components.layouts.staff', ['title' => "Messages · Vérif'All"])]
final class MessagesContact extends Component
{
    use InteractsWithToasts;

    /**
     * Rechargée par `verifall-datatables.js` après chaque écriture réussie :
     * voir `docs/DATATABLES_CONTRAT.md` §6.3.
     */
    public const EVENEMENT_TABLE_A_RECHARGER = 'messages-contact-modifies';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'new' | 'in_progress' | 'handled' | 'spam' */
    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    /**
     * '' | 'contact' | 'don'
     *
     * Le filtre par NATURE, et il n'est pas décoratif. Une demande de soutien
     * qui se noie dans le courrier courant produit exactement l'oubli qu'on
     * voulait éviter en refusant de lui donner sa propre file.
     */
    #[Url(as: 'nature', except: '')]
    public string $filtreNature = '';

    /** L'uuid du message ouvert dans le panneau de lecture. */
    public ?string $ouvert = null;

    public string $note = '';

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreStatut', 'filtreNature');
    }

    public function ouvrir(string $uuid): void
    {
        $this->ouvert = $uuid;
        $this->note = (string) ($this->message()?->handling_note ?? '');
    }

    public function fermer(): void
    {
        $this->reset('ouvert', 'note');
    }

    /**
     * Change le statut d'un message et le journalise.
     *
     * L'audit est ici et non dans le dépôt : c'est une action d'ADMINISTRATION,
     * pas une écriture de données. Le dépôt sert aussi la file publique, où
     * aucun acteur ne signe.
     */
    public function marquer(string $statut, DepotMessagesContact $depot, AuditLogService $audit): void
    {
        $this->authorize('update', ContactMessage::class);

        $message = $this->message();
        $cible = StatutMessageContact::tryFrom($statut);

        if ($message === null || $cible === null) {
            return;
        }

        $this->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['note' => 'note de traitement']);

        $avant = $message->status->value;

        $depot->changerStatut($message, $cible, auth('staff')->user(), $this->note !== '' ? $this->note : null);

        $audit->record(
            action: 'contact_message.status',
            module: 'contact',
            acteur: auth('staff')->user(),
            cible: $message,
            avant: ['status' => $avant],
            apres: ['status' => $cible->value],
            cibleLibelle: $message->name,
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Message marqué « '.$cible->libelle().' ».');
    }

    public function render(DepotMessagesContact $depot): View
    {
        $this->authorize('viewAny', ContactMessage::class);

        return view('livewire.staff.messages-contact', [
            'compteurs' => $depot->compteurs(),
            'statuts' => StatutMessageContact::cases(),
            'natures' => NatureMessageContact::cases(),
            'messageOuvert' => $this->message(),
        ]);
    }

    private function message(): ?ContactMessage
    {
        return $this->ouvert === null
            ? null
            : app(DepotMessagesContact::class)->parUuid($this->ouvert);
    }
}
