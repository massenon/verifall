<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forum citoyen.
 *
 * Ce forum n'est pas un forum de discussion ordinaire : on y signale des
 * rumeurs, la communauté donne son avis, et la plateforme peut y apposer un
 * VERDICT OFFICIEL. Trois conséquences pour le schéma :
 *
 *   · L'avis de la communauté et le verdict de Vérif'All sont deux colonnes
 *     distinctes, jamais mélangées. Une majorité qui se trompe reste une
 *     majorité qui se trompe : c'est même le mécanisme central de la
 *     désinformation, et une plateforme de fact-checking ne peut pas le
 *     confondre avec une vérification.
 *   · Un vote par personne et par sujet, tenu par une contrainte. Un sondage
 *     où l'on vote dix fois ne mesure rien.
 *   · Le lien vers la VÉRIFICATION issue du sujet est conservé : c'est la
 *     « transmission au labo », et elle doit rester traçable dans les deux
 *     sens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('threads', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('forum_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('body');

            /*
             | Lien SIGNALÉ par l'auteur. Jamais rendu cliquable sans
             | `rel="nofollow noopener"` : une plateforme qui combat la
             | désinformation ne doit pas transmettre son autorité de référencement
             | à l'arnaque qu'on lui signale.
             */
            $table->string('source_url', 500)->nullable();

            /*
             | VERDICT OFFICIEL de la plateforme, distinct de l'avis de la
             | communauté. Nul tant que l'équipe n'a pas tranché — et un sujet
             | sans verdict officiel ne doit jamais en afficher un.
             */
            $table->string('official_verdict', 20)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // La vérification née de ce sujet — la « transmission au labo ».
            $table->foreignId('verification_id')->nullable()->constrained()->nullOnDelete();

            /*
             | Compteurs DÉNORMALISÉS. Le fil se lit bien plus souvent qu'il ne
             | s'écrit : recompter les réponses et les votes à chaque affichage
             | ferait quatre agrégats par carte.
             */
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('votes_true')->default(0);
            $table->unsignedInteger('votes_doubt')->default(0);
            $table->unsignedInteger('votes_false')->default(0);

            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['forum_category_id', 'last_activity_at']);
            $table->index('is_pinned');
        });

        Schema::create('replies', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Réponse à une réponse : un seul niveau d'imbrication, comme le
            // prototype. Au-delà, un fil devient illisible sur téléphone.
            $table->foreignId('parent_id')->nullable()->constrained('replies')->cascadeOnDelete();

            $table->text('body');

            /*
             | Réponse de l'ÉQUIPE, mise en avant. Distincte d'un simple message
             | de modérateur : c'est la parole de la plateforme, et elle doit se
             | reconnaître au premier coup d'œil.
             */
            $table->boolean('is_official')->default(false);
            $table->unsignedInteger('likes_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('thread_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'true' | 'doubt' | 'false' — l'avis de la communauté, pas un
            // verdict.
            $table->string('opinion', 10);

            $table->timestamps();

            // Un sondage où l'on vote dix fois ne mesure rien.
            $table->unique(['thread_id', 'user_id']);
        });

        Schema::create('content_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Polymorphe : un « j'aime » vaut pour un sujet comme pour une
            // réponse, et dupliquer la table pour chaque cible produirait deux
            // fois le même code.
            $table->morphs('likeable');

            $table->timestamps();

            $table->unique(['user_id', 'likeable_type', 'likeable_id'], 'likes_uniques');
        });

        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('reportable');

            $table->string('reason', 40);
            $table->text('detail')->nullable();

            /*
             | Un signalement se TRAITE, il ne se supprime pas. Effacer la
             | trace, c'est perdre le fait qu'une décision a été prise — et
             | rendre impossible de reconnaître un signaleur abusif.
             */
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('handling_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('content_likes');
        Schema::dropIfExists('thread_votes');
        Schema::dropIfExists('replies');
        Schema::dropIfExists('threads');
        Schema::dropIfExists('forum_categories');
    }
};
