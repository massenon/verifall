<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Models\MailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Logs Emails » — l'écran qui répond à « le courriel est-il parti ? ».
 *
 * ## La question qui a coûté un lot entier
 *
 * `CLAUDE.md` porte un encadré sur ce que la file d'attente fait échouer en
 * silence : sans `queue:work`, le code OTP d'inscription ne part jamais, la
 * page répond 200, aucune erreur n'est levée, la suite de tests reste verte.
 * Le job attend en table `jobs`.
 *
 * Le symptôme observé était « l'inscription s'arrête à la deuxième étape » —
 * l'écran affichait « saisissez le code reçu » et la personne attendait un
 * courriel qui ne partirait jamais. **Le formulaire n'avait aucun défaut.**
 *
 * Rien, dans le produit, ne permettait de le savoir. Cet écran le permet.
 *
 * ## Lecture seule, entièrement
 *
 * `security => ['logs', 'alerts', 'email_logs', 'unit_logs']` : quatre verbes
 * de lecture, aucun d'écriture. Un journal qu'on peut modifier n'est plus un
 * journal — et celui-ci sert précisément à contredire ce qu'on croit.
 *
 * Pas de suppression non plus : la purge est le fait de
 * `PurgerDonneesExpirees`, qui applique une durée de conservation uniforme.
 * Un bouton « vider » ici permettrait d'effacer la trace d'un envoi contesté.
 *
 * ## Le corps n'est pas là, et ce n'est pas un manque
 *
 * `MailLog` le documente : stocker le corps mettrait les codes à usage unique
 * en base, ce que `OtpCodeMail` interdit explicitement. Destinataire, objet,
 * état, erreur — assez pour répondre à la question, pas assez pour créer une
 * fuite.
 */
#[Layout('components.layouts.staff', ['title' => "Journal des e-mails · Vérif'All"])]
final class JournalDesEmails extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'sent' | 'failed' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Url(as: 'type', except: '')]
    public string $filtreMailable = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreMailable(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreEtat', 'filtreMailable');
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('security.email_logs') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.journal-des-emails', [
            'courriels' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'types' => $this->typesEnvoyes(),
        ]);
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * Les classes de Mailable réellement rencontrées.
     *
     * Lues en base plutôt que déclarées en code : le filtre doit proposer ce
     * qui EXISTE dans le journal, pas ce que le code prévoit. Une classe
     * supprimée du code laisse des lignes anciennes qu'il faut pouvoir
     * retrouver.
     *
     * @return list<string>
     */
    private function typesEnvoyes(): array
    {
        return MailLog::query()
            ->whereNotNull('mailable')
            ->distinct()
            ->orderBy('mailable')
            ->pluck('mailable')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, MailLog>
     */
    private function lignes(): LengthAwarePaginator
    {
        return MailLog::query()
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('recipient', 'like', $terme)
                    ->orWhere('subject', 'like', $terme));
            })
            ->when($this->filtreEtat !== '', fn (Builder $q) => $q->where('status', $this->filtreEtat))
            ->when($this->filtreMailable !== '', fn (Builder $q) => $q->where('mailable', $this->filtreMailable))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * @return array{total: int, envoyes: int, echecs: int, echecs_24h: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => MailLog::query()->count(),
            'envoyes' => MailLog::query()->where('status', MailLog::ENVOYE)->count(),
            'echecs' => MailLog::query()->enEchec()->count(),
            /*
             | Les échecs des DERNIÈRES 24 H, séparément du total. Un cumul
             | d'échecs anciens ne dit rien de l'état actuel ; c'est le chiffre
             | récent qui distingue « ça a déjà planté » de « ça plante en ce
             | moment ».
             */
            'echecs_24h' => MailLog::query()
                ->enEchec()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }
}
