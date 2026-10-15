<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le journal public des corrections.
 *
 * ## Pourquoi cette table existe
 *
 * Le code de conduite de l'IFCN exige d'une rédaction qu'elle publie ses
 * corrections. La raison n'est pas administrative : une plateforme de
 * fact-checking qui réécrit silencieusement ses verdicts perd le droit
 * d'exiger la même honnêteté des autres. Sans cette table, un verdict
 * « Faux » devenu « Vrai » ne laisse aucune trace — et le lecteur qui avait
 * partagé le démenti ne saura jamais qu'il a changé.
 *
 * ## Pourquoi elle est polymorphe
 *
 * Deux modèles portent un verdict, et les deux se corrigent :
 *
 *   · `HubArticle` porte le verdict PUBLIÉ — la page `/actualites/{slug}` et
 *     le balisage `ClaimReview` qui la fait apparaître dans les panneaux de
 *     fact-checking ;
 *   · `VerifiedFact` porte le verdict SERVI à la place d'une analyse : quand
 *     l'équipe a tranché, aucun moteur n'est consulté et c'est ce verdict-là
 *     qui sort du rapport.
 *
 * Corriger l'un sans raconter l'autre laisserait la moitié de l'histoire dans
 * l'ombre. Une seule table, deux propriétaires.
 *
 * ## Pourquoi `raison` n'est pas nullable, et pourquoi ça ne suffit pas
 *
 * Une correction sans raison ne vaut rien : elle dit qu'on a changé d'avis
 * sans dire ce qu'on a appris. `NOT NULL` écarte l'absence de valeur ; il
 * n'écarte pas la chaîne vide. D'où la contrainte `CHECK` posée juste après —
 * sur les moteurs qui savent l'ajouter après création. SQLite ne le sait pas
 * (aucun `ALTER TABLE … ADD CONSTRAINT`), et c'est le moteur de la suite de
 * tests : l'invariant y est tenu par `VerdictRevision`, qui refuse
 * d'enregistrer une raison vide quel que soit le pilote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verdict_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // `revisable_type` + `revisable_id`, indexés ensemble : la lecture
            // la plus fréquente est « l'historique de CE contenu ».
            $table->morphs('revisable');

            /*
             | INSTANTANÉ de l'affirmation au moment de la correction.
             |
             | Le journal doit rester lisible même quand le contenu d'origine a
             | été supprimé, ou quand son titre a été réécrit depuis. Une ligne
             | de journal qui renvoie à un contenu disparu et n'en dit rien
             | n'est pas une trace, c'est un trou.
             */
            $table->string('affirmation');

            $table->string('ancien_verdict', 20);
            $table->string('nouveau_verdict', 20);

            // Obligatoire. Voir l'en-tête : c'est le cœur de l'exigence IFCN.
            $table->text('raison');

            /*
             | L'auteur est conservé pour la responsabilité INTERNE. Il n'est
             | pas affiché au public : nommer une personne sur une page
             | indexable exposerait une donnée personnelle sans nécessité, là
             | où c'est la rédaction qui engage la plateforme.
             */
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Le journal se lit du plus récent au plus ancien, toujours.
            $table->index('created_at');
        });

        $this->exigerUneRaisonNonVide();
    }

    public function down(): void
    {
        Schema::dropIfExists('verdict_revisions');
    }

    /**
     * Contrainte de base : une raison qui n'est faite que d'espaces n'est pas
     * une raison.
     *
     * Posée seulement là où le moteur sait l'ajouter à une table existante.
     * MySQL 8 et PostgreSQL le savent ; SQLite ne le sait pas, et sa seule
     * façon d'y arriver serait de réécrire la création complète en SQL brut —
     * on perdrait les conventions de nommage de Laravel pour couvrir un moteur
     * qui ne sert qu'aux tests, où le modèle tient déjà l'invariant.
     */
    private function exigerUneRaisonNonVide(): void
    {
        $moteur = Schema::getConnection()->getDriverName();

        if (in_array($moteur, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE `verdict_revisions` ADD CONSTRAINT `verdict_revisions_raison_non_vide` '
                ."CHECK (TRIM(`raison`) <> '')"
            );

            return;
        }

        if ($moteur === 'pgsql') {
            DB::statement(
                'ALTER TABLE verdict_revisions ADD CONSTRAINT verdict_revisions_raison_non_vide '
                ."CHECK (btrim(raison) <> '')"
            );
        }
    }
};
