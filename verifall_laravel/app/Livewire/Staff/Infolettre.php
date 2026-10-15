<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Identity\Services\AuditLogService;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Core\Marketing\Services\Infolettre as ServiceInfolettre;
use App\Jobs\DiffuserInfolettre;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mews\Purifier\Facades\Purifier;

/**
 * « Infolettre » — la liste, les indicateurs, et l'historique des envois.
 *
 * ## Les trois compteurs, toujours ensemble
 *
 * Destinataires, envoyés, échoués. Jamais l'un sans les deux autres : une
 * campagne qui n'affiche que ses succès laisse croire qu'elle a touché tout le
 * monde, et c'est précisément l'illusion qui fait rater un problème de
 * délivrabilité pendant six semaines.
 *
 * ## L'alerte de régularité
 *
 * Un rendez-vous hebdomadaire meurt de deux lundis manqués, et la panne d'un
 * cron ne fait aucun bruit. L'écran affiche donc l'âge du dernier envoi RÉEL,
 * et le signale dès qu'il dépasse huit jours — huit et non sept, pour laisser
 * passer le lundi en cours sans crier au loup.
 *
 * ## La diffusion manuelle — arbitrage 156 RENVERSÉ le 2 septembre 2026
 *
 * Cet écran n'envoyait pas, et le disait ici : « un bouton envoyer maintenant
 * derrière un simple clic expédierait plusieurs milliers de courriels sans
 * retour possible ». L'argument reste juste ; il ne condamnait pas la
 * fonctionnalité, seulement le « simple clic ».
 *
 * La diffusion est donc ouverte, avec quatre gardes qui répondent chacune à une
 * partie de l'objection d'origine :
 *
 *   1. **Une confirmation qui annonce le nombre** — « sera envoyé à 25 abonnés ».
 *      On ne clique pas « envoyer » sans avoir lu combien de personnes le
 *      recevront ; c'est ce chiffre qui fait hésiter, pas un avertissement.
 *   2. **La permission `newsletter.send`**, distincte de `newsletter.view` :
 *      consulter la liste et écrire à trois mille personnes ne sont pas le même
 *      geste, et la matrice les séparait déjà.
 *   3. **La file**, pas la requête. Le départ ne dépend pas d'un onglet resté
 *      ouvert, et le job reprend où il s'est arrêté.
 *   4. **Le journal d'audit**, avec l'objet et le nombre de destinataires : un
 *      envoi de masse est exactement le geste qu'on doit pouvoir attribuer.
 *
 * L'irréversibilité, elle, ne se corrige pas — elle s'annonce. C'est le rôle du
 * texte de la modale.
 */
