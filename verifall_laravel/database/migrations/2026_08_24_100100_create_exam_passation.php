<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examens — la PASSATION : enrôlement, copies, réponses.
 *
 * Trois règles tenues par le schéma lui-même, parce qu'une règle qui ne vit que
 * dans le code finit contournée par un job, un import ou une commande de
 * maintenance :
 *
 *   · UNE inscription par personne et par session. Sans elle, un double-clic
 *     sur « Convoquer » envoie deux convocations et fausse le nombre de places
 *     restantes.
 *   · UNE copie par personne, par examen et par NUMÉRO de tentative. Sans elle,
 *     deux soumissions concurrentes créent deux copies « n° 1 » et le
 *     rattrapage devient incomptable.
 *   · UNE réponse par position dans une copie. Sans elle, renvoyer le
 *     formulaire double les points de la même question.
 *
 * Ce que l'on FIGE, et pourquoi : le seuil, le barème et l'énoncé sont recopiés
 * sur la copie au moment de composer. Un examen dont on relève le seuil de 70 à
 * 80 % ne doit pas recaler après coup ceux qui ont eu 75 % quand le seuil était
 * de 70. Une note est un fait daté, pas le résultat d'un calcul rejouable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_registrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('registered');

            /*
             | Le code porté par la convocation — « CONV-K3M9 ». Public, donc
             | tiré sur un alphabet sans O/0, I/1, S/5 : un candidat le recopie
             | à la main devant un surveillant.
             */
            $table->string('convocation_code', 40)->nullable()->unique();

            /*
             | « Envoyée » / « À envoyer » se lit ICI, pas dans un statut. Une
             | date répond en plus à « depuis quand », que l'écran d'enrôlement
             | demandera tôt ou tard.
             */
            $table->timestamp('convocation_sent_at')->nullable();

            // Demande de report — l'indicateur « 6 à arbitrer » les compte, et
            // la date dit dans quel ordre les traiter.
            $table->timestamp('deferral_requested_at')->nullable();
            $table->text('deferral_reason')->nullable();

            // Émargement. Source unique du taux de présence affiché.
            $table->timestamp('attended_at')->nullable();

            $table->timestamps();

            $table->unique(['exam_session_id', 'user_id']);
            $table->index(['status', 'convocation_sent_at']);
        });

        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
             | La référence PUBLIQUE de la copie — « EX-4449 » dans la file de
             | correction. Distincte de l'uuid : c'est elle qu'un mentor dicte
             | au téléphone, et un identifiant technique n'a rien à faire là.
             */
            $table->string('reference', 40)->unique();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            /*
             | La session se détache : purger une session de l'an dernier ne
             | doit pas emporter les notes qui y ont été obtenues. Une copie
             | orpheline garde une trace utile ; une copie effacée, non.
             */
            $table->foreignId('exam_session_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 1, 2, 3… — le rattrapage se compte, il ne se devine pas.
            $table->unsignedTinyInteger('attempt_no')->default(1);

            $table->string('status', 20)->default('in_progress');

            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();

            /*
             | FIGÉS à la composition. Le seuil de l'examen peut être relevé, le
             | barème d'une question corrigé : ce qui a été exigé de CETTE copie
             | ne bouge plus. Sans ces deux colonnes, rejouer le calcul six mois
             | plus tard donnerait une autre note.
             */
            $table->unsignedTinyInteger('pass_score');
            $table->unsignedSmallInteger('max_points');

            $table->unsignedSmallInteger('score_points')->nullable();
            $table->unsignedTinyInteger('score_percent')->nullable();
            $table->boolean('passed')->nullable();

            // Le correcteur assigné, et celui qui a effectivement rendu la
            // note : ce n'est pas toujours le même, et l'écran affiche le premier.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('feedback')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'exam_id', 'attempt_no']);

            // La file de correction se lit du plus ancien au plus récent —
            // c'est ce tri qui fait apparaître les copies « urgentes (> 72 h) ».
            $table->index(['status', 'submitted_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('exam_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();

            /*
             | La question se détache. Ce qui a été demandé et ce qu'il valait
             | sont recopiés juste en dessous : une copie doit rester lisible
             | même si la question a été retirée de la banque depuis.
             */
            $table->foreignId('question_bank_item_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('position');

            // Recopiés à la composition — l'énoncé, le type et le barème.
            $table->text('prompt');
            $table->string('type', 20);
            $table->unsignedSmallInteger('points_max');

            // Réponse libre d'un côté, propositions cochées de l'autre : les
            // deux ne coexistent jamais sur la même ligne.
            $table->text('given_text')->nullable();
            $table->json('given_options')->nullable();

            /*
             | NULL = pas encore tranché. C'est exactement ce qui distingue une
             | copie en attente de correction d'une copie tout juste ratée : un
             | booléen par défaut à `false` aurait noté zéro un travail que
             | personne n'a encore lu.
             */
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('points_awarded')->nullable();
            $table->text('grader_comment')->nullable();

            $table->timestamps();

            $table->unique(['exam_attempt_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_registrations');
    }
};
