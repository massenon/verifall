<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes métier du compte Vérif'All (DICTIONNAIRE_DONNEES_COMPLET).
 *
 * Le squelette Laravel fournit name/email/password ; tout le reste vient du
 * prototype : identité complète, économie d'unités, gamification, RGPD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Identifiant public : jamais l'auto-incrément dans une URL.
            $table->uuid('uuid')->nullable()->after('id');

            // Identité — le prototype sépare prénom et nom dès l'étape 1.
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            // Chiffré au repos : donnée personnelle directement identifiante.
            $table->text('phone')->nullable()->after('email');
            $table->string('profession')->nullable()->after('phone');

            // Économie et gamification (soldes du prototype : 34 unités, 1 240 XP).
            $table->unsignedInteger('units')->default(0)->after('profession');
            $table->unsignedInteger('xp')->default(0)->after('units');
            $table->unsignedSmallInteger('streak_days')->default(0)->after('xp');

            // Cycle de vie du compte.
            // Un compte reste INACTIF tant que l'OTP n'est pas validé.
            $table->timestamp('otp_verified_at')->nullable()->after('email_verified_at');
            $table->timestamp('tour_done_at')->nullable()->after('otp_verified_at');
            $table->timestamp('last_login_at')->nullable()->after('tour_done_at');
            // IP de dernière connexion : hachée, comme partout ailleurs.
            $table->char('last_login_ip_hash', 64)->nullable()->after('last_login_at');

            // 2FA TOTP — obligatoire pour le staff, optionnelle pour le citoyen.
            // Secret et codes de récupération chiffrés par cast Eloquent.
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        // L'unicité est posée après coup : les lignes existantes ont un uuid nul.
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('uuid');
            $table->index('otp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['otp_verified_at']);
            $table->dropColumn([
                'uuid', 'first_name', 'last_name', 'phone', 'profession',
                'units', 'xp', 'streak_days',
                'otp_verified_at', 'tour_done_at', 'last_login_at', 'last_login_ip_hash',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            ]);
        });
    }
};
