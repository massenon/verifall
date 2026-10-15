<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrouillage progressif des connexions (SECURITE_LOGIN).
 *
 * 5 échecs = 1 min · 10 = 15 min · 20 = notification admin.
 *
 * En base et non en cache : un verrouillage doit survivre à un vidage de cache
 * et à un redémarrage. Un attaquant qui provoquerait une purge de cache ne
 * doit pas remettre son compteur à zéro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();

            // Clé composite identité + IP : verrouiller sur la seule identité
            // permettrait à un tiers de bloquer volontairement le compte d'un
            // citoyen ; verrouiller sur la seule IP pénaliserait un cybercafé.
            $table->char('identity_hash', 64)->index();
            $table->char('ip_hash', 64)->index();

            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamp('last_failure_at')->nullable();

            $table->timestamps();

            $table->unique(['identity_hash', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
