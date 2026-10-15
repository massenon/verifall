<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les deux boutons de l'aside du rapport qui n'écrivaient nulle part.
 *
 * ## 1. `verification_feedback` — « Ce rapport vous a-t-il été utile ? »
 *
 * Ce n'est pas une invention : `docs/kb/API_SPECIFICATION.md` §11 déclare
 * `POST /verifications/{uuid}/feedback` **absent**, et en donne la raison —
 * « aucun stockage, et transposé sur aucun écran web » — puis le dessin exact
 * de ce qu'il faudrait : *« une table `verification_feedback` (compte,
 * vérification, utile oui/non, unicité sur le couple) »*. C'est ce qui est fait
 * ici, au caractère près. L'endpoint mobile cesse donc d'être une réserve.
 *
 * **Unicité sur le couple.** Sans elle, un pouce cliqué douze fois compte
 * douze avis, et l'indicateur ne mesure plus la satisfaction mais l'agacement.
 * Le vote se change — il ne s'additionne pas.
 *
 * Pas de `guest_hash` : le rapport n'est consultable que par le compte qui l'a
 * demandé. Une colonne pour un cas qui ne peut pas se produire est une colonne
 * que quelqu'un finira par remplir de travers.
 *
 * ## 2. `counter_expertises` — « Demander un expert »
 *
 * Le prototype ouvre une fenêtre « Demander une contre-expertise » avec un
 * champ de motif, et le bouton n'était relié à rien.
 *
 * ### Pourquoi une table à part, et non `content_reports`
 *
 * `content_reports` est polymorphe et aurait pu l'accueillir. Ce serait une
 * erreur : signaler un message injurieux et contester NOTRE PROPRE verdict sont
 * deux gestes sans rapport, traités par des personnes différentes, avec un état
 * d'esprit différent. Les mêler enterrerait un appel éditorial au milieu d'une
 * file de modération — et c'est l'appel qui a le plus de valeur pour une
 * plateforme de fact-checking, puisqu'il dit qu'on s'est peut-être trompé.
 *
 * ### `restrictOnDelete` sur `verification_id`, délibérément
 *
 * Une contre-expertise sans le rapport qu'elle conteste ne veut rien dire ; et
 * supprimer un rapport CONTESTÉ sans s'en apercevoir serait le pire moment pour
 * le faire. La base refuse, et oblige à trancher la demande d'abord.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_feedback', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->boolean('useful');

            $table->timestamps();

            // Un avis par personne et par rapport. Le vote se CHANGE.
            $table->unique(['verification_id', 'user_id']);
        });

        Schema::create('counter_expertises', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('verification_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Le motif est OBLIGATOIRE : une contestation sans raison n'est pas
            // exploitable, et l'écran l'exige déjà. Le stocker facultatif
            // laisserait entrer des demandes vides que personne ne saurait traiter.
            $table->text('reason');

            $table->string('status', 16)->default('pending');

            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution')->nullable();

            $table->timestamps();

            // Le back-office liste d'abord ce qui attend : l'index porte sur ce
            // tri-là, et non sur la seule date.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_expertises');
        Schema::dropIfExists('verification_feedback');
    }
};
