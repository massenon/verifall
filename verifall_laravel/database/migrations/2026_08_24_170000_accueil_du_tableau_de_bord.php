<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que le tableau de bord doit retenir d'une visite à l'autre.
 *
 * Le prototype ouvre deux modales à l'arrivée : le quiz d'accueil, et
 * l'annonce de campagne. Toutes deux ne doivent s'ouvrir qu'une fois — une
 * modale qui revient à chaque page cesse d'être une invitation et devient un
 * obstacle. Le prototype garde cet état dans le `localStorage` ; ici il
 * appartient au compte, sinon changer de navigateur rouvrirait tout.
 *
 * « Me rappeler plus tard » est une date, pas un booléen : la personne n'a pas
 * refusé, elle a demandé un délai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarding_quiz_done_at')->nullable()->after('tour_done_at');
            $table->timestamp('onboarding_quiz_snoozed_at')->nullable()->after('onboarding_quiz_done_at');
            $table->timestamp('campaign_seen_at')->nullable()->after('onboarding_quiz_snoozed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['onboarding_quiz_done_at', 'onboarding_quiz_snoozed_at', 'campaign_seen_at']);
        });
    }
};
