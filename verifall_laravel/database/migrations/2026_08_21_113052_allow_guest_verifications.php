<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifications sans compte.
 *
 * Quelqu'un qui reçoit une rumeur sur WhatsApp doit pouvoir la soumettre en dix
 * secondes, sans formulaire d'inscription — sinon il partagera sans vérifier.
 * C'est la porte d'entrée du produit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            /*
             | `user_id` devient facultatif. Une vérification d'invité n'a pas
             | de propriétaire : elle appartient à personne, et c'est ce qui la
             | rend consultable sans session tout en restant inoffensive.
             */
            $table->foreignId('user_id')->nullable()->change();

            /*
             | Empreinte du visiteur — le haché de son adresse, jamais l'adresse.
             | Le quota a besoin de distinguer deux visiteurs, pas de savoir qui
             | ils sont ; stocker l'adresse en clair ferait de ce garde-fou un
             | fichier de connexions.
             */
            $table->string('guest_hash', 64)->nullable()->after('user_id');
            $table->index(['guest_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            $table->dropIndex(['guest_hash', 'created_at']);
            $table->dropColumn('guest_hash');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
