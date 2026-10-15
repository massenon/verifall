<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préférences de notification — page « Réglages » du prototype.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             | Colonne JSON plutôt qu'une table dédiée : ce sont quelques
             | booléens sans historique ni requête transversale. Une table
             | apporterait cinq lignes par compte et aucune réponse nouvelle.
             |
             | La lettre d'information N'EST PAS ici : c'est un CONSENTEMENT
             | marketing, qui doit rester horodaté, versionné et révocable dans
             | `consents`. La ranger parmi des préférences d'affichage ferait
             | perdre la preuve exigée par le RGPD.
             |
             | Nullable : l'absence de valeur signifie « les réglages par
             | défaut », ce qui évite d'avoir à rétro-remplir tous les comptes
             | à chaque nouvelle préférence.
             */
            $table->json('notification_preferences')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
