<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reprise d'une analyse qui n'a pas abouti.
 *
 * Une analyse qui échoue parce que les moteurs sont saturés n'est pas une
 * analyse ratée : c'est une analyse à refaire plus tard. Rendre un rapport
 * creux — « verdict indisponible » — serait la pire réponse possible : la
 * personne repart sans rien, et avec l'impression que la plateforme ne
 * fonctionne pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            // Tentatives déjà faites. Bornées : au-delà, on rend les unités
            // plutôt que d'entretenir indéfiniment un espoir.
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');

            // Moment à partir duquel une reprise est possible. Affiché à la
            // personne : « revenez dans 12 minutes » vaut mieux qu'une roue.
            $table->timestamp('retry_after')->nullable()->after('attempts');

            // Pourquoi la dernière tentative n'a rien donné — dit en clair,
            // sans détail technique.
            $table->string('retry_reason')->nullable()->after('retry_after');

            $table->index(['status', 'retry_after']);
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            $table->dropIndex(['status', 'retry_after']);
            $table->dropColumn(['attempts', 'retry_after', 'retry_reason']);
        });
    }
};
