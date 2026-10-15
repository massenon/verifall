<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes à usage unique (SECURITE_LOGIN).
 *
 * Le code n'est JAMAIS stocké en clair : seul son hachage l'est. Une fuite de
 * cette table ne permet donc pas de prendre la main sur un compte en cours
 * d'inscription ou de réinitialisation.
 *
 * L'identité est elle aussi hachée : la table servant l'anti-énumération, y
 * lire la liste des e-mails ayant demandé un code serait contradictoire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();

            // HMAC de l'e-mail ou du téléphone visé.
            $table->char('identity_hash', 64)->index();

            // 'registration' | 'password_reset' | 'login_challenge'
            $table->string('purpose', 32)->index();

            $table->char('code_hash', 64);

            // Tentatives de vérification : au-delà de 5, le code est invalidé
            // — sinon un code à 6 chiffres se force en quelques minutes.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();

            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();

            // Recherche du code actif : (identité, usage, non consommé).
            $table->index(['identity_hash', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
