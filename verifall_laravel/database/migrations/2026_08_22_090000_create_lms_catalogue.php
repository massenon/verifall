<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Académie — le CONTENU des cours.
 *
 * Séparé de la progression des apprenants, qui vit dans sa propre migration :
 * ces deux moitiés n'ont ni le même rythme d'écriture — le catalogue est écrit
 * par une poignée de mentors, la progression par des milliers de personnes —
 * ni la même durée de vie.
 *
 * Le prototype range les leçons À PLAT dans chaque cours, sans sections. Le
 * dictionnaire de données mentionne une table `sections` ; aucun des dix écrans
 * de l'Académie ne l'affiche, et une table vide traversée à chaque affichage
 * serait de la dette pure. La position ordonne les leçons ; le jour où un cours
 * aura besoin de chapitres, une table s'ajoutera sans toucher à celle-ci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('level', 20);

            // Durée ANNONCÉE, telle que le prototype l'écrit — « 1 h 20 ».
            // Recalculer une somme de minutes donnerait un chiffre exact mais
            // faux : le temps d'un cours n'est pas celui de ses vidéos.
            $table->string('duration_label', 40);
            $table->string('cover_path')->nullable();

            /*
             | Prix en UNITÉS, la monnaie du produit. `cost_units = 0` dit déjà
             | « gratuit » : un booléen en plus finirait par le contredire.
             */
            $table->unsignedSmallInteger('cost_units')->default(0);

            // Ce que l'on saura faire à la fin — liste courte affichée en tête
            // de fiche. Jamais interrogée, seulement lue : json convient.
            $table->json('outcomes')->nullable();

            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('delivers_certificate')->default(true);

            /*
             | Note et nombre d'inscrits DÉNORMALISÉS. Les recalculer à chaque
             | affichage du catalogue ferait deux agrégats par carte, sur la
             | page la plus visitée de l'Académie. Recalculés à l'écriture d'un
             | avis ou d'une inscription.
             */
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('students_count')->default(0);

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            /*
             | « Cours offert du mois » (MODULES_CARTOGRAPHIE). Une date de fin
             | plutôt qu'un booléen : un drapeau qu'il faut penser à retirer
             | reste allumé, et le cours resterait gratuit indéfiniment.
             */
            $table->timestamp('free_until')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'category']);
            $table->index('level');
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->string('type', 20)->default('text');
            $table->unsignedSmallInteger('minutes')->default(1);

            // HTML purifié à l'écriture (Quill côté studio mentor).
            $table->longText('content')->nullable();
            $table->string('video_url', 500)->nullable();

            $table->timestamps();

            // Un cours affiche ses leçons dans l'ordre, toujours.
            $table->unique(['course_id', 'position']);
        });

        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->string('title');
            $table->unsignedSmallInteger('minutes')->default(5);

            // Seuil de réussite en pourcentage, par quiz : le prototype met 70
            // partout, mais un quiz de certification pourrait exiger plus.
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->unsignedSmallInteger('xp_reward')->default(0);

            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->text('prompt');

            /*
             | L'EXPLICATION est le cœur pédagogique du quiz : le prototype
             | l'appelle « xai » et l'affiche après chaque réponse. Sans elle,
             | un quiz note sans rien apprendre.
             */
            $table->text('explanation')->nullable();

            $table->timestamps();

            $table->unique(['quiz_id', 'position']);
        });

        Schema::create('answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('position');
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);

            /*
             | Combien de fois cette réponse a été choisie. C'est la seule
             | raison de préférer une table à un tableau json d'options : savoir
             | QUELLE mauvaise réponse attire dit au mentor où sa leçon
             | n'accroche pas. Un json ne se compte pas.
             */
            $table->unsignedInteger('picked_count')->default(0);

            $table->unique(['question_id', 'position']);
        });

        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->string('title');
            $table->text('description');
            $table->unsignedSmallInteger('minutes')->default(30);

            $table->timestamps();
        });

        Schema::create('live_classes', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Rattachement FACULTATIF : le prototype propose des sessions
            // ouvertes à tous, sans cours de rattachement.
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('minutes')->default(60);

            // Lien de connexion : jamais public, servi aux inscrits seulement.
            $table->string('join_url', 500)->nullable();

            $table->unsignedSmallInteger('seats')->nullable();
            $table->boolean('is_published')->default(false);

            $table->timestamps();

            $table->index(['is_published', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_classes');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('courses');
    }
};
