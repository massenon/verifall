<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\Services\PertinenceDuFactCheck;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use App\Enums\VerdictFactCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Fact Check Tools — les vérifications déjà publiées ailleurs.
 *
 * C'est le moteur au poids le plus fort, et pour une raison simple : il ne
 * raisonne pas, il RETROUVE. Quand l'AFP ou Africa Check a déjà démenti une
 * rumeur, cela vaut mieux que l'intuition de n'importe quel modèle.
 *
 * Il s'ABSTIENT quand il ne trouve rien. Beaucoup de rumeurs béninoises n'ont
 * jamais été fact-checkées : traiter cette absence comme un signal reviendrait
 * à dire qu'une information est douteuse parce que personne ne s'en est encore
 * occupé.
 */
final class GoogleFactCheckAdapter extends AdaptateurHttp
{
    private const ENDPOINT = 'https://factchecktools.googleapis.com/v1alpha1/claims:search';

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::GoogleFactCheck;
    }

    protected function configure(): bool
    {
        return is_string(config('services.google_factcheck.key'))
            && config('services.google_factcheck.key') !== '';
    }

    /**
     * Une recherche vide sur le point d'appel réel.
     *
     * Le quota de l'API Fact Check Tools se compte en dizaines de milliers de
     * requêtes par jour : une sonde toutes les quinze minutes ne pèse rien, et
     * c'est le seul moyen de distinguer une clé absente d'une clé refusée.
     */
    protected function sonde(): array
    {
        return [
            self::ENDPOINT.'?languageCode=fr&pageSize=1&query=test&key='
                .urlencode((string) config('services.google_factcheck.key')),
            [],
        ];
    }

    public function __construct(
        private readonly PertinenceDuFactCheck $pertinence = new PertinenceDuFactCheck,
    ) {}

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);

        $reponse = Http::timeout($this->delai())->get(self::ENDPOINT, [
            // La requête est bornée : l'API cherche une affirmation, pas un
            // article entier, et un texte long ne rend jamais rien.
            'query' => mb_substr($entree->affirmation, 0, 200),
            'key' => (string) config('services.google_factcheck.key'),
            'languageCode' => 'fr',
        ]);

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return null;
        }

        $claims = (array) ($reponse->json()['claims'] ?? []);

        if ($claims === []) {
            /*
             | Aucun fact-check antérieur. Le moteur s'abstient : il rend un
             | verdict nul, donc écarté du score. Rendre « douteux » ferait
             | peser sur chaque rumeur locale le simple fait que personne ne
             | l'a encore vérifiée.
             */
            return VerdictMoteur::rendu(
                moteur: $this->nom(),
                verdict: null,
                confiance: 0,
                raisonnement: 'Aucune vérification antérieure trouvée dans la base ClaimReview.',
                latenceMs: $this->latence($debut),
            );
        }

        $preuves = [];
        $verdicts = [];
        $ecartes = 0;

        // Cinq au plus : au-delà, on empile des redites du même démenti.
        foreach (array_slice($claims, 0, 5) as $claim) {
            $revue = $claim['claimReview'][0] ?? [];
            $url = $revue['url'] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            /*
             | L'API cherche en TEXTE FLOU, et ce qu'elle rend n'est pas
             | nécessairement un fact-check de l'affirmation soumise.
             |
             | Sur « Le Président actuel du Bénin est Patrice Talon », elle a
             | rendu « Cette vidéo montre le soutien populaire du peuple béninois
             | au coup d'État » — un mot en commun. Ce démenti comptait comme un
             | vote « FAUX » adossé à une rédaction accréditée, donc pesant plus
             | lourd que deux moteurs concordants, et l'indice tombait de 95 % à
             | 64 % sans qu'une ligne du rapport ne l'explique.
             |
             | Un fact-check hors sujet est ÉCARTÉ, pas retenu en « neutre » : le
             | garder comme preuve ferait figurer dans « Sources et preuves
             | consultées » un article qui ne parle pas du sujet.
             */
            if (! $this->pertinence->concorde($entree->affirmation, $claim['text'] ?? null)) {
                $ecartes++;

                continue;
            }

            $verdict = $this->lireVerdict($revue['textualRating'] ?? null);
            $verdicts[] = $verdict;

            $preuves[] = new Preuve(
                url: $url,
                nomSource: (string) ($revue['publisher']['name'] ?? 'Vérificateur'),
                extrait: mb_substr((string) ($claim['text'] ?? ''), 0, 300),
                position: match ($verdict) {
                    VerdictFactCheck::Vrai => PositionPreuve::Confirme,
                    VerdictFactCheck::Faux => PositionPreuve::Contredit,
                    default => PositionPreuve::Neutre,
                },
                /*
                 | LE RÉFÉRENTIEL D'ABORD, le défaut ensuite.
                 |
                 | Ce paramètre valait `90` quoi qu'il arrive, au motif qu'une
                 | rédaction accréditée est la source la plus fiable du pipeline.
                 | Le motif est juste ; la conséquence ne l'était pas : les 408
                 | fiches tenues par la rédaction n'avaient aucun effet sur ces
                 | preuves, et toutes les rédactions de fact-checking recevaient
                 | la même note — y compris celles que la rédaction aurait voulu
                 | noter plus bas, ou plus haut.
                 |
                 | Le défaut reste 90, parce qu'un ClaimReview publié EST une
                 | preuve plutôt qu'une inférence. Mais une fiche expresse le
                 | surclasse désormais, dans les deux sens.
                 */
                fiabilite: $this->fiabiliteDeLaSource($url, 90),
                publieeLe: $this->dateDeLaRevue($revue['reviewDate'] ?? null),
            );
        }

        /*
         | Des fact-checks ont été trouvés, aucun ne porte sur cette
         | affirmation : le moteur S'ABSTIENT — verdict nul, écarté du score —
         | et le dit. Rendre `null` ferait apparaître « Indisponible » dans le
         | tableau, ce qui laisserait croire à une panne de l'API alors qu'elle
         | a parfaitement répondu.
         */
        if ($verdicts === []) {
            return VerdictMoteur::rendu(
                moteur: $this->nom(),
                verdict: null,
                confiance: 0,
                raisonnement: $ecartes === 0
                    ? 'Aucune vérification antérieure exploitable.'
                    : $ecartes.' vérification(s) antérieure(s) trouvée(s), mais portant sur une autre affirmation : écartée(s).',
                latenceMs: $this->latence($debut),
            );
        }

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            verdict: $this->majoritaire($verdicts),
            // La confiance croît avec le NOMBRE de rédactions concordantes,
            // sans jamais atteindre la certitude : un démenti peut vieillir.
            confiance: min(95, 60 + count($preuves) * 8),
            preuves: $preuves,
            raisonnement: count($preuves).' vérification(s) antérieure(s) trouvée(s) sur cette affirmation.',
            latenceMs: $this->latence($debut),
        );
    }

    /**
     * La date à laquelle la RÉDACTION a publié son démenti — jamais devinée.
     *
     * `claimReview.reviewDate` est le seul champ, parmi les neuf points de
     * construction d'une {@see Preuve} du pipeline, qui porte une vraie date
     * de publication fournie par la source elle-même. Un format absent ou
     * imprévu rend `null` plutôt qu'une approximation : une politique de
     * fraîcheur bâtie sur une date inventée serait pire que sur une date
     * manquante, parce qu'elle semblerait fondée.
     */
    private function dateDeLaRevue(mixed $brute): ?Carbon
    {
        if (! is_string($brute) || $brute === '') {
            return null;
        }

        try {
            return Carbon::parse($brute);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<?VerdictFactCheck>  $verdicts
     */
    private function majoritaire(array $verdicts): ?VerdictFactCheck
    {
        $comptes = [];

        foreach (array_filter($verdicts) as $verdict) {
            $comptes[$verdict->value] = ($comptes[$verdict->value] ?? 0) + 1;
        }

        if ($comptes === []) {
            return null;
        }

        arsort($comptes);

        return VerdictFactCheck::from((string) array_key_first($comptes));
    }
}
