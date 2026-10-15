<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits d'accès et d'effacement (SECURITE_RGPD §Droits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             | Suppression en DEUX temps. `deleted_at` ferme immédiatement le
             | compte ; `deletion_requested_at` porte le point de départ du
             | délai de rétractation de 30 jours. Deux dates et non une : la
             | seconde doit survivre à une restauration pour qu'on sache qu'une
             | demande a eu lieu.
             */
            $table->softDeletes();
            $table->timestamp('deletion_requested_at')->nullable()->after('deleted_at');

            /*
             | Anonymisation définitive. Renseignée, elle signifie qu'il ne
             | reste plus rien à effacer — et empêche de rejouer l'opération sur
             | une ligne déjà vidée, ce qui écraserait le marqueur lui-même.
             */
            $table->timestamp('anonymized_at')->nullable()->after('deletion_requested_at');
        });

        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // Cascade : une archive sans propriétaire n'a aucune raison de
            // survivre, et c'est justement l'archive qu'il faut détruire en
            // premier lors d'un effacement.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Chemin sur le disque PRIVÉ. Jamais servi directement : le
            // téléchargement passe par une route signée.
            $table->string('path')->nullable();

            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('size_bytes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            // La purge balaie par échéance : sans cet index elle parcourrait
            // toute la table à chaque passage.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['deleted_at', 'deletion_requested_at', 'anonymized_at']);
        });
    }
};
