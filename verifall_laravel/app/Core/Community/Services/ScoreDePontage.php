<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Console\Commands\CalculerLesScoresDePontage;
use App\Core\Application\Contracts\DepotNotes;
use App\Enums\StatutNoteCommunaute;

/**
 * L'algorithme de PONTAGE — le cœur du mécanisme des notes de communauté.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POURQUOI CE N'EST PAS UN VOTE MAJORITAIRE
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Un vote majoritaire fait remonter ce qui plaît au camp le plus nombreux. Sur
 * un forum où l'on parle de santé publique et de politique béninoise, cela
 * revient à donner le micro au groupe le plus mobilisé — c'est-à-dire à
 * fabriquer la chambre d'écho qu'on prétend combattre.
 *
 * Un algorithme de pontage fait remonter ce sur quoi des camps OPPOSÉS
 * s'accordent. Ce n'est pas la même chose que la vérité, mais c'est le
 * meilleur signal disponible sans arbitre : quand des gens qui ne sont
 * d'accord sur rien trouvent la même note utile, c'est rarement pour des
 * raisons partisanes.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LE CALCUL, EN TROIS TEMPS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * 1. ACCORD ENTRE DEUX VOTANTS. Pour chaque paire de personnes ayant voté sur
 *    au moins {@see self::NOTES_COMMUNES_MINIMUM} notes communes :
 *
 *        accord(a,b) = (votes identiques − votes opposés) ÷ votes communs
 *
 *    Le résultat va de −1 (ils s'opposent systématiquement : camps adverses) à
 *    +1 (ils votent toujours pareil : même bord). Une paire sans historique
 *    commun suffisant n'a PAS d'accord : elle vaut `null`, et non zéro. La
 *    confondre avec une opposition parfaite ferait publier une note sur le
 *    seul fait que ses approbateurs sont des inconnus l'un pour l'autre.
 *
 * 2. DIVERGENCE DES APPROBATEURS D'UNE NOTE. Parmi les gens qui ont trouvé la
 *    note utile, on moyenne le désaccord habituel de chaque paire :
 *
 *        divergence(n) = moyenne des (1 − accord(a,b)) ÷ 2, sur les paires
 *                        d'approbateurs dont l'accord est connu
 *
 *    0 = tous du même bord ; 1 = camps parfaitement opposés.
 *
 * 3. SCORE DE PONTAGE. La divergence seule ne suffit pas : une note approuvée
 *    par deux adversaires mais rejetée par vingt personnes n'a rien ponté du
 *    tout. On la pondère donc par l'utilité perçue :
 *
 *        utilité(n)  = votes utiles ÷ votes totaux
 *        pontage(n)  = utilité(n) × divergence(n)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LES DEUX PIÈGES, TRAITÉS EXPLICITEMENT
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * · DÉMARRAGE À FROID. Avec cinq votes sur tout le forum, aucune polarisation
 *   n'est mesurable et l'algorithme ne peut rien dire — ni oui, ni non. Deux
 *   garde-fous : un plancher de votes ({@see self::VOTES_MINIMUM}), et un
 *   score NUL quand aucune paire d'approbateurs n'a d'historique. Dans les
 *   deux cas la note reste EN ATTENTE, jamais « non retenue » : l'écran doit
 *   dire l'attente, pas laisser croire à un rejet.
 *
 * · COÛT. Comparer chaque paire de votants est quadratique en nombre de
 *   votants — O(V² × N). Ce calcul ne peut pas vivre dans une requête web : il
 *   tourne dans {@see CalculerLesScoresDePontage} et son
 *   résultat est STOCKÉ sur la note.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CE QUE CETTE VERSION N'EST PAS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * L'algorithme publié par X repose sur une factorisation matricielle qui
 * apprend une position sur un axe latent pour chaque votant. Ici, on mesure
 * l'accord par paires — même intention, calcul directement lisible, et aucun
 * hyperparamètre à régler à l'aveugle. Le prix à payer est la complexité
 * quadratique, acceptable à l'échelle d'un forum national et bornée par la
 * planification. Voir `docs/ARBITRAGES.md`, entrée 161.
 */
