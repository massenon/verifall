<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 9 — quatre référentiels qui vivaient en dur dans les gabarits.
 *
 * ## Ce que cette migration rend administrable
 *
 * **La FAQ.** Dix-sept questions écrites dans `lang/{fr,en}/faq.php` et
 * déroulées à la main dans `public/faq.blade.php`. Le menu d'administration
 * portait bien une entrée « Gestion FAQ » — elle pointait sur la page PUBLIQUE.
 * Corriger une réponse demandait un développeur et un déploiement, pour un
 * texte qui change à chaque question nouvelle du support.
 *
 * **Les partenaires.** Un carrousel de logos écrit en dur sur l'accueil, avec
 * les URL et les chemins d'images. Ajouter un partenaire — le geste le plus
 * ordinaire d'un projet financé — exigeait de toucher au code.
 *
 * **Les témoignages.** Même situation.
 *
 * **Les gabarits d'e-mails.** Voir plus bas : la table ne porte QUE ce qui peut
 * réellement être surchargé.
 *
 * ## La FAQ garde ses DEUX langues, et c'est le point délicat
 *
 * Passer d'un fichier de langue à une table, c'est risquer de perdre la
 * traduction — une table à une colonne `question` ne sait pas dire « en
 * anglais ». Or le site expose un sélecteur FR/EN, et les dix-sept questions
 * existent dans les deux langues.
 *
 * D'où la colonne `locale`, et une paire de lignes par question. Le semeur
 * reprend les deux fichiers existants : **rien n'est perdu, rien n'est
 * retraduit à la main.**
 *
 * ## `email_templates` ne porte QUE le sujet, et c'est délibéré
 *
 * Le réflexe serait une table `subject` + `body`. Ce serait une fausse
 * promesse : les six courriels de la plateforme portent de la LOGIQUE dans
 * leur corps — un code à usage unique et son délai d'expiration, un lien
 * signé d'export, un tableau récapitulatif hebdomadaire. Un corps modifiable
 * en base ferait perdre ces variables au premier enregistrement, et le seul
 * symptôme serait un courriel envoyé sans son code.
 *
 * Ce qui se surcharge sans danger, c'est le SUJET — la ligne qu'on voit dans
 * sa boîte, celle qu'on veut ajuster pour qu'elle passe mieux. La table s'y
 * tient, et l'écran le dit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
             | La langue de CETTE ligne. Une question existe donc en autant de
             | lignes que de langues servies — c'est ce qui permet de reprendre
             | les deux fichiers de langue sans rien perdre.
             */
            $table->string('locale', 5)->default('fr');

            /*
             | La clé qui relie les traductions d'une même question entre elles.
             | Sans elle, corriger la version française d'une question ne
             | permettrait pas de retrouver l'anglaise à corriger aussi.
             */
            $table->string('group_key', 120);

            $table->string('category', 60)->nullable();
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['locale', 'group_key']);
            $table->index(['is_published', 'locale', 'sort_order']);
        });

        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 160);

            /*
             | Le chemin du logo, relatif à `public/`. Pas un téléversement :
             | les logos actuels sont déjà déposés dans `assets/images/partners/`,
             | et un dépôt de fichier ouvrirait une chaîne complète — validation
             | du type par le contenu, disque privé, route signée — pour six
             | images qui bougent une fois par an.
             */
            $table->string('logo_path', 255)->nullable();

            $table->string('url', 500)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('author', 160);
            $table->string('role', 160)->nullable();
            $table->text('quote');
            $table->string('avatar_path', 255)->nullable();

            /*
             | Nullable, et pas « 5 par défaut ». Un témoignage sans note n'est
             | pas un témoignage cinq étoiles : c'est un témoignage sans note.
             | Mettre 5 par défaut fabriquerait des avis élogieux que personne
             | n'a donnés.
             */
            $table->unsignedTinyInteger('rating')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
             | La clé du courriel, arrêtée en code (`GabaritsDEmail::CLES`).
             | Pas de création libre : une ligne dont la clé ne correspond à
             | aucun envoi serait un réglage que personne ne lit — et l'écran
             | laisserait croire qu'on peut ajouter un courriel en ajoutant une
             | ligne.
             */
            $table->string('key', 60)->unique();

            $table->string('subject', 200);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('faqs');
    }
};
