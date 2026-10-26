<?php

declare(strict_types=1);

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| La médiathèque de la console : les images déposées par l'équipe.
|
| Les fichiers vivent sur le disque PRIVÉ, sous un nom aléatoire ; la base garde
| le nom d'origine pour l'affichage seulement. Aucune URL publique : la console
| les sert par lien signé et temporaire.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chemin', 120)->unique();
            $table->string('nom_origine', 160);
            $table->string('mime', 40);
            $table->unsignedInteger('octets');
            $table->unsignedSmallInteger('largeur')->nullable();
            $table->unsignedSmallInteger('hauteur')->nullable();
            $table->string('texte_alternatif', 200)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