final class ScoreDePontage
{
    /**
     * Nombre de votes en dessous duquel AUCUNE note n'est publiée.
     *
     * POURQUOI 5. En dessous, il n'existe presque pas de paires
     * d'approbateurs — deux votants n'en forment qu'une — et une seule paire
     * suffirait à décider de la publication. Trois amis coordonnés
     * publieraient ce qu'ils veulent. À cinq votes, on dispose d'au plus dix
     * paires, assez pour que la moyenne de divergence cesse d'être le fait
     * d'une personne.
     *
     * SI ON L'AUGMENTE : moins de notes publiées, et surtout des notes qui
     * arrivent trop tard — le contexte utile sur une rumeur qui circule sur
     * WhatsApp vaut le premier jour, pas la semaine suivante. À 20, sur un
     * forum de quelques centaines de membres actifs, presque aucune note ne
     * franchirait jamais le seuil.
     *
     * SI ON LE DIMINUE : le démarrage à froid revient, et avec lui la
     * possibilité de publier une note sur l'accord de deux comptes.
     */
    public const VOTES_MINIMUM = 5;

    /**
     * Nombre de notes votées en commun pour qu'un accord entre deux personnes
     * soit tenu pour mesuré.
     *
     * POURQUOI 2. Sur une seule note commune, l'accord vaut mécaniquement +1
     * ou −1 : ce n'est pas une tendance, c'est une coïncidence. Deux notes
     * autorisent déjà une valeur intermédiaire (0), donc une nuance.
     *
     * SI ON L'AUGMENTE : les accords deviennent plus fiables, mais on connaît
     * beaucoup moins de paires — et un forum jeune n'a plus aucune paire
     * qualifiée, donc plus aucune note publiable.
     */
    public const NOTES_COMMUNES_MINIMUM = 2;

    /**
     * Seuil de publication du score de pontage.
     *
     * POURQUOI 0,25. Le score est le produit de l'utilité (part de votes
     * « utile ») par la divergence (éloignement habituel des approbateurs).
     * Deux chemins mènent au seuil, et les deux sont exigeants :
     *
     *   · unanimité (utilité = 1) : il faut encore une divergence ≥ 0,25,
     *     c'est-à-dire des approbateurs qui, ailleurs, se contredisent au
     *     moins une fois sur quatre. Un bloc homogène, lui, plafonne à une
     *     divergence proche de zéro et ne passe jamais, quel que soit le
     *     nombre de ses membres — c'est exactement le comportement recherché.
     *   · camps parfaitement opposés (divergence = 1) : il faut encore un quart
     *     de votes favorables, donc une note qui ne soit pas massivement
     *     rejetée.
     *
     * SI ON L'AUGMENTE (0,5) : il faudrait à la fois une opposition presque
     * parfaite ET une majorité franche. Presque rien ne se publierait, et le
     * mécanisme deviendrait décoratif.
     *
     * SI ON LE DIMINUE (0,1) : une divergence de 0,1 se produit entre gens du
     * même bord qui divergent une fois sur vingt. On retomberait sur un vote
     * majoritaire déguisé — avec l'apparence de rigueur en plus, ce qui est
     * pire que le vote majoritaire assumé.
     */
    public const SEUIL_DE_PUBLICATION = 0.25;

    public function __construct(private readonly DepotNotes $depot) {}

    /**
     * Recalcule et ENREGISTRE le score de toutes les notes évaluables.
     *
     * @return array{evaluees: int, publiees: int, en_attente: int, non_retenues: int}
     */
    public function recalculerTout(): array
    {
        $resultats = $this->scores($this->depot->matriceDesVotes());

        $bilan = ['evaluees' => 0, 'publiees' => 0, 'en_attente' => 0, 'non_retenues' => 0];

        foreach ($this->depot->notesAEvaluer() as $note) {
            $resultat = $resultats[(int) $note->id] ?? [
                'score' => null,
                'statut' => StatutNoteCommunaute::EnAttente,
            ];

            $this->depot->appliquerScore($note, $resultat['score'], $resultat['statut']);

            $bilan['evaluees']++;
            $bilan[match ($resultat['statut']) {
                StatutNoteCommunaute::Publiee => 'publiees',
                StatutNoteCommunaute::EnAttente => 'en_attente',
                StatutNoteCommunaute::NonRetenue => 'non_retenues',
            }]++;
        }

        return $bilan;
    }

    /**
     * Le calcul pur : des votes en entrée, un verdict par note en sortie.
     *
     * Aucune dépendance à la base — c'est ce qui permet de l'éprouver sur des
     * configurations de votants construites à la main, y compris celles qu'un
     * jeu de démonstration ne produirait jamais.
     *
     * @param  array<int, array<int, bool>>  $votesParNote  note => [votant => utile]
     * @return array<int, array{score: float|null, statut: StatutNoteCommunaute}>
     */
    public function scores(array $votesParNote): array
    {
        $parVotant = $this->votesParVotant($votesParNote);
        $accords = $this->accordsEntreVotants($parVotant);

        $resultats = [];

        foreach ($votesParNote as $noteId => $votes) {
            $resultats[$noteId] = $this->evaluerUneNote($votes, $accords);
        }

        return $resultats;
    }

