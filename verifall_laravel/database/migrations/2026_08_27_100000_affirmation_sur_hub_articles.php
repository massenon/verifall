<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'AFFIRMATION EXAMINÉE, distincte du titre de l'article.
 *
 * ## Le défaut que cette colonne corrige, et il est grave
 *
 * `claimReviewed` recevait le titre de l'article. Or les titres sont
 * éditoriaux : ils énoncent déjà la CONCLUSION. « Aucune taxe WhatsApp n'est
 * instaurée au Bénin », noté FAUX, se lit par une machine « il est faux
 * qu'aucune taxe n'existe » — c'est-à-dire l'inverse exact du démenti.
 *
 * Le défaut était systématique : pour chaque article de démenti, donc pour la
 * majorité, le balisage structuré affirmait le contraire du verdict. Ingéré
 * par un moteur, il aurait fait publier à Vérif'All de la désinformation
 * lisible par machine — le pire mode de défaillance possible pour une
 * organisation de fact-checking.
 *
 * ## Pourquoi une colonne, et pas une transformation du titre
 *
 * Parce qu'on ne peut pas nier une phrase par programme. « Aucune taxe n'est
 * instaurée » ne se retourne pas mécaniquement en « une taxe est instaurée »
 * sans se tromper une fois sur trois, et une fois sur trois est bien trop.
 * L'affirmation telle qu'elle A CIRCULÉ est une donnée éditoriale : elle se
 * saisit, elle ne se déduit pas.
 *
 * ## Nullable, et l'absence a un sens fort
 *
 * `null` veut dire « l'affirmation d'origine n'est pas consignée ». Dans ce
 * cas, AUCUN balisage `ClaimReview` n'est émis. Pas de balisage vaut
 * infiniment mieux qu'un balisage inversé : le premier ne dit rien, le second
 * ment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->text('claim')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->dropColumn('claim');
        });
    }
};
