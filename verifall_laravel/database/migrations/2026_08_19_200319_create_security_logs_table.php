<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal de sécurité — alimente la page « Alertes Intrusion ».
 *
 * Distinct du journal d'audit, et volontairement : l'audit trace ce que font
 * les utilisateurs LÉGITIMES, la sécurité trace ce qui ressemble à une
 * attaque. Les mélanger noierait les secondes dans les premières.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table): void {
            $table->id();

            // login_failed · rate_limited · upload_rejected · otp_replayed …
            $table->string('event', 64)->index();
            // info | warning | critical
            $table->string('severity', 16)->default('info')->index();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Identité tentée, hachée : on doit pouvoir corréler des tentatives
            // sur un même compte sans stocker l'e-mail visé en clair.
            $table->char('identity_hash', 64)->nullable()->index();

            $table->char('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