    /**
     * Évalue une note isolée.
     *
     * @param  array<int, bool>  $votes  votant => utile
     * @param  array<string, float>  $accords
     * @return array{score: float|null, statut: StatutNoteCommunaute}
     */
    private function evaluerUneNote(array $votes, array $accords): array
    {
        $total = count($votes);

        // DÉMARRAGE À FROID, premier garde-fou. Trop peu de votes : on ne dit
        // NI oui NI non. Écrire « non retenue » ici serait un mensonge — la
        // note n'a pas été jugée, elle n'a pas été lue.
        if ($total < self::VOTES_MINIMUM) {
            return ['score' => null, 'statut' => StatutNoteCommunaute::EnAttente];
        }

        $approbateurs = array_keys(array_filter($votes));
        $utilite = count($approbateurs) / $total;

        /*
         | Moins de deux approbateurs : il n'y a aucune paire, donc aucun pont
         | possible. Le score est 0 et non « inconnu » : avec cinq votes et un
         | seul approbateur, la note A été évaluée, et elle n'a pas convaincu.
         */
        if (count($approbateurs) < 2) {
            return ['score' => 0.0, 'statut' => StatutNoteCommunaute::NonRetenue];
        }

        $divergences = [];

        foreach ($approbateurs as $i => $a) {
            foreach (array_slice($approbateurs, $i + 1) as $b) {
                $accord = $accords[$this->clePaire((int) $a, (int) $b)] ?? null;

                // Paire sans historique commun : on ne sait pas si ces deux
                // personnes sont du même bord. On l'IGNORE plutôt que de la
                // compter comme une divergence — sinon deux inconnus
                // suffiraient à faire un pont.
                if ($accord === null) {
                    continue;
                }

                $divergences[] = (1.0 - $accord) / 2.0;
            }
        }

        // DÉMARRAGE À FROID, second garde-fou. Aucune paire d'approbateurs
        // n'a d'historique : la polarisation n'est pas mesurable, donc le pont
        // n'est ni franchi ni manqué. La note attend.
        if ($divergences === []) {
            return ['score' => null, 'statut' => StatutNoteCommunaute::EnAttente];
        }

        $divergence = array_sum($divergences) / count($divergences);
        $score = round($utilite * $divergence, 4);

        return [
            'score' => $score,
            'statut' => $score >= self::SEUIL_DE_PUBLICATION
                ? StatutNoteCommunaute::Publiee
                : StatutNoteCommunaute::NonRetenue,
        ];
    }

    /**
     * Retourne la matrice par VOTANT plutôt que par note.
     *
     * @param  array<int, array<int, bool>>  $votesParNote
     * @return array<int, array<int, bool>> votant => [note => utile]
     */
    private function votesParVotant(array $votesParNote): array
    {
        $parVotant = [];

        foreach ($votesParNote as $noteId => $votes) {
            foreach ($votes as $votant => $utile) {
                $parVotant[$votant][$noteId] = $utile;
            }
        }

        return $parVotant;
    }

    /**
     * Accord de chaque paire de votants, dans [−1, 1].
     *
     * C'est ici qu'est la complexité quadratique, et c'est pour cette boucle
     * que le calcul vit dans une commande planifiée.
     *
     * @param  array<int, array<int, bool>>  $parVotant
     * @return array<string, float>
     */
    private function accordsEntreVotants(array $parVotant): array
    {
        $votants = array_keys($parVotant);
        $accords = [];

        foreach ($votants as $i => $a) {
            foreach (array_slice($votants, $i + 1) as $b) {
                $communes = array_intersect_key($parVotant[$a], $parVotant[$b]);

                if (count($communes) < self::NOTES_COMMUNES_MINIMUM) {
                    // Pas d'entrée du tout : l'ABSENCE de clé signifie
                    // « inconnu ». Une valeur de 0 signifierait « ni d'accord
                    // ni en désaccord », ce qui est une information.
                    continue;
                }

                $identiques = 0;

                foreach (array_keys($communes) as $noteId) {
                    if ($parVotant[$a][$noteId] === $parVotant[$b][$noteId]) {
                        $identiques++;
                    }
                }

                $nombre = count($communes);
                $accords[$this->clePaire((int) $a, (int) $b)] = ($identiques - ($nombre - $identiques)) / $nombre;
            }
        }

        return $accords;
    }

    /** Clé symétrique : l'accord de a avec b est celui de b avec a. */
    private function clePaire(int $a, int $b): string
    {
        return $a < $b ? $a.':'.$b : $b.':'.$a;
    }
}
