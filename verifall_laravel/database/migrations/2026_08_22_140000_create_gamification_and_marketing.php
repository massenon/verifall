<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gamification et marketing.
 *
 * Le principe qui gouverne ce schéma : ON NE COMPTE QUE CE QU'ON OBSERVE. Un
 * indicateur inventé est pire qu'un indicateur absent — il donne confiance dans
 * un chiffre faux, et personne ne va vérifier d'où il sort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();

            $table->string('icon', 16);
            $table->string('name');
            $table->string('description', 500);

            /*
             | Ce qu'il faut faire pour l'obtenir, en clair. Un badge verrouillé
             | sans condition affichée est une frustration gratuite : on voit
             | qu'il manque quelque chose sans savoir quoi.
             */
            $table->string('requirement_label')->nullable();

            $table->unsignedSmallInteger('xp_reward')->default(0);
            $table->unsignedSmallInteger('units_reward')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();

            // Un badge ne se gagne qu'une fois : sans la contrainte, un
            // recalcul rejoué recréditerait son XP à chaque passage.
            $table->unique(['user_id', 'badge_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            /*
             | Code de parrainage PERSONNEL, un par compte. Le prototype en
             | affiche un seul et de nombreuses invitations : c'est bien le
             | compte qui porte le code, pas l'invitation.
             */
            $table->string('referral_code', 24)->nullable()->unique()->after('tour_done_at');

            /*
             | Ouvertures du lien d'invitation. C'est la seule mesure HONNÊTE
             | de « combien de personnes ont vu mon invitation » : compter les
             | envois supposerait de suivre chaque partage WhatsApp, ce que
             | personne ne peut faire. Un simple compteur, aucune donnée
             | personnelle.
             */
            $table->unsignedInteger('referral_visits')->default(0)->after('referral_code');

            // Dernier jour d'activité retenu pour la série. Une DATE et non un
            // horodatage : la série se compte en jours, pas en heures.
            $table->date('streak_last_day')->nullable()->after('streak_days');
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();

            /*
             | Le filleul. Une ligne n'existe qu'à partir de l'INSCRIPTION :
             | avant, il n'y a personne à nommer, et fabriquer des lignes
             | « invitation envoyée » remplirait la page de fantômes.
             */
            $table->foreignId('referred_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('registered');

            $table->timestamp('registered_at')->nullable();
            // Action ÉLIGIBLE accomplie — la première vérification du filleul.
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();

            // Ce qui a été versé, figé : le barème peut changer, ce qui a été
            // accordé non.
            $table->unsignedSmallInteger('referrer_points')->default(0);
            $table->unsignedSmallInteger('referred_points')->default(0);

            $table->timestamps();

            // Un compte n'est le filleul que d'une seule personne.
            $table->unique('referred_id');
            $table->index(['referrer_id', 'status']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('email')->unique();
            $table->string('name')->nullable();

            /*
             | DOUBLE OPT-IN. Une adresse saisie dans un pied de page ne prouve
             | pas son propriétaire : sans confirmation, n'importe qui abonne
             | n'importe qui, et la plateforme se retrouve à envoyer des
             | courriels non sollicités en son nom.
             */
            $table->string('confirmation_token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();

            // Jeton de DÉSABONNEMENT, distinct : il vit dans chaque courriel
            // envoyé, et ne doit pas permettre de confirmer un abonnement.
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();

            // Empreinte de l'adresse IP au moment du consentement — la preuve
            // exigée par le RGPD, sans conserver l'adresse en clair.
            $table->string('consent_ip_hash', 64)->nullable();

            $table->timestamps();

            $table->index('confirmed_at');
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('subject');
            $table->longText('body');

            // 'subscribers' | 'citizens' | 'all'
            $table->string('audience', 20)->default('subscribers');
            $table->string('status', 20)->default('draft');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            /*
             | Compteurs d'envoi. `failed` existe autant que `sent` : une
             | campagne qui n'affiche que ses succès laisse croire qu'elle a
             | touché tout le monde.
             */
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('referrals');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['referral_code', 'referral_visits', 'streak_last_day']);
        });

        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};
