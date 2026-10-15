<?php

declare(strict_types=1);

namespace App\Support\DataTables;

use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Une requête DataTables « côté serveur », lue et BORNÉE avant d'aller plus loin.
 *
 * ## Pourquoi un objet plutôt que `$request->input()` dans le dépôt
 *
 * Le protocole DataTables envoie tout ce que le navigateur veut bien envoyer :
 * des indices de colonnes, des noms, une longueur de page, des critères
 * imbriqués. **Rien de tout cela n'est digne de confiance.** Un nom de colonne
 * qui arriverait jusqu'à `orderBy()` serait une injection ; une longueur de
 * `-1` ou de `1000000` chargerait la table entière en mémoire.
 *
 * Cet objet est la frontière : ce qui en sort a déjà été confronté aux listes
 * blanches de l'écran. Ce qui n'y figurait pas est **ignoré**, pas refusé — une
 * colonne inconnue dans le tri n'est pas une attaque qu'on signale, c'est un
 * paramètre qu'on ne suit pas, et l'écran reste utilisable.
 *
 * Il est générique : c'est le contrat des ~58 écrans suivants
 * (`docs/DATATABLES_CONTRAT.md`). Chaque écran ne fournit que SES listes
 * blanches.
 */
final readonly class RequeteDataTables
{
    public const LONGUEUR_PAR_DEFAUT = 25;

    /** Plafond d'une page affichée. Au-delà, c'est un export — et il a sa permission. */
    public const LONGUEUR_MAXIMUM = 100;

    /**
     * Plafond d'un export.
     *
     * Un export sans plafond est une extraction de base. Cinq mille lignes
     * couvrent l'usage administratif ; au-delà, la réponse le DIT
     * (`exportTronque`) plutôt que de couper en silence.
     */
    public const EXPORT_MAXIMUM = 5000;

    public const RECHERCHE_MAXIMUM = 100;

    /** Trois niveaux de tri suffisent ; au-delà c'est du bruit, ou une attaque. */
    public const TRIS_MAXIMUM = 3;

    /** Taille maximale, sérialisée, des critères SearchBuilder acceptés. */
    public const SEARCHBUILDER_OCTETS_MAXIMUM = 8192;

    /**
     * @param  list<array{colonne: string, sens: 'asc'|'desc'}>  $tri
     * @param  array<string, string>  $filtres
     * @param  array<string, mixed>|null  $searchBuilder
     */
    public function __construct(
        public int $draw,
        public int $debut,
        public int $longueur,
        public string $recherche,
        public array $tri,
        public array $filtres,
        public ?array $searchBuilder,
        public bool $export,
    ) {}

    /**
     * @param  list<string>  $colonnesTriables  clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL
     * @param  array<string, list<string>|'date'>  $filtresAutorises  clé => valeurs permises, ou 'date' (AAAA-MM-JJ)
     */
    public static function depuis(Request $requete, array $colonnesTriables, array $filtresAutorises): self
    {
        $export = $requete->boolean('export');

        return new self(
            // Renvoyé tel quel au navigateur : un entier, jamais la chaîne
            // reçue — c'est la protection XSS que la documentation DataTables
            // exige sur ce champ.
            draw: max(0, self::entier($requete->input('draw'), 0)),
            debut: $export ? 0 : max(0, self::entier($requete->input('start'), 0)),
            longueur: $export
                ? self::EXPORT_MAXIMUM
                : self::longueur(self::entier($requete->input('length'), self::LONGUEUR_PAR_DEFAUT)),
            recherche: self::recherche($requete->input('search')),
            tri: self::tri($requete->input('order'), $requete->input('columns'), $colonnesTriables),
            filtres: self::filtres($requete->input('filtres'), $filtresAutorises),
            searchBuilder: self::searchBuilder($requete->input('searchBuilder')),
            export: $export,
        );
    }

    /** Un critère au moins restreint-il le jeu ? Sinon le total filtré est le total. */
    public function restreint(): bool
    {
        return $this->recherche !== '' || $this->filtres !== [] || $this->searchBuilder !== null;
    }

    private static function entier(mixed $valeur, int $defaut): int
    {
        return is_scalar($valeur) && is_numeric($valeur) ? (int) $valeur : $defaut;
    }

    private static function longueur(int $demandee): int
    {
        // `-1` (« tout afficher ») n'est PAS honoré hors export : c'est
        // exactement la requête qui charge dix mille comptes dans le navigateur.
        if ($demandee < 1) {
            return self::LONGUEUR_PAR_DEFAUT;
        }

        return min($demandee, self::LONGUEUR_MAXIMUM);
    }

    private static function recherche(mixed $recherche): string
    {
        $valeur = is_array($recherche) ? ($recherche['value'] ?? '') : '';

        if (! is_scalar($valeur)) {
            return '';
        }

        return mb_substr(trim((string) $valeur), 0, self::RECHERCHE_MAXIMUM);
    }

    /**
     * @param  list<string>  $colonnesTriables
     * @return list<array{colonne: string, sens: 'asc'|'desc'}>
     */
    private static function tri(mixed $ordres, mixed $colonnes, array $colonnesTriables): array
    {
        if (! is_array($ordres)) {
            return [];
        }

        $tri = [];

        foreach ($ordres as $ordre) {
            if (! is_array($ordre) || count($tri) >= self::TRIS_MAXIMUM) {
                continue;
            }

            /*
             | DataTables 2 envoie le NOM de colonne quand il est déclaré, et
             | toujours l'INDICE. On accepte les deux, mais l'un comme l'autre
             | finit confronté à la liste blanche : l'indice ne fait que
             | désigner un `data`, qui doit y figurer.
             */
            $cle = null;

            if (isset($ordre['name']) && is_string($ordre['name']) && $ordre['name'] !== '') {
                $cle = $ordre['name'];
            } elseif (isset($ordre['column']) && is_scalar($ordre['column']) && is_numeric($ordre['column']) && is_array($colonnes)) {
                $colonne = $colonnes[(int) $ordre['column']] ?? null;
                $cle = is_array($colonne) && isset($colonne['data']) && is_string($colonne['data']) ? $colonne['data'] : null;
            }

            if ($cle === null || ! in_array($cle, $colonnesTriables, true)) {
                continue;
            }

            $tri[] = ['colonne' => $cle, 'sens' => ($ordre['dir'] ?? null) === 'desc' ? 'desc' : 'asc'];
        }

        return $tri;
    }

    /**
     * @param  array<string, list<string>|'date'>  $autorises
     * @return array<string, string>
     */
    private static function filtres(mixed $filtres, array $autorises): array
    {
        if (! is_array($filtres)) {
            return [];
        }

        $retenus = [];

        foreach ($autorises as $cle => $permis) {
            $valeur = $filtres[$cle] ?? null;

            if (! is_string($valeur) || $valeur === '') {
                continue;
            }

            if ($permis === 'date' ? self::estUneDate($valeur) : in_array($valeur, $permis, true)) {
                $retenus[$cle] = $valeur;
            }
        }

        return $retenus;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function searchBuilder(mixed $criteres): ?array
    {
        if (! is_array($criteres) || ! isset($criteres['criteria']) || ! is_array($criteres['criteria'])) {
            return null;
        }

        $serialise = json_encode($criteres);

        if ($serialise === false || strlen($serialise) > self::SEARCHBUILDER_OCTETS_MAXIMUM) {
            return null;
        }

        /** @var array<string, mixed> $criteres */
        return $criteres;
    }

    public static function estUneDate(string $valeur): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date !== false && $date->format('Y-m-d') === $valeur;
    }
}
