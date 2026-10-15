<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 5 — le référentiel des sources de confiance.
 *
 * ## Pourquoi ces tables existent, et ce qu'elles évitent
 *
 * `AnalyseTechniqueAdapter` juge la crédibilité d'un lien à partir de deux
 * listes écrites en dur : cinq mots qui « évoquent une institution » et sept
 * extensions réputées éphémères. Ces listes ne connaissent aucune source.
 *
 * La conséquence était mesurable et fausse : la règle pénalise **tout** domaine
 * contenant `gov`, avec la seule exception de `gouv.bj`. `cdc.gov`, `usa.gov`,
 * `sante.gouv.fr` — les institutions les plus vérifiables du web — recevaient
 * 35 points de suspicion pour usurpation d'identité institutionnelle.
 *
 * Une liste en dur ne pouvait pas corriger cela : on n'énumère pas dans le code
 * les sources fiables du monde, et surtout on ne redéploie pas pour en ajouter
 * une. C'est une décision éditoriale, prise par la rédaction, révisable.
 *
 * ## Deux tables, et pas une
 *
 * On aurait pu porter la catégorie en chaîne libre sur la source, comme
 * `tools.category`. Trois raisons de ne pas le faire ici :
 *
 *  · la liste n'est PAS fermée — `Tool` s'appuie sur les filtres du prototype,
 *    ici la rédaction crée ses propres familles au fil de sa veille ;
 *  · une catégorie porte elle-même une information : le niveau de confiance
 *    par défaut d'une « agence de presse internationale » n'est pas celui d'un
 *    « média partisan surveillé » ;
 *  · renommer une famille se fait alors en un point, pas par un `UPDATE` sur
 *    des centaines de lignes — et sans laisser « Presse » à côté de « presse ».
 *
 * ## `domain` est unique, et c'est la clé du branchement
 *
 * Le moteur interroge le référentiel PAR DOMAINE, pas par nom : c'est la seule
 * chose qu'il tient d'une URL. Deux fiches pour `lemonde.fr` rendraient la
 * réponse du moteur dépendante de l'ordre d'insertion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 500)->nullable();

            /*
             | La confiance PAR DÉFAUT des sources de cette famille, proposée à
             | la création d'une fiche. Ce n'est pas une contrainte : une fiche
             | peut s'en écarter, et c'est le propre d'un défaut.
             */
            $table->unsignedTinyInteger('default_credibility')->default(70);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('trusted_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
             | `nullOnDelete` et non `cascade` : supprimer une famille ne doit
             | pas emporter les sources qu'elle contenait. Une fiche orpheline
             | reste utilisable par le moteur et se reclasse en un geste ;
             | une fiche effacée par ricochet se redécouvre à la main.
             */
            $table->foreignId('source_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 160);

            /*
             | Le domaine seul, sans schéma ni chemin : `lemonde.fr`, jamais
             | `https://lemonde.fr/`. Le modèle normalise à l'écriture — c'est
             | la valeur que le moteur compare à `parse_url($url, PHP_URL_HOST)`.
             */
            $table->string('domain', 190)->unique();

            $table->string('url', 500)->nullable();
            $table->unsignedTinyInteger('credibility')->default(70);

            /*
             | Une source RÉFÉRENCÉE n'est pas forcément une source ACTIVE.
             | Retirer une fiche du jeu sans perdre ce qu'on avait établi à son
             | sujet — la note, la catégorie, l'historique — est le geste
             | ordinaire quand un média change de main.
             */
            $table->boolean('is_active')->default(true);

            /*
             | La NOTE ÉDITORIALE : pourquoi cette source est ici, ce qu'elle
             | vaut, ce qu'on lui reproche. C'est ce qui distingue un référentiel
             | d'une liste blanche — et ce qu'on relit deux ans plus tard pour
             | savoir si la décision tient encore.
             */
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'domain']);
            $table->index('source_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_sources');
        Schema::dropIfExists('source_categories');
    }
};
