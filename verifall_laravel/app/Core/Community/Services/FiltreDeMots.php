<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Enums\ActionMotClef;
use App\Models\ModerationKeyword;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Confronte un message aux mots surveillés par la modération.
 *
 * ## Deux actions, et la distinction est le cœur du dispositif
 *
 * Bloquer tout ce qui inquiète transformerait le forum en mur. Sur un site de
 * fact-checking, les mots d'une rumeur sont précisément ceux qu'on vient
 * signaler : quelqu'un qui écrit « on dit que le vaccin rend stérile » ne
 * propage pas cette idée, il demande qu'on la vérifie. Le bloquer serait
 * refuser le service au moment où il sert.
 *
 * Ne rien bloquer, à l'inverse, laisserait passer l'injure et l'appel à la
 * violence. D'où deux actions : `Bloquer`, réservé à ce qui n'a aucune raison
 * d'être, et `Signaler`, qui publie et met la file de modération dans la
 * boucle.
 *
 * ## La comparaison, et pourquoi elle est plus subtile qu'il n'y paraît
 *
 * Elle ignore la casse ET les accents. Sans cela, un mot surveillé
 * s'esquiverait en retirant une cédille, ce que fait n'importe qui de
 * mal intentionné au bout de deux essais.
 *
 * Elle exige en revanche des LIMITES DE MOT. Chercher une sous-chaîne brute
 * ferait qu'un mot surveillé attraperait tous les termes qui le contiennent,
 * et la liste deviendrait vite plus dangereuse que ce qu'elle surveille.
 *
 * ## Le cache, et sa durée
 *
 * Une minute. La liste est lue à CHAQUE publication ; sans cache, chaque
 * message coûterait une requête de plus. Mais elle se modifie au rythme de
 * l'actualité : une rumeur nouvelle apparaît un mardi matin, et l'équipe qui
 * ajoute un mot doit le voir agir tout de suite, pas au bout d'un quart
 * d'heure.
 */
final class FiltreDeMots
{
    private const CLEF_CACHE = 'moderation.mots-surveilles';

    /**
     * Le verdict du filtre pour un texte donné.
     *
     * @return array{action: ActionMotClef, termes: list<string>}|null
     *                                                                 `null` quand rien ne correspond, ce qui est le cas de l'immense
     *                                                                 majorité des messages.
     */
    public function examiner(string $texte): ?array
    {
        $normalise = $this->normaliser($texte);

        if ($normalise === '') {
            return null;
        }

        $trouves = [];
        $action = null;

        foreach ($this->motsSurveilles() as $mot) {
            if (! $this->contient($normalise, $mot['normalise'])) {
                continue;
            }

            $trouves[] = $mot['terme'];

            /*
             | LE PLUS SÉVÈRE L'EMPORTE. Un message qui touche à la fois un mot
             | bloquant et un mot signalé est bloqué : l'inverse laisserait
             | passer le premier au prétexte que le second est plus doux.
             */
            if ($mot['action'] === ActionMotClef::Bloquer) {
                $action = ActionMotClef::Bloquer;
            } elseif ($action === null) {
                $action = ActionMotClef::Signaler;
            }
        }

        return $trouves === [] ? null : ['action' => $action, 'termes' => $trouves];
    }

    /**
     * Compte les déclenchements, pour que la liste puisse être relue.
     *
     * Sans ce compteur, personne ne sait quelles entrées servent. Une liste de
     * mots surveillés qui grossit sans jamais être élaguée finit par bloquer
     * des choses que plus personne n'assume.
     *
     * @param  list<string>  $termes
     */
    public function comptabiliser(array $termes): void
    {
        if ($termes === []) {
            return;
        }

        ModerationKeyword::query()
            ->whereIn('term', $termes)
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);

        $this->oublier();
    }

    public function oublier(): void
    {
        Cache::forget(self::CLEF_CACHE);
    }

    /**
     * @return Collection<int, array{terme: string, normalise: string, action: ActionMotClef}>
     */
    private function motsSurveilles(): Collection
    {
        /** @var Collection<int, array{terme: string, normalise: string, action: ActionMotClef}> */
        return Cache::remember(self::CLEF_CACHE, now()->addMinute(), function (): Collection {
            return ModerationKeyword::query()
                ->where('is_active', true)
                ->get(['term', 'action'])
                ->map(fn (ModerationKeyword $mot): array => [
                    'terme' => $mot->term,
                    'normalise' => $this->normaliser($mot->term),
                    'action' => $mot->action,
                ])
                // Un terme qui se normalise en chaîne vide — seulement de la
                // ponctuation — attraperait tout. Il est écarté.
                ->filter(static fn (array $mot): bool => $mot['normalise'] !== '')
                ->values();
        });
    }

    /**
     * Minuscules, sans accents, ponctuation réduite à des espaces.
     *
     * La ponctuation devient un espace plutôt que rien : sans cela,
     * « bien-pensant » deviendrait « bienpensant » et n'attraperait plus
     * « pensant », alors que les deux mots sont bien là.
     */
    private function normaliser(string $texte): string
    {
        $texte = strip_tags($texte);
        $texte = mb_strtolower($texte);

        $sansAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
        $texte = $sansAccents === false ? $texte : $sansAccents;

        $texte = preg_replace('/[^a-z0-9]+/', ' ', $texte) ?? $texte;

        return trim(preg_replace('/\s+/', ' ', $texte) ?? $texte);
    }

    /**
     * Une correspondance sur des LIMITES DE MOT, jamais une sous-chaîne.
     *
     * Le terme surveillé peut compter plusieurs mots : les deux côtés étant
     * normalisés de la même façon, une simple recherche entourée d'espaces
     * suffit et reste exacte.
     */
    private function contient(string $texteNormalise, string $termeNormalise): bool
    {
        return str_contains(' '.$texteNormalise.' ', ' '.$termeNormalise.' ');
    }
}
