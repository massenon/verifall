<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les messages du formulaire de contact.
 *
 * Ils sont ÉCRITS EN BASE, et le courriel de notification n'est qu'un rappel.
 * L'inverse — n'envoyer qu'un courriel — perd le message dès que le serveur
 * SMTP refuse, sans que personne ne l'apprenne. Or celui qui écrit signale
 * parfois une intox : c'est la matière première du produit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('email');
            // Facultatifs au prototype : ils le restent ici. Exiger un
            // téléphone écarterait des gens qui n'en donnent pas volontiers.
            $table->string('phone')->nullable();
            $table->string('organisation')->nullable();
            $table->text('message');

            $table->string('status')->default('new')->index();

            /*
             | L'adresse IP est HACHÉE, jamais stockée en clair : elle ne sert
             | qu'à reconnaître un envoi en rafale, ce qu'une empreinte permet
             | aussi bien. En clair, ce serait une donnée personnelle conservée
             | sans nécessité.
             */
            $table->string('ip_hash', 64)->nullable()->index();

            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('handling_note')->nullable();

            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
