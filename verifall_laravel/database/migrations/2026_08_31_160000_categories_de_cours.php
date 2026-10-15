<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lot 6 — les catégories de cours deviennent une table.
 *
 * ## Le défaut, visible en une requête
 *
 * `courses.category` est une chaîne libre. Sept valeurs distinctes coexistent
 * déjà en base, écrites selon DEUX conventions :
 *
 *     Fact-Checking · OSINT · IA          ← capitales, accents, tirets
 *     investigation · securite · sante · methode   ← minuscules, sans accents
 *
 * Deux semeurs ont écrit chacun dans son style, et rien ne les a arrêtés.
 * `Citizen\Catalogue` construit sa liste de filtres par
 * `distinct()->pluck('category')` : le citoyen voit donc les deux conventions
 * côte à côte dans le même menu déroulant. Le filtre fonctionne — il compare
 * la chaîne exacte — mais la liste est illisible, et rien n'empêche « Sante »
 * de rejoindre « sante » demain.
 *
 * ## Pourquoi une table plutôt qu'une liste fermée dans le code
 *
 * `Tool::CATEGORIES` est une constante parce que les catégories d'outils sont
 * les FILTRES DU PROTOTYPE : une liste arrêtée, qui sert aussi de clés de
 * langue. Ici, non — le catalogue de l'Académie s'étend au fil des cours
 * produits, et attendre un déploiement pour créer « Vérification d'images »
 * n'a aucun sens. C'est le même raisonnement qu'au lot 5 pour les sources.
 *
 * ## La migration ADOPTE l'existant, elle ne le remplace pas
 *
 * Le réflexe serait de créer la table, d'y semer une liste propre et de
 * réaffecter les cours. Ce serait décider à la place de la rédaction quelle
 * catégorie remplace « methode ».
 *
 * Chaque valeur distincte présente en base devient donc une catégorie, telle
 * quelle : rien ne bouge à l'écran, aucun cours ne change de rayon. Le libellé
 * reste modifiable ensuite depuis le nouvel écran — en un point, et pour tous
 * les cours à la fois, ce qui est précisément ce que la chaîne libre
 * interdisait.
 *
 * ## `category` reste en place, et ce n'est pas de l'indécision
 *
 * La colonne texte est conservée et tenue à jour à l'écriture. Trois lecteurs
 * s'en servent aujourd'hui — `Citizen\Catalogue`, `Citizen\Cours`, l'API v1 —
 * et les faire tous basculer dans la même migration transformerait un ajout en
 * refonte. La table est la SOURCE, la colonne en est la copie dénormalisée ;
 * le jour où les trois lecteurs liront la relation, la colonne partira par une
 * migration à elle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 500)->nullable();

            /*
             | L'icône Material Symbols de la vignette, comme pour les outils.
             | Nullable : une catégorie sans icône s'affiche, elle ne casse pas.
             */
            $table->string('icon', 60)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('course_category_id')
                ->nullable()
                ->after('category')
                // Même choix qu'au lot 5 : supprimer une catégorie ne doit pas
                // emporter les cours. Un cours orphelin se reclasse ; un cours
                // effacé par ricochet se réécrit.
                ->constrained()
                ->nullOnDelete();
        });

        $this->adopterLesCategoriesExistantes();
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeign(['course_category_id']);
            $table->dropColumn('course_category_id');
        });

        Schema::dropIfExists('course_categories');
    }

    /**
     * Chaque valeur distincte de `courses.category` devient une catégorie.
     *
     * Aucune fusion, aucune reformulation : « Fact-Checking » et
     * « investigation » entrent tels quels. C'est à la rédaction de décider,
     * depuis l'écran, si « securite » doit devenir « Sécurité numérique » — et
     * ce choix-là vaut alors pour tous les cours du rayon d'un seul geste.
     */
    private function adopterLesCategoriesExistantes(): void
    {
        $valeurs = DB::table('courses')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $rang = 0;

        foreach ($valeurs as $valeur) {
            $rang += 10;

            $id = DB::table('course_categories')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => (string) $valeur,
                'slug' => Str::slug((string) $valeur).'-'.Str::lower(Str::random(4)),
                'sort_order' => $rang,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('courses')
                ->where('category', $valeur)
                ->update(['course_category_id' => $id]);
        }
    }
};
