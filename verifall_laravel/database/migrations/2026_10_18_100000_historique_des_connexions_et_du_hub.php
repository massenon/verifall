<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux historiques rattachés à un compte (décision RGPD D7, arbitrage n° 178).
 *
 * ## `historique_connexions` — intérêt légitime, 12 mois
 *
 * Sécurité des comptes (RGPD art. 6.1.f et 32) : repérer une prise de contrôle,
 * répondre au support. Minimisé : l'adresse IP est TRONQUÉE (dernier octet en
 * IPv4, 80 derniers bits en IPv6) pour l'affichage, et hachée pour rapprocher
 * deux connexions sans la conserver en clair. Le navigateur est réduit à sa
 * famille, l'appareil à sa catégorie : pas de chaîne User-Agent complète.
 *
 * ## `consultations_hub` — consentement, 90 jours
 *
 * Ce que la personne lit dans le Hub dit ses centres d'intérêt, sa recherche
 * d'emploi : c'est du profilage, que la sécurité ne justifie pas. Il n'est donc
 * enregistré QU'AVEC un consentement explicite (`ConsentType::HistoriqueHub`),
 * et le retrait du consentement efface l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_connexions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('espace', 16);       // citoyen | console
            $table->string('resultat', 16);     // reussie | echouee | bloquee
            $table->string('methode', 32);      // mot_de_passe | sociale
            $table->string('navigateur', 40)->nullable();
            $table->string('systeme', 40)->nullable();
            $table->string('appareil', 16)->nullable(); // ordinateur | mobile | tablette
            $table->string('ip_tronquee', 45)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->char('pays', 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('consultations_hub', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rubrique', 16);     // actualite | portail | offre
            $table->unsignedBigInteger('cible_id');
            $table->string('titre', 200);
            $table->string('action', 24);       // lu | ouvert | candidature | favori | signale
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations_hub');
        Schema::dropIfExists('historique_connexions');
    }
};
