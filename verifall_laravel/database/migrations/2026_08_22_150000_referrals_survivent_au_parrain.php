<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un parrainage survit à l'effacement du parrain.
 *
 * La première version cascadait : effacer un compte effaçait ses parrainages,
 * et le FILLEUL perdait au passage une récompense en attente qui lui était due.
 * Or ce n'est pas son affaire — il n'y est pour rien.
 *
 * Le même raisonnement est déjà appliqué ailleurs dans ce schéma : effacer une
 * vérification n'efface pas les demandes qui s'y sont adossées.
 *
 * Le code du service prévoyait déjà ce cas (`$parrain === null`) : la cascade
 * rendait cette branche inatteignable, donc morte. Schéma et code se rejoignent
 * ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropForeign(['referrer_id']);
        });

        Schema::table('referrals', function (Blueprint $table): void {
            $table->foreignId('referrer_id')->nullable()->change();
        });

        Schema::table('referrals', function (Blueprint $table): void {
            $table->foreign('referrer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropForeign(['referrer_id']);
        });

        Schema::table('referrals', function (Blueprint $table): void {
            $table->foreignId('referrer_id')->nullable(false)->change();
        });

        Schema::table('referrals', function (Blueprint $table): void {
            $table->foreign('referrer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
