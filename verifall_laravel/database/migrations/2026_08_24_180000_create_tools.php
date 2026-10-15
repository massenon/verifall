<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le répertoire d'outils de vérification.
 *
 * Ces quinze fiches sont du contenu, pas du code : une URL change, un service
 * ferme, un nouvel outil apparaît. Les figer dans un gabarit obligerait à
 * livrer une version pour corriger un lien — le back-office a d'ailleurs son
 * écran pour ça (`compte-admin/outils.html`).
 *
 * `sort_order` plutôt qu'un tri alphabétique : l'ordre du prototype va du plus
 * utile au plus spécialisé, et cet ordre est une recommandation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('description', 500);
            $table->string('category');
            /** Pastille courte affichée en haut de la carte (IMAGE, WEB, IA…). */
            $table->string('tag', 40)->nullable();
            /** Nom d'icône Material Symbols, comme dans le prototype. */
            $table->string('icon', 60)->default('build');
            $table->string('url');
            $table->string('link_label')->nullable();
            /** « Idéal pour » — les usages, en pastilles. */
            $table->json('use_cases')->nullable();
            /** Mots-clés ajoutés à la recherche, au-delà du titre et du résumé. */
            $table->string('keywords', 500)->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
