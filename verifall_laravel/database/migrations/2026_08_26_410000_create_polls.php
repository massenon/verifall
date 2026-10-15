<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sondage attaché à un sujet du forum.
 *
 * Un sondage par sujet, tenu par une contrainte d'unicité : deux sondages sur
 * le même fil poseraient deux questions concurrentes, et le lecteur ne saurait
 * pas laquelle engage la discussion.
 *
 * Les compteurs sont dénormalisés parce que le résultat s'affiche à chaque
 * chargement du sujet, alors qu'il ne change qu'au rythme des votes.
 *
 * Ce sondage n'est PAS l'avis de la communauté sur la véracité — celui-là vit
 * dans `thread_votes` et n'a que trois valeurs. Ici, l'auteur pose sa propre
 * question. Les mélanger reviendrait à laisser n'importe qui redéfinir ce que
 * « vrai » veut dire sur la plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Unique : un sujet, un sondage.
            $table->foreignId('thread_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('question', 300);

            // Un sondage clos garde ses résultats visibles : les effacer
            // reviendrait à nier qu'on a demandé leur avis à des gens.
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closes_at')->nullable();

            $table->unsignedInteger('votes_count')->default(0);

            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();

            $table->string('label', 200);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('votes_count')->default(0);

            $table->timestamps();

            $table->index(['poll_id', 'position']);
        });

        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Un vote par personne et par sondage — la contrainte porte sur le
            // SONDAGE, pas sur l'option : sans cela, on voterait une fois pour
            // chaque réponse possible.
            $table->unique(['poll_id', 'user_id'], 'vote_sondage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
