<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hub d'Infos Bénin et blog public.
 *
 * Le Hub est une revue de presse VÉRIFIÉE : chaque brève porte le verdict que
 * l'équipe a rendu — vérifié, trompeur, démenti. C'est ce qui le distingue d'un
 * agrégateur : on n'y relaie pas une information, on dit ce qu'elle vaut.
 *
 * Le verdict réutilise l'enum `VerdictFactCheck` du fact-checking. Un second
 * vocabulaire pour dire la même chose finirait par diverger, et le Hub
 * afficherait « DÉMENTI » là où le rapport dit « TROMPEUR ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            $table->string('title');
            $table->text('excerpt');
            $table->longText('body')->nullable();
            $table->string('category');

            // Verdict de l'équipe : c'est ce qui fait la valeur de la brève.
            $table->string('verdict', 20)->nullable();

            $table->string('source_name')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('cover_path')->nullable();

            // Le fait vérifié dont la brève découle, s'il existe : le Hub et la
            // base de connaissances doivent dire la même chose.
            $table->foreignId('verified_fact_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'published_at']);
            $table->index('category');
        });

        Schema::create('hub_portals', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('url', 500);
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();

            /*
             | Un portail OFFICIEL est une source dont la plateforme se porte
             | garante. Le drapeau est explicite pour que l'affichage ne
             | l'infère jamais du nom de domaine : « gouv-benin.info » n'est pas
             | « gouv.bj », et c'est précisément le piège.
             */
            $table->boolean('is_official')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(false);

            $table->timestamps();
        });

        Schema::create('hub_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            $table->string('title');
            $table->string('organisation');
            $table->string('location')->nullable();
            $table->text('description');
            $table->string('apply_url', 500)->nullable();
            $table->date('deadline')->nullable();

            /*
             | Offre VÉRIFIÉE par l'équipe. Sur un hub béninois, la fausse offre
             | d'emploi qui réclame des « frais de dossier » est l'arnaque la
             | plus répandue : publier une offre sans dire si elle a été
             | contrôlée reviendrait à la cautionner.
             */
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'deadline']);
        });

        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('excerpt');
            $table->longText('body');
            $table->string('cover_path')->nullable();

            // Mis en avant en tête de blog — un seul à la fois côté affichage.
            $table->boolean('is_featured')->default(false);

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views')->default(0);

            // Renseignés à la main quand ils diffèrent du titre et du chapô :
            // un titre de page réussi n'est pas toujours un bon titre de
            // résultat de recherche.
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('hub_jobs');
        Schema::dropIfExists('hub_portals');
        Schema::dropIfExists('hub_articles');
    }
};
