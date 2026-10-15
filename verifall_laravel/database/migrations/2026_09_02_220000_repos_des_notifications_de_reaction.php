<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le repos qui rend le courriel « réaction reçue » supportable.
 *
 * ## Deux façons de noyer quelqu'un, pas une
 *
 * Le réflexe est de se protéger du va-et-vient : quelqu'un aime, se ravise,
 * réaime — trois courriels pour un seul avis. C'est réel, mais ce n'est pas le
 * pire.
 *
 * **Le pire est le billet qui décolle.** Cinquante personnes réagissent en une
 * heure, et l'auteur reçoit cinquante courriels. C'est le mode de défaillance
 * classique de cette fonctionnalité, celui qui fait désactiver TOUTES les
 * notifications d'un coup — y compris celles qui comptaient.
 *
 * Un horodatage par CONTENU, et non par réaction, règle les deux : au plus un
 * message par sujet ou par réponse toutes les six heures, quel que soit le
 * nombre de personnes qui réagissent.
 *
 * ## Pourquoi pas sur `content_likes`
 *
 * La ligne est SUPPRIMÉE quand on retire son « j'aime ». Y poser la marque la
 * ferait disparaître avec elle, et le va-et-vient repasserait. La marque doit
 * survivre au geste qu'elle limite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table): void {
            $table->timestamp('reaction_notified_at')->nullable();
        });

        Schema::table('replies', function (Blueprint $table): void {
            $table->timestamp('reaction_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->dropColumn('reaction_notified_at');
        });

        Schema::table('threads', function (Blueprint $table): void {
            $table->dropColumn('reaction_notified_at');
        });
    }
};
