<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HSI = Hate Speech Index, pas « santé/sécurité ».
 *
 * `ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md` glose `hsi_flags` par « santé/sécurité »,
 * et c'est ainsi qu'il avait été implémenté — un simple booléen levé par des
 * mots-clés médicaux. Deux sources concordantes disent autre chose :
 *
 *   · le prototype (`compte-user/rapport.html`) porte un bloc « 🛡️ Hate Speech
 *     Indicator » avec un « Hate Speech Index (HSI) » exprimé en pourcentage ;
 *   · le moteur de juillet calcule un `hate_speech { score, tags, explanation }`
 *     dans le prompt Gemini lui-même.
 *
 * C'est aussi l'objet du projet — « un bouclier citoyen contre les discours de
 * haine en ligne ». Un booléen sur des mots-clés n'en tenait pas lieu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table): void {
            $table->dropColumn('hsi_flag');

            // Score 0-100, comme le prototype l'affiche. Nullable : un rapport
            // dont aucun moteur n'a évalué la rhétorique ne doit pas afficher
            // « 0 % » — ce serait affirmer une absence qu'on n'a pas mesurée.
            $table->unsignedTinyInteger('hate_speech_score')->nullable()->after('scores');

            /** @var list<string> tags explicatifs — « Ciblage géographique »… */
            $table->json('hate_speech_tags')->nullable()->after('hate_speech_score');

            $table->text('hate_speech_note')->nullable()->after('hate_speech_tags');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table): void {
            $table->dropColumn(['hate_speech_score', 'hate_speech_tags', 'hate_speech_note']);
            $table->boolean('hsi_flag')->default(false);
        });
    }
};
