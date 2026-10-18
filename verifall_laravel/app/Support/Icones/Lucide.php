<?php

declare(strict_types=1);

namespace App\Support\Icones;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Les icônes Lucide de la console premium, rendues en SVG côté serveur.
 *
 * Pas de lucide.js : une icône rendue au serveur existe dès le premier octet,
 * survit au remplacement du DOM par Livewire, et ne coûte aucun script. Le
 * catalogue (`resources/icones/lucide.php`) est un tableau PHP qu'OPcache garde
 * en mémoire ; il n'est lu qu'une fois par requête.
 */
final class Lucide
{
    /** @var array<string, string>|null */
    private static ?array $catalogue = null;

    public static function existe(string $nom): bool
    {
        return isset(self::catalogue()[$nom]);
    }

    /**
     * Le `<svg>` complet. Décoratif par défaut (`aria-hidden`) : le sens est
     * porté par le texte voisin. Avec `$titre`, l'icône devient une image
     * nommée, pour un bouton qui n'a pas d'autre libellé.
     *
     * Un `HtmlString` : Blade l'affiche tel quel avec `{{ }}`, sans `{!! !!}`.
     * Rien n'y vient d'une saisie : le contenu est le catalogue du dépôt, et la
     * classe comme le titre sont échappés.
     */
    public static function svg(string $nom, string $classe = 'w-4 h-4', ?string $titre = null, float $trait = 1.75): HtmlString
    {
        $contenu = self::catalogue()[$nom] ?? null;

        if ($contenu === null) {
            // Une icône mal nommée doit casser en développement et en test,
            // pas laisser un trou silencieux dans la barre de navigation.
            if (! app()->isProduction()) {
                throw new InvalidArgumentException("Icône Lucide inconnue : « {$nom} ».");
            }

            return new HtmlString('');
        }

        $accessibilite = $titre === null
            ? 'aria-hidden="true" focusable="false"'
            : 'role="img" aria-label="'.e($titre).'"';

        return new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            .' stroke-width="'.$trait.'" stroke-linecap="round" stroke-linejoin="round"'
            .' class="'.e($classe).'" '.$accessibilite.'>'.$contenu.'</svg>');
    }

    /**
     * @return array<string, string>
     */
    private static function catalogue(): array
    {
        return self::$catalogue ??= require resource_path('icones/lucide.php');
    }
}
