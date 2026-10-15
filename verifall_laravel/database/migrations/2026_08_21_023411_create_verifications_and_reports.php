<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fact-checking — demandes de vérification et rapports rendus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);
            $table->string('status', 20)->default('queued');

            /*
             | L'affirmation soumise. Non chiffrée : elle est destinée à devenir
             | publique dans un rapport, et la chiffrer empêcherait toute
             | recherche de doublons — deux personnes signalant la même rumeur
             | doivent pouvoir être rapprochées.
             */
            $table->text('claim');
            $table->string('context', 200)->nullable();

            // Fichier téléversé : disque privé, nom aléatoire, jamais servi
            // directement.
            $table->string('file_path')->nullable();

            /*
             | Coût figé À LA SOUMISSION. Le relire dans la configuration au
             | moment de rembourser exposerait à rendre plus — ou moins — que ce
             | qui a été prélevé, si le tarif a changé entre-temps.
             */
            $table->unsignedSmallInteger('cost_units');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('analysis_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // Un rapport par vérification : la contrainte unique l'impose plutôt
            // que de compter sur la discipline du code.
            $table->foreignId('verification_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('verdict', 20);
            $table->unsignedTinyInteger('confidence')->default(0);

            // Synthèse citoyenne. Nullable : la synthèse rédigée est un
            // ENRICHISSEMENT, jamais un bloquant — le rapport sort même si
            // aucun moteur de rédaction n'a répondu.
            $table->text('summary')->nullable();

            // Réserve affichée en bandeau : analyse partielle, arbitrage en
            // cours. Le silence sur une réserve vaut affirmation.
            $table->string('caveat')->nullable();

            /** @var array<string, float> scores par verdict, pour audit */
            $table->json('scores')->nullable();

            // Signalé santé ou sécurité publique : bandeau rouge du prototype.
            $table->boolean('hsi_flag')->default(false);

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('engine_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();

            $table->string('engine', 40);
            $table->string('verdict', 20)->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);

            $table->boolean('failed')->default(false);
            $table->string('fail_reason')->nullable();

            // Le raisonnement rendu par le moteur — c'est ce qui rend la
            // méthode auditable : un lecteur voit qui a dit quoi.
            $table->text('reasoning')->nullable();

            $table->timestamps();

            $table->index(['verification_id', 'engine']);
        });

        Schema::create('report_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();

            $table->string('source_url', 500);
            $table->string('source_name');
            $table->text('snippet');
            $table->string('stance', 20);
            $table->unsignedTinyInteger('reliability')->default(50);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('verification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_evidences');
        Schema::dropIfExists('engine_runs');
        Schema::dropIfExists('analysis_reports');
        Schema::dropIfExists('verifications');
    }
};
