<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutSignalementContenu;
use App\Models\ContentReport;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Signalements » — la file de modération des contenus signalés.
 *
 * ## Pourquoi cet écran existe
 *
 * Parce que la table `content_reports` se remplissait depuis des mois et que
 * **personne ne pouvait la lire** : aucun écran d'administration n'existait
 * pour aucun type de contenu. Le bouton « signaler » du forum fonctionnait, les
 * lignes s'écrivaient, et la file grossissait sans destinataire.
 *
 * C'est la panne la plus discrète qui soit : rien ne casse, rien n'alerte, et
 * ce sont les citoyens qui signalent une intox qui en paient le prix.
 *
 * ## Une seule file, tous les types
 *
 * Sujets, réponses, notes de communauté arrivent ensemble. Trois écrans
 * séparés auraient produit trois habitudes, et un type qu'on finit par ne plus
 * relever.
 *
 * ## Les plus anciens en haut
 *
 * Contrairement à tous les autres écrans du back-office. Une file de
 * modération se vide par le bas : trier du plus récent ferait glisser hors de
 * vue les signalements qui attendent depuis le plus longtemps.
 */
#[Layout('components.layouts.staff', ['title' => "Signalements · Vérif'All"])]
final class SignalementsContenu extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger la table DataTables après un tranchage. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'signalements-contenu-modifies';

    /** '' | 'pending' | 'upheld' | 'dismissed' */
    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    /** La classe du modèle signalé, ou '' pour tous. */
    #[Url(as: 'type', except: '')]
    public string $filtreType = '';

    public ?string $ouvert = null;

    public string $note = '';

    public function reinitialiser(): void
    {
        $this->reset('filtreStatut', 'filtreType');
    }

    public function ouvrir(string $uuid): void
    {
        $this->ouvert = $uuid;
        $this->note = (string) ($this->signalement()?->handling_note ?? '');
    }

    public function fermer(): void
    {
        $this->reset('ouvert', 'note');
    }

    /**
     * Tranche un signalement — retenu ou rejeté.
     *
     * Les deux issues sont journalisées de la même façon. Un rejet est une
     * décision autant qu'un retrait : c'est lui qui permet, plus tard, de
     * répondre à « pourquoi ce contenu est-il resté en ligne ? »
     */
    public function trancher(string $statut, DepotSignalementsContenu $depot, AuditLogService $audit): void
    {
        $this->authorize('update', ContentReport::class);

        $signalement = $this->signalement();
        $cible = StatutSignalementContenu::tryFrom($statut);

        if ($signalement === null || $cible === null) {
            return;
        }

        $this->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['note' => 'note de traitement']);

        $avant = $signalement->status->value;

        $depot->trancher($signalement, $cible, auth('staff')->user(), $this->note !== '' ? $this->note : null);

        $audit->record(
            action: 'content_report.'.$cible->value,
            module: 'moderation',
            acteur: auth('staff')->user(),
            cible: $signalement,
            avant: ['status' => $avant],
            apres: ['status' => $cible->value],
            cibleLibelle: class_basename((string) $signalement->reportable_type),
        );

        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Signalement '.mb_strtolower($cible->libelle()).'.');
        $this->fermer();
    }

    public function render(DepotSignalementsContenu $depot): View
    {
        $this->authorize('viewAny', ContentReport::class);

        return view('livewire.staff.signalements-contenu', [
            'compteurs' => $depot->compteurs(),
            'statuts' => StatutSignalementContenu::cases(),
            'types' => $depot->typesPresents(),
            'signalementOuvert' => $this->signalement(),
        ]);
    }

    private function signalement(): ?ContentReport
    {
        return $this->ouvert === null
            ? null
            : app(DepotSignalementsContenu::class)->parUuid($this->ouvert);
    }
}
