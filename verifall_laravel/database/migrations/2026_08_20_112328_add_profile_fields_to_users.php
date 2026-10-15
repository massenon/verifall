<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs du profil citoyen — page « Mon profil » du prototype.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('city', 80)->nullable()->after('profession');

            // Bio PUBLIQUE : visible des autres citoyens sur le forum. Elle
            // n'est donc pas chiffrée, contrairement au téléphone — chiffrer un
            // contenu destiné à être affiché n'apporterait rien et empêcherait
            // toute recherche.
            $table->string('bio', 240)->nullable()->after('city');

            /*
             | Vérification d'identité. Le prototype affiche un badge « Identité
             | vérifiée (KYC) » en dur ; il lui faut une source de vérité, sans
             | quoi la page affirmerait quelque chose que rien ne soutient.
             | Le parcours de vérification lui-même relève du back-office.
             */
            $table->timestamp('kyc_verified_at')->nullable()->after('otp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['city', 'bio', 'kyc_verified_at']);
        });
    }
};
