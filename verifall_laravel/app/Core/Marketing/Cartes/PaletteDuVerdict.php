<?php

declare(strict_types=1);

namespace App\Core\Marketing\Cartes;

use App\Enums\VerdictFactCheck;
use GdImage;
use InvalidArgumentException;

/**
 * Les couleurs de la carte de démenti, et rien d'autre.
 *
 * ## Pourquoi une classe séparée
 *
 * Parce qu'une couleur écrite en dur dans un appel `imagefilledrectangle` est
 * invisible à la relecture : `imagecolorallocate($im, 220, 38, 38)` ne dit pas
 * « rouge du verdict FAUX », il dit trois nombres. Le jour où le design system
 * bouge, personne ne les retrouve.
 *
 * ## D'où viennent ces teintes
 *
 * D'AUCUNE invention. Celle du verdict est rendue par
 * `VerdictFactCheck::couleurBarre()` — la même que la barre de fiabilité et que
 * les pastilles `.bg-vtrue` / `.bg-vfalse` / `.bg-vinvest` du build Tailwind
 * figé. Les neutres sont relevés dans ce même build : `.text-heading`
 * (`#202124`), `.text-body` (`#606261`), `.border-line` (`#E5E7EB`) et le
 * `#f8f9fa` que la page d'un fait vérifié emploie pour ses encarts.
 *
 * Une carte qui inventerait son rouge serait un rouge de plus : à côté du site,
 * elle ne ressemblerait pas à Vérif'All, et c'est précisément ce qu'un faux
 * démenti chercherait à imiter.
 */
final class PaletteDuVerdict
{
    /** Le noir du design system — `.text-heading`. */
    public const ENCRE = '#202124';

    /** Le gris de texte courant — `.text-body`. */
    public const CORPS = '#606261';

    /** Le gris des filets — `.border-line`. */
    public const LIGNE = '#E5E7EB';

    /** Le fond des encarts de la page d'un fait vérifié. */
    public const PIED = '#F8F9FA';

    public const BLANC = '#FFFFFF';

    /**
     * La couleur de la bande du verdict.
     *
     * Déléguée à l'enum : une seconde table de correspondance divergerait de la
     * première, et la carte annoncerait un verdict d'une autre couleur que la
     * page vers laquelle elle renvoie.
     */
    public static function bande(VerdictFactCheck $verdict): string
    {
        return $verdict->couleurBarre();
    }

    /**
     * Décompose un `#RRGGBB` en trois composantes.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function composantes(string $hexadecimal): array
    {
        $hexadecimal = ltrim(trim($hexadecimal), '#');

        if (preg_match('/^[0-9A-Fa-f]{6}$/', $hexadecimal) !== 1) {
            throw new InvalidArgumentException("Couleur hexadécimale invalide : « {$hexadecimal} »");
        }

        return [
            (int) hexdec(substr($hexadecimal, 0, 2)),
            (int) hexdec(substr($hexadecimal, 2, 2)),
            (int) hexdec(substr($hexadecimal, 4, 2)),
        ];
    }

    /**
     * Alloue une couleur dans une image GD.
     *
     * `$transparence` suit la convention de GD : 0 opaque, 127 invisible. Elle
     * sert au texte secondaire posé SUR la bande du verdict — un gris y serait
     * sale, un blanc atténué y reste propre quelle que soit la teinte dessous.
     */
    public static function allouer(GdImage $image, string $hexadecimal, int $transparence = 0): int
    {
        [$rouge, $vert, $bleu] = self::composantes($hexadecimal);

        $couleur = $transparence === 0
            ? imagecolorallocate($image, $rouge, $vert, $bleu)
            : imagecolorallocatealpha($image, $rouge, $vert, $bleu, max(0, min(127, $transparence)));

        // GD rend `false` quand la palette est pleine. Sur une image en
        // couleurs vraies cela n'arrive pas, mais le type doit rester honnête.
        return $couleur === false ? 0 : $couleur;
    }
}
