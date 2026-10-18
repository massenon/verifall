<?php

declare(strict_types=1);

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Le mentor reçoit le CRUD du Hub d'infos et de la Communauté (arbitrage n° 177).
 *
 * La matrice (`MatricePermissions`) est la source de vérité ; cette migration
 * ne fait que la REJOUER, pour qu'un `php artisan migrate` sur cPanel applique
 * le changement sans qu'il faille penser à relancer le semis. Le semis est
 * idempotent (`syncPermissions`) : le rejouer ne crée rien de plus que ce que
 * la matrice déclare.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        // Revenir en arrière = revenir sur la matrice dans le code, puis
        // relancer le semis. Une migration ne garde pas l'ancienne liste.
    }
};
