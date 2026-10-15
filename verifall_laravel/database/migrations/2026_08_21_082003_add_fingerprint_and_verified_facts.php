<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Économie d'appels — empreinte de contenu et base de faits déjà tranchés.
 *
 * Une rumeur virale est signalée des centaines de fois. Relancer douze moteurs
 * payants à chaque signalement coûterait cher pour rendre douze fois la même
 * réponse — et ferait attendre chacun pour rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table): void {
            /*
             | Empreinte du contenu NORMALISÉ : casse, accents, ponctuation et
             | espaces sont écartés avant hachage. « La taxe WhatsApp arrive ! »
             | et « la taxe whatsapp arrive » sont la même rumeur, et doivent
             | partager une réponse.
             |
             | Indexée : c'est une recherche faite à chaque soumission.
             */
            $table->string('content_fingerprint', 64)->nullable()->after('claim');
            $table->index(['content_fingerprint', 'status']);

            // Vérification dont la réponse a été reprise, s'il y en a une.
            // Nullable et sans cascade : effacer l'originale ne doit pas
            // effacer les demandes qui s'y sont adossées.
            $table->foreignId('reused_from_id')->nullable()->after('content_fingerprint')
                ->constrained('verifications')->nullOnDelete();
        });

        Schema::create('verified_facts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            /*
             | Faits tranchés par l'ÉQUIPE, pas par les moteurs. Consultés avant
             | tout appel payant : quand une rédaction a déjà statué sur une
             | rumeur, aucun modèle n'a besoin d'en rediscuter.
             */
            $table->string('title');
            $table->text('summary');
            $table->string('verdict', 20);

            // Mots-clés de rapprochement, normalisés comme les affirmations.
            $table->text('keywords');
            $table->string('content_fingerprint', 64)->nullable()->index();

            $table->string('official_link', 500)->nullable();
            $table->string('source_name')->nullable();

            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Combien de fois ce fait a évité une analyse — mesure l'économie
            // réelle, et indique quelles rumeurs circulent le plus.
            $table->unsignedInteger('hits')->default(0);

            $table->timestamps();

            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_facts');

        Schema::table('verifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reused_from_id');
            $table->dropIndex(['content_fingerprint', 'status']);
            $table->dropColumn('content_fingerprint');
        });
    }
};
