<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Enums\StatutSignalementContenu;
use App\Models\ContentReport;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * La file des contenus signalés, telle que DataTables la reçoit.
 *
 * Clone du pilote `TableauDesUtilisateurs` (contrat : `docs/DATATABLES_CONTRAT.md`).
 *
 * ## Ce que ce service décide
 *
 * 1. **Qui voit la file** : `viewAny` de `ContentReportPolicy`
 *    (`content_report.view` — administrateur et modérateur, jamais le mentor),
 *    rejoué ici même si la route la garde déjà.
 * 2. **Pas d'export.** `MatricePermissions` ne déclare aucun
 *    `content_report.export` ; consulter n'emporte pas le droit d'extraire
 *    (§8 du contrat). `export=1` répond 403.
 * 3. **Ce qui sort** : une liste FERMÉE de champs, construite champ par champ.
 *    Le motif et le détail sont du texte saisi par le public, le contenu visé
 *    aussi : tout part en TEXTE brut, tronqué ici, et le navigateur l'échappe.
 *    Ni l'adresse du signaleur, ni la note de décision, ni l'identifiant du
 *    contenu visé, ni le nom de sa classe PHP.
 */
final readonly class TableauDesSignalementsContenu
{
    /** Longueur des extraits envoyés au navigateur. La lecture complète est dans le panneau. */
    public const EXTRAIT_MAXIMUM = 140;

    public function __construct(
        private DepotSignalementsContenu $depot,
        private VitesseDePropagation $propagation,
    ) {}

    /**
     * Les filtres rapides, et leurs seules valeurs acceptées.
     *
     * Tous adossés à ce qui EXISTE : l'enum `StatutSignalementContenu`, la
     * liste fermée `SignalementDeContenu::motifs()`, les types réellement
     * présents en base (ceux que l'écran proposait déjà sous `?type=`), et
     * `created_at`.
     *
     * @return array<string, list<string>|'date'>
     */
    public function filtresAutorises(): array
    {
        return [
            'statut' => array_column(StatutSignalementContenu::cases(), 'value'),
            'motif' => array_keys(SignalementDeContenu::motifs()),
            'type' => $this->depot->typesPresents(),
            'recu_du' => 'date',
            'recu_au' => 'date',
        ];
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(RequeteDataTables $requete, User $acteur): array
    {
        $porte = Gate::forUser($acteur);
        $porte->authorize('viewAny', ContentReport::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour les signalements de contenu.");
        }

        $page = $this->depot->pourTableau($requete);

        // Une requête agrégée pour toute la page, pas une par ligne.
        $flambees = $this->propagation->pourLesSignalements();

        // La Policy ne dépend pas de la ligne : décidée une fois.
        $peutTrancher = $porte->allows('update', ContentReport::class);

        $lignes = [];
        foreach ($page->lignes as $signalement) {
            $lignes[] = $this->ligne($signalement, $flambees, $peutTrancher);
        }

        return [
            'draw' => $requete->draw,
            'recordsTotal' => $page->total,
            'recordsFiltered' => $page->filtres,
            'data' => $lignes,
            'exportTronque' => false,
        ];
    }

    /**
     * @param  array<string, array{alertes: int, premiere: CarbonInterface}>  $flambees
     * @return array<string, mixed>
     */
    private function ligne(ContentReport $signalement, array $flambees, bool $peutTrancher): array
    {
        $cle = $signalement->reportable_type.'|'.$signalement->reportable_id;
        $flambee = $flambees[$cle] ?? null;
        $motifs = SignalementDeContenu::motifs();
        $motif = (string) $signalement->reason;

        return [
            'uuid' => (string) $signalement->uuid,
            'type' => class_basename((string) $signalement->reportable_type),
            'extrait' => self::extraitDuContenu($signalement->reportable),
            'contenuSupprime' => $signalement->reportable === null,
            'detail' => self::extrait($signalement->detail),
            // La mesure, jamais une conclusion : le texte dit ce qui est compté.
            'propagation' => $flambee !== null
                ? $this->propagation->resume($flambee['alertes'], $flambee['premiere'])
                : 'Une seule alerte',
            'flambee' => $flambee !== null,
            // Un motif ancien, écrit en clair avant la liste fermée, s'affiche tel quel.
            'motif' => $motifs[$motif] ?? $motif,
            // `null` : compte supprimé, ou détection automatique (mot surveillé).
            'signalePar' => $signalement->reporter?->name,
            'statut' => $signalement->status->value,
            'statutLibelle' => $signalement->status->libelle(),
            // La clé sémantique de `<x-badge-statut>`, pas une classe CSS : le
            // navigateur la traduit par une table fermée.
            'statutBadge' => $signalement->status->cleBadge(),
            'recuLe' => $signalement->created_at?->toDateString(),
            'recuLeTexte' => $signalement->created_at?->translatedFormat('j M Y'),
            'actions' => [
                'ouvrir' => true,
                'trancher' => $peutTrancher && $signalement->status === StatutSignalementContenu::EnAttente,
            ],
        ];
    }

    /** Le titre, ou le corps, du contenu visé — en texte, tronqué. */
    private static function extraitDuContenu(?Model $contenu): ?string
    {
        if ($contenu === null) {
            return null;
        }

        $texte = $contenu->getAttribute('title') ?? $contenu->getAttribute('body') ?? $contenu->getAttribute('content');

        return self::extrait(is_string($texte) ? $texte : null);
    }

    private static function extrait(?string $texte): ?string
    {
        if ($texte === null) {
            return null;
        }

        // Balises retirées, entités décodées : le navigateur reçoit du TEXTE et
        // l'échappe. Envoyer `&amp;` le ferait afficher tel quel.
        $propre = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($texte), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $propre === '' ? null : Str::limit($propre, self::EXTRAIT_MAXIMUM);
    }
}
