<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Lms\Services\EspaceDuMentor;
use App\Core\Lms\Services\StatistiquesDeCours;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Progression » — l'outil de rattrapage du mentor.
 *
 * ## Ce qu'il ajoute à « Mes Étudiants »
 *
 * Le trombinoscope dit qui est là. Celui-ci dit **qui décroche, et où** :
 * meilleur score de quiz, état de décrochage, relance, export de la cohorte.
 * Les deux partagent leur source et pas leur usage.
 *
 * ## « Décroché » est DÉRIVÉ, pas stocké
 *
 * Une inscription commencée, jamais terminée, et muette depuis un mois. Aucune
 * colonne ne le dit, et c'est délibéré : un statut stocké devient faux le jour
 * où l'on change le seuil, et il faut alors le recalculer sur toute la table.
 * Dérivé, le seuil se change en une ligne.
 *
 * ## L'export, et sa borne
 *
 * `Exporter la cohorte` rend un CSV. Il est borné à
 * {@see EspaceDuMentor::EXPORT_MAXIMUM} lignes : au-delà, ce n'est plus un
 * fichier qu'on ouvre, et il tombe en mémoire avant d'être écrit. La borne est
 * annoncée à l'écran plutôt que découverte.
 */
#[Layout('components.layouts.staff', ['title' => "Progression · Vérif'All"])]
final class ProgressionDesApprenants extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cours', except: '')]
    public string $filtreCours = '';

    /** '' | 'en-cours' | 'termine' | 'decroche' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    public function mount(): void
    {
        abort_unless($this->acteur()->can('student.progress'), 403);
    }

    public function render(EspaceDuMentor $espace): View
    {
        $mentor = $this->acteur();
        $toutVoir = $this->voitTout();

        $inscrits = $espace->cohorte(
            $mentor, $toutVoir, $this->recherche, $this->filtreCours, $this->filtreEtat, 20,
        );

        return view('livewire.staff.progression-des-apprenants', [
            'inscrits' => $inscrits,
            // Chargés EN UNE FOIS pour la page : une requête par ligne ferait
            // vingt allers-retours, et le dépôt interdit le chargement paresseux.
            /*
             | `collect($inscrits->items())` et non `getCollection()` : ce dernier
             | vit sur l'implémentation, pas sur le contrat rendu par le service.
             | S'y fier compile aujourd'hui et casse le jour où l'on change de
             | paginateur.
             */
            'scores' => $espace->meilleursScores(collect($inscrits->items())),
            'mesCours' => $espace->coursDuMentor($mentor, $toutVoir),
            'exportMaximum' => EspaceDuMentor::EXPORT_MAXIMUM,
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreCours', 'filtreEtat'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCours', 'filtreEtat');
        $this->resetPage();
    }

    /**
     * Relance un inscrit qui a décroché.
     *
     * Le service tient le repos de sept jours et refuse ceux qui ont fini :
     * l'écran ne fait que passer la main. Dupliquer la règle ici la ferait
     * diverger le jour où l'un des deux écrans change.
     */
    public function relancer(
        string $uuid,
        StatistiquesDeCours $statistiques,
        EspaceDuMentor $espace,
        AuditLogService $audit,
    ): void {
        abort_unless($this->acteur()->can('student.progress'), 403);

        $auteur = $this->acteur();

        $inscription = Enrollment::query()
            ->with(['user', 'course'])
            ->where('uuid', $uuid)
            ->first();

        /*
         | La cible doit appartenir à MES cours. Sans ce contrôle, un uuid deviné
         | relancerait l'inscrit d'un collègue — et le courriel partirait sous la
         | signature du mauvais formateur.
         */
        if ($inscription === null
            || ! in_array((int) $inscription->course_id, $espace->idsDesCours($auteur, $this->voitTout()), true)) {
            $this->toastError('Cette inscription n’existe plus.');

            return;
        }

        try {
            $statistiques->relancer($inscription, $auteur);
        } catch (RuntimeException $refus) {
            // Le service porte les règles — repos de sept jours, cours déjà
            // terminé — et son message les explique. Les réécrire ici les ferait
            // diverger de ceux de l'écran « Statistiques du cours ».
            $this->toastError($refus->getMessage());

            return;
        }

        $audit->record(
            action: 'course.reminder',
            module: 'academie',
            acteur: $auteur,
            cible: $inscription->course,
            apres: ['citoyen' => $inscription->user?->uuid, 'avancement' => (int) $inscription->progress],
            cibleLibelle: (string) $inscription->course?->title,
        );

        // Tournure impersonnelle : « X est relancé » imposerait un accord, donc
        // un genre qu'on ne connaît pas et qu'on n'a pas à supposer.
        $this->toastSuccess(sprintf('Relance envoyée à %s.', $inscription->user?->name ?? 'cet inscrit'));
    }

    /** Le CSV de la cohorte filtrée. */
    public function exporter(EspaceDuMentor $espace): StreamedResponse
    {
        abort_unless($this->acteur()->can('student.progress'), 403);

        $lignes = $espace->pourExport(
            $this->acteur(), $this->voitTout(), $this->recherche, $this->filtreCours, $this->filtreEtat,
        );

        $scores = $espace->meilleursScores($lignes);
        $nom = 'cohorte-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($lignes, $scores): void {
            $sortie = fopen('php://output', 'wb');

            /*
             | Le BOM UTF-8. Sans lui, Excel lit le fichier en ANSI et rend
             | « Aïcha » en « AÃ¯cha » — sur un export de noms de personnes,
             | c'est la première chose qu'on remarque et la dernière qu'on
             | pense à corriger.
             */
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, ['Apprenant', 'Adresse', 'Cours', 'Progression (%)', 'Meilleur quiz (%)', 'Dernière activité', 'Statut'], ';');

            foreach ($lignes as $inscription) {
                fputcsv($sortie, [
                    $inscription->user?->name ?? 'Compte supprimé',
                    $inscription->user?->email ?? '',
                    $inscription->course?->title ?? '',
                    (int) $inscription->progress,
                    $scores[$inscription->user_id.':'.$inscription->course_id] ?? '',
                    $inscription->last_activity_at?->format('d/m/Y') ?? '',
                    $inscription->completed_at !== null ? 'Terminé' : 'En cours',
                ], ';');
            }

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function voitTout(): bool
    {
        return $this->acteur()->can('user.view');
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
