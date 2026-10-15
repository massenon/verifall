<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le chemin de la carte de démenti d'une brève publiée.
 *
 * ## Pourquoi une colonne, et pas un chemin déduit
 *
 * On pourrait recomposer le chemin à chaque affichage — il est déterministe.
 * Mais alors la page devrait interroger le disque pour savoir si la carte
 * existe, à chaque visite, pour poser une `og:image`. Une colonne dit la même
 * chose sans toucher au système de fichiers, et elle dit surtout QUELLE carte
 * est la bonne : celle qui a réellement été écrite, pas celle qu'on attend.
 *
 * ## Pourquoi le nom du fichier porte une empreinte
 *
 * WhatsApp et Facebook gardent une `og:image` en cache pendant des jours, par
 * URL. Une carte corrigée sous le même nom continuerait d'afficher l'ancien
 * verdict là où il compte le plus. Le nom change avec le contenu ; l'ancien
 * fichier est effacé par le service.
 *
 * Volontairement PAS dans `$fillable` du modèle : aucun formulaire ne remplit
 * cette colonne, seule la file d'attente l'écrit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->string('share_card_path')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->dropColumn('share_card_path');
        });
    }
};
