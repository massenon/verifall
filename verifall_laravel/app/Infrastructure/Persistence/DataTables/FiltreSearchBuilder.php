<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DataTables;

use App\Support\DataTables\RequeteDataTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Traduit les critères de l'extension SearchBuilder en clauses Eloquent.
 *
 * ## Ce que le navigateur envoie, et ce qu'on en garde
 *
 * En traitement côté serveur, SearchBuilder envoie un arbre :
 *
 *     { logic: 'AND', criteria: [
 *         { origData: 'email', condition: 'contains', value: ['@gouv.bj'] },
 *         { logic: 'OR', criteria: [ … ] },
 *     ] }
 *
 * Trois choses de cet arbre ne sont PAS crues :
 *
 *   · **la colonne** — `origData` doit figurer dans la liste blanche de l'écran,
 *     qui seule donne le nom SQL. Le nom affiché (`data`) n'est jamais lu ;
 *   · **le type** — celui que le navigateur annonce est ignoré ; c'est la liste
 *     blanche qui dit si la colonne est un texte, un nombre ou une date, donc
 *     quelles conditions ont un sens ;
 *   · **la taille** — profondeur et nombre de critères sont plafonnés. Un arbre
 *     de dix mille nœuds est une requête SQL de dix mille clauses.
 *
 * Un critère incomplet ou inconnu est **ignoré** : SearchBuilder envoie aussi
 * les lignes que l'utilisateur est en train de remplir, et les refuser rendrait
 * l'écran inutilisable pendant la saisie.
 *
 * Le `LIKE` n'échappe pas `%` et `_` : c'est la convention des dépôts existants
 * (`DepotMessagesContactEloquent`), et un joker saisi par un administrateur ne
 * fait qu'élargir SA recherche, dans un périmètre déjà autorisé.
 */
