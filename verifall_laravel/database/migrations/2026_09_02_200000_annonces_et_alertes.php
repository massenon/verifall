<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les deux marqueurs qui débloquent les deux derniers courriels.
 *
 * ## `courses.announced_at` — l'annonce ne se rejoue pas
 *
 * Publier n'est pas un geste unique : on dépublie pour corriger une leçon, on
 * republie une heure après. Sans marqueur, chaque bascule renverrait l'annonce
 * à toute l'audience — et la troisième correction d'un mentor deviendrait le
 * troisième courriel de la journée pour les mêmes personnes.
 *
 * `is_published` ne suffit pas : il dit l'état, pas si l'annonce est partie.
 * Même raisonnement que `enrollments.xp_awarded_at`, et pour le même motif.
 *
 * ## `hub_articles.is_urgent` — le marqueur que le courriel attendait
 *
 * `alerte_intox` était déclaré au catalogue et non câblé, faute de pouvoir dire
 * qu'un démenti est urgent. **Une case à cocher, et non une catégorie** : la
 * catégorie décrit le SUJET (santé, politique), l'urgence décrit la
 * circulation. Une intox sur la santé n'est pas urgente parce qu'elle parle de
 * santé, elle l'est parce qu'elle se propage maintenant.
 *
 * Elle est délibérément séparée de « à la une » : mettre en avant sur le site
 * et écrire à trois mille personnes ne sont pas le même engagement.
 *
 * ## `hub_articles.alerted_at` — et l'alerte non plus ne se rejoue pas
 *
 * Même rôle que `announced_at`, avec un enjeu plus net : une alerte est le
 * message le plus intrusif que la plateforme envoie. La renvoyer parce que
 * quelqu'un a corrigé une faute de frappe serait le meilleur moyen d'apprendre
 * à nos lecteurs à ignorer nos alertes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->timestamp('announced_at')->nullable()->after('is_published');
        });

        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->boolean('is_urgent')->default(false)->after('is_published');
            $table->timestamp('alerted_at')->nullable()->after('is_urgent');
        });
    }

    public function down(): void
    {
        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->dropColumn(['is_urgent', 'alerted_at']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('announced_at');
        });
    }
};
