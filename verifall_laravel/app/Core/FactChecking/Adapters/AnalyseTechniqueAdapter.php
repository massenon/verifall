<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\FactChecking\Services\DomaineDeLaSource;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use Illuminate\Support\Facades\Http;

/**
 * Analyse technique d'un lien — sans aucune clé d'API.
 *
 * Trois signaux durs, qui ne dépendent d'aucun modèle et ne coûtent rien :
 *
 *   · l'ÂGE du domaine. Un site créé il y a trois jours qui annonce une
 *     décision gouvernementale est un signal fort, indépendamment du texte.
 *   · la présence dans les ARCHIVES du web. Une page prétendument ancienne
 *     dont aucune capture n'existe pose une question.
 *   · le domaine lui-même : usurpation de nom officiel, extension inhabituelle.
 *
 * Ces signaux ne suffisent jamais à conclure seuls — d'où une confiance
 * plafonnée. Un domaine récent peut publier une information exacte.
 *
 * ## Lot 5 — le référentiel passe AVANT les heuristiques
 *
 * Les listes ci-dessous ne connaissent aucune source : elles reconnaissent des
 * FORMES. C'était leur limite, et elle produisait un faux positif net — la
 * règle d'usurpation pénalisait tout domaine contenant `gov`, `cdc.gov` et
 * `sante.gouv.fr` compris, avec pour seule exception `gouv.bj`.
 *
 * Une source que la rédaction a expressément référencée n'a pas à être devinée.
 * Le référentiel est donc consulté d'abord, et il tranche.
 */
final class AnalyseTechniqueAdapter extends AdaptateurHttp
{
    public function __construct(
        private readonly DepotSourcesFiables $sources,
        private readonly DomaineDeLaSource $domaines = new DomaineDeLaSource,
    ) {}

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::AnalyseTechnique;
    }

    public function supporte(TypeContenu $type): bool
    {
        return $type === TypeContenu::Lien;
    }

    /** Aucune clé requise : ce moteur fonctionne partout. */
    protected function configure(): bool
    {
        return true;
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);
        $url = trim($entree->affirmation);
        $domaine = parse_url($url, PHP_URL_HOST);

        if (! is_string($domaine) || $domaine === '') {
            return null;
        }

        $signaux = [];
        $suspicion = 0;

        // ── le référentiel de la rédaction, avant toute heuristique ───────
        $referencee = $this->sources->trouverParDomaine($domaine);

        if ($referencee !== null) {
            $famille = $referencee->categorie?->name;

            $signaux[] = 'Source référencée par la rédaction : '.$referencee->name
                .($famille !== null ? ' ('.$famille.')' : '')
                .', crédibilité '.$referencee->credibility.' / 100.';
        }

        // ── usurpation de nom officiel ────────────────────────────────────
        /*
         | Sautée pour une source référencée : la question « ce domaine
         | usurpe-t-il une institution ? » a déjà été tranchée à la main, et par
         | quelqu'un qui savait. La deviner à nouveau ne peut que la contredire.
         */
        if ($referencee === null && ! $this->estUnSuffixeInstitutionnel($domaine)) {
            foreach (['gouv', 'gov', 'presidence', 'ministere', 'officiel'] as $mot) {
                if (str_contains($domaine, $mot)) {
                    $signaux[] = 'Le domaine évoque une institution sans en être une (« '.$mot.' »).';
                    $suspicion += 35;
                    break;
                }
            }
        }

        // ── extension inhabituelle ────────────────────────────────────────
        foreach (['.tk', '.ml', '.ga', '.cf', '.gq', '.top', '.xyz'] as $extension) {
            if (str_ends_with($domaine, $extension)) {
                $signaux[] = 'Extension de domaine fréquemment utilisée pour des sites éphémères ('.$extension.').';
                $suspicion += 25;
                break;
            }
        }

        // ── antériorité dans les archives du web ──────────────────────────
        $premiereCapture = $this->premiereCapture($domaine);

        if ($premiereCapture === null) {
            $signaux[] = 'Aucune archive du site n\'a été trouvée : le domaine est très récent ou confidentiel.';
            $suspicion += 20;
        } else {
            $signaux[] = 'Première archive du site : '.$premiereCapture.'.';
        }

        // `publieeLe` reste absent, délibérément : la première capture
        // archivée est la date où LA MACHINE A VU le domaine, pas celle où
        // son contenu a été publié. Les confondre inventerait une date.
        $preuves = [new Preuve(
            url: 'https://web.archive.org/web/*/'.$domaine,
            nomSource: 'Internet Archive',
            extrait: $premiereCapture !== null
                ? 'Le domaine est archivé depuis '.$premiereCapture.'.'
                : 'Aucune capture archivée pour ce domaine.',
            position: $premiereCapture !== null ? PositionPreuve::Neutre : PositionPreuve::Contredit,
            fiabilite: 85,
        )];

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            // Ces signaux qualifient le SUPPORT, pas l'affirmation : ils
            // rendent « trompeur » au pire, jamais « faux ». Un site douteux
            // peut relayer une information exacte.
            verdict: $suspicion >= 40 ? VerdictFactCheck::Trompeur : null,
            // Plafonnée : la technique éclaire, elle ne tranche pas.
            confiance: min(70, $suspicion),
            preuves: $preuves,
            raisonnement: implode(' ', $signaux),
            latenceMs: $this->latence($debut),
        );
    }

    /**
     * Ce domaine porte-t-il un suffixe RÉSERVÉ aux institutions ?
     *
     * ## Le faux positif que cette garde supprime
     *
     * La règle d'usurpation cherchait `gouv` ou `gov` n'importe où dans le
     * domaine, et n'exemptait que `gouv.bj`. Elle frappait donc exactement les
     * sources qu'un vérificateur consulte en premier : `cdc.gov`, `usa.gov`,
     * `sante.gouv.fr`, `www.gov.uk` — trente-cinq points de suspicion pour
     * usurpation d'identité institutionnelle, infligés à l'institution.
     *
     * La règle n'était pas mal réglée, elle était mal posée : `gouv` dans
     * `actu-gouv-benin.info` est un signal, `gouv` dans `sante.gouv.fr` est
     * l'inverse d'un signal. Ce qui les sépare n'est pas la présence du mot,
     * c'est sa POSITION.
     *
     * Le motif vit dans {@see DomaineDeLaSource} : `GoogleCustomSearchAdapter`
     * pose la même question pour sa note de crédibilité, et deux motifs
     * divergents donneraient deux réponses à la même question.
     */
    private function estUnSuffixeInstitutionnel(string $domaine): bool
    {
        return $this->domaines->estInstitutionnel($domaine);
    }

    /**
     * Date de la première capture archivée, si elle existe.
     */
    private function premiereCapture(string $domaine): ?string
    {
        $reponse = Http::timeout(8)->get('https://archive.org/wayback/available', [
            'url' => $domaine,
            'timestamp' => '20100101',
        ]);

        if ($reponse->failed()) {
            // Les archives sont indisponibles : on ne conclut RIEN. Traiter
            // cette panne comme une absence d'archive accuserait un site sain.
            return null;
        }

        $horodatage = data_get($reponse->json(), 'archived_snapshots.closest.timestamp');

        if (! is_string($horodatage) || mb_strlen($horodatage) < 6) {
            return null;
        }

        return mb_substr($horodatage, 6, 2).'/'.mb_substr($horodatage, 4, 2).'/'.mb_substr($horodatage, 0, 4);
    }
}
