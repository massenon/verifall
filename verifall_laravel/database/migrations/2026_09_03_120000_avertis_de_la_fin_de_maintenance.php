<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ceux qui demandent à être prévenus quand la maintenance se termine.
 *
 * ## Une table à part, et surtout PAS la liste d'infolettre
 *
 * Le réflexe serait de réutiliser `newsletter_subscribers` : une adresse est une
 * adresse. Ce serait détourner un consentement.
 *
 * Quelqu'un qui laisse son adresse sur une page de maintenance demande **un
 * message, une fois, sur un sujet précis**. Il ne s'abonne pas à l'infolettre
 * hebdomadaire, et l'y verser reviendrait exactement à ce qu'on reproche aux
 * plateformes dont ce produit démonte les rumeurs.
 *
 * ## `notified_at` plutôt qu'une suppression
 *
 * La ligne est marquée, pas effacée. Deux raisons : une maintenance peut être
 * suivie d'une autre le lendemain, et il faut savoir qui a déjà été prévenu
 * pour ne pas écrire deux fois ; et une réclamation « je n'ai rien reçu » se
 * tranche en regardant la date, pas une absence de ligne.
 *
 * ## L'adresse est unique
 *
 * Rafraîchir la page et resoumettre est le geste le plus naturel du monde quand
 * on attend. Sans contrainte, la même personne recevrait trois messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_watchers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();

            // Quelle maintenance : l'horodatage d'ouverture de celle en cours au
            // moment de l'inscription. Sans lui, on ne saurait pas si la
            // personne attend CELLE-CI ou une précédente jamais notifiée.
            $table->timestamp('waiting_since');

            $table->timestamp('notified_at')->nullable();

            // Pour tracer un abus sans conserver l'adresse en clair ailleurs.
            $table->string('ip_hash', 64)->nullable();

            $table->timestamps();

            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_watchers');
    }
};
