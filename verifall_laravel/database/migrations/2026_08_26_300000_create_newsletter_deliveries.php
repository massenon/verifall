<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qui a reçu quelle édition — la table qui rend l'envoi rejouable.
 *
 * Sans elle, « l'infolettre a-t-elle déjà été envoyée ce lundi ? » n'a de
 * réponse que dans la tête de celui qui a lancé la commande. Avec elle, la
 * question se pose à la base, et la base répond la même chose à deux processus
 * lancés en même temps.
 *
 * Elle ne stocke AUCUNE adresse en propre : deux clés étrangères, trois dates,
 * et le message d'erreur du relais. L'adresse reste dans
 * `newsletter_subscribers` et disparaît avec elle — la cascade est délibérée,
 * un désabonné qui demande l'effacement de ses données ne doit pas laisser
 * derrière lui une trace nominative.
 *
 * Réserve nommée : un message d'erreur SMTP cite souvent l'adresse
 * (« 550 <rene@…> user unknown »). La cascade le fait disparaître avec le reste,
 * mais la colonne n'est donc pas exempte de donnée personnelle et ne doit pas
 * être exportée telle quelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_deliveries', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')
                ->constrained('newsletter_subscribers')->cascadeOnDelete();

            /*
             | `sent_at` NUL veut dire « réservé, pas encore parti ». La ligne
             | est créée AVANT l'envoi, précisément pour que deux processus
             | concurrents ne puissent pas réserver le même destinataire : le
             | second se heurte à l'unicité et passe son chemin.
             */
            $table->timestamp('sent_at')->nullable();

            // Un échec est une information, pas un silence. Il alimente le
            // compteur `failed_count` de la campagne — celui sans lequel un
            // envoi raté à moitié se lit comme un envoi réussi.
            $table->timestamp('failed_at')->nullable();
            $table->string('error', 500)->nullable();

            $table->timestamps();

            /*
             | LA garantie d'idempotence. En base et non dans le code : un
             | `if (déjà envoyé)` en PHP ne survit pas à deux exécutions
             | simultanées, et c'est exactement le cas qui se produit quand un
             | cron prend du retard et que quelqu'un relance à la main.
             */
            $table->unique(['campaign_id', 'newsletter_subscriber_id'], 'newsletter_deliveries_unique');

            // Reprise d'un envoi interrompu : « qui reste-t-il à servir sur
            // cette édition ? » doit se répondre sans balayer la table.
            $table->index(['campaign_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
    }
};
