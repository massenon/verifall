<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examens — l'ÉPREUVE et sa banque de questions.
 *
 * Pourquoi une famille de tables et non un `quizzes` un peu enrichi : un quiz
 * se passe quand on veut, se corrige tout seul, et n'a ni date, ni place, ni
 * correcteur. Les quatre écrans d'examen du back-office exigent exactement
 * l'inverse — une session planifiée, une convocation, une liste d'émargement,
 * une file de correction humaine et un rattrapage numéroté. Faire porter tout
 * cela à `quizzes` aurait rendu nullable la moitié de ses colonnes, et rendu
 * incompréhensible le quiz de fin de leçon, qui est le cas courant.
 *
 * Découpage en deux migrations, comme le LMS : ici le CONTENU — écrit par une
 * poignée de mentors, rarement — et dans la suivante la PASSATION, écrite par
 * des milliers de candidats. Ni le même rythme, ni la même durée de vie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            /*
             | Le cours est FACULTATIF et se détache proprement. Un examen
             | certifiant survit à la refonte du cours qui y préparait : cascader
             | la suppression effacerait les relevés de notes de gens qui n'y
             | sont pour rien.
             */
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Consignes affichées avant de composer. HTML purifié à l'écriture.
            $table->longText('instructions')->nullable();

            // Seuil de réussite en pourcentage — la colonne « Seuil » de la
            // liste. Recopié sur chaque copie au moment de la rendre.
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->unsignedSmallInteger('duration_minutes')->default(60);

            /*
             | Le RATTRAPAGE, tenu par un nombre et non par un booléen : « avec
             | rattrapage » ne dit pas combien, et la deuxième question arrive
             | toujours. 1 = pas de seconde chance.
             */
            $table->unsignedTinyInteger('max_attempts')->default(2);

            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();

            /*
             | Compteurs DÉNORMALISÉS des deux colonnes de la liste, recalculés
             | à chaque correction finalisée — jamais incrémentés. Un compteur
             | incrémenté dérive au premier événement rejoué, et personne ne
             | s'en aperçoit avant qu'il soit absurde.
             |
             | `candidates_count` compte les copies CORRIGÉES, pas les
             | inscrits : afficher 612 candidats dont 400 ne se sont jamais
             | présentés donnerait un taux de réussite faux.
             */
            $table->unsignedInteger('candidates_count')->default(0);
            $table->unsignedInteger('passed_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'course_id']);
        });

        Schema::create('exam_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            /*
             | La session n'a pas de libellé : « 25 août · 09:00 » se compose à
             | l'affichage. Un libellé stocké se serait désynchronisé de la date
             | au premier report.
             */
            $table->timestamp('starts_at');

            // Null = sans limite de places. Zéro voudrait dire « complet », ce
            // qui n'est pas la même chose.
            $table->unsignedSmallInteger('seats')->nullable();

            /*
             | Surveillance, annoncée dès le sous-titre de `examens.html`. Elle
             | se décide par SESSION et non par examen : la même certification
             | peut se passer en centre surveillé le matin et à distance le
             | soir, et la convocation ne dit pas la même chose dans les deux cas.
             */
            $table->boolean('is_proctored')->default(true);
            $table->string('location')->nullable();

            $table->string('status', 20)->default('planned');

            // Recalculés à chaque inscription et à chaque émargement : ils
            // portent « Inscrits session d'août » et le taux de présence.
            $table->unsignedInteger('registrations_count')->default(0);
            $table->unsignedInteger('attended_count')->default(0);

            $table->timestamps();

            /*
             | Deux sessions du même examen à la même minute sont une double
             | saisie, jamais une intention : la moitié des candidats serait
             | convoquée sur la ligne fantôme.
             */
            $table->unique(['exam_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('question_bank_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Rattachement pédagogique, pas structurel : c'est le filtre
            // « Cours » de la banque. La question survit au cours.
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);
            $table->text('prompt');

            /*
             | L'explication XAI, affichée après la réponse. C'est ce qui
             | distingue une évaluation d'un tri : sans elle, on note sans rien
             | apprendre. HTML purifié à l'écriture.
             */
            $table->longText('explanation')->nullable();

            // Le BARÈME de la question. Il vit ici et nulle part ailleurs :
            // recopié sur la copie au moment de composer.
            $table->unsignedSmallInteger('points')->default(10);

            $table->string('status', 20)->default('draft');

            /*
             | De quoi CALCULER le taux de réussite affiché, au lieu de le
             | stocker. Deux compteurs mesurés valent mieux qu'un pourcentage
             | figé qui ne dit plus sur combien il porte.
             */
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
            $table->index('course_id');
        });

        Schema::create('question_bank_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_bank_item_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('position');
            $table->string('label', 500);

            /*
             | La moitié droite d'une paire, pour l'appariement. Nulle partout
             | ailleurs : une table `pairs` de plus pour un seul des cinq types
             | serait traversée à vide quatre fois sur cinq.
             */
            $table->string('match_label', 500)->nullable();

            $table->boolean('is_correct')->default(false);

            // Combien de fois cette proposition a été choisie — la même raison
            // que sur `answers` : savoir QUELLE mauvaise réponse attire dit où
            // la leçon n'accroche pas. Un tableau JSON ne se compte pas.
            $table->unsignedInteger('picked_count')->default(0);

            $table->timestamps();

            $table->unique(['question_bank_item_id', 'position']);
        });

        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_item_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');

            $table->timestamps();

            /*
             | Une question ne peut être posée qu'une fois dans un examen :
             | sinon elle compterait deux fois au barème, et le total sur lequel
             | on note ne serait plus celui qu'on annonce.
             */
            $table->unique(['exam_id', 'question_bank_item_id']);
            $table->unique(['exam_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('question_bank_options');
        Schema::dropIfExists('question_bank_items');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exams');
    }
};
