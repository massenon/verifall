<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avancement réel du pipeline — page « Analyse en cours ».
 *
 * Le prototype fait défiler six étapes sur des minuteurs aléatoires. Ici elles
 * reflètent ce que le pipeline fait VRAIMENT : si l'analyse cale sur les
 * moteurs, la barre s'arrête là. Une barre qui avance toute seule pendant qu'un
 * service est en panne ment à la personne qui la regarde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            $table->unsignedTinyInteger('pipeline_step')->default(0)->after('status');
        });

        Schema::table('analysis_reports', function (Blueprint $table): void {
            /** @var list<array{etape: string, at: string}> chronologie horodatée */
            $table->json('timeline')->nullable()->after('scores');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_reports', fn (Blueprint $table) => $table->dropColumn('timeline'));
        Schema::table('verifications', fn (Blueprint $table) => $table->dropColumn('pipeline_step'));
    }
};
