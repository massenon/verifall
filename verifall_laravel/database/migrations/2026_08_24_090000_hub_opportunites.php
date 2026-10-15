<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le Hub devient un Hub d'OPPORTUNITÉS.
 *
 * Trois rubriques désormais — Actualités, Opportunités Bénin, Opportunités
 * Internationales — et deux colonnes pour les porter.
 *
 * `scope` sépare ce qui se passe au Bénin de ce qui se passe ailleurs :
 * quelqu'un qui cherche un emploi à Cotonou et quelqu'un qui cherche une bourse
 * à l'étranger ne lisent pas la même liste.
 *
 * `type` classe l'opportunité. Une liste fermée plutôt qu'un champ libre : sur
 * un champ libre, la même chose finit écrite de six façons et le filtre cesse
 * de fonctionner sans que personne s'en aperçoive — il rend simplement moins de
 * résultats.
 *
 * `closed_at` retient QUAND une offre a été retirée. Sans cette date, on ne
 * saurait pas distinguer une offre jamais publiée d'une offre expirée, et le
 * rétablir demanderait de rouvrir l'historique à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_jobs', function (Blueprint $table): void {
            $table->string('scope', 20)->default('benin')->after('organisation');
            $table->string('type', 20)->default('autre')->after('scope');

            $table->timestamp('closed_at')->nullable()->after('published_at');

            $table->index(['scope', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('hub_jobs', function (Blueprint $table): void {
            $table->dropIndex(['scope', 'type']);
            $table->dropColumn(['scope', 'type', 'closed_at']);
        });
    }
};