final readonly class FiltreSearchBuilder
{
    public const PROFONDEUR_MAXIMUM = 3;

    public const CRITERES_MAXIMUM = 20;

    /**
     * @param  array<string, array{colonne: string, type: 'string'|'num'|'date'}>  $colonnes
     */
    public function __construct(private array $colonnes) {}

    /**
     * @template TModele of Model
     *
     * @param  Builder<TModele>  $requete
     * @param  array<string, mixed>|null  $arbre
     */
    public function appliquer(Builder $requete, ?array $arbre): void
    {
        if ($arbre === null) {
            return;
        }

        $compteur = 0;

        // Enveloppé : sans parenthèses, un `OR` de premier niveau s'échapperait
        // des autres filtres de l'écran et rouvrirait le jeu entier.
        $requete->where(function (Builder $groupe) use ($arbre, &$compteur): void {
            $this->groupe($groupe, $arbre, 1, $compteur);
        });
    }

    /**
     * @template TModele of Model
     *
     * @param  Builder<TModele>  $requete
     * @param  array<array-key, mixed>  $groupe
     */
    private function groupe(Builder $requete, array $groupe, int $profondeur, int &$compteur): void
    {
        $criteres = $groupe['criteria'] ?? null;

        if ($profondeur > self::PROFONDEUR_MAXIMUM || ! is_array($criteres)) {
            return;
        }

        $booleen = is_string($groupe['logic'] ?? null) && strtoupper($groupe['logic']) === 'OR' ? 'or' : 'and';

        foreach ($criteres as $critere) {
            if (! is_array($critere)) {
                continue;
            }

            if (++$compteur > self::CRITERES_MAXIMUM) {
                return;
            }

            if (isset($critere['criteria'])) {
                $requete->where(function (Builder $sousGroupe) use ($critere, $profondeur, &$compteur): void {
                    $this->groupe($sousGroupe, $critere, $profondeur + 1, $compteur);
                }, null, null, $booleen);

                continue;
            }

            $this->critere($requete, $critere, $booleen);
        }
    }

    /**
     * @template TModele of Model
     *
     * @param  Builder<TModele>  $requete
     * @param  array<array-key, mixed>  $critere
     */
    private function critere(Builder $requete, array $critere, string $booleen): void
    {
        $cle = $critere['origData'] ?? null;
        $condition = $critere['condition'] ?? null;

        if (! is_string($cle) || ! isset($this->colonnes[$cle]) || ! is_string($condition)) {
            return;
        }

        ['colonne' => $colonne, 'type' => $type] = $this->colonnes[$cle];

        $valeurs = [];

        foreach (is_array($critere['value'] ?? null) ? $critere['value'] : [] as $valeur) {
            if (is_scalar($valeur) && trim((string) $valeur) !== '') {
                $valeurs[] = mb_substr(trim((string) $valeur), 0, RequeteDataTables::RECHERCHE_MAXIMUM);
            }
        }

        // Les deux conditions sans valeur, communes aux trois types.
        if ($condition === 'null') {
            $type === 'string'
                ? $requete->where(fn (Builder $q) => $q->whereNull($colonne)->orWhere($colonne, ''), null, null, $booleen)
                : $requete->whereNull($colonne, $booleen);

            return;
        }

        if ($condition === '!null') {
            $type === 'string'
                ? $requete->where(fn (Builder $q) => $q->whereNotNull($colonne)->where($colonne, '!=', ''), null, null, $booleen)
                : $requete->whereNotNull($colonne, $booleen);

            return;
        }

        match ($type) {
            'string' => $this->texte($requete, $colonne, $condition, $valeurs, $booleen),
            'num' => $this->nombre($requete, $colonne, $condition, $valeurs, $booleen),
            'date' => $this->date($requete, $colonne, $condition, $valeurs, $booleen),
        };
    }

    /**
     * @template TModele of Model
     *
     * @param  Builder<TModele>  $requete
     * @param  list<string>  $valeurs
     */
    private function texte(Builder $requete, string $colonne, string $condition, array $valeurs, string $booleen): void
    {
        if ($valeurs === []) {
            return;
        }

        $v = $valeurs[0];

        [$operateur, $motif] = match ($condition) {
            '=' => ['=', $v],
            '!=' => ['!=', $v],
            'contains' => ['like', '%'.$v.'%'],
            '!contains' => ['not like', '%'.$v.'%'],
            'starts' => ['like', $v.'%'],
            '!starts' => ['not like', $v.'%'],
            'ends' => ['like', '%'.$v],
            '!ends' => ['not like', '%'.$v],
            default => [null, null],
        };

        if ($operateur !== null) {
            $requete->where($colonne, $operateur, $motif, $booleen);
        }
    }

    /**
     * @template TModele of Model
     *
     * @param  Builder<TModele>  $requete
     * @param  list<string>  $valeurs
     */
    private function nombre(Builder $requete, string $colonne, string $condition, array $valeurs, string $booleen): void
    {
        $nombres = array_values(array_map(
            static fn (string $v): float => (float) $v,
            array_filter($valeurs, static fn (string $v): bool => is_numeric($v)),
        ));

        if ($nombres === []) {
            return;
        }

        if (in_array($condition, ['between', '!between'], true)) {
            if (count($nombres) < 2) {
                return;
            }

            $bornes = [min($nombres[0], $nombres[1]), max($nombres[0], $nombres[1])];

            $requete->whereBetween($colonne, $bornes, $booleen, $condition === '!between');

            return;
        }

        if (in_array($condition, ['=', '!=', '<', '<=', '>=', '>'], true)) {
            $requete->where($colonne, $condition, $nombres[0], $booleen);
        }
    }

    /**
     * @template TModele of Model
     *
     * @param  Builder<TModele>  $requete
     * @param  list<string>  $valeurs
     */
    private function date(Builder $requete, string $colonne, string $condition, array $valeurs, string $booleen): void
    {
        $dates = array_values(array_filter($valeurs, RequeteDataTables::estUneDate(...)));

        if ($dates === []) {
            return;
        }

        if (in_array($condition, ['between', '!between'], true)) {
            if (count($dates) < 2) {
                return;
            }

            sort($dates);
            [$du, $au] = $dates;

            $requete->where(function (Builder $q) use ($colonne, $du, $au, $condition): void {
                $condition === 'between'
                    ? $q->whereDate($colonne, '>=', $du)->whereDate($colonne, '<=', $au)
                    : $q->whereDate($colonne, '<', $du)->orWhereDate($colonne, '>', $au);
            }, null, null, $booleen);

            return;
        }

        if (in_array($condition, ['=', '!=', '<', '>'], true)) {
            $requete->whereDate($colonne, $condition, $dates[0], $booleen);
        }
    }
}
