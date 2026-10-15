<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue une demande de soutien d'un message ordinaire.
 *
 * POURQUOI DANS LA MÊME TABLE. Une demande de don est un message : quelqu'un
 * écrit, quelqu'un répond. Lui donner sa propre table imposerait un second
 * écran d'administration, une seconde recherche, une seconde file à relever —
 * et c'est la seconde file qu'on oublie de relever.
 *
 * Une colonne et un filtre suffisent, à condition que le filtre existe : sans
 * lui, les demandes de soutien se noieraient dans le courrier courant, ce qui
 * reviendrait au même oubli par un autre chemin.
 *
 * Le montant est du TEXTE LIBRE, et c'est délibéré. Il est déclaratif : la
 * plateforme n'encaisse rien à ce stade, et le stocker en décimal laisserait
 * croire à une transaction là où il n'y a qu'une intention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('kind')->default('contact')->index()->after('uuid');
            $table->string('donation_amount')->nullable()->after('message');
            $table->string('donation_recurrence')->nullable()->after('donation_amount');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'donation_amount', 'donation_recurrence']);
        });
    }
};
