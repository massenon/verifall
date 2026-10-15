<?php

declare(strict_types=1);

use App\Core\Lms\Services\StatistiquesDeCours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La date de la dernière relance, sans laquelle la relance est du harcèlement.
 *
 * L'écran « Statistiques de cours » permet d'écrire à un inscrit qui décroche.
 * Sans trace de l'envoi précédent, rien n'empêche de cliquer dix fois — ni la
 * même personne dix jours de suite, ni deux membres de l'équipe le même matin
 * sans savoir que l'autre l'a fait.
 *
 * Une relance est un service ; deux relances en trois jours font se désabonner
 * de tout. C'est à l'application de tenir le délai, pas à la discipline de qui
 * clique — voir {@see StatistiquesDeCours::REPOS_JOURS}.
 *
 * Nulle par défaut : « jamais relancé » n'est pas « relancé il y a très
 * longtemps », et une date de complaisance à la création fausserait le premier
 * envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('last_reminder_at')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('last_reminder_at');
        });
    }
};
