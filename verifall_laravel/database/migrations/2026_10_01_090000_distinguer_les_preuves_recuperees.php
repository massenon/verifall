<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une source OUVERTE ne se compte pas comme une source CITÉE.
 *
 * OpenAI et DeepSeek n'ont pas d'accès web. Leur champ `sources` est une liste
 * de domaines tirés de leur entraînement — `gouv.bj`, `rfi.fr`, `bbc.com` — avec
 * pour extrait la phrase générique « Élément factuel recoupé. » Aucune de ces
 * pages n'a été ouverte ; le chemin peut même être inventé.
 *
 * Elles étaient enregistrées comme des preuves consultées, et le rapport
 * annonçait « 5 sources consultées » là où UNE seule avait été récupérée. C'est
 * la faute des vingt redirections comptées pour vingt sources, en pire : là, au
 * moins, les documents existaient.
 *
 * Le défaut par défaut est `true` : les lignes DÉJÀ en base viennent surtout de
 * Gemini et de Google Fact Check, qui récupèrent réellement. Marquer l'existant
 * à `false` serait aussi faux dans l'autre sens, et irréversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_evidences', function (Blueprint $table): void {
            $table->boolean('fetched')->default(true)->after('reliability');
        });
    }

    public function down(): void
    {
        Schema::table('report_evidences', function (Blueprint $table): void {
            $table->dropColumn('fetched');
        });
    }
};
