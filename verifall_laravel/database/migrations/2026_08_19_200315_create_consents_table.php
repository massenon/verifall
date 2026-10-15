<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des consentements (RGPD — SECURITE_RGPD §5).
 *
 * Le RGPD exige de pouvoir PROUVER un consentement : qui, à quoi, à quelle
 * version du texte, quand, depuis où. Une simple colonne booléenne sur `users`
 * ne le permettrait pas — elle ne dirait ni quand, ni à quelle version des CGU.
 *
 * Les lignes ne sont jamais modifiées : un retrait de consentement crée une
 * NOUVELLE ligne « revoked ». L'historique est la preuve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'cgu' (obligatoire) · 'privacy' · 'newsletter' (facultatif, séparé)
            $table->string('type', 32)->index();

            // Version du texte accepté : sans elle, impossible de savoir à quoi
            // l'utilisateur a consenti après une mise à jour des CGU.
            $table->string('document_version', 32);

            $table->boolean('granted')->default(true);

            // IP pseudonymisée : la preuve n'exige pas de conserver l'adresse.
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            // Requête de preuve la plus fréquente : « quel est l'état courant
            // du consentement X pour l'utilisateur Y ? »
            $table->index(['user_id', 'type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
