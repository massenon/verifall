<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lire l'ancienne base et en nettoyer les valeurs — le socle commun des reprises.
 *
 * Ces méthodes vivaient dans `ImporterAncienneBase`, et y ont écrit en
 * production. Elles en ont été DÉPLACÉES à l'identique le 14 septembre 2026,
 * au moment d'ajouter quatre reprises (sources, Académie, forum, Hub) : les
 * recopier dans chaque classe aurait fait cinq lecteurs de dump à tenir
 * d'accord, et le premier qui aurait corrigé une coquille l'aurait corrigée
 * une fois sur cinq. Les tests des comptes, vérifications et outils, restés
 * inchangés, attestent que le déplacement n'a rien modifié.
 *
 * Seule addition : `comptesParAdresse()`, qui recâble un ancien `users.id` sur
 * le compte nouveau par l'ADRESSE — la seule clé commune aux deux bases.
 */
trait LitLAncienneBase
{
    /** @param  array<string, int>  $comptage */
    private function enumerer(array $comptage): string
    {
        $morceaux = [];

        foreach ($comptage as $valeur => $nombre) {
            $morceaux[] = sprintf('« %s » (%d fois)', $valeur, $nombre);
        }

        return implode(', ', $morceaux);
    }

    // ════════════════════════════════════════════════════════════ la lecture

    /**
     * Extrait les lignes d'une table du dump, sans charger les 32 Mo en mémoire.
     *
     * Le dump est produit par `mysqldump` : les colonnes sont déclarées dans
     * l'`INSERT INTO … (…) VALUES`, et chaque tuple suit. On lit en flux et on
     * ne garde que la table demandée.
     *
     * @return list<array<string, string|null>>
     */
    private function lireTable(string $chemin, string $table): array
    {
        $flux = fopen($chemin, 'rb');

        if ($flux === false) {
            throw new RuntimeException('Lecture impossible : '.$chemin);
        }

        $colonnes = [];
        $lignes = [];
        $dansLaTable = false;

        while (($ligne = fgets($flux)) !== false) {
            if (str_starts_with($ligne, 'INSERT INTO `'.$table.'`')) {
                $dansLaTable = true;

                if (preg_match('/\(([^)]*)\) VALUES/', $ligne, $trouve) === 1) {
                    $colonnes = array_map(
                        static fn (string $c): string => trim($c, " `\r\n"),
                        explode(',', $trouve[1]),
                    );
                }

                continue;
            }

            if (! $dansLaTable) {
                continue;
            }

            $ligne = rtrim($ligne);

            if (! str_starts_with($ligne, '(')) {
                $dansLaTable = false;

                continue;
            }

            $valeurs = $this->decouper(rtrim($ligne, ',;'));

            if (count($valeurs) === count($colonnes)) {
                $lignes[] = array_combine($colonnes, $valeurs);
            }
        }

        fclose($flux);

        return $lignes;
    }

    /**
     * Découpe un tuple SQL en valeurs, en respectant les chaînes échappées.
     *
     * Un `explode(',')` couperait au milieu de « Bonjour, madame » et décalerait
     * toutes les colonnes suivantes — le genre d'erreur qui produit un import
     * qui « marche » avec des données mélangées.
     *
     * @return list<string|null>
     */
    private function decouper(string $tuple): array
    {
        $tuple = substr($tuple, 1, -1);
        $valeurs = [];
        $courante = '';
        $dansChaine = false;
        $echappe = false;

        for ($i = 0, $n = strlen($tuple); $i < $n; $i++) {
            $c = $tuple[$i];

            if ($echappe) {
                $courante .= match ($c) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '0' => "\0",
                    default => $c,
                };
                $echappe = false;

                continue;
            }

            if ($c === '\\' && $dansChaine) {
                $echappe = true;

                continue;
            }

            if ($c === "'") {
                $dansChaine = ! $dansChaine;

                continue;
            }

            if ($c === ',' && ! $dansChaine) {
                $valeurs[] = $this->normaliser($courante);
                $courante = '';

                continue;
            }

            $courante .= $c;
        }

        $valeurs[] = $this->normaliser($courante);

