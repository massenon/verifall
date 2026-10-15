<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qui a escaladé un sujet en fact-check, et quand.
 *
 * `threads.verification_id` disait déjà QUE le sujet était parti au
 * laboratoire ; il ne disait ni par qui, ni à quel moment. Deux conséquences
 * concrètes, toutes deux rencontrées à l'usage :
 *
 *   · impossible de répondre à « depuis combien de temps attend-on ce
 *     verdict ? » — la date de création de la vérification appartient au
 *     laboratoire, pas au fil ;
 *   · impossible de reconnaître quelqu'un dont les escalades aboutissent, ce
 *     qui est justement la matière première de la réputation citoyenne.
 *
 * Le journal d'audit porte déjà l'information, mais il n'est ni lisible par un
 * citoyen ni interrogeable par sujet : c'est une preuve, pas une source de
 * données d'affichage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table): void {
            $table->foreignId('escalated_by')->nullable()->after('verification_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('escalated_by');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn('escalated_at');
        });
    }
};
