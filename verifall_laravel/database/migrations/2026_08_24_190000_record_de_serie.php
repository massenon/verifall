<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le record personnel de série.
 *
 * L'écran des statistiques l'affiche (« Record personnel : 12 jours »). Sans
 * colonne, ce chiffre serait invérifiable : la série courante remonte à 1 dès
 * qu'un jour est manqué, et l'ancienne valeur est perdue pour toujours.
 *
 * On l'initialise à la série en cours plutôt qu'à zéro : quelqu'un qui tient
 * sept jours au moment de la migration a bien déjà fait sept jours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('streak_best')->default(0)->after('streak_last_day');
        });

        DB::table('users')->update(['streak_best' => DB::raw('streak_days')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('streak_best');
        });
    }
};
