<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Économie — recharges Mobile Money et journal des unités.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 20);
            $table->string('pack', 20);

            /*
             | Montant et quantité figés À L'INITIATION, depuis la
             | configuration serveur. Jamais relus du client au moment de
             | créditer : sinon il suffirait de rejouer la requête avec un
             | autre nombre pour se créditer ce qu'on veut.
             */
            $table->unsignedInteger('amount_fcfa');
            $table->unsignedInteger('units');

            $table->string('status', 20)->default('pending');

            /*
             | Référence de l'opérateur, UNIQUE. C'est la clé d'idempotence :
             | un rappel rejoué — par l'opérateur qui n'a pas reçu notre accusé,
             | ou par un attaquant qui l'a intercepté — ne peut pas créditer
             | deux fois.
             */
            $table->string('provider_ref')->nullable()->unique();

            // Le numéro de téléphone est une donnée personnelle : chiffré au
            // repos, comme celui du profil.
            $table->text('phone_encrypted')->nullable();

            $table->string('failure_reason')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            // La réconciliation balaie les demandes en attente par ancienneté.
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('unit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             | Signé : positif au crédit, négatif au débit. Un seul champ plutôt
             | qu'un montant et un sens séparés — deux colonnes finiraient par
             | se contredire, et la somme du journal doit pouvoir être comparée
             | au solde sans interprétation.
             */
            $table->integer('delta');

            // Solde APRÈS l'opération : rend le journal vérifiable ligne à
            // ligne, sans rejouer tout l'historique.
            $table->unsignedInteger('balance_after');

            $table->string('reason', 30);
            $table->string('label');

            // Origine facultative — un paiement, une vérification, un cours.
            $table->nullableMorphs('source');

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_logs');
        Schema::dropIfExists('payments');
    }
};
