<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les mots surveillés par la modération du forum.
 *
 * EN BASE ET NON EN CONFIGURATION, pour une raison de fond : la liste doit se
 * modifier au rythme de l'actualité. Une rumeur nouvelle apparaît un mardi
 * matin, et attendre un déploiement pour la surveiller revient à ne pas la
 * surveiller.
 *
 * `term` est unique : le même mot déclaré deux fois avec deux actions
 * différentes produirait un comportement dépendant de l'ordre de lecture,
 * c'est-à-dire imprévisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_keywords', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Conservé tel qu'il est saisi ; la comparaison se fait sans casse
            // ni accents, côté service.
            $table->string('term', 120)->unique();
            $table->string('action')->default('flag')->index();

            // Pourquoi ce mot est là. Sans cette note, personne n'ose retirer
            // une entrée six mois plus tard, faute de savoir ce qu'elle visait.
            $table->string('note', 300)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_keywords');
    }
};
