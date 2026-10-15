<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit (SECURITE_RGPD §4) — alimente la page « Journal d'Audit ».
 *
 * Toute action sensible du back-office y laisse une trace : qui, quoi, sur
 * quoi, depuis où, et le diff avant/après. C'est ce diff qui distingue un
 * journal exploitable d'une simple liste d'événements.
 *
 * L'acteur est en nullOnDelete : la suppression d'un compte ne doit pas
 * effacer l'historique des actions administratives qu'il a menées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Dénormalisé : le nom de l'acteur doit rester lisible même après
            // anonymisation RGPD du compte.
            $table->string('actor_label')->nullable();

            $table->string('action', 64)->index();
            $table->string('module', 48)->index();

            $table->string('target_type', 64)->nullable();
            $table->string('target_uuid', 36)->nullable();
            $table->string('target_label')->nullable();

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            // Filtres de la page d'audit : par cible, et par acteur dans le temps.
            $table->index(['target_type', 'target_uuid']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
