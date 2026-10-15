<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La marque sans laquelle la prime d'XP serait versée en boucle.
 *
 * ## Pourquoi une colonne, et pas une déduction
 *
 * Le quiz déduit l'unicité de son journal de tentatives : « a-t-il déjà
 * réussi ? » se lit dans `quiz_attempts`. **Un cours n'a pas d'équivalent.**
 * `completed_at` est bien figée à la première complétion, mais
 * `ProgressionAcademie::rafraichir()` s'exécute à CHAQUE leçon lue, chaque quiz
 * corrigé, chaque exercice remis — et une personne qui relit une leçon après
 * avoir fini repasserait par la branche d'achèvement.
 *
 * Sans marque, la prime serait donc versée autant de fois qu'on rouvre le
 * cours. Cinq relectures d'un cours à 250 XP, et voilà 1 250 XP pour un
 * parcours qui en vaut 250 — soit deux paliers de niveau gagnés en rouvrant
 * une page.
 *
 * ## Posée même quand la prime vaut zéro
 *
 * Elle enregistre que **le moment de l'attribution est passé**, pas qu'une
 * somme a été versée. Un cours dont le mentor fixe la récompense APRÈS coup ne
 * paiera donc pas rétroactivement ceux qui l'avaient déjà terminé : la prime
 * est celle qui était annoncée quand ils l'ont suivi, ce qui est aussi la seule
 * règle qu'on puisse expliquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('xp_awarded_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('xp_awarded_at');
        });
    }
};
