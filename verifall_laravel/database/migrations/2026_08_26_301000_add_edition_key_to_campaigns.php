<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La clé d'édition — ce qui empêche deux infolettres pour la même semaine.
 *
 * Une campagne n'avait jusqu'ici aucune clé naturelle : deux lignes « Récap de
 * la semaine » se créaient sans que rien ne s'y oppose, et chacune envoyait la
 * sienne. La clé vaut `hebdo-2026-W35` — l'année ISO et le numéro de semaine
 * ISO, qui est le seul découpage où le lundi ouvre bien la semaine.
 *
 * Elle est NULLE pour toutes les campagnes rédigées à la main : celles-là n'ont
 * pas d'édition, elles ont un auteur qui décide quand elles partent. Une
 * colonne unique et nullable accepte autant de NULL qu'on veut, en MySQL comme
 * en SQLite — c'est ce qui permet de contraindre les unes sans gêner les autres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('edition_key', 40)->nullable()->unique()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            /*
             | L'index nommé explicitement : SQLite ne sait pas retirer une
             | contrainte d'unicité en devinant son nom, et une migration qui
             | ne se rejoue pas à l'envers n'est pas une migration.
             */
            $table->dropUnique('campaigns_edition_key_unique');
            $table->dropColumn('edition_key');
        });
    }
};
