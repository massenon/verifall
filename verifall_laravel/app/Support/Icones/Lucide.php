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

    /**
     * Les noms Material Symbols de l'ancienne console, traduits en Lucide.
     *
     * Certains écrans reçoivent encore un nom d'icône de la base ou d'une
     * énumération (`visibility_off`, `payments`…). Plutôt que de les réécrire
     * tous d'un coup, `svg()` traduit : un nom inconnu devient un cercle, jamais
     * un trou, et chaque nouvel écran écrit directement du Lucide.
     *
     * @var array<string, string>
     */
    private const DEPUIS_MATERIAL = [
        'add' => 'plus', 'edit' => 'pencil', 'delete' => 'trash-2', 'close' => 'x', 'warning' => 'triangle-alert',
        'tune' => 'sliders-horizontal', 'push_pin' => 'pin', 'person_remove' => 'user-minus', 'open_in_new' => 'external-link',
        'lock_open' => 'lock-open', 'lock' => 'lock', 'download' => 'download', 'block' => 'ban', 'arrow_forward' => 'arrow-right',
        'wallpaper' => 'image', 'visibility' => 'eye', 'visibility_off' => 'eye-off', 'upload' => 'upload', 'star' => 'star',
        'restore' => 'rotate-ccw', 'radio_button_unchecked' => 'circle', 'print' => 'printer', 'plagiarism' => 'file-search',
        'person_add' => 'user-plus', 'notifications' => 'bell', 'more_vert' => 'ellipsis-vertical', 'monitoring' => 'activity',
        'list_alt' => 'list', 'image' => 'image', 'how_to_reg' => 'user-check', 'gpp_bad' => 'shield-x', 'verified' => 'badge-check',
        'fact_check' => 'file-check', 'expand_more' => 'chevron-down', 'event_busy' => 'calendar-x', 'error' => 'circle-alert',
        'design_services' => 'pen-tool', 'content_copy' => 'copy', 'co_present' => 'presentation', 'check' => 'check',
        'arrow_upward' => 'arrow-up', 'arrow_downward' => 'arrow-down', 'arrow_back' => 'arrow-left', 'trending_up' => 'trending-up',
        'trending_down' => 'trending-down', 'trending_flat' => 'minus', 'article' => 'file-text', 'graphic_eq' => 'audio-lines',
        'link' => 'link', 'mic' => 'mic', 'play_circle' => 'circle-play', 'videocam' => 'video', 'mail' => 'mail',
        'payments' => 'banknote', 'science' => 'flask-conical', 'shield' => 'shield', 'toggle_on' => 'toggle-right',
        'toll' => 'coins', 'travel_explore' => 'globe', 'workspace_premium' => 'award', 'pause' => 'pause', 'play_arrow' => 'play',
        'school' => 'graduation-cap', 'person' => 'user', 'group' => 'users', 'flag' => 'flag', 'search' => 'search',
        'schedule' => 'clock', 'info' => 'info', 'done' => 'check', 'cancel' => 'circle-x', 'sync' => 'refresh-cw',
        'bolt' => 'zap', 'forum' => 'messages-square', 'favorite' => 'heart', 'quiz' => 'list-checks', 'receipt_long' => 'receipt',
        'campaign' => 'megaphone', 'database' => 'database', 'cloud_upload' => 'cloud-upload', 'history' => 'history',
        'sensors' => 'radio', 'local_fire_department' => 'flame', 'translate' => 'languages', 'public' => 'globe',
    ];

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
        // Un nom Material hérité (avec « _ », sans « - ») est traduit ; un nom
        // inconnu devient un cercle plutôt qu'une exception en pleine page.
        if (! isset(self::catalogue()[$nom]) && ! str_contains($nom, '-')) {
            $nom = self::DEPUIS_MATERIAL[$nom] ?? 'circle';
        }

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
