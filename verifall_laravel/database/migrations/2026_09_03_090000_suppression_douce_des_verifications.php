<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La suppression d'une vérification devient RÉVERSIBLE.
 *
 * ## Pourquoi pas une suppression franche
 *
 * `signalement.delete` est déclarée depuis l'origine, et l'écran qui l'emploiera
 * arrive avec cette migration. Le geste qu'elle autorise porte sur un objet
 * particulier : une vérification a été **demandée par quelqu'un**, elle lui a
 * coûté une unité, et elle figure dans son historique.
 *
 * L'effacer franchement, c'est retirer une ligne du compte d'un citoyen depuis
 * le back-office, sans qu'il l'ait demandé et sans qu'il puisse le constater
 * autrement que par une absence. Le journal d'audit garderait la trace du geste,
 * pas le contenu — et une réclamation deviendrait intranchable.
 *
 * Une suppression douce retire la ligne de toutes les listes, y compris de
 * l'espace citoyen, tout en la gardant récupérable. C'est la même règle que le
 * reste du produit : « un signalement se tranche, la trace demeure ».
 *
 * ## Ce que la colonne ne dit PAS
 *
 * Ni qui a supprimé, ni pourquoi : c'est le rôle du journal d'audit, qui
 * enregistre les deux. Les dupliquer ici créerait deux vérités sur le même
 * geste, et celle de la table finirait par diverger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