        return $valeurs;
    }

    private function normaliser(string $brut): ?string
    {
        $brut = trim($brut);

        return $brut === 'NULL' ? null : $brut;
    }

    /**
     * Nettoie une valeur de texte reprise telle quelle de l'ancienne base.
     *
     * ## Ce que ça corrige, et pourquoi ça ne se voyait pas
     *
     * 41 noms sur 133 portent une espace de bord, et 40 une espace double
     * interne — « Jean marie  EYEMI ». Importés tels quels, ils s'affichent
     * ainsi partout : entête de tableau de bord, fil du forum, liste du
     * back-office. Et ils cassent la recherche par nom, qui ne trouve pas
     * « Jean marie EYEMI ».
     *
     * Le relevé qui les a manqués la première fois cherchait
     * `name <> TRIM(name)`. En collation `utf8mb4_unicode_ci` — PAD SPACE — les
     * espaces de FIN sont ignorées à la comparaison : l'expression est toujours
     * fausse. Il faut comparer les LONGUEURS. La leçon vaut au-delà d'ici : un
     * contrôle de propreté écrit en SQL doit se méfier de la collation qui
     * l'exécute.
     *
     * `Str::squish` fait les deux d'un coup — bords et répétitions internes —
     * et traite les espaces insécables, fréquentes dans du texte collé depuis
     * un traitement de texte ou WhatsApp.
     *
     * Une chaîne qui ne contenait QUE des espaces devient `null` et non `''` :
     * une colonne facultative doit être vide, pas contenir du vide.
     */
    private function propre(?string $valeur): ?string
    {
        $net = Str::squish((string) $valeur);

        return $net === '' ? null : $net;
    }

    /**
     * Nettoie un texte LONG, sans écraser sa mise en forme.
     *
     * ## Pourquoi ce second nettoyeur, et pas `propre()`
     *
     * `Str::squish` remplace toute suite d'espaces par une seule — retours à la
     * ligne compris. Sur un nom, c'est ce qu'on veut. Sur une allégation de
     * 4 995 caractères, cela colle les paragraphes en un bloc : 75 contenus et
     * 32 descriptions de l'ancienne base sont multilignes, et cette mise en
     * forme est celle que la personne a écrite. La détruire à l'import serait
     * réécrire son propos.
     *
     * On retire donc les bords — 64 contenus en portent — et rien d'autre.
     *
     * Le titre, lui, passe par `propre()` : un titre EST une ligne, et les 28
     * qui contiennent un retour à la ligne sont eux-mêmes le défaut à corriger.
     */
    private function propreTexte(?string $valeur): ?string
    {
        $net = trim((string) $valeur);

        return $net === '' ? null : $net;
    }

    private function date(?string $valeur): ?string
    {
        return ($valeur === null || $valeur === '' || str_starts_with($valeur, '0000')) ? null : $valeur;
    }

    /**
     * Ancien `users.id` → nouvel `users.id`, par l'adresse de courriel.
     *
     * L'identifiant ancien ne désigne RIEN dans la nouvelle base (§3.1 du
     * document) ; l'adresse, normalisée comme à l'import des comptes, est la
     * seule clé commune. Un compte ancien sans compte nouveau n'a pas d'entrée :
     * l'appelant le compte comme introuvable, il ne le devine pas.
     *
     * Les comptes supprimés en douceur sont inclus : ils ont été repris avec
     * leur `deleted_at`, et ce qui leur appartenait leur revient.
     *
     * Seules `id` et `email` sont exploitées : les empreintes de mot de passe,
     * lues avec le reste de la ligne, ne sortent pas de cette méthode.
     *
     * @return array<int, int>
     */
    private function comptesParAdresse(string $chemin): array
    {
        $anciensParAdresse = [];

        foreach ($this->lireTable($chemin, 'users') as $ancien) {
            $adresse = mb_strtolower(trim((string) ($ancien['email'] ?? '')));

            if ($adresse !== '') {
                $anciensParAdresse[$adresse] = (int) ($ancien['id'] ?? 0);
            }
        }

        $correspondance = [];

        foreach (array_chunk(array_keys($anciensParAdresse), 500) as $lot) {
            foreach (User::withTrashed()->whereIn('email', $lot)->get(['id', 'email']) as $compte) {
                $adresse = mb_strtolower((string) $compte->email);

                if (isset($anciensParAdresse[$adresse])) {
                    $correspondance[$anciensParAdresse[$adresse]] = (int) $compte->id;
                }
            }
        }

        return $correspondance;
    }
}
