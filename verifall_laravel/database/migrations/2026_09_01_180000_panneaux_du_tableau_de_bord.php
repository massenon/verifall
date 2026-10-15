<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\ClassificationThematique;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qui manquait au tableau de bord d'administration pour dire la vérité.
 *
 * Le prototype (`compte-admin/dashboard.html`) montre dix panneaux. Quatre
 * n'avaient AUCUNE colonne derrière eux — ils affichaient des nombres écrits en
 * dur dans le HTML. Les transposer tels quels aurait produit un écran qui a
 * l'air de mesurer et qui n'observe rien.
 *
 * Chaque ajout ci-dessous existe pour un panneau nommé, et pour lui seul.
 *
 * ## 1. `verifications.theme` — panneau « Top Thématiques »
 *
 * Le prototype affiche « Général 427 · Politique 29 · Santé 12 · Arnaque 6 ».
 * Aucune de ces valeurs ne correspond à une taxonomie du projet : ni
 * `hub_articles.category`, ni `forum_categories`, ni `hub_portals.category` ne
 * portent « Arnaque ». C'est une taxonomie propre aux VÉRIFICATIONS, et elle
 * n'existait nulle part.
 *
 * Classée par {@see ClassificationThematique},
 * une heuristique lexicale déterministe — donc explicable et testable — et non
 * un appel de modèle : une thématique ne vaut pas une requête facturée, et un
 * modèle qui rend « Politiqu3 » un jour sur cent ruinerait l'agrégat.
 *
 * ## 2. `verifications.assigned_to` — panneau « Dernières Activités »
 *
 * La colonne « Assigné à » du prototype était décorative : rien ne portait
 * l'assignation d'une vérification à un journaliste. `handled_by` existe sur
 * `content_reports`, mais c'est la modération d'un signalement, pas la prise en
 * charge d'une analyse.
 *
 * `nullOnDelete` : une vérification assignée à quelqu'un qui quitte l'équipe
 * redevient libre. La supprimer avec la personne perdrait le travail ; la
 * garder pointée sur un compte effacé afficherait un nom fantôme.
 *
 * ## 3. `hub_articles.source_clicks` — panneau « Performance Articles »
 *
 * Le prototype annonce « Titre · Vues · Clics ». `views` existait ; « clics »
 * n'existait pas, et n'a de sens que défini : c'est le nombre de départs vers
 * `source_url`, la source d'origine. C'est la seule mesure qui distingue un
 * article lu d'un article qui a réellement renvoyé quelqu'un à la preuve.
 *
 * ## 4. `visitor_hits` — panneau « Navigateurs »
 *
 * Aucune analytique n'existait ; `docs/RESTE_A_FAIRE.md` le note depuis le
 * 25 août comme ce qui manque à deux panneaux du tableau de bord.
 *
 * **Une ligne par famille de navigateur et par jour, et rien d'autre.** Pas
 * d'adresse IP, pas d'identifiant de visiteur, pas d'en-tête brut, aucun
 * cookie, aucun tiers : un compteur ne permet de reconstituer le parcours de
 * personne. C'est le seul dessin qui répond à la question posée sans faire
 * entrer de données personnelles dans le projet, et donc sans rien ajouter aux
 * obligations de `docs/SECURITE_RGPD.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            // 32 caractères : la liste est CLOSE et courte. Une colonne longue
            // inviterait à y écrire du texte libre, et l'agrégat n'aurait
            // plus de sens.
            $table->string('theme', 32)->nullable()->after('type');

            $table->foreignId('assigned_to')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');

            // Le panneau agrège par thème sur une fenêtre de dates : sans cet
            // index, il balaie toute la table à chaque ouverture de l'écran.
            $table->index(['theme', 'created_at']);
        });

        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->unsignedInteger('source_clicks')->default(0)->after('views');
        });

        Schema::create('visitor_hits', function (Blueprint $table): void {
            $table->id();

            // La FAMILLE, jamais la chaîne complète. Un User-Agent entier est
            // quasi identifiant — version du système, du navigateur, parfois du
            // matériel : conservé tel quel, il redevient une donnée personnelle.
            $table->string('browser', 32);
            $table->date('day');
            $table->unsignedInteger('hits')->default(0);

            $table->timestamps();

            // Une seule ligne par famille et par jour : le middleware
            // incrémente, il n'insère pas. Sans cette contrainte, une rafale
            // simultanée créerait des doublons que l'agrégat compterait deux fois.
            $table->unique(['browser', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_hits');

        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->dropColumn('source_clicks');
        });

        Schema::table('verifications', function (Blueprint $table): void {
            $table->dropIndex(['theme', 'created_at']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['theme', 'assigned_at']);
        });
    }
};