#[Layout('components.layouts.staff', ['title' => "Infolettre · Vérif'All"])]
final class Infolettre extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** Au-delà, le rendez-vous est considéré comme manqué. */
    public const JOURS_AVANT_ALERTE = 8;

    /**
     * Rechargée par `verifall-datatables.js` après chaque écriture réussie
     * sur l'onglet Abonnés : voir `docs/DATATABLES_CONTRAT.md` §6.3.
     */
    public const EVENEMENT_TABLE_A_RECHARGER = 'abonnes-modifies';

    /** Objet : la ligne que la personne verra dans sa boîte. */
    public const OBJET_MAXIMUM = 150;

    /** 'abonnes' | 'historique' | 'diffuser' */
    #[Url(as: 'vue', except: 'abonnes')]
    public string $onglet = 'abonnes';

    public string $objet = '';

    /** HTML de l'éditeur, purifié À L'ENREGISTREMENT, jamais à l'affichage. */
    public string $corps = '';

    /** La modale de confirmation, celle qui annonce le nombre. */
    public bool $confirmation = false;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'confirmes' | 'en-attente' | 'desabonnes' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    public function mount(): void
    {
        $this->authorize('viewAny', NewsletterSubscriber::class);
    }

    public function render(DepotAbonnes $depot, ServiceInfolettre $infolettre): View
    {
        $derniere = $depot->derniereEditionEnvoyee();

        return view('livewire.staff.infolettre', [
            'indicateurs' => $depot->indicateurs(),
            // L'onglet « Abonnés » est en DataTables côté serveur
            // (`staff.newsletter.data`) : render() ne la calcule plus.
            'envois' => $this->onglet === 'historique' ? $depot->historique(15) : null,
            'derniereEdition' => $derniere,
            // Carbon 3 rend un flottant : sans le cast, l'écran afficherait
            // « il y a 8.4 jours », ce qu'aucun être humain n'écrit.
            'joursDepuisEnvoi' => $derniere?->sent_at !== null
                ? (int) $derniere->sent_at->diffInDays(now())
                : null,
            'editionEnCours' => $infolettre->cleEdition(now()),
            // Le nombre annoncé par la modale. Recompté à chaque rendu : un
            // chiffre périmé sur un écran d'envoi de masse est pire que pas de
            // chiffre du tout.
            'destinataires' => $this->onglet === 'diffuser' ? $depot->compterDestinataires() : 0,
            'peutDiffuser' => $this->acteur()->can('newsletter.send'),
            'objetMaximum' => self::OBJET_MAXIMUM,
        ]);
    }

    public function updated(string $propriete): void
    {
        // Seul l'historique reste paginé par Livewire : « Abonnés » est en
        // DataTables, et sa propre pagination ne connaît plus `resetPage()`.
        if ($propriete === 'onglet') {
            $this->resetPage();
        }
    }

    public function changerOnglet(string $onglet): void
    {
        $this->onglet = in_array($onglet, ['abonnes', 'historique', 'diffuser'], true) ? $onglet : 'abonnes';
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'filtreEtat']);
        $this->resetPage();
    }

    /**
     * Retire un abonné de la liste, à sa demande.
     *
     * Le cas réel : quelqu'un écrit au support ou appelle plutôt que de
     * cliquer. Le geste DÉSABONNE — il n'efface pas la ligne : la date de
     * confirmation est la preuve que le consentement a existé, et on la détruit
     * précisément le jour où elle servirait.
     *
     * Journalisé, comme toute écriture du back-office sur une donnée
     * personnelle.
     */
    public function retirer(string $uuid, DepotAbonnes $depot, AuditLogService $journal): void
    {
        $this->authorize('send', NewsletterSubscriber::class);

        $abonne = $depot->parUuid($uuid);

        if ($abonne === null || $abonne->unsubscribed_at !== null) {
            $this->toastInfo('Cette adresse ne reçoit déjà plus l\'infolettre.');

            return;
        }

        $avant = $abonne->only(['unsubscribed_at']);
        $abonne = $depot->desabonner($abonne);

        $journal->recordDiff(
            'newsletter', $this->acteur(), $abonne, $avant,
            $abonne->only(['unsubscribed_at']),
            // Le libellé de cible est l'adresse : sans elle, le journal dirait
            // « un abonné a été retiré » et ne servirait à rien lors d'une
            // réclamation.
            (string) $abonne->email,
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Adresse retirée de la liste. La preuve du consentement passé est conservée.');
    }

    /**
     * Ouvre la confirmation — elle ne part pas d'ici.
     *
     * La validation a lieu AVANT la modale, et non après : découvrir que l'objet
     * est vide dans une boîte de dialogue qui annonce « 3 000 destinataires »
     * est le moment le plus mal choisi pour lire un message d'erreur.
     */
    public function confirmerLaDiffusion(): void
    {
        abort_unless($this->acteur()->can('newsletter.send'), 403);

        $this->validate();

        $this->confirmation = true;
    }

    public function annulerLaDiffusion(): void
    {
        $this->confirmation = false;
    }

    /**
     * Met la diffusion en file.
     *
     * ## Ce qui est fait ICI et ce qui est fait dans le job
     *
     * Ici : la garde de permission, la validation, la PURIFICATION, la
     * création de la campagne, la trace d'audit. Là-bas : l'envoi, par lots,
     * avec réservation avant expedition.
     *
     * Le corps est purifié avant d'être écrit, jamais à l'affichage : un HTML
     * dangereux stocké puis nettoyé à chaque lecture finit toujours par sortir
     * par la lecture qu'on avait oubliée — et un courriel part déjà hors de
     * l'application, là où aucun filtre ne le rattrapera.
     */
    public function diffuser(DepotAbonnes $depot, AuditLogService $journal): void
    {
        abort_unless($this->acteur()->can('newsletter.send'), 403);

        $donnees = $this->validate();

        $destinataires = $depot->compterDestinataires();

        if ($destinataires === 0) {
            // Pas d'envoi à vide : une campagne sans destinataire polluerait
            // l'historique d'une ligne qui ressemble à un échec.
            $this->confirmation = false;
            $this->toastWarning('Aucun abonné confirmé : il n\'y a personne à qui écrire.');

            return;
        }

        $corps = trim((string) Purifier::clean($donnees['corps'], 'default'));

        /*
         | Une clé d'édition propre à la diffusion manuelle, horodatée à la
         | seconde. Elle ne doit surtout pas entrer en collision avec celles du
         | récapitulatif hebdomadaire (`YYYY-Www`) : deux campagnes qui
         | partageraient une clé partageraient leurs lignes de livraison, et la
         | seconde ne partirait à personne.
         */
        $campagne = $depot->creerCampagne('manuelle-'.now()->format('YmdHis'), [
            'uuid' => (string) Str::uuid(),
            'name' => Str::limit($donnees['objet'], 80),
            'subject' => $donnees['objet'],
            'body' => $corps,
            'audience' => ServiceInfolettre::AUDIENCE,
            'status' => StatutCampagne::Brouillon->value,
            'recipients_count' => $destinataires,
            'created_by' => $this->acteur()->id,
        ]);

        DiffuserInfolettre::dispatch($campagne->id);

        $journal->record(
            action: 'newsletter.broadcast',
            module: 'newsletter',
            acteur: $this->acteur(),
            cible: $campagne,
            // Pas d'« avant » : une diffusion ne modifie rien, elle crée.
            apres: ['objet' => $donnees['objet'], 'destinataires' => $destinataires],
            // Le libellé est l'objet : sans lui le journal dirait « une campagne
            // a été diffusée », ce qui ne répond à aucune réclamation.
            cibleLibelle: Str::limit($donnees['objet'], 80),
        );

        $this->confirmation = false;
        $this->reset(['objet', 'corps']);
        $this->dispatch('corps-diffusion-charge', html: '');

        $this->toastSuccess(sprintf(
            'Diffusion mise en file pour %d destinataire%s. Elle part dès le prochain passage de la file.',
            $destinataires,
            $destinataires > 1 ? 's' : '',
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'objet' => ['required', 'string', 'min:3', 'max:'.self::OBJET_MAXIMUM],
            // Le corps arrive de Quill : vide, il vaut `<p><br></p>`, que le
            // pont JavaScript remplace déjà par une chaîne vide.
            'corps' => ['required', 'string', 'min:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['objet' => "objet de l'email", 'corps' => 'contenu du message'];
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
