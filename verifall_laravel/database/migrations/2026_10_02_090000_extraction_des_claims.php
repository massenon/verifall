<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — une vérification porte 0..N claims, le verdict s'attache au claim.
 *
 * Contrat V-8, verrouillé par NEWINSTRUCTION24 : `analysis_reports.verdict`
 * devient NULLABLE, et `NULL` ne signifie JAMAIS « mixte » — c'est la colonne
 * `composition_status`, distincte, qui porte cette information. Aucune
 * cinquième valeur n'est ajoutée à `VerdictFactCheck` (décision V-2).
 *
 * Livrée dans le MÊME lot que la correction des quatre vues qui
 * déréférençaient `verdict` sans garde (`mes-verifications`,
 * `tableau-de-bord`, `verifier`, `verifier-invite`) : rendre la colonne
 * nullable sans elles ferait planter le premier rapport mixte sur les écrans
 * les plus exposés du produit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table): void {
            $table->id();
            // Rattaché à la VÉRIFICATION, pas au rapport : `analysis_reports`
            // est en 1-1 avec `verifications` et naît APRÈS l'extraction, donc
            // un claim ne pourrait pas s'y accrocher à l'instant où il naît.
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();

            // L'ordre dans le contenu soumis — pas un ordre d'importance,
            // aucune pondération n'existe entre claims (contrat V-8, I3).
            $table->unsignedTinyInteger('position');

            $table->text('texte');
            // L'extrait d'où le claim est tiré, quand l'extraction l'identifie
            // distinctement de sa forme normalisée.
            $table->text('texte_original')->nullable();

            $table->string('acte_de_langage', 20);

            // Colonne de la phase 3 (résolution temporelle), posée maintenant
            // pour ne pas rouvrir cette migration plus tard — inutilisée tant
            // que la phase 3 n'écrit rien dedans.
            $table->string('portee_temporelle', 20)->nullable();

            // NULL tant que non analysé, et pour tout claim dont l'acte de
            // langage ne PEUT pas porter de verdict (Question, Opinion,
            // Prediction, NonFactuel) — `ActeDeLangage::peutPorterUnVerdict()`.
            $table->string('verdict', 20)->nullable();
            $table->unsignedTinyInteger('confiance')->nullable();

            // Renseigné pour une QUESTION : la réponse vérifiée, jamais un
            // verdict sur le contenu soumis (contrat V-8).
            $table->text('reponse')->nullable();

            $table->timestamps();

            $table->unique(['verification_id', 'position']);
        });

        Schema::table('analysis_reports', function (Blueprint $table): void {
            // NULLABLE : `NULL` signifie « aucun verdict factuel unique
            // applicable », jamais « mixte ». Les rapports déjà écrits
            // gardent leur verdict scalaire tel quel — ce changement ne
            // touche que la contrainte, aucune ligne existante.
            //
            // `->change()` est natif depuis Laravel 11 (compileChange() dans
            // chaque grammaire) : aucune dépendance à doctrine/dbal, qui
            // n'est d'ailleurs pas vendorée ici.
            $table->string('verdict', 20)->nullable()->change();

            // Défaut HOMOGENE : tout rapport déjà écrit porte un claim
            // unique, donc une composition homogène par construction.
            $table->string('composition_status', 20)->default('homogene')->after('verdict');
        });

        Schema::table('engine_runs', function (Blueprint $table): void {
            // NULL pour les lignes déjà écrites — une analyse antérieure au
            // claim unique continue de se lire sans lui.
            $table->foreignId('claim_id')->nullable()->after('verification_id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('report_evidences', function (Blueprint $table): void {
            $table->foreignId('claim_id')->nullable()->after('verification_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_evidences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('claim_id');
        });

        Schema::table('engine_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('claim_id');
        });

        Schema::table('analysis_reports', function (Blueprint $table): void {
            $table->dropColumn('composition_status');
            $table->string('verdict', 20)->nullable(false)->change();
        });

        Schema::dropIfExists('claims');
    }
};
