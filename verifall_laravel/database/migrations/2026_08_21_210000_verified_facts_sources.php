<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un fait vérifié s'appuie sur PLUSIEURS sources.
 *
 * Le prototype le dit deux fois : le formulaire demande « Sources (URLs, une
 * par ligne) », et le tableau affiche une colonne « SOURCES » qui compte « 4
 * sources ». La table n'en portait qu'une.
 *
 * Ce n'est pas cosmétique. Un fait tranché par l'équipe court-circuite douze
 * moteurs et produit un rapport à 100 % de confiance : s'il n'expose qu'un
 * lien là où la rédaction en a recoupé quatre, le lecteur voit un verdict
 * catégorique adossé à une seule page. C'est exactement ce qu'on reproche à la
 * désinformation.
 *
 * Les deux anciennes colonnes sont REPRISES puis SUPPRIMÉES : garder les deux
 * écritures ferait deux façons de dire la même chose, et un jour elles
 * diraient deux choses différentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_facts', function (Blueprint $table): void {
            // Liste ordonnée : [{ url, name }]. La première est la source
            // principale — celle qu'on cite en premier dans le rapport.
            $table->json('sources')->nullable()->after('summary');
        });

        foreach (DB::table('verified_facts')->select('id', 'official_link', 'source_name')->cursor() as $fait) {
            if ($fait->official_link === null || $fait->official_link === '') {
                continue;
            }

            DB::table('verified_facts')->where('id', $fait->id)->update([
                'sources' => json_encode(
                    [['url' => $fait->official_link, 'name' => $fait->source_name ?? '']],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ]);
        }

        Schema::table('verified_facts', function (Blueprint $table): void {
            $table->dropColumn(['official_link', 'source_name']);
        });
    }

    public function down(): void
    {
        Schema::table('verified_facts', function (Blueprint $table): void {
            $table->string('official_link', 500)->nullable()->after('summary');
            $table->string('source_name')->nullable()->after('official_link');
        });

        foreach (DB::table('verified_facts')->select('id', 'sources')->cursor() as $fait) {
            $sources = json_decode((string) $fait->sources, true);

            if (! is_array($sources) || $sources === []) {
                continue;
            }

            DB::table('verified_facts')->where('id', $fait->id)->update([
                'official_link' => $sources[0]['url'] ?? null,
                'source_name' => $sources[0]['name'] ?? null,
            ]);
        }

        Schema::table('verified_facts', function (Blueprint $table): void {
            $table->dropColumn('sources');
        });
    }
};
