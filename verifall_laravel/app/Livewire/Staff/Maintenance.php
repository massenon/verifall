<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Services\ModeMaintenance;
use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Models\MaintenanceWatcher;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * « Mode maintenance » — fermer le site, et le rouvrir.
 *
 * ## Deux gestes irréversibles à leur manière, et une confirmation pour chacun
 *
 * Fermer coupe le service à tout le monde d'un coup. Rouvrir envoie un courriel
 * à chaque personne qui attendait — un envoi qui ne se rappelle pas. Les deux
 * passent donc par une confirmation qui annonce ce qui va se produire, avec des
 * chiffres : combien de gens seront prévenus, depuis combien de temps le site
 * est fermé.
 *
 * ## L'heure de fin est une PROMESSE, et l'écran le dit
 *
 * Elle n'éteint rien toute seule. Une maintenance qui se rouvrirait au
 * chronomètre rouvrirait un site à moitié migré si l'opération a pris dix
 * minutes de plus — précisément le moment où il ne faut pas laisser entrer.
 *
 * Ce que l'heure apporte : un décompte honnête sur la page publique, un en-tête
 * `Retry-After` pour les robots, et la capacité de dire « nous avons du retard »
 * plutôt que d'afficher un compteur figé à zéro.
 *
 * ## Les avertis sont prévenus à la RÉOUVERTURE
 *
 * Pas à l'heure annoncée : ils arriveraient sur un site encore fermé, et le
 * courriel qui promet le retour deviendrait le deuxième mensonge de la journée.
 */
#[Layout('components.layouts.staff', ['title' => "Mode maintenance · Vérif'All"])]
final class Maintenance extends Component
{
    use InteractsWithToasts;

    /** Date et heure de fin annoncée, au format du champ `datetime-local`. */
    public string $finAnnoncee = '';

    public string $message = '';

    /** 'ouvrir' | 'fermer' | null — quelle confirmation est à l'écran. */
    public ?string $confirmation = null;

    public function mount(ModeMaintenance $maintenance): void
    {
        abort_unless($this->acteur()->can('maintenance.mode'), 403);

        $this->message = $maintenance->message();
        $this->finAnnoncee = $maintenance->finAnnoncee()?->format('Y-m-d\TH:i') ?? '';
    }

    public function render(ModeMaintenance $maintenance): View
    {
        $enAttente = MaintenanceWatcher::query()->whereNull('notified_at')->count();

        return view('livewire.staff.maintenance', [
            'actif' => $maintenance->estActif(),
            'debut' => $maintenance->debut(),
            'finReelle' => $maintenance->finAnnoncee(),
            'enRetard' => $maintenance->enRetard(),
            // Le chiffre qui pèse sur la décision de rouvrir : c'est le nombre
            // de courriels qui partiront.
            'enAttente' => $enAttente,
            'dejaPrevenus' => MaintenanceWatcher::query()->whereNotNull('notified_at')->count(),
        ]);
    }

    public function demander(string $geste): void
    {
        abort_unless($this->acteur()->can('maintenance.mode'), 403);

        $this->confirmation = in_array($geste, ['ouvrir', 'fermer'], true) ? $geste : null;
    }

    public function annuler(): void
    {
        $this->confirmation = null;
    }

    /** Ferme le site au public. */
    public function ouvrirLaMaintenance(ModeMaintenance $maintenance): void
    {
        abort_unless($this->acteur()->can('maintenance.mode'), 403);

        $donnees = $this->validate([
            // `nullable` : une maintenance sans heure annoncée est légitime — on
            // ne sait pas toujours combien de temps ça prendra, et inventer une
            // heure pour remplir le champ serait pire que de ne rien dire.
            'finAnnoncee' => ['nullable', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:500'],
        ], attributes: ['finAnnoncee' => 'heure de fin', 'message' => 'message']);

        try {
            $maintenance->activer(
                $this->acteur(),
                $donnees['finAnnoncee'] !== '' && $donnees['finAnnoncee'] !== null
                    ? CarbonImmutable::parse($donnees['finAnnoncee'])
                    : null,
                (string) ($donnees['message'] ?? ''),
            );
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        $this->confirmation = null;
        $this->toastSuccess('Site fermé au public. Le personnel connecté garde son accès.');
    }

    /** Rouvre, et prévient ceux qui attendaient. */
    public function fermerLaMaintenance(ModeMaintenance $maintenance, ExpediteurDeCourriels $courriels): void
    {
        abort_unless($this->acteur()->can('maintenance.mode'), 403);

        $debut = $maintenance->debut();
        $attendaient = $maintenance->desactiver($this->acteur());

        $duree = $debut !== null ? $debut->diffForHumans(now(), syntax: CarbonInterface::DIFF_ABSOLUTE) : 'quelques instants';

        foreach ($attendaient as $averti) {
            /*
             | Le courriel part par l'expéditeur commun, donc en FILE. Sur cPanel
             | mutualisé, cela suppose le cron `queue:work` — sans lui rien ne
             | part, et les gens attendent un message qui ne viendra pas.
             |
             | `maintenance_terminee` est verrouillé au catalogue : on ne peut
             | pas éteindre un envoi que la page publique a promis.
             */
            $courriels->envoyer(
                $this->destinataireFictif($averti),
                'maintenance_terminee',
                ['prenom' => '', 'duree' => $duree],
                route('public.home'),
            );

            $averti->forceFill(['notified_at' => now()])->save();
        }

        $this->confirmation = null;

        $this->toastSuccess($attendaient === []
            ? 'Site rouvert. Personne n’avait demandé à être prévenu.'
            : sprintf('Site rouvert. %d personne(s) prévenue(s) par courriel.', count($attendaient)));
    }

    /**
     * Un destinataire minimal pour l'expéditeur commun.
     *
     * ## Pourquoi ce détour
     *
     * `ExpediteurDeCourriels` prend un `User` : il vérifie les préférences de
     * notification, qui n'existent que sur un compte. Or un averti de
     * maintenance n'a PAS de compte — c'est souvent quelqu'un qui n'a jamais pu
     * se connecter, puisque le site était fermé.
     *
     * On lui fabrique donc un destinataire non persisté, porteur de la seule
     * chose dont l'expéditeur a besoin : une adresse. Il n'est jamais enregistré,
     * et `notification_preferences` reste vide — ce qui laisse les défauts, tous
     * à vrai. C'est correct : la personne a explicitement demandé ce message.
     */
    private function destinataireFictif(MaintenanceWatcher $averti): User
    {
        $destinataire = new User;
        $destinataire->forceFill([
            'email' => $averti->email,
            'name' => '',
            'notification_preferences' => [],
        ]);

        return $destinataire;
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
