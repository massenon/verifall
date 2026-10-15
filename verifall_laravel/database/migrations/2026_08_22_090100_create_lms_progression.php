<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Académie — la PROGRESSION des apprenants.
 *
 * Trois principes tenus par le schéma lui-même, parce qu'une règle qui ne vit
 * que dans le code finit toujours par être contournée par un job, un import ou
 * une commande de maintenance :
 *
 *   · UNE inscription par personne et par cours. Sans la contrainte, un
 *     double-clic sur « S'inscrire » débite deux fois des unités.
 *   · UNE complétion par personne et par leçon. Sans elle, relire une leçon
 *     ferait monter l'avancement au-delà de 100 %.
 *   · UN avis par personne et par cours. Sans elle, une note se noie sous les
 *     doublons de son auteur.
 *
 * Les TENTATIVES de quiz, elles, sont volontairement multiples : garder
 * l'historique des essais est ce qui permet de dire « vous progressez ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('enrolled');

            // Avancement RECALCULÉ à chaque événement, jamais incrémenté : un
            // compteur qu'on incrémente dérive au premier événement rejoué.
            $table->unsignedTinyInteger('progress')->default(0);

            /*
             | Ce qui a été PAYÉ, figé. Le prix du cours peut changer ; ce que
             | quelqu'un a déboursé, non. Sans cette colonne, un remboursement
             | se calculerait au tarif du jour.
             */
            $table->unsignedSmallInteger('paid_units')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('lesson_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            /*
             | Secondes RÉELLEMENT passées. Le prototype exige un temps de
             | lecture minimal avant de valider : sans mesure, « terminer un
             | cours » se réduirait à cliquer six fois sur Suivant, et le
             | certificat ne vaudrait rien.
             */
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('completed_at');

            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('score');
            $table->boolean('passed')->default(false);
            $table->unsignedSmallInteger('duration_s')->default(0);

            // Ce qui a été répondu, question par question — pour rejouer la
            // correction commentée sans redemander le quiz.
            $table->json('given_answers')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'quiz_id']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('submitted');
            $table->text('note')->nullable();

            // Fichier sur le disque PRIVÉ, nom aléatoire, servi par route
            // signée — comme tout téléversement du produit.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('grade')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            /*
             | Une seule remise en cours par exercice : renvoyer un travail
             | REMPLACE le précédent. Empiler les versions ferait une file de
             | correction où le mentor ne sait plus laquelle lire.
             */
            $table->unique(['user_id', 'assignment_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            // Un avis peut être masqué par la modération sans être effacé :
            // supprimer efface aussi la preuve qu'il a existé.
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'is_visible']);
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
             | Code PUBLIC de vérification — « VERIF-A-K3M9-P2XT ». C'est lui
             | qui circule sur un CV, et lui qu'un recruteur saisira sur la page
             | publique. Distinct de l'uuid : un identifiant technique n'a rien
             | à faire imprimé sur un diplôme.
             */
            $table->string('code', 40)->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            /*
             | Nom et intitulé RECOPIÉS à l'émission. Un certificat atteste de
             | ce qui a été suivi CE jour-là : si le cours est renommé ou le
             | compte anonymisé après un effacement RGPD, le document déjà
             | délivré ne doit pas se mettre à mentir — ni redevenir nominatif.
             */
            $table->string('holder_name');
            $table->string('course_title');

            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('live_class_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_class_id')->constrained()->cascadeOnDelete();

            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'live_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_class_registrations');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('enrollments');
    }
};
