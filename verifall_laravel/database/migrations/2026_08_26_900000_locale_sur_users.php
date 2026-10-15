<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La langue choisie par une personne, conservée d'un appareil à l'autre.
 *
 * NULLABLE, et c'est le point. `null` ne veut pas dire « français » : il veut
 * dire « cette personne n'a jamais choisi ». La distinction compte le jour où
 * l'on voudra savoir combien de gens lisent réellement en anglais — un
 * remplissage par défaut aurait effacé cette information pour toujours.
 *
 * Sans compte, le choix vit en session : il suit la personne le temps de sa
 * visite, ce qui est tout ce qu'on peut promettre à quelqu'un qui ne s'est pas
 * identifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
