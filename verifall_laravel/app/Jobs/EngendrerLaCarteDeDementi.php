<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Marketing\Cartes\CarteDeDementi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fabrique la carte de démenti HORS de la requête.
 *
 * ## Pourquoi une file
 *
 * Le dessin ouvre trois polices, encode un QR, compose une image de 1200 × 630
 * et écrit un fichier : entre cent et deux cents millisecondes. Ce n'est pas
 * énorme, et c'est exactement pour cela qu'on serait tenté de le faire pendant
 * la publication — jusqu'au jour où le disque est lent, où la police est
 * absente, ou où l'on publie dix brèves d'affilée. Celui qui publie attendrait
 * alors un travail dont il n'a aucun besoin immédiat.
 *
 * `QUEUE_CONNECTION=database` sur ce poste : la file tourne avec
 * `php artisan queue:work`. En l'absence d'ouvrier, la carte n'est pas
 * fabriquée — la page reste juste, mais son `og:image` retombe sur l'image de
 * partage par défaut. C'est une dégradation visible, jamais une page cassée.
 *
 * ## Pourquoi l'identifiant et non le modèle
 *
 * Un modèle sérialisé dans la charge utile serait une PHOTOGRAPHIE de l'article
 * au moment de la mise en file. Si le verdict change dans la minute qui suit,
 * la carte serait fabriquée avec l'ancien. On recharge, donc, et on dessine
 * l'état vrai. `SerializesModels` recharge aussi, mais il échoue bruyamment
 * quand l'article a disparu ; ici, un article supprimé n'est pas un incident.
 */
final class EngendrerLaCarteDeDementi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Un dessin qui dépasse la minute est un dessin qui ne finira pas. */
    public int $timeout = 60;

    public function __construct(public readonly int $articleId) {}

    public function handle(CarteDeDementi $fabrique): void
    {
        $fabrique->engendrerPourIdentifiant($this->articleId);
    }

    /**
     * L'échec n'a pas de conséquence pour le lecteur — mais il doit se VOIR.
     *
     * Sans cette trace, une police déplacée sur le serveur produirait un parc
     * de cartes qui cesse silencieusement de grandir, et personne ne le
     * remarquerait avant de partager une brève sur WhatsApp.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Carte de démenti : engendrement abandonné.', [
            'article' => $this->articleId,
            'motif' => $exception?->getMessage(),
        ]);
    }
}
