<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes de communauté — le contexte que la foule ajoute à un sujet.
 *
 * Le mécanisme n'est PAS un vote majoritaire, et le schéma en porte la trace :
 *
 *   · `bridging_score` est calculé HORS REQUÊTE WEB. Comparer chaque paire de
 *     votants est quadratique ; le faire à l'affichage ferait payer à chaque
 *     lecteur le coût d'un calcul qui ne change que quelques fois par heure.
 *     La colonne est donc un RÉSULTAT stocké, jamais une expression.
 *   · `status` distingue « en attente » de « écartée ». Confondre les deux
 *     ferait passer pour un rejet ce qui n'est qu'un manque de votes — et la
 *     personne qui a pris le temps d'écrire une note n'aurait aucune raison de
 *     recommencer.
 *   · `sources` n'est pas facultatif au sens métier : une note sans source
 *     n'est qu'un avis de plus, et c'est précisément ce dont un fil polarisé
 *     n'a pas besoin. La contrainte vit dans le service, qui sait refuser avec
 *     un message ; la colonne, elle, est simplement NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Court par construction : une note est un ajout de contexte, pas
            // une seconde tribune. Le prototype ne réserve pas plus de place.
            $table->string('body', 1000);

            // Liste d'URL. JSON plutôt qu'une table fille : on ne cherche
            // jamais « les notes qui citent tel domaine », on les affiche
            // toujours avec leur note.
            $table->json('sources');

            /*
             | 'pending'   — pas assez de votes, ou pas encore de pont mesurable
             | 'published' — a franchi le pont, visible de tous
             | 'not_helpful' — évaluée, et jugée non utile
             */
            $table->string('status', 20)->default('pending');

            /*
             | Score de PONTAGE, entre 0 et 1. Jamais affiché à un citoyen :
             | hors contexte, ce nombre ne veut rien dire, et l'exposer invite
             | à le jouer.
             */
            $table->decimal('bridging_score', 5, 4)->nullable();
            $table->timestamp('scored_at')->nullable();

            /*
             | Compteurs DÉNORMALISÉS, recalculés à chaque vote dans la même
             | transaction. Un fil se lit bien plus souvent qu'il ne se vote.
             */
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'status']);
        });

        Schema::create('community_note_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // true = utile, false = pas utile. Deux valeurs et pas trois :
            // l'algorithme de pontage mesure un ACCORD entre votants, et une
            // abstention ne dit rien sur lequel des deux camps on tient.
            $table->boolean('helpful');

            $table->timestamps();

            // Un vote par personne et par note. Sans cette contrainte, la
            // mesure de polarisation compterait dix fois la même personne et
            // conclurait à un pont là où il n'y a qu'un acharné.
            $table->unique(['community_note_id', 'user_id'], 'vote_note_unique');

            // Le calcul lit TOUS les votes d'un votant pour établir avec qui
            // il vote habituellement : c'est l'accès par `user_id` qui porte
            // la commande planifiée.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_note_votes');
        Schema::dropIfExists('community_notes');
    }
};
